initConnectServer();
function initConnectServer()
{

	if (jws.browserSupportsWebSockets())
	{
		lWSC = new jws.jWebSocketJSONClient(
				{
				});
	}
	ConnectServer();
}
function ConnectServer()
{
	var hangupId='';
	var lURL = jws.getDefaultServerURL();
	var lRes = lWSC.logon(lURL,user_extension, user_extension,
			{
				OnOpen: function(aEvent)
				{
				},
				OnGoodBye: function(aEvent)
				{
					console.log("logOut");
				},
				OnMessage: function(aToken, aEvent)
				{
//console.log(aEvent);
				if(aEvent.action=="ChangeMemberStatus")
				{
					

				}
				if(aEvent.action=="customNotification")
				{
					
					if(aEvent.type == "statuschanged")
					{
						interfaces = aEvent.Member;
						Queue = aEvent.Queue;
						msg = aEvent.msg;
						status = aEvent.status;
						//console.log(status);
						if(status == "Resume")
						{
							toastr["success"]("Success : Member "+interfaces+" From Queue: "+Queue+" is Un-Paused")
						}else{
							toastr["success"]("Success : Member "+interfaces+" From Queue: "+Queue+" is Paused")
						}
					}else if(aEvent.type == "removequeuemember")
					{
						interfaces = aEvent.Member;
						Queue = aEvent.Queue;
						msg = aEvent.msg;
						toastr["success"]("Success : Member "+interfaces+" From Queue: "+Queue+" is Removed")
					}
					else if(aEvent.type == "addqueuemember")
					{
						interfaces = aEvent.Member;
						Queue = aEvent.Queue;
						msg = aEvent.msg;
						toastr["success"]("Success : Member "+interfaces+" From Queue: "+Queue+" is Added")
					}
				}
				
				
				if(aEvent.action=="QueueParamEvent")
					{
						var QueueNumber = aEvent.queue;
						var max = aEvent.max;
						var aboned = aEvent.aboned;
						var weight = aEvent.weight;
						var completed = aEvent.completed;
						var strategy = aEvent.strategy;
					}
				if(aEvent.action=="QueueJoin")
					{
						var queue = aEvent.queue;
						//console.log(aEvent);
						/* var queue = aEvent.queue;
						
						var phoneNumber = aEvent.caller;
						
						var uniqueid = aEvent.uniqueid;
						$('#'+queue+'asteriskid').val(uniqueid);
						$('#'+queue+'statuschange').html("[Ringing]"); */
						$('#'+queue+'statuschange').html("[Ringing]");
						$('#'+queue+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
						 $('#'+queue+'usericon').attr('src','../image/ring.png');
					}
					
				if(aEvent.action=="QueueLeave")
					{
						//console.log(aEvent);
						var queueNumber = aEvent.queue;
						
						//var phoneNumber = aEvent.caller;
						//10000usericon
						console.log(queueNumber);
						//$("#10000usericon").removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant');
						
						$('#'+queueNumber+'usericon').removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant').addClass('contctIcon');
						$('#'+queueNumber+'usericon').attr('src','../image/queue.png');
						$('#'+queueNumber+'statuschange').html("[ Idle ]");
					}
					
					if(aEvent.action=="DisconnectEvent")
					{
						console.log(aEvent);
						toastr["warning"]("Asterisk PBX is Disconnected");
					}
					if(aEvent.action=="PJSipNotifyEvent")
					{
						console.log(aEvent);
						var pjextension = aEvent.Peer;
						var pjContacts = aEvent.Contacts;
						var AsteriskIp = aEvent.AsteriskIP;
						var AsteriskIp = aEvent.PJActiveChannels;
						
						//10001statuschange
						if(pjContacts != "mahavir")
						{
							$('#'+pjextension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
							$('#'+pjextension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
							var currentStatusValue = $('#'+pjextension+'statuschange').html();
							
							if(currentStatusValue == "[Connected]")
							{
								var status =  "[Connected]";
							}else if(currentStatusValue == "[Ringing]"){
								var status =  "[Ringing]";
							}else{
								var status =  "[Registered]";
							}
							
							$('#'+pjextension+'statuschange').html(status);
							
							
							var arr = pjContacts.split(',');
							
							var string="";
							var stringToShow="";
							var multiIpPort="";
							for (i = 0; i < arr.length; i++) {
								//10001/sip:10001@202.9.126.220:5060;rinstance=01e1e85bae5259e7
								firstvariable = pjextension+"/sip:"+pjextension+"@";
								secondvariable = ";";
								if(arr[i])
								{
									multiIpPort =arr[i].match(new RegExp(firstvariable + "(.*)" + secondvariable));
									//console.log(multiIpPort);
									if(multiIpPort != null)
									{
										abc = $.inArray(multiIpPort[1], multiIpPort)
										/* console.log(multiIpPort[1]);
										console.log(multiIpPort); */
										if(abc)
										{

											string +=multiIpPort[1]+"<br>";
											
											//string +=multiIpPort[1]+"<br>";
											
										}
									}
										//for(i=0;i<1;i++)
										//{
										//stringToShow +=multiIpPort[1]+"<a href=''>Click</a>";
										//}
								}
							
							}
							//console.log(string);
							//console.log('#'+pjextension+'ipport');
							$('#'+pjextension+'ipport').html(string);
						
						}else{
							//console.log("Null value Detected...");
							var status =  "[Unregistered]";
							//var ipport =  "(Unspecified) : 0";
							var ipport =  "(Unspecified) : 0";
							$('#'+pjextension+'statuschange').html(status);
							$('#'+pjextension+'ipport').html(ipport);
							//(Unspecified) : 0
						}
						
						
					}
					if(aEvent.action=="ExtensionStatus")
					{
						//console.log(aEvent);
						extension = aEvent.Extension;
						Status = aEvent.Status;
						if(Status == 1)
						{
							$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
							$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
							$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
							$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
							$('#'+extension+'usericon').removeClass('preview-item shake-horizontal shake-opacity shake-constant');
							
						}
						if(Status == 8)
						{
							$('#'+extension+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
						}
						if(Status == 0)
						{
							$('#'+extension+'usericon').removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant').addClass('contctIcon');
							
							$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
							$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
							$('#'+extension).removeClass('popBoxconnect info-box').addClass('popBoxGreen info-box');
						}
						setTimeout(function (){	
						$('#'+extension+'usericon').removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant').addClass('contctIcon');
						}, 5000);
					}
					if(aEvent.action=="Channel")
						{
							$("#channelRow").show();
							//$("#channelsuccess").show();
							//$("#channelload").hide();
							var text="";
							for (i = 0; i < aEvent.value.length; i++) { 
						if(i==0)
						{
							text += "<b>"+aEvent.value[i] + "</b><br>";
							text = text.replace(/  +/g, '&emsp;&emsp;&ensp;&ensp;');
							text = text.replace(/ /g, '&emsp;&emsp;&ensp;&ensp;');
						}else{
							text += aEvent.value[i] + "<br>";
							text = text.replace(/  +/g, '&emsp;&emsp;');
							text = text.replace(/ /g, '&emsp;&emsp;');
						}
							}
							$('#channelreport').html(text);
						}

						if(aEvent.action=="QueueSingle")
						{
							//console.log(aEvent);
							var text="";
							var queueNumber =aEvent.queue;
							var queueInformation =aEvent.QueueValue;
							for (i = 0; i < aEvent.QueueValue.length; i++) { 
							text += "<b>"+aEvent.QueueValue[i] + "</b><br>";
							text = text.replace(/  +/g, '&emsp;');
							text = text.replace(/ /g, '&emsp;');
							}
							
							$('#queueLable').html("Queue : "+queueNumber);
							$('#queuenumber').val(queueNumber);
							$('#queuedescription').html(text);
							
						}
					if(aEvent.action=="QueueDetail")
						{
							
							var stringqueue="";
							var statusClass = 'popBoxGreen info-box';
							status="Registered";
							//String json="{\"action\":\"QueueDetail\",\"queue\":\""+QueueNumber+"\",\"holdTime\":\""+holdTime+"\",\"available\":\""+availabe+"\",\"talkTime\":\""+talkTime+"\",\"logged\":\""+logged+"\",\"callers\":\""+callers+"\"}";
							QueueNumber = aEvent.queue;
							holdTime = aEvent.holdTime;
							available = aEvent.available;
							talkTime = aEvent.talkTime;
							logged = aEvent.logged;
							callers = aEvent.callers;
							ActionID = aEvent.ActionID;
							if(ActionID == "techupdate")
							{
								$('#'+QueueNumber+'log').html(logged);
								$('#'+QueueNumber+'caller').html(callers);
								
								
								
								$('#'+QueueNumber+'hold').prop('title', 'Avg.Hold Time : '+holdTime);
								$('#'+QueueNumber+'tt').prop('title', 'Talk Time : '+talkTime);
							}
						//	$.ajax({url: "checkExtensionStatus.php?action=queue&queue="+QueueNumber+"&ht="+holdTime+"&av="+available+"&tt="+talkTime+"&li="+logged+"&cl="+callers, success: function(result){
						//		var result =  JSON.parse(result);
								//console.log(result);
								
								
							//	}});
						}
						
						if(aEvent.action=="QueueDetailComplte")
						{
							//console.log(aEvent);
							$('#queuerow').show();
							var stringqueue="";
							var statusClass = 'popBoxGreenQueue info-box';
							//status="Registered";
							setInterval(function(){
								var stringqueue="";
							$.ajax({url: "checkExtensionStatus.php?action=getQueue", success: function(result){
								var result =  JSON.parse(result);
					
					
								var count = result.count;

								var data = result.data;
								
								var queueNumber="";
								for (i = 0; i < count; i++) {
								//console.log(data[0].holdtime);
								queueNumber = data[i].number;
								holdtime = data[i].holdtime;
								available = data[i].available;
								talktime = data[i].talktime;
								loggedin = data[i].loggedin;
								callers = data[i].callers;
								status = data[i].current_status;
								
								if(status == "Ringing")
								{
									userIconsStatus = 'preview-item shake-horizontal shake-opacity shake-constant';
									icon = '../image/ring.png';
									statusss ='[ Ringing ]';
								}else
								{
								userIconsStatus ="";
								icon ='../image/queue.png';
								statusss ='[ Idle ]';
								}
							
								stringqueue+="<div class='col-md-3 col-sm-6 col-xs-12'><section class='"+statusClass+"' id='"+queueNumber+"'> <div class='clearFix'> <img src='"+icon+"' class='contctIcon "+userIconsStatus+"' id='"+queueNumber+"usericon'></img><aside class='contctIconExtension'>"+queueNumber+"</aside><aside class='rihtSec' id='"+queueNumber+"statuschange'>"+statusss+"</aside><input type='hidden' id="+queueNumber+"asteriskid></div><div class='contctIPQueue' id='"+queueNumber+"type'><span class='label label-warning' style='font-size:20px' id='"+queueNumber+"caller' data-toggle='tooltip' title='Live Calls'>"+callers+"</span> Callers  &nbsp;&nbsp;&nbsp;<span class='label label-success' data-toggle='tooltip' title='Live Agents' style='font-size:20px' id='"+queueNumber+"log'>"+loggedin+"</span> Members <br></div><div class='iconSwec'> <img src='../image/queuedetail.png' data-toggle='modal' title='Queue Information' onclick='getQueueDetail("+queueNumber+")' data-target='#centralModalSm'> <img src='../image/holdtime.png' id='"+queueNumber+"hold' data-toggle='tooltip' title='Avg.Hold Time : "+holdtime+"'> <img src='../image/talktime.png' data-toggle='tooltip' title='Talk Time : "+talktime+"' id='"+queueNumber+"tt' > </div></section></div>";
								}
								
								$('#queuedata').html(stringqueue);
								}});
								}, 2000);
						}
					
					if(aEvent.action=="LIST")
						{
						$('#extensionrow').show();
						var val= aEvent.value;
						var type = aEvent.type;
						console.log(type);
						if(type != "pjsip")
						{
						val = val.replace(/rowend$/, '');
						var arr = val.split('rowend');
						var string="";
						var stringiax="";
						var flag=false;
						var statusiax="";
						var portiax="";
								
								
								var i;
								for (i = 0; i < arr.length-1; i++) {
								if(arr[i] != arr[0])
								{
									colarra = arr[i].split("colend");
									
									if(type=="sip")
									{
									//	console.log(colarra);
										
									
									if(colarra.length == 9)
									{
										flag=true;
										status = colarra[7];
										port = colarra[6];
									}else if(colarra.length == 7){
										flag=true;
									if(colarra[i] =="undefined" || colarra[i] ==null || colarra[i] =='' )
									{
										flag=true;
										colarra[i]="";
									}
										
										var matches = colarra[5].match(/\d+/g);
										if (matches != null) {
										//alert('number');
										port = colarra[5];
										status = colarra[6];
										}else
										{
											port = colarra[4];
											status = colarra[5];
										}
										
										//console.log("length 7 found");
									}
									else if(colarra.length == 8){
										flag=true;
									if(colarra[i] =="undefined" || colarra[i] ==null || colarra[i] =='' )
									{
										colarra[i]="";
									}
										status = colarra[7];
										port = colarra[6];
									}else if(colarra.length == 6)
										{
											flag=true;
											status = colarra[5];
											port = colarra[4];
										}
									else{
										flag=false;
									}
									}
									else if(type=="iax")
									{
										console.log(colarra);
										console.log(colarra.length);
										if(colarra.length == 6)
										{
											flag=true;
											status = colarra[5];
											port = colarra[4];
										}else if(colarra.length == 7)
										{
											flag=true;
											status = colarra[5];
											port = colarra[4];
										}
										
									}

									if(flag)
									{
									if(colarra[0].includes("/"))
									{
										var extension = colarra[0].split('/');
										extension = extension[0];
									}else{
										var extension = colarra[0];
									}
									
									if(status.includes("OK"))
									{
										var statusClass = 'popBoxGreen info-box';
										status="Registered";
									}else if(status.includes("UNKNOWN"))
									{
										var statusClass = 'popBoxRed info-box';
										status="Unregistered";
										//console.log(status);
									}else if(status.includes("Unmonitored"))
									{
										var statusClass = 'popBoxYellow info-box';
									}
									else if(colarra[1].includes("Unspecified"))
									{
										status="Unregistered";
										var statusClass = 'popBoxRed info-box';
									}
									if(status.includes("UNREACHABLE"))
									{
										var statusClass = 'popBoxRed info-box';
										status="Unregistered";
									}
									
					
							if(type=="iax")
							{
						
							stringiax+="<div class='col-md-3 col-sm-6 col-xs-12'><section class='"+statusClass+"' id="+extension+"> <div class='clearFix'> <img src='../image/agent.png' class='contctIcon' id='"+extension+"usericon'></img><aside class='contctIconExtension'>"+extension+"-IAX2</aside><aside class='rihtSec' id='"+extension+"statuschange'>["+status+"]</aside><input type='hidden' id="+extension+"asteriskid></div><div class='contctIP' id='"+extension+"ipport'>"+colarra[1]+" : "+port+"</div> <div class='iconSwec'> <img src='../image/transfer.png' data-toggle='tooltip' title='Transfer Call' onclick=transferCall('"+extension+"') > <img src='../image/voicemail.png' data-toggle='tooltip' title='Listen VoiceMail' onclick=VoiceMail('"+extension+"','IAX2')> <img src='../image/spy.png' data-toggle='tooltip' title='Spy Call' onclick=spyCall('"+extension+"','IAX2')> <img src='../image/barge.png' data-toggle='tooltip' title='Barge Call' onclick=bargeWhisperCall('"+extension+"','IAX2','barge')> <img src='../image/whisper.png' data-toggle='tooltip' title='Whisper Call' onclick=bargeWhisperCall('"+extension+"','IAX2','whisper')> <img src='../image/minus.png' data-toggle='tooltip' title='HangUp Call' onclick=hangupCall('"+extension+"')>  </div> </section></div>";
							$.ajax({url: "checkExtensionStatus.php?ext="+extension, success: function(result){
										var results = result.split('|');
										//TodoOnRinging = $.inArray("Ringing", results)
										extension = results[1];
										
										
									if(result[0]!="")
									{
										if(results[0] == "Ringing")
										{
											$('#'+extension+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
											$('#'+extension+'usericon').attr('src','../image/ring.png');
											$('#'+extension+'asteriskid').val(results[2]);
											$('#'+extension+'statuschange').html("[Ringing]");

										}
										if(results[0] == "Connected")
										{
											$('#'+extension+'usericon').attr('src','../image/ring.png');
											$('#'+extension+'asteriskid').val(results[2]);
											$('#'+extension+'statuschange').html("[Connected]");
											$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
										}
										if(results[0] == "Disconnected")
										{
											$('#'+extension+'asteriskid').val('');
											//$('#'+extension+'statuschange').html("[Registered]");
										 	/* $('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxGreen info-box');  */
										}
									}
									//$("#div1").html(result);
								}});
							

							}else if(type=="sip")
							{
								
								//status = "max";
								string+="<div class='col-md-3 col-sm-6 col-xs-12'><section class='"+statusClass+"' id="+extension+"> <div class='clearFix'> <img src='../image/agent.png' class='contctIcon' id='"+extension+"usericon'></img><aside class='contctIconExtension'>"+extension+"-SIP</aside> <aside class='rihtSec' id='"+extension+"statuschange'>["+status+"]</aside><input type='hidden' id="+extension+"asteriskid> </div><div class='contctIP' id='"+extension+"ipport'>"+colarra[1]+" : "+port+"</div> <div class='iconSwec'> <img src='../image/transfer.png' data-toggle='tooltip' title='Transfer Call' onclick=transferCall('"+extension+"')  > <img src='../image/voicemail.png' data-toggle='tooltip' title='Listen VoiceMail' onclick=VoiceMail('"+extension+"','SIP')> <img src='../image/spy.png' data-toggle='tooltip' title='Spy Call' onclick=spyCall('"+extension+"','SIP')> <img src='../image/barge.png' data-toggle='tooltip' title='Barge Call' onclick=bargeWhisperCall('"+extension+"','SIP','barge')> <img src='../image/whisper.png' data-toggle='tooltip' title='Whisper Call' onclick=bargeWhisperCall('"+extension+"','SIP','whisper')> <img src='../image/minus.png' data-toggle='tooltip' title='HangUp Call' onclick=hangupCall('"+extension+"')>  </div> </section></div>";
								//console.log(extension);
								$.ajax({url: "checkExtensionStatus.php?ext="+extension, success: function(result){
									
										var results = result.split('|');
										extension = results[1];
										//console.log(results[0]);
									if(result[0]!="")
									{
										if(results[0] == "Ringing")
										{
											$('#'+extension+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
											$('#'+extension+'usericon').attr('src','../image/ring.png');
											$('#'+extension+'asteriskid').val(results[2]);
											$('#'+extension+'statuschange').html("[Ringing]");
				
										}
										if(results[0] == "Connected")
										{
											$('#'+extension+'usericon').attr('src','../image/ring.png');
											$('#'+extension+'asteriskid').val(results[2]);
											$('#'+extension+'statuschange').html("[Connected]");
											$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
										}
										if(results[0] == "Disconnected")
										{
											$('#'+extension+'asteriskid').val("");
											//$('#'+extension+'statuschange').html("[Registered]");
										 	/* $('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxGreen info-box');  */
										}
									}
									//$("#div1").html(result);
								}});
							}
							
								}
								}
								}
						}else
						{
							//PJSIP
							var stringpj="";
							//console.log(aEvent);
							var valuees = aEvent.value;
							if(valuees != "")
							{
							//$('#pjsiprow').show();
							var statusClass = 'popBoxRed info-box';
							var extensionList = valuees.split(',');
							var status ="";
							
							// 
							for (i = 0; i < extensionList.length; i++) { 
								stringpj+="<div class='col-md-3 col-sm-6 col-xs-12'><section class='"+statusClass+"' id="+extensionList[i]+"> <div class='clearFix'> <img src='../image/agent.png' class='contctIcon' id='"+extensionList[i]+"usericon'></img><aside class='contctIconExtension'>"+extensionList[i]+"-PJSIP</aside><aside class='rihtSec' id='"+extensionList[i]+"statuschange'>"+status+"</aside><input type='hidden' id="+extensionList[i]+"asteriskid> </div><div class='contctIP' id='"+extensionList[i]+"ipport'></div> <div class='iconSwec'> <img src='../image/transfer.png' data-toggle='tooltip' title='Transfer Call' onclick=transferCall('"+extensionList[i]+"') > <img src='../image/voicemail.png' data-toggle='tooltip' title='Listen VoiceMail' onclick=VoiceMail('"+extensionList[i]+"','PJSIP')> <img src='../image/spy.png' data-toggle='tooltip' title='Spy Call' onclick=spyCall('"+extensionList[i]+"','PJSIP')> <img src='../image/barge.png' data-toggle='tooltip' title='Barge Call' onclick=bargeWhisperCall('"+extensionList[i]+"','PJSIP','barge')> <img src='../image/whisper.png' data-toggle='tooltip' title='Whisper Call' onclick=bargeWhisperCall('"+extensionList[i]+"','PJSIP','whisper')> <img src='../image/minus.png' data-toggle='tooltip' title='HangUp Call' onclick=hangupCall('"+extensionList[i]+"')> </div> </section></div>";
								$.ajax({url: "checkExtensionStatus.php?ext="+extensionList[i], success: function(result){
									
										var results = result.split('|');
										extension = results[1];
										//console.log(results[0]);
									if(result[0]!="")
									{
										if(results[0] == "Ringing")
										{
											$('#'+extension+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
											$('#'+extension+'asteriskid').val(results[2]);
											$('#'+extensionList[i]+'statuschange').html("[Ringing]");
											$('#'+extension+'usericon').attr('src','../image/ring.png');
				
										}
										if(results[0] == "Connected")
										{
											
											
											$('#'+extension+'usericon').attr('src','../image/ring.png');
											$('#'+extension+'asteriskid').val(results[2]);
											$('#'+extension+'statuschange').html("[Connected]");
											$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
											$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
										}
										if(results[0] == "Disconnected")
										{
											$('#'+extension+'asteriskid').val("");
											//$('#'+extension+'statuschange').html("[Registered]");
										 	/* $('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
											$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxGreen info-box');  */
										}
									}
									//$("#div1").html(result);
								}});
								
							}
							
							stringpj = $('#agentdata').html() +stringpj;
								
								$('#agentdata').html(stringpj);
						//	$('#agentdatapjsip').html(stringpj);
							
							}else{
								//$('#pjsiprow').hide();
							}
							
						}
								
								
							if(type =="sip")
							{
								arr[arr.length-1]="SIP PEER Summary : "+arr[arr.length-1];
								$('#summary').html(arr[arr.length-1]);
								$('#agentdata').html(string);
								
							}
							if(type =="iax"){
								arr[arr.length-1]="<br><br>IAX2 PEER Summary : "+arr[arr.length-1];
								arr[arr.length-1] = $('#summary').html() +arr[arr.length-1];
								
								$('#summary').html(arr[arr.length-1]);
								//$('#summaryiax').html(arr[arr.length-1]);
								stringiax = $('#agentdata').html() +stringiax;
								//$('#agentdataiax').html(stringiax);
								$('#agentdata').html(stringiax);
							}
							
						}
					if(aEvent.action=="peerNotifyEvent")
						{
							//console.log(aEvent);
							peer = aEvent.Peer;
							ipPort = aEvent.Address;
						//	console.log(aEvent.Address);
							//console.log(aEvent.Status);
							if(peer.includes("/"))
									{
										
										var extension = peer.split('/');
										extension = extension[1];
									}else{
										extension = peer;
									}
									
									
									var Status = "["+aEvent.Status+"]";
									if(aEvent.Status == "Registered" || aEvent.Status == "Reachable")
									{
										$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
										$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
										
										
										$('#'+extension+'statuschange').html(Status);
										$('#'+extension+'ipport').html(ipPort);
									}
									if(aEvent.Status == "Unregistered" || aEvent.Status == "Unreachable")
									{
										$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxRed info-box');
										$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxRed info-box');
										
										$('#'+extension+'statuschange').html(Status);
										//$('#'+extension+'ipport').html("(Unspecified) : 0");
										$('#'+extension+'ipport').html("");
										
									}
						}
					if(aEvent.action != undefined || aEvent.code != '-1')
					{
						if(aEvent.action=="Ring")
						{
							//console.log(aEvent);
							var phoneNumber = aEvent.PhoneNumber;
							var extension = aEvent.Extension;
							
							var phoneLength = phoneNumber.length;
							var uniqueid = aEvent.uniqueid;
							var extensionStatus = $('#'+extension+'statuschange').html();
							if(extensionStatus.trim() == "[Registered]")
							{
								$('#'+extension+'asteriskid').val(uniqueid);
								$('#'+extension+'statuschange').html("[Ringing]");
								$('#'+extension+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
								$('#'+extension+'usericon').attr('src','../image/ring.png');
							}
						}
						if(aEvent.action=="Connect")
						{
							var phoneNumber = aEvent.PhoneNumber;
							var extension = aEvent.Extension;
							var phoneLength = phoneNumber.length;
							var extensionLength = extension.length;
							var uniqueid = aEvent.uniqueid;
							
							if(phoneLength <= 7 && extensionLength <= 7 )
							{
								$('#'+extension+'usericon').removeClass('preview-item shake-horizontal shake-opacity shake-constant');
								$('#'+extension+'usericon').attr('src','../image/ring.png');
								$('#'+extension+'statuschange').html("[Connected]");
								$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
								$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
								$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
								$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
								
								$('#'+phoneNumber+'asteriskid').val(uniqueid);
								$('#'+phoneNumber+'statuschange').html("[Connected]");
								$('#'+phoneNumber+'usericon').removeClass('preview-item shake-horizontal shake-opacity shake-constant');
								$('#'+phoneNumber).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
								$('#'+phoneNumber).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
								$('#'+phoneNumber).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
								$('#'+phoneNumber).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
								   
						   
							}else
							{
								$('#'+extension+'asteriskid').val(uniqueid);
								
								$('#'+extension+'usericon').removeClass('preview-item shake-horizontal shake-opacity shake-constant');
								$('#'+extension+'usericon').attr('src','../image/ring.png');
								$('#'+extension+'statuschange').html("[Connected]");
								$('#'+extension).removeClass('popBoxGreen info-box').addClass('popBoxconnect info-box');
								$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxconnect info-box');
								$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxconnect info-box');
								$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxconnect info-box');
							}
						}
						if(aEvent.action=="Down")
						{
							
							var extension = aEvent.Extension;
							var phoneNumber = aEvent.PhoneNumber;
							var phoneLength = phoneNumber.length;
							var extensionLength = extension.length;
							
							if(phoneLength <= 7 && extensionLength <= 7 )
							{
							$('#'+extension+'asteriskid').val("");
								
							
							$('#'+extension+'usericon').removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant').addClass('contctIcon');
							$('#'+extension+'usericon').attr('src','../image/agent.png');
							$('#'+extension+'statuschange').html("[Registered]");
							$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
							$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
							$('#'+extension).removeClass('popBoxconnect info-box').addClass('popBoxGreen info-box');
								
								
							$('#'+phoneNumber+'asteriskid').val("");
							$('#'+phoneNumber+'usericon').removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant').addClass('contctIcon');
							$('#'+phoneNumber+'statuschange').html("[Registered]");
							$('#'+phoneNumber+'usericon').attr('src','../image/agent.png');
							$('#'+phoneNumber).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
							$('#'+phoneNumber).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
							$('#'+phoneNumber).removeClass('popBoxconnect info-box').addClass('popBoxGreen info-box');
								   
						   
							}else
							{
							$('#'+extension+'asteriskid').val("");
							$('#'+extension+'statuschange').html("[Registered]");
							$('#'+extension+'usericon').removeClass('contctIcon preview-item shake-horizontal shake-opacity shake-constant').addClass('contctIcon');
							$('#'+extension+'usericon').attr('src','../image/agent.png');
							$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
							$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
							$('#'+extension).removeClass('popBoxconnect info-box').addClass('popBoxGreen info-box');
							}
						}
						if(aEvent.action=="DialEvent")
						{
							console.log(aEvent);
							var phoneNumber = aEvent.PhoneNumber;
							var extension = aEvent.Extension;
							var phoneLength = phoneNumber.length;
							var extensionLength = extension.length;
							var unique = aEvent.uniqueid;
							
							//$('#'+extension+'usericon').addClass('preview-item shake-horizontal shake-opacity shake-constant');
							//console.log("api.php?action=createcall&extension="+extension+"&direction="+aEvent.Direction+"&status="+aEvent.action+"&source="+phoneNumber+"&st="+aEvent.starttime+"&unique_id="+aEvent.uniqueid);
							//toastr["success"]("Call Notification : New "+aEvent.Direction+" Call From : "+phoneNumber)
								setTimeout(function (){
								var newWin = window.open("call_popup.php?uid="+unique);             
								if(!newWin || newWin.closed || typeof newWin.closed=='undefined') 
								{
									toastr["error"]("Failed : Popup Blocked , Please allow Popup")
								}
								}, 1000);
						}
						if(aEvent.action=="CONNECTED")
						{
					
							var phoneNumber = aEvent.PhoneNumber;
							$('.flux').text('Connected');
							//toastr["success"]("Call Connected : Call From : "+phoneNumber)
						}
						if(aEvent.action=="HANGUP")
						{
							var phoneNumber = aEvent.PhoneNumber;
							$('.flux').text('Disconnected');
							toastr["warning"]("Call Disconnected : Number : "+phoneNumber)
							cvalue = document.getElementById("note").value;
							document.cookie = "note" + "=" + cvalue + ";";
							setTimeout(function (){	
							location.reload();
							}, 5000);
						}
					}
				},
				OnClose: function(aEvent)
				{
					console.log(aEvent);
					if(page == "live_report.php")
					{
						$("#extensionrow").show();
						$("#summary").html("Please Start TECHEXTENSION Service to get Details");
						toastr["warning"]("Failed : Please Open Port 9898 for JwebSocket")
					}
				}
			});

}

	function transferCall(Ownextension)
	{
		var asterisk_id = $('#'+Ownextension+'asteriskid').val();
		if(!asterisk_id)
		{
			toastr["error"]("Failed : No Current Call to Transfer")
			return;
		}
	var transferToExtension = window.prompt('Enter Extension Number', '');
	console.log(transferToExtension);
	if(transferToExtension == "")
	{
		toastr["error"]("Failed : Please Enter Extension Number to Transfer")
		return;
	}
	var lMessageToken =
			{
					ns: lWSC.NS,
					type: "TransferCall",
					ip:asteriskip,
					extension:Ownextension,
					TransferToExtension:transferToExtension,
					UniqueID : asterisk_id
			};
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
	
	}
	
	
	
function originateCall(callto,user_context,user_channel,user_extension,prefix)
{
	if(prefix)
	{
		callto = prefix+callto;
	}
	var lMessageToken = 
	{
		ns: lWSC.NS,
		type: "call",
		cphonenumber:callto, 
		CustomContext:user_context,
		CustomChannel:user_channel,
		extension:user_extension,
		ip:asteriskip
	};
					
	var lCallbacks = 
	{
	OnFailure: function( aToken )
	{
		alert("fail");
	}};
	var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
	//console.log(lRes.code);
	if(lRes.code=='-1')
	{
		console.log(lMessageToken);
		console.log("Failed : Request cannot sent to Asterisk to make call");
		toastr["error"]("Failed : Request cannot sent to Asterisk to make call")
	}
	
}




	function spyCall(Ownextension,channelType)
	{
		var asterisk_id = $('#'+Ownextension+'asteriskid').val();
		if(!asterisk_id)
		{
			toastr["error"]("Failed : No Current Call to Spy")
			return;
		}
	
		var lMessageToken = 
		{
			ns: lWSC.NS,
			type: "spy",
			cphonenumber:admin_channel, 
			CustomContext:"spy",
			CustomChannel:channelType+"/"+Ownextension,
			extension:Ownextension,
			ip:asteriskip
		};
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
	
	}
	
	
	function bargeWhisperCall(Ownextension,channelType,type)
	{
		var asterisk_id = $('#'+Ownextension+'asteriskid').val();
		if(!asterisk_id)
		{
			toastr["error"]("Failed : No Current Call to Spy")
			return;
		}
	
		var lMessageToken = 
		{
			ns: lWSC.NS,
			type: "spy",
			cphonenumber:admin_channel, 
			CustomContext:type,
			CustomChannel:channelType+"/"+Ownextension,
			extension:Ownextension,
			ip:asteriskip
		};
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
	
	}
	
	
	function VoiceMail(Ownextension,channelType)
	{
		var asterisk_id = $('#'+Ownextension+'asteriskid').val();
		if(!asterisk_id)
		{
			//toastr["error"]("Failed : No Current Call to Spy")
			//return;
		}
	
		var lMessageToken = 
		{
			ns: lWSC.NS,
			type: "spy",
			cphonenumber:admin_channel, 
			CustomContext:"voicemail",
			CustomChannel:channelType+"/"+Ownextension,
			extension:Ownextension,
			ip:asteriskip
		};
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
	}
	
	
	
	function hangupCall(extension)
	{
		var asterisk_id = $('#'+extension+'asteriskid').val();
		if(!asterisk_id)
		{
			toastr["error"]("Failed : No Call To Hangup")
			//alert('No Call To Hangup');
			return;
		}
		var lMessageToken =
			{
					ns: lWSC.NS,
					type: "HangUp",
					ip:asteriskip,
					extension:extension,
					UniqueID : asterisk_id
			};
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};
			var lRes=lWSC.sendToken(lMessageToken, lCallbacks);
				$('#'+extension).removeClass('popBoxconnect info-box').addClass('popBoxGreen info-box');
				$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
				$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
				$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxGreen info-box');
	}
	
	
	
	
	function answerCall(s_channel,d_channel)
	{
		/* var asterisk_id = $('#'+extension+'asteriskid').val();
		if(!asterisk_id)
		{
			toastr["error"]("Failed : No Call To Answer")
			//alert('No Call To Hangup');
			return;
		} */
		var lMessageToken =
			{
					ns: lWSC.NS,
					type: "Answer",
					ip:asteriskip,
					s_channel:s_channel,
					d_channel : d_channel
			};
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};
			var lRes=lWSC.sendToken(lMessageToken, lCallbacks);
				$('#'+extension).removeClass('popBoxconnect info-box').addClass('popBoxGreen info-box');
				$('#'+extension).removeClass('popBoxYellow info-box').addClass('popBoxGreen info-box');
				$('#'+extension).removeClass('popBoxRed info-box').addClass('popBoxGreen info-box');
				$('#'+extension).removeClass('popBoxdial info-box').addClass('popBoxGreen info-box');
	}
	
	
	
	
if(page == "queue.php")
{
	var AllQueueChecker = setInterval(function(){
	//Page On Load
				var lMessageToken =
			{
					ns: lWSC.NS,
					type: "GetRealtimereport",
					extension:"",
					ip:asteriskip,
					typeRequest:"allQueueReport"
			};		
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
if(lRes.code == -1)
			{
				$("#extensionrow").show();
				$("#summary").html("Please Start AsterCTi Service to get Details");
				toastr["error"]("Failed : AsterCTi Service file is not working.")
				console.log("AsterCTi Service is Not Working")
				clearInterval(AllQueueChecker);
				
				//$("#summary").html("Please Start TECHEXTENSION Service to get Details");
				//$("#channelsuccess").show();
				//$("#channelreport").html("The Operational File Not Working");
				//$("#channelload").hide();
				
				//$("#queuesuccess").show();
				//$("#queuereport").html("The Operational File Not Working");
				//$("#queueload").hide();
				//stopReload();
			}
//console.log(lRes);
							}, 2000);
	
	
}
	
if(page == "live_report.php")
{

	
setTimeout(function (){
	$(".content-wrapper").removeClass("opacity");
	//Page On Load
	$("#loaderForDialerFirstClick").hide();
			var lMessageToken =
			{
					ns: lWSC.NS,
					type: "GetRealtimereport",
					extension:"",
					ip:asteriskip,
					typeRequest:""
			};		
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
			console.log(lMessageToken);
			console.log(lRes);
if(lRes.code == -1)
			{
				$("#extensionrow").show();
				$("#summary").html("Please Start TECHEXTENSION Service to get Details");
				toastr["error"]("Failed : TechExtension Service file is not working.")
				
			}
//console.log(lRes);
							}, 5000);
							
							
function getReportChannel()
{
	//$("#channelsuccess").hide();
	//$("#channelload").show();
	

	var lMessageToken =
			{
					ns: lWSC.NS,
					type: "GetRealtimereport",
					extension:'',
					ip:asteriskip,
					typeRequest:"channelReloadRequest"
			};		
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
			//console.log(lRes);
			if(lRes.code == -1)
			{
				$("#channelsuccess").show();
				$("#channelreport").html("The Operational File Not Working");
				$("#channelload").hide();
				stopReload();
			}

}

function getQueueDetail(queue)
{
	var queue=String(queue);
	
	console.log(queue);
	//queue = queue.trim();
	/*$("#channelsuccess").hide();
	$("#channelload").show();*/
	
	var lMessageToken =
			{
					ns: lWSC.NS,
					type: "GetRealtimereport",
					extension:queue,
					ip:asteriskip,
					typeRequest:""
			};		
			var lCallbacks = 
			{
					OnFailure: function( aToken )
					{
								alert("fail");
					}
			};

			var lRes=lWSC.sendToken( lMessageToken, lCallbacks );
			//console.log(lRes);
			if(lRes.code == -1)
			{
				//$("#channelsuccess").show();
				//$("#channelreport").html("The Operational File Not Working");
				//$("#channelload").hide();
				//stopReload();
			}
}
}