<?php
/**
 * Authentication, sessions, roles, CSRF.
 * Roles: admin (everything), operator (campaigns/audio/dnc), viewer (read only).
 */
class Auth
{
    const ROLE_ADMIN = 'admin';
    const ROLE_OPERATOR = 'operator';
    const ROLE_VIEWER = 'viewer';

    private static $user = null;

    public static function startSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name('ACSESSID');
        session_set_cookie_params(0, Config::get('app', 'base_url') . '/', '', $secure, true);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function user()
    {
        if (self::$user !== null) {
            return self::$user ?: null;
        }
        self::$user = false;
        if (!empty($_SESSION['uid'])) {
            $u = Db::get()->one('SELECT * FROM users WHERE id = ? AND is_active = 1', array((int)$_SESSION['uid']));
            if ($u) {
                // session fixation / password change protection
                if (!empty($_SESSION['pwd_stamp']) && $_SESSION['pwd_stamp'] !== substr(md5($u['password_hash']), 0, 12)) {
                    self::logout();
                    return null;
                }
                self::$user = $u;
            }
        }
        return self::$user ?: null;
    }

    public static function check()
    {
        return self::user() !== null;
    }

    public static function role()
    {
        $u = self::user();
        return $u ? $u['role'] : null;
    }

    public static function can($role)
    {
        $order = array(self::ROLE_VIEWER => 1, self::ROLE_OPERATOR => 2, self::ROLE_ADMIN => 3);
        $mine = self::role();
        if (!$mine || !isset($order[$mine]) || !isset($order[$role])) {
            return false;
        }
        return $order[$mine] >= $order[$role];
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            $_SESSION['after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            Util::redirect(Config::get('app', 'base_url') . '/login');
        }
    }

    public static function requireRole($role)
    {
        self::requireLogin();
        if (!self::can($role)) {
            http_response_code(403);
            View::render('error', array('code' => 403, 'message' => I18n::t('forbidden')));
            exit;
        }
    }

    /** @return array|string user row on success, error string on failure */
    public static function login($username, $password)
    {
        $db = Db::get();
        $username = trim((string)$username);
        $ip = Util::clientIp();
        if ($username === '' || $password === '') {
            return 'login_failed';
        }
        if (self::isLocked($username, $ip)) {
            return 'login_locked';
        }
        $u = $db->one('SELECT * FROM users WHERE username = ?', array($username));
        if ($u && $u['auth_source'] === 'local' && (int)$u['is_active'] === 1 && password_verify($password, $u['password_hash'])) {
            self::onLoginOk($u);
            return $u;
        }
        // Issabel / Elastix panel users (also accepted for a same-named local user whose local password did not match)
        if (Settings::get('issabel_login') === '1') {
            $iu = self::issabelCheck($username, $password);
            if ($iu) {
                if ($u && $u['auth_source'] === 'local') {
                    Logger::info("login: local user '$username' authenticated with Issabel panel credentials");
                }
                if (!$u) {
                    $role = $iu['is_admin'] ? Settings::get('issabel_admin_role', 'admin') : self::ROLE_VIEWER;
                    $id = $db->insert('users', array(
                        'username' => $username,
                        'display_name' => $iu['display_name'],
                        'password_hash' => '',
                        'role' => $role,
                        'auth_source' => 'issabel',
                        'is_active' => 1,
                        'created_at' => Util::now(),
                    ));
                    $u = $db->one('SELECT * FROM users WHERE id = ?', array($id));
                }
                if ((int)$u['is_active'] === 1) {
                    self::onLoginOk($u);
                    return $u;
                }
            }
        }
        $db->insert('login_attempts', array('username' => substr($username, 0, 64), 'ip' => $ip, 'attempted_at' => Util::now()));
        return 'login_failed';
    }

    private static function onLoginOk(array $u)
    {
        $db = Db::get();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['pwd_stamp'] = substr(md5($u['password_hash']), 0, 12);
        $_SESSION['csrf'] = Util::token(16);
        $db->update('users', array('last_login_at' => Util::now(), 'last_login_ip' => Util::clientIp()), 'id = ?', array((int)$u['id']));
        $db->exec('DELETE FROM login_attempts WHERE username = ? OR ip = ?', array($u['username'], Util::clientIp()));
        Audit::log('login', 'user', $u['id'], $u['username']);
        self::$user = null;
    }

