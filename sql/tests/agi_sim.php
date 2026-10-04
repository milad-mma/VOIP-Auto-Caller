<?php
/** Simulates Asterisk talking to bin/agi.php through stdin/stdout with a mocked DB. */
define('APP_ROOT', dirname(__DIR__)); define('AC_CLI', true);
error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors', '0');
require APP_ROOT . '/app/bootstrap.php';
require __DIR__ . '/MockDb.php';
$ref = new ReflectionClass('Db'); $p = $ref->getProperty('instance'); $p->setAccessible(true); $mock = new MockDb(); $p->setValue(null, $mock);
@mkdir(Config::storage('audio'), 0777, true); file_put_contents(Config::storage('audio/ac_1.wav'), str_repeat('x', 100));
$agi = new Agi(); $ivr = new AgiIvr($agi); $ivr->run();
fwrite(STDERR, "UPDATES: " . json_encode($mock->updates) . "\n");
