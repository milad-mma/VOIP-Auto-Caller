<?php
	error_reporting(0);
	//date_default_timezone_set('Asia/Kolkata');
	require 'Database_Class.php';
	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	ini_set('display_errors', 'On');
	session_start();
	

function createContact($current_user,$first_name,$last_name,$email,$phone,$address,$phone2,$note,$status,$prefix,$city,$postal_code,$country)
{
	$result_Arr=array();
	$to_fetch="*";
	if($email){
		$where_cond = "phone='$phone' or email='$email' ";
	}else{
		$where_cond = "phone='$phone'";
	}
	$tablename="contacts";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
		//if(true)
		if($count == 0)
		{
			$user="";
			$tablename='contacts';
			$fields='`first_name`, `last_name`,`email` ,`phone`, `address`, `date_created`, `date_modified`, `phone2`, `user_id`, `note`, `status`, `assigned_user`,`prefix`,`city`,`postal_code`,`country`';
			$value="'$first_name','$last_name','$email','$phone','$address',NOW(),NOW(),'$phone2','$current_user','$note','$status','$current_user','$prefix','$city','$postal_code','$country'";
			$result=insert_record($fields,$value,$tablename);
			//$resultsss=insert_record_max($fields,$value,$tablename);
			//$result_Arr['insert']=$value;
		  if($result){
			 $to_fetch="*";
			$tablename='contacts';
			$where_cond="first_name='$first_name' and last_name='$last_name' and date_created=NOW()";
			$results=select_record($to_fetch,$where_cond,$tablename);
			 while($row=mysqli_fetch_array($results)){
				$id=$row['id'];
			} 
			 $result_Arr['id']=$id;
			 $result_Arr['status']="1";
		  }
		}else{
			 while($row=mysqli_fetch_array($result)){
				$id=$row['id'];
			} 
				$result_Arr['id']=$id;
				$result_Arr['status']="0";
		}
    return $result_Arr;
}






function getAgentsInCampaign($campaign_id)
{
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "campaign_id='$campaign_id'";
	$tablename="dialer_agent_group";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}


function getAllAgentInAllCampaign()
{
	 
	$result_Arr=array();
	$to_fetch="DISTINCT agent_id";
	$where_cond = "1";
	$tablename="dialer_agent_group";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row['agent_id'];
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}




function getAgentConferenceStatus($agent_id){
	$to_fetch="*";
	$where_cond = "agent='$agent_id' and channel !=''";
	$tablename="dialer_conference";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$conference=$row['conference'];
				  }
				  $result_Arr['conference']=$conference;
				}else{
					$result_Arr['conference']="0";
				}
return $result_Arr;
}

function getAgentConferenceStatusInCampaign($agent_id,$campaign_id){
	$to_fetch="*";
	$where_cond = "agent='$agent_id' and campaign_id='$campaign_id' and channel !=''";
	$tablename="dialer_conference";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$conference=$row['conference'];
				$channel=$row['channel'];
				  }
				  $result_Arr['count']=$count;
				  $result_Arr['conference']=$conference;
				  $result_Arr['channel']=$channel;
				}else{
					$result_Arr['count']=$count;
					$result_Arr['conference']="0";
					$result_Arr['channel']='';
				}
return $result_Arr;
}

































function getTicketComments($ticket_id){
	$to_fetch="*";
	$where_cond = "ticket_id = $ticket_id order by date_time DESC";
	$tablename="ticket_comments";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}











function getVBCallLimit()
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "name='TEVB'";
	$tablename="modules";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$value=$row['value'];
			} 
			
	  }else{
			$value=0;
	  }
	  return $value;
}






function getDialerCampaignFromId($id)
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "id='$id'";
	$tablename="dialer";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}



function getContact($contact_id)
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "id='$contact_id'";
	$tablename="contacts";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}















function generateRandomPassword($length){
  $charset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  $base = strlen($charset);
  $result = '';

  $now = explode(' ', microtime())[1];
  while ($now >= $base){
    $i = $now % $base;
    $result = $charset[$i] . $result;
    $now /= $base;
  }
  return substr($result, -5);
}

















