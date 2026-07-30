<?php

$dest = $argv[1];

function str_getcsv_line($string)
{
	$string = preg_replace_callback(
        '|"[^"]+"|',
        create_function(
            '$matches',
            'return str_replace(\',\',\'*comma*\',$matches[0]);'
        ),$string );
$array = explode(',',$string);
$array = str_replace('*comma*',',',$array);
return $array;

}

if(file_exists($argv[1]))
{
	require_once($basepath.'connection.php');
	set_time_limit(0);
	chdir($basepath);

	$csv = array();
	$lines = file($dest, FILE_IGNORE_NEW_LINES);
	
	foreach ($lines as $key => $value)
	{
		if(!empty($value))
	    $csv[$key] = str_getcsv_line($value);
	}
	
	$audioIndex = count($csv[0])-2; 
	$phoneIndex = count($csv[0])-1;
	$itemCount = count($csv,0);
	$fields=implode(",",$csv[0]);
	$query = "insert into logs(fields,time,status,options,type,csvFile) values('$fields',NOW(),'upload','Nil','heading','$dest')";
	$result = mysqli_query($connection, $query) or die("Database Error");

	
	
	echo "Records Found : ".($itemCount-1)."<br>";
	$i=1;
	while($i<=$itemCount-1)
	{	
		$config = parse_ini_file("config.ini",true);
		$interval = $config['callblaster']['interval'];
		$waittime = $config['waittimes']['waittime'];
		$prefix = $config['prefixc']['prefix'];
		//pause-stop controls
		file_put_contents('control.ini', $reset_controls);
		$controls=parse_ini_file('control.ini');
		
		if($controls['stop']){
			exit();
		}
		
		if(!$controls['pause']):
			
		$number = trim($csv[$i][$phoneIndex]);
		// پاک‌سازی: حذف هر چیزی جز رقم (فاصله، اعشار ناخواسته اکسل مثل 9123456789.0، نماد علمی و غیره)
		$number = preg_replace('/\.0+$/', '', $number); // حذف .0 انتهایی که اکسل گاهی اضافه می‌کنه
		$number = preg_replace('/\D/', '', $number);   // فقط ارقام باقی می‌مونه
		$audio = $csv[$i][$audioIndex];
		$fields = implode(",",$csv[$i]);
		$query = "insert into logs(fields,time,status,options,type,csvFile) values('$fields',NOW(),'Dialling','Nil','field','$dest')";
		$result = mysqli_query($connection, $query) or die("Database Error");
		$id = mysqli_insert_id($connection);
		$phone = $number;
		// اعمال خودکار پیشوند خروجی بر اساس طول شماره:
		// ۱۰ رقم (موبایل خام بدون صفر، مثل 9123456789)      -> prefix + 0 + شماره
		// ۱۱ رقم (با صفر ابتدایی، مثل 09123456789)            -> prefix + شماره
		// ۱۲ رقم یا بیشتر (فرض بر اینه که قبلاً کامله)          -> بدون تغییر
		$len = strlen($phone);
		if($prefix !== '' && $len <= 11) {
			if($len === 11 && substr($phone,0,1) === '0') {
				$phone = $prefix.$phone;
			} elseif($len === 10) {
				$phone = $prefix.'0'.$phone;
			}
		}
		$phone=substr($phone,0,15);
		$callFile = "Channel: local/$phone@from-internal\r\n";
		$callFile .= "WaitTime: $waittime\r\n";
		$callFile .= "CallerID: $caller_id\r\n";
		$callFile .= "Context: callblaster\r\n";
		$callFile .= "Extension: 333\r\n";
		$callFile .= "Account: callblaster\r\n";
		$callFile .= "Set: userAudio=$audio\r\n";
		$callFile .= "Set: userNumber=$number\r\n";
		$callFile .= "Set: dbid=$id\r\n";
		$callFileName = $number."_".time().".call";
		file_put_contents("/tmp/$callFileName",$callFile);
		$time=date("c",time());
		try
		{
			exec("mv /tmp/$callFileName /var/spool/asterisk/outgoing/$callFileName");
			$msg = $time." -- Call file to 1".$number." created -- CSV file: $dest\n";
			$status="Dialled";
		}
		catch(Exception $e)
		{
			$msg=$time." -- ERROR:".$e->getMessage()." -- CSV file : $dest\n";
			$status="Dial Failed";
		}
		
		$query = "update logs set status='$status', time=NOW() where autoID='$id'";
		$result = mysqli_query($connection, $query) or die("Database Error");
		file_put_contents("logs/callLog.txt",$msg,FILE_APPEND);

		//sleep($interval);
		$interval=$interval*1000000;
		usleep($interval);
		
		$i++;
	elseif($controls['pause']):
		sleep(1);
	endif;
	}
}

?>
