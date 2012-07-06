<!DOCTYPE HTML PUBLIC 
    "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
	<title>Activity</title>
	<style>
		.activitys{
			margin:0px;
			padding:0px;
		}
		.activitys ul{
			margin:0px;
			padding:0px;
		}
		.activitys li{
			display:block;
			height:35px;
			widht:100%;
			list-style:none;
			background:url('images/activity.jpg') no-repeat 0px 0px;
			margin:0px;
			padding:0px;
			padding-left:45px;
			line-height:16px;
			font-size:12px;
			font-weight:bold;
			margin-bottom:2px;
		}
		.walk{
			background:url('images/activity.jpg') no-repeat 0px -35px #3BB5DC !important;
		}
		.run{
			background:url('images/activity.jpg') no-repeat 0px 0px #80C350 !important;
		}
		.bike{
			background:url('images/activity.jpg') no-repeat 0px -70px #FBAB64 !important;
		}
		
	</style>
  </head>
<?php
	//Import needed classes
	require_once("classes/class.Api.php");
	
	//Create the Api
	$api = new Api();
	
	$key = "NmYzMzg4NWVkYTFhOTAwMDA4ZWI";
	$secret = "YjdiNTNkZDE3YmE4YTc5ZDBlNzI";
	
	$oauth = $api->oauthLogin($key, $secret);
	
	if($oauth){
		$sensor = $api->readSensor("142086");
			
		if(isset($_GET['start_time']) && isset($_GET['end_time'])){
			$endtime = ($_GET['end_time']/1000);
			$starttime = ($_GET['start_time']/1000);
			$interval = ($endtime-$starttime)/100;
		}else{
			$endtime = 0;
			$starttime = 0;
			$interval = 0;
		}
		if(isset($sensor) && $sensor != null){
			$dataarray = $sensor->getData(0,1000,$starttime,$endtime,0,0,0,'ASC',TRUE,$interval);
		}
	}
	$duration = array();
	$distance = array();
	//duration
	$duration['Running'] = 0;
	$duration['Walking'] = 0;
	$duration['Biking'] = 0;
	//steps
	$steps = 0;
	$runsteps = 0;
	//distance
	$distance['Running'] = 0;
	$distance['Walking'] = 0;
	$distance['Biking'] = 0;
	if(isset($dataarray) && $dataarray != ""){
		foreach ($dataarray as $data) {
			$obj = json_decode($data->getValue());
			if(isset($obj->{'duration'}))
				$duration[$obj->{'type'}] += $obj->{'duration'};
			if(isset($obj->{'distance'}))
				$distance[$obj->{'type'}] += $obj->{'distance'};
			if($obj->{'type'} == 'Walking'){
				if(isset($obj->{'steps'}))
					$steps += $obj->{'steps'};
			}elseif($obj->{'type'} == 'Running'){
				if(isset($obj->{'steps'}))
					$runsteps += $obj->{'steps'};
			}
		}
	}
?>
  <body>
  	<div class="activitys">
  		<ul>
  			<li class="walk"><?php 
  			$hours = floor($duration['Walking'] / 3600);
  			$time = number_format(((($duration['Walking'] / 3600 - $hours)/100*60)+$hours) , 2, ':', '');
  			echo $time.' uur <br/>'.number_format(($distance['Walking'] / 1000), 2, '.', '').' km '.$steps.' steps';?></li>
  			<li class="run"><?php 
  			$hours = floor($duration['Running'] / 3600);
  			$time = number_format(((($duration['Running'] / 3600 - $hours)/100*60)+$hours) , 2, ':', '');
  			echo $time.' uur <br/>'.number_format(($distance['Running'] / 1000), 2, '.', '').' km '.$runsteps.' steps';?></li>
  			<li class="bike"><?php 
  			$hours = floor($duration['Biking'] / 3600);
  			$time = number_format(((($duration['Biking'] / 3600 - $hours)/100*60)+$hours) , 2, ':', '');
  			echo $time.' uur <br/>'.number_format(($distance['Biking'] / 1000), 2, '.', '').' km';?></li>
  		</ul>
  	</div>
    

  </body>
</html>




