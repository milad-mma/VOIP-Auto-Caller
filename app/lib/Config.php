<?php
/**
 * Static config (config/config.php written by the installer) + DB backed settings.
 */
class Config
{
    private static $cfg;

    public static function load()
    {
        if (self::$cfg !== null) {
            return;
        }
        $file = APP_ROOT . '/config/config.php';
        $cfg = array();
        if (is_file($file)) {
            $cfg = include $file;
        }
        if (!is_array($cfg)) {
            $cfg = array();
        }
        $defaults = array(
            'db' => array('host' => 'localhost', 'name' => 'autocaller', 'user' => 'autocaller', 'pass' => '', 'socket' => ''),
            'ami' => array('host' => '127.0.0.1', 'port' => 5038, 'user' => 'autocaller', 'secret' => ''),
            'app' => array(
                'base_url' => '/autocaller',
                'secret' => '',
                'timezone' => 'Asia/Tehran',
                'lang' => 'fa',
                'storage' => APP_ROOT . '/storage',
                'asterisk_bin' => '/usr/sbin/asterisk',
                'php_bin' => '/usr/bin/php',
                'debug' => false,
            ),
            'issabel' => array(
                // mysql root credentials of the PBX, used only for "login with Issabel user"
                'conf_file' => '/etc/issabel.conf',
                'mysql_root' => '',
            ),
        );
        foreach ($defaults as $k => $v) {
            if (!isset($cfg[$k]) || !is_array($cfg[$k])) {
                $cfg[$k] = array();
            }
            $cfg[$k] = array_merge($v, $cfg[$k]);
        }
        self::$cfg = $cfg;
    }

    public static function get($section, $key = null, $default = null)
    {
        self::load();
        if (!isset(self::$cfg[$section])) {
            return $default;
        }
        if ($key === null) {
            return self::$cfg[$section];
        }
        return isset(self::$cfg[$section][$key]) ? self::$cfg[$section][$key] : $default;
    }

    public static function storage($sub = '')
    {
        $s = rtrim(self::get('app', 'storage'), '/');
        return $sub === '' ? $s : $s . '/' . ltrim($sub, '/');
    }

    public static function isInstalled()
    {
        return is_file(APP_ROOT . '/config/config.php');
    }
}

/**
 * Runtime settings, stored in the `settings` table (key/value) and editable from the UI.
 */
class Settings
{
    private static $cache;

    public static function defaults()
    {
        return array(
            'callerid_name' => 'AutoCaller',
            'callerid_number' => '',
            'dial_prefix' => '',            // e.g. 9 for outside line
            'channel_tech' => 'local',      // local | sip | pjsip | custom
            'trunk_name' => '',
            'channel_template' => '',       // custom template e.g. SIP/{trunk}/{number}
            'outbound_context' => 'from-internal',
            'country_code' => '98',
            'global_max_concurrent' => '4', // hard ceiling across all campaigns
            'default_ring_timeout' => '30',
            'default_gap_ms' => '1500',
            'default_max_retries' => '1',
            'default_retry_delay_min' => '15',
            'default_concurrent' => '2',
            'work_start' => '09:00',
            'work_end' => '21:00',
            'work_days' => '0,1,2,3,4,5,6', // 0=Sunday ... 6=Saturday (PHP w)
            'respect_holidays' => '1',
            'cdr_lookup' => '1',            // enrich with asteriskcdrdb after hangup
            'amd_enabled' => '0',           // answering machine detection (needs app_amd)
            'stale_call_minutes' => '10',
            'login_max_attempts' => '5',
            'login_lock_minutes' => '15',
            'issabel_login' => '1',
            'issabel_admin_role' => 'admin',
            'api_enabled' => '1',
            'ui_lang' => 'fa',
            'retention_days' => '0',        // 0 = keep forever
            'default_ivr' => '',            // JSON, pre-fills the IVR table of new campaigns
        );
    }

    public static function all()
    {
        if (self::$cache === null) {
            self::$cache = self::defaults();
            try {
                $rows = Db::get()->all('SELECT k, v FROM settings');
                foreach ($rows as $r) {
                    self::$cache[$r['k']] = $r['v'];
                }
            } catch (Exception $e) {
                // table may not exist yet during install
            }
        }
        return self::$cache;
    }

    public static function get($k, $default = null)
    {
        $a = self::all();
        if (array_key_exists($k, $a)) {
            return $a[$k];
        }
        return $default;
    }

    public static function set($k, $v)
    {
        $db = Db::get();
        $db->run('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', array($k, (string)$v));
        if (self::$cache !== null) {
            self::$cache[$k] = (string)$v;
        }
    }

    public static function reset()
    {
        self::$cache = null;
    }
}