function getPortalURL()
{
	
	$to_fetch='*';
	$tablename='license';
	$where_cond="1";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	if($count>0){
		$datas=get_sgn_rows($result);
		
		$result_Arr['status']="1";
		$result_Arr['url']=$datas['url'];
		$result_Arr['mac']=$datas['mac'];
		$result_Arr['user']=$datas['user'];
		$result_Arr['no_of_crm']=$datas['no_of_crm'];
		$result_Arr['no_of_asterisk']=$datas['no_of_asterisk'];
	}else{
		$result_Arr['status']="0";
		$result_Arr['url']='';
		$result_Arr['mac']='';
		$result_Arr['user']='';
		$result_Arr['no_of_crm']='';
		$result_Arr['no_of_asterisk']='';
	}
	return $result_Arr;
}




















	
	function createPortalLoginHistory($user_id,$login)
	{
		
		$result_Arr=array();	
		
		$tablename='portal_login_history';
		$fields='`user_id`, `login`, `date_time`';
		$value="'$user_id','$login',NOW()";
		$result=insert_record($fields,$value,$tablename);

		return $result_Arr;
	}

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

	
	
	
	function checkEmailPresentInAdmin($email){
	  
	  $result_Arr=array();	
	 
	  $to_fetch='*';
	  $tablename='admin';
	  $where_cond="email='$email'";
	  $result=select_record($to_fetch,$where_cond,$tablename);
	   $count=get_num_rows($result);
	  if($count>0){
			$datas=get_sgn_rows($result);
			$result_Arr['status']="1";
			$result_Arr['data']=$datas;
			
	  }else{
			$result_Arr['status']="0";
			$result_Arr['data']='';
	  } 
	  return $result_Arr;
	}
	
	

	


function getCRMNameFromURL($url)
{
	$url = trim($url);
 
 $to_fetch='*';
	  $tablename='crm_configuration';
	  $where_cond="crm_url='$url'";
	  $result=select_record($to_fetch,$where_cond,$tablename);
	    $count=get_num_rows($result);
	  if($count>0){
			$datas=get_sgn_rows($result);
			$result_Arr['status']="1";
			$result_Arr['data']=$datas;
			
	  }else{
			$result_Arr['status']="0";
			$result_Arr['data']='';
	  }  
	//  $result_Arr['status']=$result;
	  return $result_Arr;
}





function getAdminProfile($user_id)
{
 
 $to_fetch='*';
	  $tablename='admin';
	  $where_cond="user_id='$user_id'";
	  $result=select_record($to_fetch,$where_cond,$tablename);
	   $count=get_num_rows($result);
	  if($count>0){
			$datas=get_sgn_rows($result);
			$result_Arr['status']="1";
			$result_Arr['data']=$datas;
			
	  }else{
			$result_Arr['status']="0";
			$result_Arr['data']='';
	  } 
	  return $result_Arr;
}


function getCompanyName()
{
 
 $to_fetch='*';
	  $tablename='admin';
	  $where_cond="user_id='1' ";
	 $result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$name=$row['company_name'];
			} 
	  }else{
	  }
	  return $name;
}









function updateProfilePicture($id,$path,$type)
{
	
	$tablename="";
	if($type == "user")
	{
		$tablename="user";
	}else{
		$tablename="admin";
	}
	$condition="user_id='$id'";
	$fields="profile_image='$path'";
	$result=update_record($fields,$condition,$tablename);
	$result_Arr['status'] = $result; 
	return $result;
}


function updateAdminBasicProfile($value_arr)
{
//$user_id."*".$name."*".$email."*".$designation."*".$phone;
	
	$result_Arr=array();
	$value_arr=explode('*',$value_arr); 
	$tablename="admin";
	$condition="user_id='$value_arr[0]'";
	$fields="name='$value_arr[1]',email='$value_arr[2]',designation='$value_arr[3]',contact='$value_arr[4]',popup_type='$value_arr[5]' ";
	$result=update_record($fields,$condition,$tablename);
	$result_Arr['status'] = $result; 
	return $result_Arr;
}



function changeAdminPassword($dataToRecieve)
{

	$user_id = $dataToRecieve[0];
	$old_password = $dataToRecieve[1];
	$old_pass_encrypt=md5($old_password);
	$new_password = $dataToRecieve[2];
	$new_password_encrypt = md5($new_password) ;
	$user_id_db="";
	
	$to_fetch="*";
	$where_cond = "password='$old_pass_encrypt' and user_id='$user_id' ";
	$tablename="admin";
	$result=select_record($to_fetch,$where_cond,$tablename);
	  while($row=mysqli_fetch_array($result)){
				$user_id_db=$row['user_id'];
			}  
	
	
 	 if($user_id_db)
	{
		$tablename="admin";
		$condition="user_id='$user_id_db'";
		$fields="password='$new_password_encrypt',original_password='$new_password'";
		$result=update_record($fields,$condition,$tablename);
		$result_Arr['status'] = "1"; 
	}else
	{	
		$result_Arr['status'] = "-1"; 
	}
	
	
	return $result_Arr;
}











