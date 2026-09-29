<?php
class Request
{
    public static function method()
    {
        return isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    }

    public static function isPost()
    {
        return self::method() === 'POST';
    }

    public static function path()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $q = strpos($uri, '?');
        if ($q !== false) {
            $uri = substr($uri, 0, $q);
        }
        $base = rtrim(Config::get('app', 'base_url'), '/');
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public static function get($k, $default = null)
    {
        return isset($_GET[$k]) ? $_GET[$k] : $default;
    }

    public static function post($k, $default = null)
    {
        return isset($_POST[$k]) ? $_POST[$k] : $default;
    }

    public static function str($k, $default = '', $max = 255)
    {
        $v = isset($_POST[$k]) ? $_POST[$k] : (isset($_GET[$k]) ? $_GET[$k] : $default);
        if (is_array($v)) {
            return $default;
        }
        return mb_substr(trim((string)$v), 0, $max, 'UTF-8');
    }

    public static function int($k, $default = 0, $min = null, $max = null)
    {
        $v = isset($_POST[$k]) ? $_POST[$k] : (isset($_GET[$k]) ? $_GET[$k] : null);
        return Util::intOr(is_array($v) ? null : $v, $default, $min, $max);
    }

    public static function wantsJson()
    {
        $a = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        $x = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
        return strpos($a, 'application/json') !== false || strtolower($x) === 'xmlhttprequest';
    }

    public static function jsonBody()
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return array();
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : array();
    }

    public static function header($name)
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$k]) ? $_SERVER[$k] : null;
    }
}

class Flash
{
    public static function set($type, $msg)
    {
        $_SESSION['flash'][] = array('type' => $type, 'msg' => $msg);
    }

    public static function pull()
    {
        $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();
        unset($_SESSION['flash']);
        return $f;
    }
}

class View
{
    public static $title = '';

    public static function render($template, array $vars = array(), $layout = 'layout')
    {
        $file = APP_ROOT . '/app/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('view not found: ' . $template);
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();
        if ($layout) {
            include APP_ROOT . '/app/views/' . $layout . '.php';
        } else {
            echo $content;
        }
    }

    public static function partial($template, array $vars = array())
    {
        $file = APP_ROOT . '/app/views/' . $template . '.php';
        extract($vars, EXTR_SKIP);
        include $file;
    }

    public static function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo Util::json($data);
        exit;
    }

    public static function url($path = '')
    {
        return rtrim(Config::get('app', 'base_url'), '/') . '/' . ltrim($path, '/');
    }

    /** URL of a static asset with a cache-busting version derived from the file's mtime */
    public static function asset($path)
    {
        $f = APP_ROOT . '/public/' . ltrim($path, '/');
        $v = is_file($f) ? substr(md5(AC_VERSION . filemtime($f) . filesize($f)), 0, 8) : AC_VERSION;
        return self::url($path) . '?v=' . $v;
    }
}

class I18n
{
    private static $lang = 'fa';
    private static $strings = array();

    public static function setLang($lang)
    {
        $lang = in_array($lang, array('fa', 'en'), true) ? $lang : 'fa';
        self::$lang = $lang;
        $f = APP_ROOT . '/app/lang/' . $lang . '.php';
        self::$strings = is_file($f) ? include $f : array();
        if ($lang !== 'en') {
            $en = APP_ROOT . '/app/lang/en.php';
            if (is_file($en)) {
                self::$strings = array_merge(include $en, self::$strings);
            }
        }
    }

    public static function lang()
    {
        return self::$lang;
    }

    public static function isRtl()
    {
        return self::$lang === 'fa';
    }

    public static function t($key)
    {
        $args = func_get_args();
        array_shift($args);
        $s = isset(self::$strings[$key]) ? self::$strings[$key] : $key;
        if ($args) {
            $s = vsprintf($s, $args);
        }
        return $s;
    }
}

/** shorthand */
function t($key)
{
    $args = func_get_args();
    return call_user_func_array(array('I18n', 't'), $args);
}

function h($s)
{
    return Util::h($s);
}

class Logger
{
    private static $file;

    public static function setFile($f)
    {
        self::$file = $f;
    }

    public static function log($level, $msg)
    {
        $line = date('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . (defined('AC_CLI') ? '' : (Util::clientIp() . ' ')) . $msg . "\n";
        $f = self::$file ? self::$file : Config::storage('logs/app.log');
        @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
        if (defined('AC_CLI') && AC_CLI && (Config::get('app', 'debug') || $level !== 'debug')) {
            fwrite(STDERR, $line);
        }
    }

    public static function info($m)
    {
        self::log('info', $m);
    }

    public static function warn($m)
    {
        self::log('warn', $m);
    }

    public static function error($m)
    {
        self::log('error', $m);
    }

    public static function debug($m)
    {
        if (Config::get('app', 'debug')) {
            self::log('debug', $m);
        }
    }
}
