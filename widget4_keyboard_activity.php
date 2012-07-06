<!DOCTYPE HTML PUBLIC 
    "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>

    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript" src="js/timeline.js"></script>
    <link rel="stylesheet" type="text/css" href="css/timeline.css">
    <!--[if IE]><script src="js/excanvas.js"></script><![endif]-->

  </head>

  <body>
    <div id="widgetTimeLine"></div>
  </body>
</html>




<?php
	//Import needed classes
	require_once("classes/class.Api.php");
	
	//Create the Api
	$api = new Api();
	
	$key = "NzM0OTNhNjJlZmM1OGIzNDkzZDk";
	$secret = "YzI3YzZmYjAyOWQ2MmJiMTMxNjA";
	
	$oauth = $api->oauthLogin($key, $secret);
	
	if($oauth){
		foreach ($api->listSensors(0, 100, FALSE, TRUE, FALSE) as $s) {
			if($s->getName() == "keyboard activity"){
				$sensor = $s;
			}
		}
			
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
			$dataarray = $sensor->getData(0,100,$starttime,$endtime,0,0,0,'ASC',TRUE,$interval);
		}
	}

	
if($endtime != 0 && $starttime != 0){
?>
	
<script type="text/javascript">
      google.load("visualization", "1");
      
      // Set callback to run when API is loaded
      google.setOnLoadCallback(drawVisualization); 

      // Called when the Visualization API is loaded.
      function drawVisualization() {
        // Create and populate a data table.
        var data = new google.visualization.DataTable();
        data.addColumn('datetime', 'start');
        data.addColumn('datetime', 'end');
        data.addColumn('string', 'content');
        data.addColumn('string', 'group');
        
        <?php 
			if(isset($dataarray) && $dataarray != ""){
				$prevDate = 0;
				
				foreach ($dataarray as $data) {
					if($prevDate !=0){
						echo "data.addRow([new Date(".($prevDate)."), new Date(". $data->getDate()*1000 ."), '".$prevValue."', 'Keyboard']);";
					}
					$prevDate = $data->getDate()*1000;
					$prevValue = $data->getValue();
				}
			}
		?>

        // specify options
        options = {
          "width":  "100%", 
          <?php if(isset($_GET['size'])){echo "'height': '90px' ";}else{echo "'height': '400px' ";}?>,
          "style": "box",
          "start": new Date(<?php echo $starttime*1000;?>),
          "end": new Date(<?php echo $endtime*1000;?>),
          "stackEvents": true,
          "animate": false,
          "eventMargin": 10, 
          "eventMarginAxis": 5,
          "showMajorLabels": false,
          "showCustomTime": false,
          "showNavigation": false,
          "axisOnTop": true,
          "snapEvents": true,
          "groupsOnRight": false
        };

        // Instantiate our timeline object.
        var timeline = new links.Timeline(document.getElementById('widgetTimeLine'));
        
        // Draw our timeline with the created data and options 
        timeline.draw(data, options);
      }
   </script>
<?php }else{
	echo '<p style="text-align:center;">Loading widget</p>';
}?>