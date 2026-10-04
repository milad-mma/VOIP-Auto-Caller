<?php
class SettingsController extends Controller
{
    private function fields()
    {
        // key => array(type, min, max) ; type: text|int|bool|hm|select
        return array(
            'callerid_name' => array('text'), 'callerid_number' => array('dial'), 'dial_prefix' => array('dial'),
            'channel_tech' => array('select', array('pool', 'local', 'sip', 'pjsip', 'custom')), 'trunk_name' => array('text'), 'channel_template' => array('text'),
            'outbound_context' => array('ident'), 'country_code' => array('digits'),
            'global_max_concurrent' => array('int', 1, 500), 'default_ring_timeout' => array('int', 5, 120), 'default_gap_ms' => array('int', 0, 600000),
            'default_max_retries' => array('int', 0, 10), 'default_retry_delay_min' => array('int', 1, 1440), 'default_concurrent' => array('int', 1, 200),
            'work_start' => array('hm'), 'work_end' => array('hm'), 'work_days' => array('days'), 'respect_holidays' => array('bool'),
            'cdr_lookup' => array('bool'), 'amd_enabled' => array('bool'), 'stale_call_minutes' => array('int', 2, 120),
            'login_max_attempts' => array('int', 0, 100), 'login_lock_minutes' => array('int', 1, 1440), 'issabel_login' => array('bool'),
            'issabel_admin_role' => array('select', array('admin', 'operator', 'viewer')), 'api_enabled' => array('bool'),
            'ui_lang' => array('select', array('fa', 'en')), 'retention_days' => array('int', 0, 3650),
        );
    }

