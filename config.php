<?php
/**
* @file
*
* All Callblaster code is released under the GNU General Public License.
* See COPYRIGHT.txt and LICENSE.txt.
*
*....................
* imapro.ir
*/

//Database configuration
//.............................................
$db_host="localhost";
$db_name="callblaster";
$db_user="callblaster";
$db_pass="callblaster";
//.............................................


//caller id and name
$config = parse_ini_file("config.ini",true);
$caller_id=$config['callid']['caller_id'];
$prefix=isset($config['prefixc']['prefix']) ? $config['prefixc']['prefix'] : '';
//..............................................


//paths
//..............................................

$basepath="/var/www/html/autocaller/";

$agipath="/var/lib/asterisk/agi-bin/";

$welcomeSound = $basepath."audio/welcome";
//sound file without extension
//..............................................



//agi configurations
$config = parse_ini_file("config.ini",true);
for ($i = 1; $i <= 9; $i++) {
    ${"exten_$i"}   = isset($config["press$i"]['extension']) ? $config["press$i"]['extension'] : '';
    ${"context_$i"} = isset($config["press$i"]['context']) ? $config["press$i"]['context'] : '';
    ${"priority_$i"} = "1";
}

?>
