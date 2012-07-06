<!DOCTYPE HTML PUBLIC 
    "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Graph demo</title>

    <style type="text/css">
	    body {font: 8pt arial;padding:0;margin:0;}
	    .graph-frame{
	    	border:none!important;
	    }
		.call_list{
			margin:0px;
			padding:0px;
		}
		.call_list li{
			list-style: none;
			display: block;
			width:187px;
			height:20px;
			font-size:10px;
			border-bottom:1px solid #ccc;
			float:left;
			margin:0px;
			padding:0px;
		}
	</style>

    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript" src="js/graph.js"></script>
    <link rel="stylesheet" type="text/css" href="css/graph.css">
    <!--[if IE]><script src="js/excanvas.js"></script><![endif]-->
    
    <?php
    	date_default_timezone_set('Europe/Amsterdam');
    	if(isset($_GET['size']) && $_GET['size'] == "1x1"){
    		echo '<style>';
				echo '.graph-legend{
					display:none;
				}';
				echo '.graph-axis-button-menu{
					display:none;
				}';
			echo '</style>';
    	}
    ?>
  </head>

  <body>
    <div id="mygraph"></div>
    
    <div id="info"></div>
  </body>
</html>




<?php
	//Import needed classes
	require_once("classes/class.Api.php");
	
	$ini_array = parse_ini_file("../config/config.ini");
	$baseUrl = $ini_array["base_url"];
	$pdo = new PDO("mysql:host=".$ini_array["hostname"].";dbname=".$ini_array["dbname"], $ini_array["username"], $ini_array["password"]);
	$deviceid = 0;
	
	//Create the Api
	$api = new Api();
	
	$key = "MmUxNDQxYjEyMjFmYmY5ZWJlYmM";
	$secret = "MDRiYTcyZDM1YmZjNzA2NTkwNzE";
	
	$oauth = $api->oauthLogin($key, $secret);
	
	if($oauth){
		
		$stmt = $pdo->query("SELECT * FROM `widget_config` WHERE user_id = '".$api->readCurrentUser()->getID()."' AND `session_key` = 'widget8_acc_deviceid' LIMIT 1;");
		$obj = $stmt->fetch(PDO::FETCH_OBJ);
		if($obj != null){
			$deviceid = $obj->session_value;
		}
		
		
		if(isset($_GET['deviceid']) && $obj == null){
			$pdo->exec("INSERT INTO `widget_config`(id, session_key, session_value, user_id) VALUES (NULL, 'widget8_acc_deviceid', '".$_GET['deviceid']."','".$api->readCurrentUser()->getID()."');");
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
				foreach ($device->getMySensors(0,100,TRUE) as $s) {
					if($s->getName() == "accelerometer"){
						$sensor = $s;
					}
				}
			}
			
			if(isset($_GET['start_time']) && isset($_GET['current_time'])){
				$endtime = $_GET['current_time']/1000;
				$fromtime = ($_GET['start_time']/1000);
				$interval = ($endtime-$fromtime)/200;
			}else{
				$endtime = time()+(60*60*12);
				$fromtime = time()-(60*60*12);
				$interval = ($endtime-$fromtime)/200;
			}
			if(isset($sensor) && $sensor != null){
				$dataarray = $sensor->getData(0,200,$fromtime,$endtime,0,0,0,'ASC',FALSE,$interval);
			}
		}
		
	}
	
if($deviceid != 0 && $endtime != 0 && $fromtime != 0){
?>
	
<script type="text/javascript">
      google.load("visualization", "1");
      
      // Set callback to run when API is loaded
      google.setOnLoadCallback(drawVisualization); 

      // Called when the Visualization API is loaded.
      function drawVisualization() {
        // Create and populate a data table.
        var data = new google.visualization.DataTable();
        data.addColumn('datetime', 'time');
        data.addColumn('number', 'x-axis');
        data.addColumn('number', 'y-axis');
        data.addColumn('number', 'z-axis');


		<?php 
			if(isset($dataarray) && $dataarray != ""){
				foreach ($dataarray as $data) {
					$obj = get_object_vars(json_decode($data->getValue()));
					echo "data.addRow([new Date(". $data->getDate()*1000 ."), ". $obj["x-axis"] .", ". $obj["y-axis"] .", ". $obj["z-axis"] ."]);";
				}
			}
		?>
        

        // specify options
        options = {
          'width':  '100%', 
          <?php if(isset($_GET['details']) && $_GET['details'] == 'true'){
          	echo "'height': '400px', ";
		  }elseif(isset($_GET['size'])){
		  	if($_GET['size'] == '2x2' || $_GET['size'] == '1x2')
		  		echo "'height': '300px', ";
			elseif($_GET['size'] == '1x1' || $_GET['size'] == '2x1')
		  		echo "'height': '120px', ";
		  }?>
          'start': new Date(<?php echo $fromtime*1000;?>),
          'end': new Date(<?php echo $endtime*1000;?>),
          'moveable': false,
          'zoomable': false
        };

        // Instantiate our graph object.
        var graph = new links.Graph(document.getElementById('mygraph'));
        
        // Draw our graph with the created data and options 
        graph.draw(data, options);
      }
   </script>
<?php }elseif($deviceid != 0){
	echo '<p style="text-align:center;">Loading widget</p>';
}?>