    public function index()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $holidays = $this->db->all('SELECT * FROM holidays ORDER BY hdate DESC LIMIT 200');
        $pool = Trunks::all();
        $active = Trunks::activeCounts();
        foreach ($pool as &$t) {
            $t['active'] = isset($active[(int)$t['id']]) ? $active[(int)$t['id']] : 0;
        }
        unset($t);
        $this->view('settings', array('s' => Settings::all(), 'holidays' => $holidays, 'ami' => Config::get('ami'), 'trunks' => $this->trunks(), 'pool' => $pool, 'issabelTrunks' => Trunks::fromIssabel()));
    }

    /** Try to list trunks from the Issabel asterisk DB for the dropdown help */
    private function trunks()
    {
        $root = Auth::pbxRootPassword();
        if ($root === null) {
            return array();
        }
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=asterisk;charset=utf8', 'root', $root, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
            return $pdo->query('SELECT trunkid, name, tech, channelid, disabled FROM trunks ORDER BY trunkid')->fetchAll();
        } catch (Exception $e) {
            return array();
        }
    }

    public function save()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        foreach ($this->fields() as $k => $def) {
            $raw = Request::post($k, null);
            switch ($def[0]) {
                case 'bool':
                    $v = $raw === '1' || $raw === 'on' ? '1' : '0';
                    break;
                case 'int':
                    $v = (string)Util::intOr($raw, (int)Settings::get($k), $def[1], $def[2]);
                    break;
                case 'hm':
                    $v = Util::isHm(Util::toAsciiDigits((string)$raw)) ? Util::toAsciiDigits((string)$raw) : Settings::get($k);
                    break;
                case 'days':
                    $arr = is_array($raw) ? array_values(array_unique(array_filter(array_map('intval', $raw), function ($x) {
                        return $x >= 0 && $x <= 6;
                    }))) : array();
                    sort($arr);
                    $v = $arr ? implode(',', $arr) : '0,1,2,3,4,5,6';
                    break;
                case 'select':
                    $v = in_array((string)$raw, $def[1], true) ? (string)$raw : Settings::get($k);
                    break;
                case 'dial':
                    $v = Util::dialSafe((string)$raw);
                    break;
                case 'digits':
                    $v = preg_replace('/\D/', '', Util::toAsciiDigits((string)$raw));
                    break;
                case 'ident':
                    $v = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$raw);
                    if ($v === '') {
                        $v = 'from-internal';
                    }
                    break;
                default:
                    $v = Util::oneLine(is_array($raw) ? '' : (string)$raw, 200);
            }
            Settings::set($k, $v);
        }
        Audit::log('settings.save', 'settings');
        Flash::set('success', t('saved'));
        $this->redirect('/settings');
    }

    public function testAmi()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        try {
            $ami = Ami::fromConfig();
            $ami->connect(4);
            $ver = trim($ami->command('core show version'));
            $ami->close();
            $this->json(array('ok' => true, 'message' => t('ami_ok') . ($ver ? ' — ' . Util::oneLine($ver, 120) : '')));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'message' => $e->getMessage()));
        }
    }

    public function holidayAdd()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $date = Util::toAsciiDigits(Request::str('hdate', '', 20));
        $ts = strtotime($date);
        if ($ts === false) {
            Flash::set('error', t('bad_datetime'));
        } else {
            $this->db->run('INSERT IGNORE INTO holidays (hdate, title) VALUES (?, ?)', array(date('Y-m-d', $ts), Request::str('title', '', 128)));
            Flash::set('success', t('saved'));
        }
        $this->redirect('/settings#holidays');
    }

    // ---- trunk pool ----
    public function trunkSave()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $id = Request::int('id', 0, 0);
        $tech = Request::str('tech', 'sip', 8);
        if (!in_array($tech, array('sip', 'pjsip', 'custom'), true)) {
            $tech = 'sip';
        }
        $d = array(
            'name' => Request::str('name', '', 64),
            'tech' => $tech,
            'channel_id' => preg_replace('/[^A-Za-z0-9_\-\.@]/', '', Request::str('channel_id', '', 64)),
            'dial_template' => Util::oneLine(Request::str('dial_template', '', 160), 160),
            'dial_prefix' => Util::dialSafe(Request::str('dial_prefix', '', 16)),
            'callerid' => Util::dialSafe(Request::str('callerid', '', 64)),
            'max_channels' => Request::int('max_channels', 1, 1, 500),
            'is_enabled' => Request::int('is_enabled', 1, 0, 1),
            'sort' => Request::int('sort', 0, -1000, 1000),
        );
        if ($d['channel_id'] === '') {
            Flash::set('error', t('invalid_input'));
            $this->redirect('/settings#trunk-pool');
        }
        if ($d['name'] === '') {
            $d['name'] = $d['channel_id'];
        }
        $dup = $this->db->val('SELECT id FROM trunks WHERE channel_id = ? AND id <> ?', array($d['channel_id'], $id));
        if ($dup) {
            Flash::set('error', t('trunk_exists'));
            $this->redirect('/settings#trunk-pool');
        }
        if ($id) {
            $this->db->update('trunks', $d, 'id = ?', array($id));
        } else {
            $id = $this->db->insert('trunks', $d);
        }
        Audit::log('trunk.save', 'trunk', $id, $d['channel_id']);
        Flash::set('success', t('saved'));
        $this->redirect('/settings#trunk-pool');
    }

    public function trunkDelete($p)
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $this->db->exec('DELETE FROM trunks WHERE id = ?', array((int)$p['id']));
        Audit::log('trunk.delete', 'trunk', (int)$p['id']);
        $this->redirect('/settings#trunk-pool');
    }

    /** add every SIP/PJSIP trunk of the PBX that is not in the pool yet */
    public function trunkImport()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $n = 0;
        $u = 0;
        foreach (Trunks::fromIssabel() as $t) {
            if ($t['disabled'] || $t['channel_id'] === '') {
                continue;
            }
            $ex = $this->db->one('SELECT id, callerid FROM trunks WHERE channel_id = ?', array($t['channel_id']));
            if ($ex) {
                // existing trunk without a caller id: fill it from Issabel
                if ($ex['callerid'] === '' && $t['callerid'] !== '') {
                    $this->db->update('trunks', array('callerid' => $t['callerid']), 'id = ?', array((int)$ex['id']));
                    $u++;
                }
                continue;
            }
            $this->db->insert('trunks', array('name' => $t['name'] !== '' ? $t['name'] : $t['channel_id'], 'tech' => $t['tech'], 'channel_id' => $t['channel_id'], 'callerid' => $t['callerid'], 'max_channels' => 1, 'is_enabled' => 1, 'sort' => $n));
            $n++;
        }
        if ($u) {
            Flash::set('success', t('trunk_cids_updated', $u));
        }
        Audit::log('trunk.import', 'trunk', null, "$n added");
        Flash::set('success', t('trunks_imported', $n));
        $this->redirect('/settings#trunk-pool');
    }

    public function holidayImportIran()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $jy = Request::int('jy', 0, 1300, 1500);
        if (!$jy) {
            Flash::set('error', t('invalid_input'));
            $this->redirect('/settings#holidays');
        }
        $n = 0;
        foreach (Jalali::iranHolidays($jy) as $h) {
            $title = $h['title'] . ($h['lunar'] ? ' ' . t('lunar_mark') : '');
            $n += $this->db->exec('INSERT IGNORE INTO holidays (hdate, title) VALUES (?, ?)', array($h['date'], mb_substr($title, 0, 128, 'UTF-8')));
        }
        Audit::log('holidays.import_iran', 'settings', $jy, "$n added");
        Flash::set('success', t('iran_holidays_added', $n, $jy));
        $this->redirect('/settings#holidays');
    }

    public function holidayDelete($p)
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $this->db->exec('DELETE FROM holidays WHERE id = ?', array((int)$p['id']));
        $this->redirect('/settings#holidays');
    }
}

