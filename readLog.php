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

if($_REQUEST['action']=="getLog")
{

	$file = trim(urldecode($_REQUEST['file']));
	
	$query = "select * from logs where csvFile='$file' and type='heading'";

	$result = mysqli_query($connection, $query);
	
	$ret='<table cellspacing="5" cellpadding="5"><thead>';

	if($result and mysqli_num_rows($result)>0)
	{
		$row = mysqli_fetch_assoc($result);
		$head = explode(",",$row['fields']);
		
		for($i=0;$i<count($head);$i++)
		$ret.="<th>".$head[$i]."</th>";
		
		$ret.="<th>Time</th><th>Status</th><th>Option Choosen</th>";
	}
	
	$ret.="</thead>";
	
	$query = "select * from logs where csvFile='$file' and type='field' and status!='Completed' and time>DATE_SUB(NOW(),INTERVAL 5 MINUTE)";
	$result = mysqli_query($connection, $query);
	
	if($result and mysqli_num_rows($result)>0)
	{
		for($i=0;$i<mysqli_num_rows($result);$i++)
		{
			$row=mysqli_fetch_assoc($result);
			$fields = explode(",",$row['fields']);
			$ret.="<tr align='center'>";
			for($j=0;$j<count($fields);$j++)
			{
				$ret.="<td>".$fields[$j]."</td>";
			}
			$ret.="<td>".$row['time']."</td><td>".$row['status']."</td><td>".$row['options']."</td>";
			
			$ret.="</tr>";
		}
	}
	$ret.="</table>";
	echo $ret;
	
	
	
	
	

}




?>
