#!/usr/bin/env php
<?php
/**
 * AutoCaller command line tool.
 *
 *   php bin/console.php migrate                 apply sql/*.sql migrations
 *   php bin/console.php user:create <name> [role] [password]
 *   php bin/console.php user:passwd <name> [password]
 *   php bin/console.php user:rename <old> <new>
 *   php bin/console.php doctor                  check DB / AMI / asterisk / dirs / php
 *   php bin/console.php ami:test
 *   php bin/console.php dialer:status
 *   php bin/console.php cleanup                 apply retention_days
 *   php bin/console.php audio:import <dir>      import wav/mp3 files as audio prompts (e.g. from v1)
 *   php bin/console.php setting <key> [value]
 */
define('AC_CLI', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$argv = isset($argv) ? $argv : array();
$cmd = isset($argv[1]) ? $argv[1] : 'help';

function out($s)
{
    fwrite(STDOUT, $s . "\n");
}

function fail($s, $code = 1)
{
    fwrite(STDERR, $s . "\n");
    exit($code);
}

function readSecret($prompt)
{
    fwrite(STDOUT, $prompt);
    if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
        system('stty -echo');
        $p = rtrim(fgets(STDIN), "\r\n");
        system('stty echo');
        fwrite(STDOUT, "\n");
        return $p;
    }
    return rtrim(fgets(STDIN), "\r\n");
}