class UserController extends Controller
{
    public function index()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $rows = $this->db->all('SELECT * FROM users ORDER BY id');
        $local = array();
        foreach ($rows as $r) {
            $local[$r['username']] = $r;
        }
        $issabel = array();
        $issabelEnabled = Settings::get('issabel_login') === '1';
        if ($issabelEnabled) {
            $adminRole = Settings::get('issabel_admin_role', 'admin');
            foreach (Auth::issabelUsers() as $iu) {
                $row = isset($local[$iu['name']]) ? $local[$iu['name']] : null;
                if ($row && $row['auth_source'] === 'local') {
                    // a local account shadows this panel user; panel password is still accepted for it
                    $iu['state'] = 'local';
                    $iu['role'] = $row['role'];
                    $iu['active'] = (int)$row['is_active'] === 1;
                    $iu['last_login_at'] = $row['last_login_at'];
                } elseif ($row) {
                    $iu['state'] = 'linked';
                    $iu['role'] = $row['role'];
                    $iu['active'] = (int)$row['is_active'] === 1;
                    $iu['last_login_at'] = $row['last_login_at'];
                    $iu['row_id'] = (int)$row['id'];
                    $iu['has_pw'] = $row['password_hash'] !== '';
                } else {
                    $iu['state'] = 'auto';
                    $iu['role'] = $iu['is_admin'] ? $adminRole : Auth::ROLE_VIEWER;
                    $iu['active'] = true;
                    $iu['last_login_at'] = null;
                }
                $issabel[] = $iu;
            }
        }
        $rows = array_values(array_filter($rows, function ($r) {
            return $r['auth_source'] === 'local';
        }));
        $this->view('users', array('rows' => $rows, 'issabel' => $issabel, 'issabelEnabled' => $issabelEnabled, 'aclOk' => $issabelEnabled && Auth::aclPdo() !== null));
    }

    /** Pre-assign a role / block an Issabel panel user for this app */
    public function issabel()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $name = Request::str('username', '', 64);
        $role = Request::str('role', 'viewer', 16);
        $active = Request::int('is_active', 1, 0, 1);
        if (!in_array($role, array('admin', 'operator', 'viewer'), true)) {
            $role = 'viewer';
        }
        if ($role === 'admin' && !Auth::isSuper()) {
            Flash::set('error', t('only_root_grants_admin'));
            $this->redirect('/users#issabel');
        }
        $pw = (string)Request::post('password', '');
        if ($pw !== '' && !Auth::isSuper()) {
            Flash::set('error', t('only_root'));
            $this->redirect('/users#issabel');
        }
        if ($pw !== '' && strlen($pw) < 8) {
            Flash::set('error', t('password_min'));
            $this->redirect('/users#issabel');
        }
        $known = false;
        foreach (Auth::issabelUsers() as $iu) {
            if ($iu['name'] === $name) {
                $known = $iu;
            }
        }
        if (!$known) {
            Flash::set('error', t('invalid_input'));
            $this->redirect('/users');
        }
        $me = Auth::user();
        $row = $this->db->one('SELECT * FROM users WHERE username = ?', array($name));
        if ($row && $row['auth_source'] === 'local') {
            Flash::set('error', t('issabel_user_shadowed', $name));
            $this->redirect('/users');
        }
        if ($row && (int)$row['id'] === (int)$me['id']) {
            $role = 'admin';
            $active = 1;
        }
        if ($row && $row['role'] === 'admin' && (int)$row['id'] !== (int)$me['id'] && !Auth::isSuper()) {
            Flash::set('error', t('only_root_manages_admins'));
            $this->redirect('/users#issabel');
        }
        if (!$row && $known['is_admin'] && !Auth::isSuper()) {
            Flash::set('error', t('only_root_manages_admins'));
            $this->redirect('/users#issabel');
        }
        if (Request::int('reset', 0, 0, 1) === 1) {
            if (!Auth::isSuper() || !$row || (int)$row['id'] === (int)$me['id']) {
                Flash::set('error', t('only_root'));
                $this->redirect('/users#issabel');
            }
            $this->db->exec('DELETE FROM api_keys WHERE user_id = ?', array((int)$row['id']));
            $this->db->exec('DELETE FROM users WHERE id = ?', array((int)$row['id']));
            Audit::log('user.issabel.reset', 'user', $row['id'], $name);
            Flash::set('success', t('deleted'));
            $this->redirect('/users#issabel');
        }
        $data = array('role' => $role, 'is_active' => $active);
        if ($pw !== '') {
            $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        } elseif (Request::int('clear_password', 0, 0, 1) === 1 && Auth::isSuper()) {
            $data['password_hash'] = '';
        }
        if ($row) {
            $this->db->update('users', $data, 'id = ?', array((int)$row['id']));
        } else {
            $this->db->insert('users', array_merge(array('username' => $name, 'display_name' => $known['description'] !== '' ? $known['description'] : $name, 'password_hash' => '', 'auth_source' => 'issabel', 'created_at' => Util::now()), $data));
        }
        Audit::log('user.issabel', 'user', $row ? $row['id'] : $this->db->lastId(), "$name role=$role active=$active");
        Flash::set('success', t('saved'));
        $this->redirect('/users#issabel');
    }

    public function store()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $u = Request::str('username', '', 64);
        $pw = (string)Request::post('password', '');
        $role = Request::str('role', 'viewer', 16);
        if (!preg_match('/^[A-Za-z0-9_.@\-]{2,64}$/', $u) || strlen($pw) < 8 || !in_array($role, array('admin', 'operator', 'viewer'), true)) {
            Flash::set('error', t('invalid_input') . ' (' . t('password_min') . ')');
            $this->redirect('/users');
        }
        if ($this->db->val('SELECT id FROM users WHERE username = ?', array($u))) {
            Flash::set('error', t('user_exists'));
            $this->redirect('/users');
        }
        if ($role === 'admin' && !Auth::isSuper()) {
            Flash::set('error', t('only_root_grants_admin'));
            $this->redirect('/users');
        }
        $id = $this->db->insert('users', array('username' => $u, 'display_name' => Request::str('display_name', $u, 128), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => $role, 'auth_source' => 'local', 'is_active' => 1, 'created_at' => Util::now()));
        Audit::log('user.create', 'user', $id, $u);
        Flash::set('success', t('saved'));
        $this->redirect('/users');
    }

    public function update($p)
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $u = $this->db->one('SELECT * FROM users WHERE id = ?', array((int)$p['id']));
        if (!$u) {
            $this->notFound();
        }
        $me = Auth::user();
        if (Auth::isSuperRow($u) && !Auth::isSuper()) {
            Flash::set('error', t('root_protected'));
            $this->redirect('/users');
        }
        if ($u['role'] === 'admin' && (int)$u['id'] !== (int)$me['id'] && !Auth::isSuper()) {
            Flash::set('error', t('only_root_manages_admins'));
            $this->redirect('/users');
        }
        $upd = array('display_name' => Request::str('display_name', $u['display_name'], 128));
        $role = Request::str('role', $u['role'], 16);
        if (in_array($role, array('admin', 'operator', 'viewer'), true)) {
            if ($role === 'admin' && $u['role'] !== 'admin' && !Auth::isSuper()) {
                Flash::set('error', t('only_root_grants_admin'));
                $this->redirect('/users');
            }
            $upd['role'] = $role;
        }
        $active = Request::int('is_active', 1, 0, 1);
        if ((int)$u['id'] === (int)$me['id'] || Auth::isSuperRow($u)) {
            $upd['role'] = 'admin';
            $active = 1;
        }
        $upd['is_active'] = $active;
        $pw = (string)Request::post('password', '');
        if ($pw !== '') {
            if (strlen($pw) < 8) {
                Flash::set('error', t('password_min'));
                $this->redirect('/users');
            }
            if ($u['auth_source'] === 'issabel' && !Auth::isSuper()) {
                Flash::set('error', t('only_root'));
                $this->redirect('/users');
            }
            $upd['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        $this->db->update('users', $upd, 'id = ?', array((int)$u['id']));
        Audit::log('user.update', 'user', $u['id'], $u['username']);
        Flash::set('success', t('saved'));
        $this->redirect('/users');
    }

    public function delete($p)
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $this->csrf();
        $me = Auth::user();
        if ((int)$p['id'] === (int)$me['id']) {
            Flash::set('error', t('cannot_delete_self'));
            $this->redirect('/users');
        }
        $target = $this->db->one('SELECT * FROM users WHERE id = ?', array((int)$p['id']));
        if ($target && Auth::isSuperRow($target)) {
            Flash::set('error', t('root_protected'));
            $this->redirect('/users');
        }
        if ($target && $target['role'] === 'admin' && !Auth::isSuper()) {
            Flash::set('error', t('only_root_manages_admins'));
            $this->redirect('/users');
        }
        $this->db->exec('DELETE FROM api_keys WHERE user_id = ?', array((int)$p['id']));
        $this->db->exec('DELETE FROM users WHERE id = ?', array((int)$p['id']));
        Audit::log('user.delete', 'user', (int)$p['id']);
        Flash::set('success', t('deleted'));
        $this->redirect('/users');
    }

    public function profile()
    {
        Auth::requireLogin();
        $this->view('profile', array('u' => Auth::user()));
    }

    public function profileSave()
    {
        Auth::requireLogin();
        $this->csrf();
        $me = Auth::user();
        $upd = array('display_name' => Request::str('display_name', $me['display_name'], 128));
        $pw = (string)Request::post('password', '');
        if ($pw !== '') {
            if ($me['auth_source'] !== 'local' && $me['password_hash'] === '') {
                Flash::set('error', t('issabel_user_no_password'));
                $this->redirect('/profile');
            }
            if (!password_verify((string)Request::post('current_password', ''), $me['password_hash'])) {
                Flash::set('error', t('wrong_current_password'));
                $this->redirect('/profile');
            }
            if (strlen($pw) < 8) {
                Flash::set('error', t('password_min'));
                $this->redirect('/profile');
            }
            $upd['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            $_SESSION['pwd_stamp'] = substr(md5($upd['password_hash']), 0, 12);
        }
        $this->db->update('users', $upd, 'id = ?', array((int)$me['id']));
        Flash::set('success', t('saved'));
        $this->redirect('/profile');
    }
}

