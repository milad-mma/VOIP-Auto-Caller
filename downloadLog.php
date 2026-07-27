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

require_once("connection.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(isset($_REQUEST['file']))
{
	$file = $_REQUEST['file'];
	$file=substr($basepath,0,-1).$file;
	header('Content-type: text/csv');
	header("Content-disposition: attachment;filename=log.csv");
	
	//echo $file;
	//echo "<br>";
	//echo $_REQUEST['file'];
	
	$query = "select * from logs where csvFile='$file' and type='heading'";

	$result = mysqli_query($connection, $query);
	
	$ret='';

	if($result and mysqli_num_rows($result)>0)
	{
		$row = mysqli_fetch_assoc($result);
		$head = explode(",",$row['fields']);
		
		for($i=0;$i<count($head);$i++)
		$ret.=$head[$i].",";
		
		$ret.="Time,Status,Option Choosen";
	}
	
	$ret.="\n";
	
	$query = "select * from logs where csvFile='$file' and type='field'";
	$result = mysqli_query($connection, $query);
	
	if($result and mysqli_num_rows($result)>0)
	{
		for($i=0;$i<mysqli_num_rows($result);$i++)
		{
			$row=mysqli_fetch_assoc($result);
			$fields = explode(",",$row['fields']);
			
			for($j=0;$j<count($fields);$j++)
			{
				$ret.=$fields[$j].",";
			}
			$ret.=$row['time'].",".$row['status'].",".$row['options'];
			
			$ret.="\n";
		}
	}
	
	echo $ret;
	
	
}




?>
