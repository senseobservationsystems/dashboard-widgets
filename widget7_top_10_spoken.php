<?php
	//Import needed classes
	require_once("classes/class.Api.php");
	
	$ini_array = parse_ini_file("../config/config.ini");
	$baseUrl = $ini_array["base_url"];
	$pdo = new PDO("mysql:host=".$ini_array["hostname"].";dbname=".$ini_array["dbname"], $ini_array["username"], $ini_array["password"]);
	
	//Create the Api
	$api = new Api();
	
	$key = "ZmQ4OWQ5MTMzMTQyMzZlMTFkNmM";
	$secret = "OGJjM2IwZGVkNDRkZjhkOGNkNjM";
	
	$oauth = $api->oauthLogin($key, $secret);

	if(isset($_GET['start_time'])){
		$parameters = "?id=".$_GET['id']."&start_time=".$_GET['start_time']."&end_time=".$_GET['end_time']."&current_time=".$_GET['current_time']."&size=".$_GET['size']."&settings=".$_GET['settings']."&share=".$_GET['share'];
	}else{
		$parameters = "";
	}
?>
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
	<script src="../js/jquery-1.7.1.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#content').html("<p style=\"text-align:center;\"><img src=\"images/ajax-loader.gif\" alt=\"LOADING\" /></p>");
			$('#content').load("widget7_load_content.php<?php echo $parameters;?>");
		});
	</script>
	</head>
	<body>
		<div id="content">
		<p style="font-size:12px; text-align: center;">
			<img src="images/ajax-loader.gif" alt="LOADING" />
		</p>
		</div>
		
	</body>
</html>