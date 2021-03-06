<html>
	<head>
	<style type="text/css">
		.call_list{
			margin:0px;
			padding:0px;
		}
		.call_list li{
			list-style: none;
			display: block;
			width:187px;
			height:30px;
			font-size:11px;
			border-bottom:1px solid #ccc;
			float:left;
			margin:0px;
			padding:0px;
		}
		.content{
			display: none;
		}
	</style>
	<script type="text/javascript">
		function showContent(){
			var content = document.getElementById('content');
			content.style.display = "block";
			var loading = document.getElementById('loading');
			loading.style.display = "none";
		}
	</script>
	</head>
	<body onload="showContent()">
		<div id="content">
		<p style="font-size:12px;">
		<?php
			//Import needed classes
			require_once("classes/class.Api.php");
			
			$ini_array = parse_ini_file("../config/config.ini");
			$baseUrl = $ini_array["base_url"];
			$pdo = new PDO("mysql:host=".$ini_array["hostname"].";dbname=".$ini_array["dbname"], $ini_array["username"], $ini_array["password"]);
			
			//Create the Api
			$api = new Api();
			
			$key = "MGFlMjVmYTAzMGRiNGQ4YzBiZDU";
			$secret = "OGQxYTNhODBhYmU1ODhjYjNkNzc";
			
			$oauth = $api->oauthLogin($key, $secret);
			
			$deviceid = 0;
			
			if($oauth){
				
				$stmt = $pdo->query("SELECT * FROM `widget_config` WHERE user_id = '".$api->readCurrentUser()->getID()."' AND `session_key` = 'widget1_top_10_deviceid' LIMIT 1;");
				$obj = $stmt->fetch(PDO::FETCH_OBJ);
				if($obj != null){
					$deviceid = $obj->session_value;
				}
				
				if(isset($_GET['deviceid']) && $obj == null){
					$pdo->exec("INSERT INTO `widget_config`(id, session_key, session_value, user_id) VALUES (NULL, 'widget1_top_10_deviceid', '".$_GET['deviceid']."','".$api->readCurrentUser()->getID()."');");
					$deviceid = $_GET['deviceid'];
				}elseif(isset($_GET['deviceid']) && $_GET['deviceid'] != $obj->session_value){
					$sql = "UPDATE `widget_config` SET session_value=? WHERE id=?";
					$q = $pdo->prepare($sql);
					$q->execute(array($_GET['deviceid'],$obj->id));
					$deviceid = $_GET['deviceid'];
				}
				
				if(($deviceid == 0) || (isset($_GET['settings']) && $_GET['settings'] == 'true')){
					$devices = $api->listDevices();
					echo '<ul>';
					foreach($devices as $device){
						echo '<li><a href="?deviceid='.$device->getID().'" title="'.$device->getType().'">'.$device->getType().'</a></li>';
					}
					echo '</ul>';
				}else{
					$device = $api->readDevice($deviceid);
				
					if(isset($device) && $device != null){
						foreach ($device->getMySensors(0,1000,TRUE) as $s) {
							if($s->getName() == "call state"){
								$sensor = $s;
							}
						}
					}
					$incomming = "";
					$calling = false;
					$num = 0;
					$total = 10;
					if(isset($_GET['size']) && $_GET['size'] == "1x1"){
						$total = 5;
					}
					
					if(isset($_GET['current_time'])){
						$currenttime = $_GET['current_time']/1000;
						$fromtime = ($_GET['current_time']/1000)-60*60*24*60;
					}else{
						$currenttime = 0;
						$fromtime = 0;
					}
			
					echo '<ul class="call_list">';
					if(isset($sensor) && $sensor != null){
						$dataarray = $sensor->getData(0,1000,$fromtime,$currenttime,0,0,0,'DESC',FALSE);
						foreach ($dataarray as $data) {
							if($num < $total){
								$obj = json_decode($data->getValue());
								
								if($calling && $obj->state == 'ringing' && isset($obj->incomingNumber)){
									$num++;
									echo '<li>'.$num.') '.date('d-m-y m:h:s',$data->getDate()).' -> '.$obj->incomingNumber.'</li>';
								}
								
								if($obj->state == 'calling'){
									$calling = true;
								}else{
									$calling = false;
								}
							}
						}
					}
					echo '</ul>';
				}
				
			}
			
			
			?>
		</p>
		</div>
		<div id="loading">
			loading...
		</div>
	</body>
</html>