function migrate()
{
    $db = Db::get();
    $db->exec("CREATE TABLE IF NOT EXISTS schema_version (version INT NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $applied = array();
    foreach ($db->all('SELECT version FROM schema_version') as $r) {
        $applied[(int)$r['version']] = true;
    }
    $files = glob(APP_ROOT . '/sql/*.sql');
    sort($files);
    $n = 0;
    foreach ($files as $f) {
        if (!preg_match('/(\d+)_[^\/]+\.sql$/', $f, $m)) {
            continue;
        }
        $ver = (int)$m[1];
        if (isset($applied[$ver])) {
            continue;
        }
        $sql = file_get_contents($f);
        // strip comments, split on ; at end of line
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $stmts = preg_split('/;\s*(\r?\n|$)/', $sql);
        foreach ($stmts as $s) {
            $s = trim($s);
            if ($s === '') {
                continue;
            }
            $db->pdo()->exec($s);
        }
        $db->run('INSERT INTO schema_version (version, applied_at) VALUES (?, ?)', array($ver, Util::now()));
        out('applied migration ' . basename($f));
        $n++;
    }
    // seed settings defaults that are missing
    foreach (Settings::defaults() as $k => $v) {
        $db->run('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)', array($k, $v));
    }
    out($n ? "migrations done ($n)" : 'schema up to date');
}

switch ($cmd) {
    case 'migrate':
        migrate();
        break;

    case 'user:create':
        $name = isset($argv[2]) ? trim($argv[2]) : '';
        $role = isset($argv[3]) ? $argv[3] : 'admin';
        if ($name === '' || !preg_match('/^[A-Za-z0-9_.@\-]{2,64}$/', $name)) {
            fail('usage: user:create <username> [admin|operator|viewer] [password]');
        }
        if (!in_array($role, array('admin', 'operator', 'viewer'), true)) {
            fail('invalid role');
        }
        $pass = isset($argv[4]) ? $argv[4] : readSecret('Password: ');
        if (strlen($pass) < 8) {
            fail('password must be at least 8 characters');
        }
        $db = Db::get();
        if ($db->one('SELECT id FROM users WHERE username = ?', array($name))) {
            $db->update('users', array('password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role, 'is_active' => 1, 'auth_source' => 'local'), 'username = ?', array($name));
            out("user $name updated");
        } else {
            $db->insert('users', array('username' => $name, 'display_name' => $name, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role, 'auth_source' => 'local', 'is_active' => 1, 'created_at' => Util::now()));
            out("user $name created with role $role");
        }
        break;

    case 'user:passwd':
        $name = isset($argv[2]) ? trim($argv[2]) : '';
        $pass = isset($argv[3]) ? $argv[3] : readSecret('New password: ');
        if ($name === '' || strlen($pass) < 8) {
            fail('usage: user:passwd <username> [password>=8 chars]');
        }
        $n = Db::get()->update('users', array('password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'auth_source' => 'local', 'is_active' => 1), 'username = ?', array($name));
        out($n ? 'password updated' : 'user not found');
        break;

    case 'user:rename':
        $from = isset($argv[2]) ? trim($argv[2]) : '';
        $to = isset($argv[3]) ? trim($argv[3]) : '';
        if ($from === '' || !preg_match('/^[A-Za-z0-9_.@\-]{2,64}$/', $to)) {
            fail('usage: user:rename <old> <new>');
        }
        $db = Db::get();
        $u = $db->one("SELECT id FROM users WHERE username = ? AND auth_source = 'local'", array($from));
        if (!$u) {
            out("no local user '$from'");
            exit(2);
        }
        if ($db->val('SELECT id FROM users WHERE username = ?', array($to))) {
            out("user '$to' already exists");
            exit(3);
        }
        $db->update('users', array('username' => $to), 'id = ?', array((int)$u['id']));
        out("renamed $from -> $to");
        break;

    case 'user:has-admin':
        $n = (int)Db::get()->val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND auth_source = 'local' AND is_active = 1 AND password_hash <> ''");
        out($n ? "yes ($n)" : 'no');
        exit($n ? 0 : 1);

    case 'issabel:test':
        $name = isset($argv[2]) ? $argv[2] : 'admin';
        $pass = isset($argv[3]) ? $argv[3] : readSecret("Issabel password for $name: ");
        $acl = Config::get('issabel', 'acl_db', '/var/www/db/acl.db');
        out("acl store: $acl " . (is_file($acl) ? (is_readable($acl) ? '(readable)' : '(NOT readable by uid ' . (function_exists('posix_geteuid') ? posix_geteuid() : '?') . ')') : '(missing -> mysql acl fallback)'));
        out('pdo_sqlite: ' . (in_array('sqlite', PDO::getAvailableDrivers(), true) ? 'yes' : 'NO - install php-pdo'));
        $rp = Auth::pbxRootPassword();
        out('mysql root password: ' . ($rp === null ? 'not readable (only needed for CDR / trunk list)' : 'ok'));
        $r = Auth::issabelCheck($name, $pass);
        out($r ? "OK: $name is " . ($r['is_admin'] ? 'an Issabel admin' : 'a normal Issabel user') . " -> role " . ($r['is_admin'] ? Settings::get('issabel_admin_role', 'admin') : 'viewer') : 'FAILED (see storage/logs/app.log)');
        $local = Db::get()->one('SELECT username, role, auth_source, is_active FROM users WHERE username = ?', array($name));
        out('local user with this name: ' . ($local ? $local['auth_source'] . '/' . $local['role'] . ($local['is_active'] ? '' : ' (inactive)') : 'none'));
        out('issabel_login setting: ' . Settings::get('issabel_login'));
        exit($r ? 0 : 1);

    case 'setting':
        $k = isset($argv[2]) ? $argv[2] : '';
        if ($k === '') {
            foreach (Settings::all() as $kk => $vv) {
                out(str_pad($kk, 26) . ' = ' . $vv);
            }
            break;
        }
        if (isset($argv[3])) {
            Settings::set($k, $argv[3]);
            out("$k set");
        } else {
            out((string)Settings::get($k));
        }
        break;

    case 'ami:test':
        try {
            $ami = Ami::fromConfig();
            $ami->connect(5);
            out('AMI login OK (' . Config::get('ami', 'host') . ':' . Config::get('ami', 'port') . ' as ' . Config::get('ami', 'user') . ')');
            $v = $ami->command('core show version');
            if ($v !== '') {
                out(trim($v));
            }
            $ami->close();
        } catch (Exception $e) {
            fail('AMI FAILED: ' . $e->getMessage());
        }
        break;

    case 'dialer:status':
        $s = Db::get()->one('SELECT * FROM daemon_status WHERE id = 1');
        if (!$s) {
            fail('no status row');
        }
        $age = $s['heartbeat_at'] ? time() - strtotime($s['heartbeat_at']) : null;
        out('pid: ' . ($s['pid'] ? $s['pid'] : '-'));
        out('started: ' . $s['started_at']);
        out('heartbeat: ' . $s['heartbeat_at'] . ($age !== null ? " ({$age}s ago)" : ''));
        out('ami: ' . ($s['ami_connected'] ? 'connected' : 'DISCONNECTED'));
        out('active calls: ' . $s['active_calls']);
        out('last error: ' . ($s['last_error'] ? $s['last_error'] : '-'));
        exit(($age !== null && $age < 30) ? 0 : 2);

    case 'legacy:import-config':
        $f = isset($argv[2]) ? $argv[2] : '';
        $ini = $f && is_file($f) ? @parse_ini_file($f, true) : false;
        if (!$ini) {
            fail('usage: legacy:import-config <old config.ini>');
        }
        $g = function ($sec, $key) use ($ini) {
            return isset($ini[$sec][$key]) ? trim((string)$ini[$sec][$key]) : '';
        };
        $applied = array();
        $prefix = Util::dialSafe($g('prefixc', 'prefix'));
        Settings::set('dial_prefix', $prefix);
        $applied[] = "dial_prefix=" . ($prefix === '' ? '(none)' : $prefix);
        $cid = $g('callid', 'caller_id');
        if ($cid !== '') {
            if (preg_match('/^[0-9+*#]+$/', $cid)) {
                Settings::set('callerid_number', $cid);
                $applied[] = "callerid_number=$cid";
            } else {
                Settings::set('callerid_name', Util::oneLine($cid, 64));
                $applied[] = "callerid_name=$cid";
            }
        }
        $wt = (int)$g('waittimes', 'waittime');
        if ($wt >= 5 && $wt <= 120) {
            Settings::set('default_ring_timeout', (string)$wt);
            $applied[] = "default_ring_timeout=$wt";
        }
        $iv = (float)$g('callblaster', 'interval');
        if ($iv > 0 && $iv <= 600) {
            Settings::set('default_gap_ms', (string)(int)round($iv * 1000));
            $applied[] = "default_gap_ms=" . (int)round($iv * 1000);
        }
        $mr = $g('retry', 'maxretries');
        if ($mr !== '' && ctype_digit($mr)) {
            Settings::set('default_max_retries', (string)min(10, (int)$mr));
            $applied[] = "default_max_retries=$mr";
        }
        $rt = (int)$g('retry', 'retrytime');
        if ($rt >= 60) {
            Settings::set('default_retry_delay_min', (string)max(1, (int)round($rt / 60)));
            $applied[] = "default_retry_delay_min=" . max(1, (int)round($rt / 60));
        }
        $trunk = $g('trunkname', 'name');
        if ($trunk !== '') {
            Settings::set('trunk_name', Util::oneLine($trunk, 64));
            Settings::set('channel_tech', 'sip');
            $applied[] = "channel_tech=sip trunk_name=$trunk";
        }
        $ivr = Campaign::defaultIvr();
        for ($i = 1; $i <= 9; $i++) {
            $ext = Util::dialSafe($g("press$i", 'extension'));
            if ($ext !== '') {
                $ctx = preg_replace('/[^A-Za-z0-9_\-]/', '', $g("press$i", 'context'));
                $ivr['digits'][(string)$i] = array('action' => 'transfer', 'target' => $ext, 'context' => $ctx !== '' ? $ctx : 'from-internal', 'tag' => '');
                $applied[] = "press$i -> $ext";
            }
        }
        Settings::set('default_ivr', Util::json(Campaign::cleanIvr($ivr)));
        out('applied: ' . implode(', ', $applied));
        break;

    case 'call:test':
        // Originate one call synchronously through AMI, exactly like the dialer does, and print the outcome.
        $phone = isset($argv[2]) ? Util::normalizePhone($argv[2], Settings::get('country_code', '98')) : '';
        if ($phone === '') {
            fail('usage: call:test <phone> [audio-name]   (dials the number and plays the audio, or a demo prompt)');
        }
        $audioName = isset($argv[3]) ? $argv[3] : '';
        $file = 'demo-congrats';
        if ($audioName !== '') {
            $a = Db::get()->one('SELECT * FROM audio_files WHERE name = ? OR id = ?', array($audioName, (int)$audioName));
            if (!$a) {
                fail("audio '$audioName' not found");
            }
            $file = preg_replace('/\.[A-Za-z0-9]+$/', '', Config::storage($a['path']));
        }
        $eff = Campaign::effective(array('callerid_name' => null, 'callerid_number' => null, 'dial_prefix' => null, 'channel_tech' => null, 'trunk_name' => null));
        $channel = Campaign::channelFor($eff, $phone);
        $cid = Dialer::callerIdString($eff['callerid_name'], $eff['callerid_number']);
        out("channel : $channel");
        out("callerid: $cid");
        out("audio   : $file");
        try {
            $ami = Ami::fromConfig();
            $ami->connect(5);
        } catch (Exception $e) {
            fail('AMI: ' . $e->getMessage());
        }
        $timeout = (int)Settings::get('default_ring_timeout', 30);
        out("dialing (up to {$timeout}s)...");
        $r = $ami->action('Originate', array(
            'Channel' => $channel, 'Application' => 'Playback', 'Data' => $file,
            'Timeout' => $timeout * 1000, 'CallerID' => $cid, 'Account' => 'autocaller-test', 'Async' => 'false',
        ), $timeout + 10);
        $ami->close();
        if (!$r) {
            fail('no response from AMI within ' . ($timeout + 10) . 's');
        }
        out('response: ' . $r['Response'] . (isset($r['Message']) ? ' - ' . $r['Message'] : '') . (isset($r['Reason']) ? ' (reason ' . $r['Reason'] . ' = ' . CallStatus::fromReason($r['Reason']) . ')' : ''));
        if (strtolower($r['Response']) !== 'success') {
            out('hint: watch "asterisk -rvvv" while running this; check outbound routes accept ' . $eff['dial_prefix'] . $phone . ' and that the caller id number is allowed');
            exit(1);
        }
        break;

    case 'audio:import':
        $dir = isset($argv[2]) ? rtrim($argv[2], '/') : '';
        if ($dir === '' || !is_dir($dir)) {
            fail('usage: audio:import <directory with wav/mp3/gsm files>');
        }
        $db = Db::get();
        $n = 0;
        foreach (glob($dir . '/*') as $f) {
            if (!is_file($f) || !preg_match('/\.(wav|mp3|gsm|ogg)$/i', $f)) {
                continue;
            }
            $name = Audio::cleanName(pathinfo($f, PATHINFO_FILENAME));
            if ($db->val('SELECT id FROM audio_files WHERE name = ?', array($name))) {
                out("skip $name (exists)");
                continue;
            }
            $id = $db->insert('audio_files', array('name' => $name, 'original_name' => basename($f), 'path' => 'audio/pending', 'created_by' => null, 'created_at' => Util::now()));
            $rel = 'audio/ac_' . $id . '.wav';
            $dest = Config::storage($rel);
            try {
                Audio::convert($f, $dest);
                @chmod($dest, 0664);
                $db->update('audio_files', array('path' => $rel, 'duration_sec' => Audio::duration($dest), 'size_bytes' => (int)filesize($dest)), 'id = ?', array($id));
                out("imported $name (" . Audio::duration($dest) . 's)');
                $n++;
            } catch (Exception $e) {
                $db->exec('DELETE FROM audio_files WHERE id = ?', array($id));
                out("FAILED $name: " . $e->getMessage());
            }
        }
        out("$n audio files imported");
        break;

    case 'cleanup':
        $days = (int)Settings::get('retention_days', 0);
        if ($days <= 0) {
            out('retention disabled');
            break;
        }
        $db = Db::get();
        $n1 = $db->exec('DELETE FROM call_attempts WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)', array($days));
        $n2 = $db->exec("DELETE cc FROM campaign_contacts cc JOIN campaigns c ON c.id = cc.campaign_id WHERE c.status IN ('completed','stopped') AND c.finished_at < DATE_SUB(NOW(), INTERVAL ? DAY)", array($days));
        $n3 = $db->exec('DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', array($days));
        $n4 = $db->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        out("deleted attempts=$n1 contacts=$n2 audit=$n3 login_attempts=$n4");
        break;

    case 'doctor':
        $ok = true;
        out('AutoCaller ' . AC_VERSION . ' doctor');
        out('PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
        foreach (array('pdo_mysql', 'mbstring', 'json', 'iconv') as $ext) {
            $has = extension_loaded($ext);
            out(($has ? '  [ok] ' : '  [MISSING] ') . 'ext ' . $ext);
            $ok = $ok && $has;
        }
        out((extension_loaded('zip') ? '  [ok] ' : '  [warn] ') . 'ext zip (xlsx import/export; falls back to unzip/csv)');
        out((function_exists('pcntl_signal') ? '  [ok] ' : '  [warn] ') . 'ext pcntl (graceful daemon shutdown)');
        try {
            Db::get()->val('SELECT 1');
            out('  [ok] database connection');
            $v = Db::get()->val('SELECT VERSION()');
            out('       server ' . $v);
            $missing = array();
            foreach (array('users', 'campaigns', 'campaign_contacts', 'call_attempts', 'settings', 'dnc', 'audio_files', 'api_keys', 'daemon_status') as $t) {
                if (!Db::get()->tableExists($t)) {
                    $missing[] = $t;
                }
            }
            out($missing ? '  [MISSING] tables: ' . implode(',', $missing) . ' (run migrate)' : '  [ok] schema');
            $ok = $ok && !$missing;
        } catch (Exception $e) {
            out('  [FAIL] database: ' . $e->getMessage());
            $ok = false;
        }
        foreach (array('audio', 'uploads', 'tmp', 'logs', 'run') as $d) {
            $p = Config::storage($d);
            $w = is_dir($p) && is_writable($p);
            out(($w ? '  [ok] ' : '  [FAIL] ') . 'writable ' . $p);
            $ok = $ok && $w;
        }
        $ast = Config::get('app', 'asterisk_bin');
        out((is_executable($ast) ? '  [ok] ' : '  [warn] ') . 'asterisk binary ' . $ast);
        $sox = Util::which('sox');
        out(($sox ? '  [ok] ' : '  [warn] ') . 'sox ' . ($sox ? $sox : '(not found, will use asterisk file convert)'));
        try {
            $ami = Ami::fromConfig();
            $ami->connect(4);
            out('  [ok] AMI login');
            $ami->close();
        } catch (Exception $e) {
            out('  [FAIL] AMI: ' . $e->getMessage());
            $ok = false;
        }
        try {
            $s = Db::get()->one('SELECT * FROM daemon_status WHERE id = 1');
            $age = $s && $s['heartbeat_at'] ? time() - strtotime($s['heartbeat_at']) : null;
            out((($age !== null && $age < 30) ? '  [ok] ' : '  [warn] ') . 'dialer daemon heartbeat ' . ($age === null ? 'never' : $age . 's ago'));
        } catch (Exception $e) {
        }
        foreach (array('/etc/asterisk/extensions_custom.conf') as $f) {
            $has = is_readable($f) && strpos(file_get_contents($f), '[autocaller-ivr]') !== false;
            out(($has ? '  [ok] ' : '  [warn] ') . 'dialplan context autocaller-ivr in ' . $f);
        }
        $rp = Auth::pbxRootPassword();
        out(($rp !== null ? '  [ok] ' : '  [warn] ') . 'PBX mysql root password readable (Issabel login / CDR lookup)');
        try {
            $pdo = Auth::aclPdo();
            if ($pdo) {
                $n = (int)$pdo->query('SELECT COUNT(*) FROM acl_user')->fetchColumn();
                out("  [ok] Issabel ACL store reachable ($n panel users)");
            } else {
                out('  [warn] Issabel ACL store not reachable (see storage/logs/app.log) - panel-user login disabled');
            }
        } catch (Exception $e) {
            out('  [warn] Issabel ACL store: ' . $e->getMessage());
        }
        $admins = (int)Db::get()->val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
        out(($admins ? '  [ok] ' : '  [warn] ') . "$admins active admin user(s)" . ($admins ? '' : ' - create one: console.php user:create admin admin'));
        out('  running as uid ' . (function_exists('posix_geteuid') ? posix_geteuid() : '?') . ', /etc/issabel.conf readable: ' . (is_readable('/etc/issabel.conf') ? 'yes' : 'no') . ', config.php readable: ' . (is_readable(APP_ROOT . '/config/config.php') ? 'yes' : 'no'));
        out($ok ? 'RESULT: OK' : 'RESULT: PROBLEMS FOUND');
        exit($ok ? 0 : 1);

    case 'help':
    default:
        out("usage: console.php migrate | user:create | user:passwd | user:rename | user:has-admin | issabel:test [user] | setting | ami:test | call:test <phone> [audio] | dialer:status | cleanup | audio:import <dir> | legacy:import-config <ini> | doctor");
        break;
}