function updateAdminTechextensionProfile($value_arr)
{
	
	$result_Arr=array();
	$value_arr=explode('*',$value_arr);
	//$dataToSend = $_SESSION['tech_user_id']."*".$extension."*".$asterisk_ip."*".$channel."*".$context."*".$prefix;
	$tablename="admin";
	$condition="user_id='$value_arr[0]'";
	$fields="extension='$value_arr[1]',asterisk_ip='$value_arr[2]',channel='$value_arr[3]',context='$value_arr[4]',prefix='$value_arr[5]' ";
	$result=update_record($fields,$condition,$tablename);
	$result_Arr['status'] = "1"; 
	return $result_Arr;
}



function checkEmailInUser($email)
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "email='$email' ";
	$tablename="user";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	return $count;
}




function getAllAsteriskInfo()
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "1";
	$tablename="asterisk_ip";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}



function getAllLicenseModuleInfo()
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "1";
	$tablename="modules";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}


function getAllCRMInfo()
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "1";
	$tablename="crm_configuration";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}



























































































function createPortalURL($url)
{
	 
	$to_fetch="*";
	$id="";
	$tablename='license';
	$where_cond="1";
	$result=select_record($to_fetch,$where_cond,$tablename);
	while($row=mysqli_fetch_array($result)){
		$id=$row['id'];
	}
	if($id)
	{
		
		$condition="id='$id'";
		$fields="url='$url',date_time=NOW()";
		$result=update_record($fields,$condition,$tablename);
	}else
	{
		
		$fields='`url`,`date_time`';
		$value="'$url',NOW()";
		$result=insert_record($fields,$value,$tablename);
	}
	return $result;
}








function getUserInfoFromId($user_id)
{
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "user_id='$user_id'";
	$tablename="user";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
		  
			$to_fetch="*";
			$where_cond = "user_id='$user_id'";
			$tablename="user";
			$result=select_record($to_fetch,$where_cond,$tablename);
			$count=get_num_rows($result);
			if($count>0){
			while($row=mysqli_fetch_array($result)){
			$data[]=$row;
			} 
			}
		  
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }
	  return $result_Arr;
}






function getAdminInfoFromId($user_id)
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "user_id='$user_id'";
	$tablename="admin";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}






































































function getDialerCampaignList($user_id)
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond = "1";
	$tablename="dialer";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}



























function getAllDialerDisposition()
{ 
	$to_fetch='*';
	$tablename='dialer_disposition';
	$where_cond="1 order by id asc";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}







function getDialerDisposition($campaign_id)
{ 
	$to_fetch='*';
	$tablename='dialer_campaign_disposition';
	$where_cond="campaign_id='$campaign_id'";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}







































function getContactDetailsFromVoiceListId($list_id)
{
	$to_fetch="*";
		$where_cond = "id='$list_id'";
		$tablename="voice_broadcasting_list";
		$result=select_record($to_fetch,$where_cond,$tablename);
		$attemp='';
		$count=get_num_rows($result);
		  if($count>0){
				 while($row=mysqli_fetch_array($result)){
					$contact_id=$row['contact_id'];
					$contacDetails = getContact($contact_id);
					$contact_name = $contacDetails['data'][0]['first_name']." ".$contacDetails['data'][0]['last_name'];
					$attemp=$row['attemp'];
		  }
		  $dataToReturn['contact_id']=$contact_id;
		  $dataToReturn['contact_name']=$contact_name;
		  $dataToReturn['attemp']=$attemp;
		  }else{
			$dataToReturn['contact_id']="";
			$dataToReturn['contact_name']="";
			$dataToReturn['attemp']="";
		  }
		  return $dataToReturn;
}