class ApiKeyController extends Controller
{
    public function index()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $me = Auth::user();
        $rows = Auth::can(Auth::ROLE_ADMIN)
            ? $this->db->all('SELECT k.*, u.username FROM api_keys k JOIN users u ON u.id = k.user_id ORDER BY k.id DESC')
            : $this->db->all('SELECT k.*, u.username FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.user_id = ? ORDER BY k.id DESC', array((int)$me['id']));
        $new = isset($_SESSION['new_api_key']) ? $_SESSION['new_api_key'] : null;
        unset($_SESSION['new_api_key']);
        $this->view('apikeys', array('rows' => $rows, 'newKey' => $new, 'base' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'server') . View::url('/api/v1')));
    }

    public function store()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $key = 'ack_' . Util::token(24);
        $this->db->insert('api_keys', array(
            'user_id' => Auth::user()['id'], 'name' => Request::str('name', 'key', 64), 'key_hash' => hash('sha256', $key),
            'key_prefix' => substr($key, 0, 10), 'allowed_ips' => preg_replace('/[^0-9a-fA-F.:, ]/', '', Request::str('allowed_ips', '', 255)),
            'is_active' => 1, 'created_at' => Util::now(),
        ));
        $_SESSION['new_api_key'] = $key;
        Audit::log('apikey.create', 'apikey', $this->db->lastId());
        $this->redirect('/apikeys');
    }

    public function delete($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $me = Auth::user();
        $k = $this->db->one('SELECT k.*, u.username, u.auth_source FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.id = ?', array((int)$p['id']));
        if (!$k) {
            $this->redirect('/apikeys');
        }
        $owner = array('username' => $k['username'], 'auth_source' => $k['auth_source']);
        $mine = (int)$k['user_id'] === (int)$me['id'];
        if (!$mine && (!Auth::can(Auth::ROLE_ADMIN) || (Auth::isSuperRow($owner) && !Auth::isSuper()))) {
            Flash::set('error', t('root_protected'));
            $this->redirect('/apikeys');
        }
        $this->db->exec('DELETE FROM api_keys WHERE id = ?', array((int)$p['id']));
        Audit::log('apikey.delete', 'apikey', (int)$p['id']);
        $this->redirect('/apikeys');
    }
}

