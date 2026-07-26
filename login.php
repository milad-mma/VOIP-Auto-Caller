<!DOCTYPE html>
<?php
include_once("controller/route.php");

if($_SESSION['tech_admin_id'])
{
	echo "<script>location.href='index.php';</script>";
}
$user_id=checkAnyAdminPresent();
if(!$user_id)
{
	echo "<script>location.href='registration.php'</script>";
}


?>
<html>
  <head>
    <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Caller</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<link rel="stylesheet" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="assets/css/font-awesome.min.css">
	<link rel="stylesheet" href="assets/css/theme.css">
	<link rel="stylesheet" href="assets/css/login.css">

	<link rel="stylesheet" type="text/css" href="assets/css/toastr/toastr.css">
	<link rel="icon" href="assets/img/brand/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="assets/img/brand/favicon.ico" type="image/x-icon">
  </head>
  <body>

  
   <div class="form-body">
        <div class="row">
            <div class="img-holder">
                <div class="bg"></div>
                <div class="info-holder">
                    <h3>Auto Caller</h3>
                    <p>سامانه تماس‌گیر خودکار</p>
                </div>
            </div>
            <div class="form-holder">
                <div class="form-content">
                    <div class="form-items">
                        <div class="website-logo-inside">
                            <a href="index.php" class="brand-text-logo"><img src="assets/img/brand/logo.svg" alt="Auto Caller" height="40"></a>
                        </div>
						<h3>ورود</h3>
                        <div class="page-links">
                            
                        </div>
                        <form action="login.php" method="POST" id='login'>
                            <input class="form-control" type="text" name="username" placeholder="نام کاربری" required>
                            <input class="form-control" type="password" name="password" placeholder="رمز عبور" required>
                            <div class="form-button">
                                <button id="submit" type="submit" class="ibtn" name="login">ورود</button>
                            </div>
                        </form>
                      <!--  <div class="other-links">
                            <span>Or login with</span><a href="#">Facebook</a><a href="#">Google</a><a href="#">Linkedin</a>
                        </div>
						-->
                    </div>
                </div>
				
            </div>

        </div>
    </div>


    <script src="assets/js/vendor/jquery.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.min.js"></script>
<script type="text/javascript" src="assets/js/toastr/toastr.min.js"></script>
  </body>
  </html>
<?php



  if(isset($_POST['login']))
	{
		$email = $_POST['username'];
		$password = $_POST['password'];
		
		$dataToSend = $email."*".$password;
		$result = CheckAdminLogin($dataToSend);
		//print_r($result);
		if($result['status'] >= "1")
			{
				//echo "<script>$('#email_error').hide();</script>";
				$_SESSION['tech_admin_id'] = $result['data']['user_id'];
				$_SESSION['user_extension'] = $result['data']['extension'];
				$_SESSION['user_channel'] = $result['data']['channel'];
				$_SESSION['asteriskip'] = $result['data']['asterisk_ip'];
				echo "<script>location.href='index.php';</script>";
			}else if($result['status'] == "0")
			{
				//echo "<script>$('#email_error').show();</script>";
				echo "<script>toastr['warning']('Wrong Credentials')</script>";
			}  
		}
		
		
?>

