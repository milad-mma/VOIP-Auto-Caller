<?php
/**
 * Renders every GET page of the web UI with a mocked database (no MySQL needed) and
 * reports PHP errors/exceptions. Usage: php tests/render.php [path]
 */
define('APP_ROOT', dirname(__DIR__));
define('AC_CLI', false); // behave like the web SAPI
$path = isset($argv[1]) ? $argv[1] : '/';
$method = isset($argv[2]) ? $argv[2] : 'GET';
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = $path;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'pbx';
$_SERVER['HTTP_ACCEPT'] = strpos($path, '.json') !== false ? 'application/json' : 'text/html';
error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors', '0');
require APP_ROOT . '/app/bootstrap.php';

require __DIR__ . '/MockDb.php';
$ref = new ReflectionClass('Db');
$p = $ref->getProperty('instance');
$p->setAccessible(true);
$p->setValue(null, new MockDb());

$_SESSION['uid'] = getenv('NOLOGIN') ? 0 : 1;
$_SESSION['csrf'] = 'tok';
$_SESSION['pwd_stamp'] = null;
$_POST['_csrf'] = 'tok';
$_SESSION['new_api_key'] = 'ack_test';
$_SESSION['import_abc'] = array('campaign' => 5);
$_GET = array();
// pwd_stamp check: compute from mock hash -> we bypass by leaving empty (Auth skips when empty)

ob_start();
$errors = array();
set_error_handler(function ($no, $str, $file, $line) use (&$errors) { $errors[] = "$str @$file:$line"; return true; });
try {
    $r = new Router();
    // mirror routes of public/index.php
    $src = file_get_contents(APP_ROOT . '/public/index.php');
    preg_match_all("/\\\$r->(get|post|any)\\('([^']+)', '([^']+)'\\);/", $src, $mm, PREG_SET_ORDER);
    foreach ($mm as $m) {
        $r->add(strtoupper($m[1]) === 'ANY' ? '*' : strtoupper($m[1]), $m[2], $m[3]);
    }
    $r->dispatch(Request::method(), Request::path());
} catch (Exception $e) {
    $errors[] = get_class($e) . ': ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine();
}
$out = ob_get_clean();
$code = http_response_code();
echo str_pad($method . ' ' . $path, 40) . ' ' . $code . ' ' . strlen($out) . "b" . ($errors ? "\n   ERRORS: " . implode("\n   ", $errors) : '') . "\n";
if (getenv('DUMP')) {
    echo $out;
}