class SystemController extends Controller
{
    public function index()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $daemon = $this->db->one('SELECT * FROM daemon_status WHERE id = 1');
        $age = $daemon && $daemon['heartbeat_at'] ? time() - strtotime($daemon['heartbeat_at']) : null;
        $info = array(
            'app_version' => AC_VERSION,
            'php' => PHP_VERSION,
            'db' => $this->db->val('SELECT VERSION()'),
            'asterisk' => trim(Util::exec(escapeshellcmd(Config::get('app', 'asterisk_bin')) . ' -rx "core show version"')),
            'issabel' => $this->pbxVersion(),
            'sox' => Util::which('sox') ? 'yes' : 'no',
            'ffmpeg' => Util::which('ffmpeg') ? 'yes' : 'no',
            'zip_ext' => extension_loaded('zip') ? 'yes' : 'no',
            'disk_free' => Util::humanSize(@disk_free_space(Config::storage())),
            'audio_files' => (int)$this->db->val('SELECT COUNT(*) FROM audio_files'),
            'contacts' => (int)$this->db->val('SELECT COUNT(*) FROM campaign_contacts'),
            'attempts' => (int)$this->db->val('SELECT COUNT(*) FROM call_attempts'),
            'dialplan' => is_readable('/etc/asterisk/extensions_custom.conf') && strpos(file_get_contents('/etc/asterisk/extensions_custom.conf'), '[autocaller-ivr]') !== false ? 'ok' : 'missing',
        );
        $logs = array();
        foreach (array('dialer', 'agi', 'app') as $l) {
            $f = Config::storage('logs/' . $l . '.log');
            $logs[$l] = is_file($f) ? Util::humanSize(filesize($f)) : '-';
        }
        $this->view('system', array('daemon' => $daemon, 'age' => $age, 'info' => $info, 'logs' => $logs));
    }

    private function pbxVersion()
    {
        foreach (array('/etc/issabel/version' => 'Issabel', '/etc/elastix/version' => 'Elastix', '/etc/issabel.conf' => 'Issabel', '/etc/elastix.conf' => 'Elastix') as $f => $n) {
            if (is_file($f)) {
                $rpm = trim(Util::exec('rpm -q ' . ($n === 'Issabel' ? 'issabel-framework' : 'elastix-framework') . ' 2>/dev/null'));
                return $n . ($rpm && strpos($rpm, 'not installed') === false ? ' (' . $rpm . ')' : '');
            }
        }
        return 'unknown';
    }

    public function log($p)
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $name = in_array($p['name'], array('dialer', 'agi', 'app', 'php-error'), true) ? $p['name'] : 'app';
        $f = Config::storage('logs/' . $name . '.log');
        $lines = Request::int('lines', 200, 10, 2000);
        $txt = '';
        if (is_file($f)) {
            $txt = Util::exec('tail -n ' . $lines . ' ' . escapeshellarg($f));
        }
        if (Request::wantsJson()) {
            $this->json(array('ok' => true, 'log' => $txt));
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $txt;
        exit;
    }

    public function audit()
    {
        $this->requireRole(Auth::ROLE_ADMIN);
        $page = Request::int('page', 1, 1);
        $per = 50;
        $total = (int)$this->db->val('SELECT COUNT(*) FROM audit_log');
        $rows = $this->db->all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . (($page - 1) * $per) . ',' . $per);
        $this->view('audit', array('rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per));
    }
}
