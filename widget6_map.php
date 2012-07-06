<?php
	//Import needed classes
	require_once("classes/class.Api.php");
	
	$ini_array = parse_ini_file("../config/config.ini");
	$baseUrl = $ini_array["base_url"];
	$pdo = new PDO("mysql:host=".$ini_array["hostname"].";dbname=".$ini_array["dbname"], $ini_array["username"], $ini_array["password"]);
	$deviceid = 0;
	
	//Create the Api
	$api = new Api();
	
	$key = "YjZkMDFlOWU5MDJiMzU3MjZiODc";
	$secret = "N2U4ODdmMjZkNDI1YzQ0Yzk0NGU";
	
	$oauth = $api->oauthLogin($key, $secret);
	
	if($oauth){
		$stmt = $pdo->query("SELECT * FROM `widget_config` WHERE user_id = '".$api->readCurrentUser()->getID()."' AND `session_key` = 'widget6_map_deviceid' LIMIT 1;");
		$obj = $stmt->fetch(PDO::FETCH_OBJ);
		if($obj != null){
			$deviceid = $obj->session_value;
		}
		
		
		if(isset($_GET['deviceid']) && $obj == null){
			$pdo->exec("INSERT INTO `widget_config`(id, session_key, session_value, user_id) VALUES (NULL, 'widget6_map_deviceid', '".$_GET['deviceid']."','".$api->readCurrentUser()->getID()."');");
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
				echo '<li><a href="?id='.$_GET['id'].'&deviceid='.$device->getID().'" title="'.$device->getType().'">'.$device->getType().'</a></li>';
			}
			echo '</ul>';
		}else{
			$device = $api->readDevice($deviceid);
			
			
			if(isset($device) && $device != NULL){
				foreach ($device->getMySensors(0,100,TRUE) as $s) {
					if($s->getName() == "position"){
						$sensor = $s;
					}
				}
			}else{
				$devices = $api->listDevices();
				echo '<ul>';
				foreach($devices as $device){
					echo '<li><a href="?id='.$_GET['id'].'&deviceid='.$device->getID().'" title="'.$device->getType().'">'.$device->getType().'</a></li>';
				}
				echo '</ul>';
			}
			
			if(isset($_GET['start_time']) && isset($_GET['current_time'])){
				$endtime = $_GET['current_time']/1000;
				$fromtime = ($_GET['start_time']/1000);
				$interval = ($endtime-$fromtime)/200;
			}else{
				$endtime = 0;
				$fromtime = 0;
				$interval = 0;
			}
			if(isset($sensor) && $sensor != null){
				$dataarray = $sensor->getData(0,100,$fromtime,$endtime,0,0,0,'DESC',FALSE,$interval);
			}
			if(isset($dataarray) && Count($dataarray) == 0){
				echo 'No location data found in this period.';
			}
		}
	}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <style type="text/css">
      html { height: 100% }
      body { height: 100%; margin: 0; padding: 0 }
      #map_canvas { height: 100% }
    </style>
    <script type="text/javascript" src="http://maps.googleapis.com/maps/api/js?key=AIzaSyBLmOiipLNWIY0VhcKDj-2Vp9ugNG9R9FQ&sensor=false">
    </script>
    <script type="text/javascript">
    function initialize() {
		  var myLatLng = new google.maps.LatLng(<?php echo json_decode($dataarray[0]->getValue())->latitude;?>, <?php echo json_decode($dataarray[0]->getValue())->longitude;?>);
		  var myOptions = {
		    zoom: 14,
		    panControl: false,
		    zoomControl: false,
		    scaleControl: false,
		    mapTypeControl: false,
			streetViewControl: false,
			overviewMapControl: false,
		    center: myLatLng,
		    mapTypeId: google.maps.MapTypeId.ROADMAP
		  }
		
		  var map = new google.maps.Map(document.getElementById("map_canvas"),
		      myOptions);
		  var flightPlanCoordinates = [
		 	<?php 
		  	if(isset($dataarray) && $dataarray != ""){
		  		$i = 0;
		  		foreach($dataarray as $data){
		  			$i++;
					$obj = json_decode($data->getValue());
					?>
						new google.maps.LatLng(<?php echo $obj->latitude;?>, <?php echo $obj->longitude;?>),
					<?php 
				}
		  	}?>
		  ];
		  var flightPath = new google.maps.Polyline({
		    path: flightPlanCoordinates,
		    strokeColor: "#FF0000",
		    strokeOpacity: 1.0,
		    strokeWeight: 2
		  });
		
		  flightPath.setMap(map);
		  var marker = new google.maps.Marker({
			    position: myLatLng,
			    title:"I'm here!"
		  });

		  marker.setMap(map);
	}
    </script>
  </head>
  <body onload="initialize()">
    <div id="map_canvas" style="width:100%; height:100%"></div>
  </body>
</html>