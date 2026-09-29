<?php
/**
 * Common bootstrap for web (public/index.php), API, CLI (bin/*).
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
if (!defined('AC_CLI')) {
    define('AC_CLI', PHP_SAPI === 'cli');
}
define('AC_VERSION', '2.0.0');

error_reporting(E_ALL & ~E_DEPRECATED & ~2048); // 2048 = E_STRICT (constant deprecated in PHP 8.4)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
mb_internal_encoding('UTF-8');

require APP_ROOT . '/app/lib/Polyfill.php';

spl_autoload_register(function ($class) {
    $map = array(
        'Util' => 'Util', 'Db' => 'Db', 'Config' => 'Config', 'Settings' => 'Config',
        'Auth' => 'Auth', 'Audit' => 'Auth',
        'Request' => 'Http', 'Flash' => 'Http', 'View' => 'Http', 'I18n' => 'Http', 'Logger' => 'Http',
        'Router' => 'Router',
        'Ami' => 'Ami', 'AmiException' => 'Ami',
        'Dialer' => 'Dialer',
        'Agi' => 'Agi', 'AgiIvr' => 'Agi',
        'Campaign' => 'Campaign', 'CallStatus' => 'Campaign', 'Schedule' => 'Campaign',
        'Importer' => 'Importer', 'XlsxReader' => 'Importer',
        'Exporter' => 'Exporter', 'XlsxWriter' => 'Exporter',
        'Audio' => 'Audio', 'Jalali' => 'Jalali',
        'Dnc' => 'Dnc',
        'Controller' => 'Router',
    );
    if (isset($map[$class])) {
        require_once APP_ROOT . '/app/lib/' . $map[$class] . '.php';
        return;
    }
    if (substr($class, -10) === 'Controller') {
        $f = APP_ROOT . '/app/controllers/' . $class . '.php';
        if (is_file($f)) {
            require_once $f;
            return;
        }
        // several controllers may share a file: load them all once
        static $loadedAll = false;
        if (!$loadedAll) {
            $loadedAll = true;
            foreach (glob(APP_ROOT . '/app/controllers/*.php') as $cf) {
                require_once $cf;
            }
        }
    }
});

Config::load();
date_default_timezone_set(Config::get('app', 'timezone', 'Asia/Tehran'));

set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

if (!AC_CLI) {
    Auth::startSession();
    $lang = getenv('AC_LANG') ? getenv('AC_LANG') : (isset($_SESSION['lang']) ? $_SESSION['lang'] : null);
    if (!$lang) {
        $lang = Settings::get('ui_lang', Config::get('app', 'lang', 'fa'));
    }
    I18n::setLang($lang);
} else {
    I18n::setLang(Config::get('app', 'lang', 'fa'));
}
