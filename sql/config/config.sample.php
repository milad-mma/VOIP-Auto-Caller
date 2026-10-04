<?php
// Copy to config.php (install.sh does this automatically)
return array(
    'db'  => array('host' => 'localhost', 'name' => 'autocaller', 'user' => 'autocaller', 'pass' => 'CHANGE_ME', 'socket' => ''),
    'ami' => array('host' => '127.0.0.1', 'port' => 5038, 'user' => 'autocaller', 'secret' => 'CHANGE_ME'),
    'app' => array(
        'base_url' => '/autocaller',
        'secret' => 'CHANGE_ME',
        'timezone' => 'Asia/Tehran',
        'lang' => 'fa',
        'storage' => '/opt/autocaller/storage',
        'asterisk_bin' => '/usr/sbin/asterisk',
        'php_bin' => '/usr/bin/php',
        'debug' => false,
    ),
    'issabel' => array('conf_file' => '/etc/issabel.conf'),
);