    private static function isLocked($username, $ip)
    {
        $max = (int)Settings::get('login_max_attempts', 5);
        $min = (int)Settings::get('login_lock_minutes', 15);
        if ($max <= 0) {
            return false;
        }
        $n = (int)Db::get()->val(
            'SELECT COUNT(*) FROM login_attempts WHERE (username = ? OR ip = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            array($username, $ip, $min)
        );
        return $n >= $max;
    }

    public static function logout()
    {
        if (self::check()) {
            Audit::log('logout', 'user', self::$user['id'], self::$user['username']);
        }
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], isset($p['httponly']) ? $p['httponly'] : true);
        }
        session_destroy();
        self::$user = null;
    }

    /**
     * Validate a user against the Issabel/Elastix ACL database.
     * Returns array(display_name, is_admin) or null.
     */
    public static function issabelCheck($username, $password)
    {
        $pdo = self::aclPdo();
        if (!$pdo) {
            return null;
        }
        try {
            $st = $pdo->prepare('SELECT * FROM acl_user WHERE name = ? LIMIT 1');
            $st->execute(array($username));
            $row = $st->fetch();
            if (!$row) {
                Logger::info("issabel login: no acl_user named '$username'");
                return null;
            }
            $ok = false;
            $stored = isset($row['md5_password']) ? (string)$row['md5_password'] : '';
            if ($stored !== '') {
                if (substr($stored, 0, 1) === '$') {
                    $ok = password_verify($password, $stored);
                } else {
                    $ok = hash_equals(strtolower($stored), md5($password));
                }
            }
            if (!$ok) {
                Logger::info("issabel login: wrong password for '$username'");
                return null;
            }
            $isAdmin = ($username === 'admin');
            try {
                $st = $pdo->prepare('SELECT g.name FROM acl_membership m JOIN acl_group g ON g.id = m.id_group WHERE m.id_user = ?');
                $st->execute(array($row['id']));
                foreach ($st->fetchAll() as $g) {
                    if (in_array(strtolower($g['name']), array('administrator', 'admin'), true)) {
                        $isAdmin = true;
                    }
                }
            } catch (Exception $e) {
                // ignore, membership table layout may differ
            }
            $display = isset($row['description']) && $row['description'] !== '' && $row['description'] !== null ? $row['description'] : $username;
            return array('display_name' => $display, 'is_admin' => $isAdmin);
        } catch (Exception $e) {
            Logger::warn('issabel login check failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * All Issabel/Elastix panel users with their groups.
     * @return array of array(name, description, groups[], is_admin)
     */
    public static function issabelUsers()
    {
        $pdo = self::aclPdo();
        if (!$pdo) {
            return array();
        }
        try {
            $users = $pdo->query('SELECT id, name, description, extension FROM acl_user ORDER BY name')->fetchAll();
            $groups = array();
            try {
                foreach ($pdo->query('SELECT m.id_user, g.name FROM acl_membership m JOIN acl_group g ON g.id = m.id_group')->fetchAll() as $g) {
                    $groups[$g['id_user']][] = $g['name'];
                }
            } catch (Exception $e) {
            }
            $out = array();
            foreach ($users as $u) {
                $gs = isset($groups[$u['id']]) ? $groups[$u['id']] : array();
                $isAdmin = $u['name'] === 'admin';
                foreach ($gs as $g) {
                    if (in_array(strtolower($g), array('administrator', 'admin'), true)) {
                        $isAdmin = true;
                    }
                }
                $out[] = array('name' => $u['name'], 'description' => (string)$u['description'], 'extension' => (string)$u['extension'], 'groups' => $gs, 'is_admin' => $isAdmin);
            }
            return $out;
        } catch (Exception $e) {
            Logger::warn('issabel user list: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Connection to the Issabel/Elastix ACL store.
     * Issabel 4 & 5 and Elastix keep panel users in SQLite (/var/www/db/acl.db); a MySQL 'acl' db is tried as a fallback.
     * @return PDO|null
     */
    public static function aclPdo()
    {
        $opts = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 3);
        $sqlite = Config::get('issabel', 'acl_db', '/var/www/db/acl.db');
        if ($sqlite && is_file($sqlite)) {
            if (!is_readable($sqlite)) {
                Logger::warn("issabel login: $sqlite exists but is not readable by this process");
            } elseif (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                Logger::warn('issabel login: PHP pdo_sqlite extension missing (Issabel 4: yum install php-pdo ; Issabel 5: dnf install php-pdo)');
            } else {
                try {
                    return new PDO('sqlite:' . $sqlite, null, null, $opts);
                } catch (Exception $e) {
                    Logger::warn('issabel login: sqlite open failed: ' . $e->getMessage());
                }
            }
        }
        $root = self::pbxRootPassword();
        if ($root === null) {
            Logger::warn('issabel login: no acl.db and no mysql root password available');
            return null;
        }
        try {
            return new PDO('mysql:host=localhost;dbname=acl;charset=utf8', 'root', $root, $opts);
        } catch (Exception $e) {
            Logger::warn('issabel login: no ACL store found (' . $sqlite . ' missing, mysql: ' . $e->getMessage() . ')');
            return null;
        }
    }

    /** Read mysqlrootpwd from /etc/issabel.conf or /etc/elastix.conf */
    public static function pbxRootPassword()
    {
        $fromConfig = Config::get('issabel', 'mysql_root', '');
        if ($fromConfig !== '' && $fromConfig !== null) {
            return $fromConfig;
        }
        $candidates = array(Config::get('issabel', 'conf_file'), '/etc/issabel.conf', '/etc/elastix.conf');
        foreach ($candidates as $f) {
            if ($f && is_readable($f)) {
                $txt = file_get_contents($f);
                if (preg_match('/^\s*mysqlrootpwd\s*=\s*(.*)$/m', $txt, $m)) {
                    return trim($m[1]);
                }
            }
        }
        return null;
    }

    // ---- CSRF ----
    public static function csrfToken()
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = Util::token(16);
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField()
    {
        return '<input type="hidden" name="_csrf" value="' . Util::h(self::csrfToken()) . '">';
    }

    public static function checkCsrf()
    {
        $t = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
        if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
            http_response_code(400);
            if (Request::wantsJson()) {
                echo Util::json(array('ok' => false, 'error' => 'csrf'));
            } else {
                View::render('error', array('code' => 400, 'message' => I18n::t('csrf_failed')));
            }
            exit;
        }
    }

    // ---- API keys ----
    /** @return array|null api_keys row joined with user */
    public static function apiUser($key)
    {
        if (!$key || Settings::get('api_enabled') !== '1') {
            return null;
        }
        $hash = hash('sha256', $key);
        $row = Db::get()->one(
            'SELECT k.*, u.username, u.role, u.is_active FROM api_keys k JOIN users u ON u.id = k.user_id WHERE k.key_hash = ? AND k.is_active = 1',
            array($hash)
        );
        if (!$row || (int)$row['is_active'] !== 1) {
            return null;
        }
        if (!empty($row['allowed_ips'])) {
            $ips = array_map('trim', explode(',', $row['allowed_ips']));
            if (!in_array(Util::clientIp(), $ips, true)) {
                return null;
            }
        }
        Db::get()->update('api_keys', array('last_used_at' => Util::now()), 'id = ?', array((int)$row['id']));
        return $row;
    }
}

class Audit
{
    public static function log($action, $objectType = '', $objectId = null, $details = '')
    {
        try {
            $u = Auth::check() ? Auth::user() : null;
            Db::get()->insert('audit_log', array(
                'user_id' => $u ? (int)$u['id'] : null,
                'username' => $u ? $u['username'] : (defined('AC_CLI') ? 'cli' : 'anonymous'),
                'ip' => defined('AC_CLI') ? '' : Util::clientIp(),
                'action' => $action,
                'object_type' => $objectType,
                'object_id' => $objectId === null ? null : (int)$objectId,
                'details' => is_string($details) ? substr($details, 0, 1000) : Util::json($details),
                'created_at' => Util::now(),
            ));
        } catch (Exception $e) {
            // never break the app because of audit
        }
    }
}
