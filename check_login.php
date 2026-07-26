<?php
$username = $_POST['user']; //Set UserName
$password = $_POST['pwd']; //Set Password
$msg ='';
if(isset($username, $password)) {
    ob_start();
	
require_once("conf.php");
$link = mysqli_connect($host,$user,$pass,$db) or die(mysqli_connect_error());

    $myusername = stripslashes($username);
    $mypassword = stripslashes($password);
    $myusername_esc = mysqli_real_escape_string($link, $myusername);
    $mypassword_esc = mysqli_real_escape_string($link, $mypassword);
    $sql="SELECT * FROM login_admin WHERE user_name='$myusername_esc' and user_pass=SHA('$mypassword_esc')";
    $result=mysqli_query($link, $sql);
    $count=mysqli_num_rows($result);
    // If result matched $myusername and $mypassword, table row must be 1 row
    if($count==1){
        // Register $myusername, $mypassword and redirect to file "admin.php"
        /*session_register("admin");
        session_register("password");
        $_SESSION['name']= $myusername;*/

	session_start();
        $_SESSION['login'] = "1";
        $_SESSION['name']= $myusername;

        header("location:index.php");
    }
    else {
        $msg = "Wrong Username or Password. Please retry&type=0";
        header("location:login.php?msg=".urlencode($msg));
    }
    ob_end_flush();
}
else {
    header("location:index.php?msg=Please enter  username and password");
}
?>
