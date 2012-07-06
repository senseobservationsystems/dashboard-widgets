<?php
	//Import needed classes
	require_once("classes/class.Api.php");
	
	//Create the Api
	$api = new Api();
	
	$key = "ZDFlNTY5ODZjY2E3YWY2ZGIyYjE";
	$secret = "MTAwOTI5MTI2NzgwNTI3ZmVlMzI";
	
	$oauth = $api->oauthLogin($key, $secret);
?>
<style>
	body, html{
		margin:0;
		padding:0;
		text-align:center;
	}
</style>
<?php
	
	if($oauth){
		foreach ($api->listSensors(0, 100, FALSE, TRUE, FALSE) as $s) {
			if($s->getName() == "Activity"){
				$sensor = $s;
			}
		}
			
		if(isset($_GET['start_time']) && isset($_GET['current_time'])){
			$endtime = ($_GET['current_time']/1000);
			$starttime = ($_GET['start_time']/1000);
		}else{
			$endtime = 0;
			$starttime = 0;
		}
		if(isset($sensor) && $sensor != null){
			$dataarray = $sensor->getData(0,1,$starttime,$endtime,0,0,0,'DESC',TRUE,0);
			foreach ($dataarray as $data) {
				echo '<img src="images/'.$data->getValue().'.jpg" alt="'.$data->getValue().'"/>';
				if($data->getValue() == 'sit'){
					echo '<p>You are sitting right now.</p>';
				}elseif($data->getValue() == 'stand'){
					echo '<p>You are standing right now.</p>';
				}
				
			}
		}
	}

?>
	