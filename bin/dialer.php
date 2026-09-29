#!/usr/bin/env php
<?php
/**
 * AutoCaller dialer daemon. Run by systemd (autocaller-dialer.service) as user asterisk.
 *   php bin/dialer.php          run in foreground
 */
define('AC_CLI', true);
require dirname(__DIR__) . '/app/bootstrap.php';
set_time_limit(0);
ini_set('memory_limit', '256M');

$lock = Config::storage('run/dialer.lock');
$fp = fopen($lock, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "another dialer instance is already running\n");
    exit(1);
}
ftruncate($fp, 0);
fwrite($fp, (string)getmypid());
fflush($fp);

Logger::setFile(Config::storage('logs/dialer.log'));
$d = new Dialer();
$d->run();
flock($fp, LOCK_UN);
exit(0);