function addMemberToCampaign($contact_id,$campaign_id,$user_id)
{
	
	$to_fetch="*";
	$where_cond = "user_id='$user_id' and campaign_id='$campaign_id' and contact_id='$contact_id'";
	$tablename="sms_campaign_details";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	if($count == 0)
	{
	$tablename='sms_campaign_details';
	$fields='`user_id`, `campaign_id`, `contact_id`,`date_added`';
	$value="'$user_id','$campaign_id','$contact_id',NOW()";
		$result=insert_record($fields,$value,$tablename);
	}else{
		$result="0";	
	}
    return $result;
}









function addMemberToVoiceBroadcast($contact_id,$campaign_id,$user_id)
{
	
	$to_fetch="*";
	$where_cond = "user_id='$user_id' and voice_broadcast_id='$campaign_id' and contact_id='$contact_id'";
	$tablename="voice_broadcasting_list";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	if($count == 0)
	{
	$tablename='voice_broadcasting_list';
	$fields='`user_id`, `voice_broadcast_id`, `contact_id`,`date_created`,`attemp`';
	$value="'$user_id','$campaign_id','$contact_id',NOW(),'0'";
		$result=insert_record($fields,$value,$tablename);
	}else{
		$result="0";	
	}
    return $count;
}


function addContactsToDialer($contact_id,$campaign_id,$user_id)
{
	
	$to_fetch="*";
	$where_cond = "user_id='$user_id' and campaign_id='$campaign_id' and contact_id='$contact_id'";
	$tablename="dialer_list";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	if($count == 0)
	{
	$fields='`user_id`, `campaign_id`, `contact_id`,`date_created`,`attemp`,`status`';
	$value="'$user_id','$campaign_id','$contact_id',NOW(),'0',''";
		$result=insert_record($fields,$value,$tablename);
	}else{
		$result="0";	
	}
    return $count;
}



















































































function getAsteriskById($asterisk_id)
{
	 
	$result_Arr=array();
	$to_fetch="*";
	$where_cond="id='$asterisk_id'";
	$tablename="asterisk_ip";
	$result=select_record($to_fetch,$where_cond,$tablename);
	$count=get_num_rows($result);
	  if($count>0){
			 while($row=mysqli_fetch_array($result)){
				$data[]=$row;
			} 
			$result_Arr['count']=$count;
			$result_Arr['data']=$data;
	  }else{
			$result_Arr['count']="0";
			$result_Arr['data']='';
	  }
	  return $result_Arr;
}





































function updateAdminProfileCRM($arr_value)
{
	 
	$result_Arr=array();
	$value_arr=explode('*',$arr_value);
	$crm_url = trim($value_arr[1]);

	$tablename="admin";
	$condition="user_id='$value_arr[0]'";
	$fields="crm_url='$value_arr[1]',crm_username='$value_arr[2]',crm_password='$value_arr[3]',secret='$value_arr[4]',crm_type='$value_arr[5]',crm_user_id='$value_arr[6]',ms_client_id='$value_arr[7]',call_log_in_crm='$value_arr[8]' ";
	$result=update_record($fields,$condition,$tablename);
	$result_Arr['status'] = $result; 
	return $result_Arr;
}




























			
			
			
			












































function removeAllQueue()
{
	 
	$result_Arr=array();
	$tablename="queue_info";
	$condition="1";
	$result=delete_record($condition,$tablename);
	return $result;
}




/* function createLivePickUpCallByMember($queue,$caller,$IP,$uniqueID,$member_number,$member_name,$holtime,$ringtime)
{
	//($queue,$max,$aboned,$weight,$completed,$strategy)
	 
	$result_Arr=array();  

	$id="";
	$to_fetch="*";
	$tablename='live_queue_member_pickup_call';
	$where_cond="queue='$queue' and member_number='$member_number'";
	$result=select_record($to_fetch,$where_cond,$tablename);
	while($row=mysqli_fetch_array($result)){
		$id=$row['id'];
	}
	if(!$id)
	{
		$fields='`queue`, `caller`, `pbx_ip`, `unique_id`, `member_number`, `member_name`, `holtime`, `ringtime`, `date_time`';
		$value="'$queue','$caller','$IP','$uniqueID','$member_number','$member_name','$holtime','$ringtime',NOW()";
		$result=insert_record($fields,$value,$tablename);
		
	}
	else{
		$condition="id='$id'";
		$fields="caller='$caller',date_time=NOW(),pbx_ip='$IP',unique_id='$uniqueID',member_name='$member_name',holtime='$holtime',ringtime='$ringtime'";
		$result=update_record($fields,$condition,$tablename);
	}
	return "success";
} */






























































































?>