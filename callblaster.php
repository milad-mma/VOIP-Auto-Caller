#!/usr/bin/php
<?php
/**
* @file
*
* All codes is released under the GNU General Public License.
* See COPYRIGHT.txt and LICENSE.txt.
*
*....................
* imapro.ir
*/

require('connection.php');
require($agipath.'phpagi.php');
error_reporting(E_ALL);


$agi = new AGI();

$dbid = $agi->get_variable("dbid");
$dbid=$dbid['data'];
$userNumber = $agi->get_variable("userNumber");
$userNumber = $userNumber['data'];

$audio = $agi->get_variable("userAudio");

if($audio=='')
$audio=$welcomeSound;
else
$audio = $basepath."audio/".$audio['data'];

$msg = date("r",time()). " -- Call in progress -- Number:$userNumber  -- Audio:$audio\n";
file_put_contents($basepath."logs/callLog.txt",$msg,FILE_APPEND);

$query = "update logs set status='Connected' where autoID='$dbid'";
$result = mysqli_query($connection, $query) or die("Database Error");

$validKeys = ['1','2','3','4','5','6','7','8','9'];
$keys="Nil";
$count=0;

do
{
	if($count>0)break;
	$result = $agi->get_data("$audio",1000,1);
	$keys = $result['result'];
	if(in_array($keys,$validKeys)) break;
	$count++;
}while(!in_array($keys,$validKeys));


$query = "update logs set options='$keys' where autoID='$dbid'";
$result = mysqli_query($connection, $query) or die("Database Error");
$msg = date("r",time()). " -- User pressed $keys -- Number:$userNumber  -- Audio:$audio\n";
file_put_contents($basepath."logs/callLog.txt",$msg,FILE_APPEND);

if(in_array($keys,$validKeys))
{
	$context  = ${"context_$keys"};
	$exten    = ${"exten_$keys"};
	$priority = ${"priority_$keys"};

	if($exten !== '' && $exten !== null)
	{
		$agi->exec_goto($context,$exten,$priority);

		$query = "update logs set status='Transferred' where autoID='$dbid'";
		$result = mysqli_query($connection, $query) or die("Database Error");
	}
}

$query = "update logs set status='Completed' where autoID='$dbid'";
$result = mysqli_query($connection, $query) or die("Database Error");

?>
