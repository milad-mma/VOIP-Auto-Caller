#!/usr/bin/env php
<?php
/**
 * AGI entry point. Called by Asterisk for every answered outbound call:
 *   exten => s,1,AGI(/opt/autocaller/bin/agi.php)
 */
define('AC_CLI', true);
require dirname(__DIR__) . '/app/bootstrap.php';
set_time_limit(0);
Logger::setFile(Config::storage('logs/agi.log'));
try {
    $agi = new Agi();
    $ivr = new AgiIvr($agi);
    $ivr->run();
} catch (Exception $e) {
    Logger::error('agi fatal: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
} catch (Error $e) {
    Logger::error('agi fatal: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
}
exit(0);
