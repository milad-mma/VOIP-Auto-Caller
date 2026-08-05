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

?>

<html>
<?php

require_once('connection.php');


error_reporting(E_ALL);
ini_set('display_errors', 1);


function str_getcsv_line($string){
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

function spawn($cmd,$outputfile,$pidfile)
{
 exec(sprintf("%s >> %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
}


$reset_controls="pause=false\nstop=false";
file_put_contents('control.ini', $reset_controls);
 
$config = parse_ini_file("config.ini",true);
$interval = $config['callblaster']['interval'];

if($_POST['action']=="Start Campain")
{
	if(!isset($_FILES['csvFile']) or $_FILES['csvFile']['error']>0)
	{
		echo "File upload error : ".$_FILES['csvFile']['error'];
	}
	else
	{
		$ts=time();
		// پاک‌سازی نام فایل: فقط حروف/رقم/نقطه/خط‌تیره/آندرلاین مجاز (فاصله، پرانتز و غیره حذف می‌شن
		// چون بدون escape داخل دستور exec() میرن و می‌تونن دستور شل رو بشکنن)
		$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['csvFile']['name']);
		$dest = $basepath."files/".$ts.$safeName;
		move_uploaded_file($_FILES['csvFile']['tmp_name'],$dest);
		
		$msg = "Recieved File $dest at ".date("r",time());
		file_put_contents("logs/uploads.txt",$msg,FILE_APPEND);

		$command = "/usr/bin/php ".escapeshellarg($basepath."asyncCall.php")." ".escapeshellarg($dest);
		//echo $command;

		spawn("$command","/tmp/".$ts.$safeName,"/tmp/pid_".$ts.$safeName);
		
		/*
		
		$csv = array();
		$lines = file($dest, FILE_IGNORE_NEW_LINES);
		
		foreach ($lines as $key => $value)
		{
		    $csv[$key] = str_getcsv_line($value);
		}
		
		$audioIndex = count($csv[0])-2; 
		$phoneIndex = count($csv[0])-1;
		$itemCount = count($csv,0);
		$fields=implode(",",$csv[0]);
		$query = "insert into logs(fields,time,status,options,type,csvFile) values('$fields',NOW(),'upload','Nil','heading','$dest')";
		$result = mysqli_query($connection, $query) or die("Database Error");

		
		
		echo "Records Found : ".($itemCount-1)."<br>";
		for($i=1;$i<=$itemCount-1;$i++)
		{
			$config = parse_ini_file("config.ini",true);
			$interval = $config['callblaster']['interval'];
			$number = $csv[$i][$phoneIndex];
			$audio = $csv[$i][$audioIndex];
			$fields = implode(",",$csv[$i]);
			$query = "insert into logs(fields,time,status,options,type,csvFile) values('$fields',NOW(),'Dialling','Nil','field','$dest')";
			$result = mysqli_query($connection, $query) or die("Database Error");
			$id = mysqli_insert_id($connection);
			$phone = $number;
			$phone=substr($phone,0,15);
			$callFile = "Channel: local/$phone@from-internal\n";
			$callFile .= "MaxRetries: 2\n";
			$callFile .= "WaitTime: 30\n";
			$callFile .= "CallerID: $caller_id\n";
			$callFile .= "Context: callblaster\n";
			$callFile .= "Extension: 333\n";
			$callFile .= "Set: userAudio=$audio\n";
			$callFile .= "Set: userNumber=$number\n";
			$callFile .= "Set: dbid=$id\n";
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

			sleep($interval);
		}*/
				
	}
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>در حال اجرای کمپین</title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css" type="text/css" media="all">
<link rel="stylesheet" href="assets/css/theme.css" type="text/css" media="all">
<link rel="icon" href="assets/img/brand/favicon.svg" type="image/svg+xml">
<style>
	body { font-family: var(--font-family-base); }
	.run-wrap { max-width: 900px; margin: 32px auto; padding: 0 20px; }
	.run-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
	.run-header h1 { font-size: var(--font-size-lg); color: var(--color-text-heading); margin: 0; }
	.run-header a { font-size: var(--font-size-sm); }
	.run-controls { display: flex; gap: 10px; margin-bottom: 16px; }
	.run-controls button { padding: 10px 22px; border: none; border-radius: var(--radius-sm); font-weight: 600; font-size: var(--font-size-sm); cursor: pointer; }
	#pause-btn { background: var(--color-warning); color: #fff; }
	#stop-btn { background: var(--color-danger); color: #fff; }
	.run-controls button:disabled { opacity: 0.5; cursor: not-allowed; }
	#logger { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 16px; min-height: 120px; overflow-x: auto; }
	#logger table { width: 100%; border-collapse: collapse; }
	#logger th, #logger td { padding: 8px 10px; text-align: right; border-bottom: 1px solid var(--color-border); font-size: var(--font-size-sm); }
	.run-footer { text-align: center; margin-top: 24px; font-size: var(--font-size-xs); color: var(--color-text-muted); }
	.run-footer a { color: var(--color-text-muted); }
</style>
</head>
<body>

<div class="run-wrap">
	<div class="run-header">
		<h1>وضعیت زنده‌ی کمپین</h1>
		<a href="index.php">← بازگشت به داشبورد</a>
	</div>

	<div class="run-controls">
		<button id="pause-btn" value="pause">توقف موقت</button>
		<button id="stop-btn">توقف کامل</button>
	</div>

	<div id="logger"></div>

	<div class="run-footer">
		<a href="https://imapro.ir" target="_blank">imapro.ir</a>
	</div>
</div>

<script type="text/javascript" src="assets/js/vendor/jQuery-2.1.4.min.js"></script>
<script type="text/javascript">
function updateLogger(file)
{
	$.post("readLog.php",{action:"getLog",file:file},function(data,status){
		$('#logger').html(data);
	});
}

$(document).ready(function(){
	var t = setInterval(function(){updateLogger("<?php echo urlencode($dest); ?>");},1000);

	$("#pause-btn").click(function(){
		var act=$("#pause-btn").val();
		var chng='توقف موقت';
		var chngval='pause';
		$.post("control.php",{action:act},function(data){
			if(act=='start'){
				chngval='pause';
				chng='توقف موقت';
			} else {
				chngval='start';
				chng='ادامه';
			}
			$("#pause-btn").val(chngval);
			$("#pause-btn").html(chng);
		});
	});

	$("#stop-btn").click(function(){
		$.post("control.php",{action:'stop'},function(data){
			$("#pause-btn").attr('disabled','disabled');
			$("#stop-btn").attr('disabled','disabled');
			alert("کمپین متوقف شد");
		});
	});
});
</script>

</body>
</html>
