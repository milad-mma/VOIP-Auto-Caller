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

require_once'config.php';

$connection = mysqli_connect($db_host,$db_user,$db_pass,$db_name) or die("ERROR connecting to database");

$query="CREATE TABLE IF NOT EXISTS `logs` (
  `autoID` int(11) NOT NULL AUTO_INCREMENT,
  `fields` blob NOT NULL,
  `time` datetime NOT NULL,
  `status` text NOT NULL,
  `options` text NOT NULL,
  `type` text NOT NULL,
  `csvFile` text NOT NULL,
  PRIMARY KEY (`autoID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=63089 ;
";

mysqli_query($connection, $query);


?>
	