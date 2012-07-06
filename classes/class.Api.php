<?php

error_reporting(E_ALL);
session_start();

//import required classes
require_once("classes/objects/Sensor.php");
require_once("classes/objects/Device.php");
require_once("classes/objects/User.php");
require_once("classes/objects/Group.php");
require_once("classes/objects/Data.php");
require_once("classes/objects/Service.php");
require_once("classes/core/CommonsenseOAuthClient.class.php");

/**
 * sense dashboard - class.Api.php
 *
 * $Id$
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Api handels all calls with the commonSense API
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Api
{
    // --- ASSOCIATIONS ---


    // --- ATTRIBUTES ---

    /**
     * For every API request that needs authorization the session_id needs to be specified either in the X-SESSION_ID header, the Cookie header as session_id, or as parameter with the name session_id.
     *
     * @access private
     * @var int
     */
    private $session_id = null;
    
    /**
     * The oAuth key for loggin in, using oAuth.
     *
     * @access private
     * @var int
     */
    private $oauth_key = null;
	
	/**
     * The oAuth secret for loggin in, using oAuth.
     *
     * @access private
     * @var int
     */
    private $oauth_secret = null;
	
	/**
     * The oAuth callback url for loggin in, using oAuth.
     *
     * @access private
     * @var int
     */
    private $oauth_callback = null;
	
	/**
     * The oAuth connection, to get data using oAuth. If this connection exist u can use it to get data.
     *
     * @access private
     * @var int
     */
    private $oauth_connection = null;

    /**
     * Contains the current Error message
     *
     * @access private
     * @var string
     */
    private $error_message = "";
	
	/**
     * Shows if there is an error
     *
     * @access private
     * @var Boolean
     */
    private $error = false;

    // --- OPERATIONS ---

	public function api(){
		if(isset($_SESSION['session_id']))
			$this->session_id = $_SESSION['session_id'];
	}

    /**
     * Returns the session id if exists
     *
     * @access public
     * @return mixed
     */
    public function getSessionId()
    {
		if($this->session_id != null){
			return $this->session_id;
		}else{
			return false;
		}
    }
	
	public function setSessionId($sessionid){
		$this->session_id = $sessionid;
	}

    /**
     * This function handles all json calls with the common sense API
     *
     * @access private
     * @param  string data
     * @param  string type (POST, GET, PUT, DELETE)
     * @param  string method (users/current.json)
     * @return json object
     */
    private function call($data, $type, $method)
    {
    	if(!empty($this->oauth_connection)){
    		if($type == "POST"){
    			$obj = json_decode($this->oauth_connection->post($method, $data));
    		}elseif($type == "GET"){
    			$obj = json_decode($this->oauth_connection->get($method, $data));
    		}elseif($type == "DELETE"){
    			$obj = json_decode($this->oauth_connection->delete($method, $data));
    		}elseif($type == "PUT"){
    			//$obj = $this->oauth_connection->post($method, $data);
    			//Is thare a oAuth put method?
    			$obj = null;
    		}else{
    			$obj = null;
    		}
    		
    	}elseif(!empty($this->session_id)){
    		$data_string = json_encode($data);  
			$ch = curl_init('http://api.sense-os.nl/'.$method);                                                                      
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);                                                                                                                                       
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			if(count($data) != 0)     
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
			    'Content-Type: application/json',                                                                       
			    'X-SESSION_ID: '. $this->session_id
			));                                                                                                                   
			
			$result = curl_exec($ch);
			$obj = json_decode($result);
    	}else{
    		$obj = null;
    	}                                                   
		return $obj;
    }

/**
     * This function handles all json calls with the common sense API
     *
     * @access private
     * @param  string data
     * @param  string type (POST, GET, PUT, DELETE)
     * @param  string method (users/current.json)
     * @return json object
     */
    private function callByJson($data_string, $type, $method)
    {

		$ch = curl_init('http://api.sense-os.nl/'.$method);                                                                      
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);                                                                                                                                       
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if(count($data) != 0)     
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                       
		    'X-SESSION_ID: '. $this->session_id
		));                                                                                                                   
		
		$result = curl_exec($ch);
		$obj = json_decode($result);
		
		return $obj;
    }

    /**
     * This function handles all errors and returns a error message
     *
     * @access private
     * @return string
     */
    private function error($msg)
    {
		$this->error = true;
		$this->error_message .= $msg;
    }
	
	/**
     * get error log
     *
     * @access private
     * @return string
     */
    public function getErrorLog()
    {
		if($this->error)
			return $this->error_message;
		else
			return "";
    }

    /**
     * With this method a user can login with his username and md5 password hash. The function sets the needed session_id. This session_id is used for authentication. A user can be logged in on multiple locations.
     *
     * @access public
     * @param  string username
     * @param  string password
     * @return mixed
     */
    public function login($username, $password)
    {
		if($username != "" && $password != ""){
			$data = array("username" => $username, "password" => md5($password));                                                                    
			$data_string = json_encode($data);                                                                                   
			 
			$ch = curl_init('http://api.sense-os.nl/login.json');                                                                      
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
			    'Content-Type: application/json',                                                                                
			    'Content-Length: ' . strlen($data_string))                                                                       
			);                                                                                                                   
			 
			$result = curl_exec($ch);
			$obj = json_decode($result);
			
			if(isset($obj->{'error'}) && $obj->{'error'} == "unauthorized"){
				$this->error("No username or password! \n");
				return false;
			}else{
				$this->session_id = $obj->{'session_id'};
				$_SESSION['session_id'] = $obj->{'session_id'};
			}
			return true; //should return user
		}else{
			$this->error("No username or password! \n");
			return false;
		}
    }

	/**
     * With this method a user can login using oAuth. The function sets the needed session_id. This session_id is used for authentication at the oAuth authentication page.
     *
     * @access public
     * @param  string oauth key
     * @param  string oauth secret
     * @return mixed
     */
	public function oauthLogin($key, $secret){
		if(isset($_GET['id'])){
			$uniek_id = $_GET['id'];
		}else{
			$uniek_id = 'unk';
		}
		$pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		if ($_SERVER["SERVER_PORT"] != "80")
		{
		    $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER['PHP_SELF'];
		} 
		else 
		{
		    $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER['PHP_SELF'];
		}
		$callback = $pageURL;
		if(empty($this->oauth_key) && empty($this->oauth_secret) && empty($this->oauth_callback)){
			$this->oauth_key = $key;
			$this->oauth_secret = $secret;
			$this->oauth_callback = $callback;
		}
		if(isset($_COOKIE["senseDashboardAccesToken_oauth_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]) && isset($_COOKIE["senseDashboardAccesToken_oauth_token_secret_".$uniek_id.$this->oauth_key.$this->oauth_secret]) && !empty($this->oauth_key) && !empty($this->oauth_secret) ){
			$access_token = array();
			$access_token["oauth_token"] = $_COOKIE["senseDashboardAccesToken_oauth_token_".$uniek_id.$this->oauth_key.$this->oauth_secret];
			$access_token["oauth_token_secret"] = $_COOKIE["senseDashboardAccesToken_oauth_token_secret_".$uniek_id.$this->oauth_key.$this->oauth_secret];

			// Create a CommonsenseOAuthClient object with consumer/user tokens.
			$this->oauth_connection = new CommonsenseOAuthClient(
				$this->oauth_key, 
				$this->oauth_secret, 
				$access_token["oauth_token"],
				$access_token["oauth_token_secret"]
			);
			$this->oauth_connection->setResponseFormat("html");
		}else{
			if((empty($_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]) && empty($_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]) && (!empty($this->oauth_key) && !empty($this->oauth_secret) && !empty($this->oauth_callback))) || (empty($_GET['oauth_verifier']) && empty($_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]) && (!empty($this->oauth_key) && !empty($this->oauth_secret) && !empty($this->oauth_callback)))){
				$connection = new CommonsenseOAuthClient($key, $secret);

				// Get temporary credentials.
				$params = $connection->getRequestToken($this->oauth_callback);
				
				if (!isset($params["oauth_token"])) {
					var_dump($params);
					exit(1);
				}
				
				// Save temporary credentials to session.
				$token = $params["oauth_token"];
				$secret = $params["oauth_token_secret"];
				
				$_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret] = array(
					"oauth_token" => $token,
					"oauth_token_secret" => $secret
				);
				
				switch ($connection->httpCode) {
				  case 200:
				    // Build authorize URL and redirect user to the provider.
				    //$url = $connection->getAuthorizeURL($token, "", $_GET['sessionid']);
				    $parm = "";
					if(isset($_GET['id'])){
						$parm .= "&id=".$_GET['id'];
					}
					if(isset($_GET['current_time'])){
						$parm .= "&current_time=".$_GET['current_time'];
					}else{
						$parm .= "&current_time=0";
					}
					if(isset($_GET['start_time'])){
						$parm .= "&start_time=".$_GET['start_time'];
					}else{
						$parm .= "&start_time=0";
					}
					if(isset($_GET['end_time'])){
						$parm .= "&end_time=".$_GET['end_time'];
					}else{
						$parm .= "&end_time=0";
					}
					if(isset($_GET['size'])){
						$parm .= "&size=".$_GET['size'];
					}else{
						$parm .= "&size=1x1";
					}
					if(isset($_GET['settings'])){
						$parm .= "&settings=".$_GET['settings'];
					}else{
						$parm .= "&settings=false";
					}
					if(isset($_GET['share'])){
						$parm .= "&share=".$_GET['share'];
					}else{
						$parm .= "&share=false";
					}
					if(isset($_GET['details'])){
						$parm .= "&details=".$_GET['details'];
					}else{
						$parm .= "&details=false";
					}
				    $url = $connection->getAuthorizeURL($token).$parm;
				    header("Location: " . $url); 
				    break;
				    
				  default:
				  	echo "<strong><pre>Error: ".$connection->httpInfo."</pre></strong>";
				}
			}else if(empty($_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret])){
					try {
						// If the oauth_token is old redirect to the connect page.
						if (isset($_REQUEST["oauth_token"]) && 
							$_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]["oauth_token"] !== $_REQUEST["oauth_token"]) 
						{
						 	$_SESSION["oauth_status".$uniek_id] = "oldtoken";
						 	
							unset($_SESSION["status_".$uniek_id.$this->oauth_key.$this->oauth_secret]);
							unset($_SESSION["oauth_token"]);
							unset($_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]);
							 
							/* Redirect to page with the connect to Twitter option. */
							header('Location: '.$this->oauth_callback);
		
						}
					
						// Create CommonsenseOAuthClient object with app key/secret and token 
						// key/secret from default phase.
						$connection = new CommonsenseOAuthClient(
						 	$this->oauth_key, 
							$this->oauth_secret, 
							$_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]["oauth_token"],
							$_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]["oauth_token_secret"]
						);	
						
						// Request access tokens from Commonsense.
						$access_token = $connection->getAccessToken($_REQUEST);
						
						// Remove no longer needed request tokens.
						unset($_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]);
						
						// Save the access tokens. Normally these would be saved in a database 
						// for future use.
						$_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret] = $access_token;
							
						// If HTTP response is 200 continue otherwise send to connect page to 
						// retry.
						echo $connection->httpCode;
						if (200 == $connection->httpCode) {
							// The user has been verified and the access tokens can be saved for future use.
							$_SESSION["status_".$uniek_id.$this->oauth_key.$this->oauth_secret] = "verified";
							header("Location: ".$this->oauth_callback);
						} else {
							unset($_SESSION["status_".$uniek_id.$this->oauth_key.$this->oauth_secret]);
							unset($_SESSION["request_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]);
							unset($_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret]);
							header('Location: '.$this->oauth_callback);
						}
					} catch (Exception $e) {
						die("OAuth Error: ".$e);
					}
			}else if($_SESSION["status_".$uniek_id.$this->oauth_key.$this->oauth_secret] == "verified" && !empty($_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret])){
				// Get user access tokens out of the session.
				$access_token = $_SESSION["access_token_".$uniek_id.$this->oauth_key.$this->oauth_secret];
				setcookie("senseDashboardAccesToken_oauth_token_".$uniek_id.$this->oauth_key.$this->oauth_secret,$access_token["oauth_token"]);
				setcookie("senseDashboardAccesToken_oauth_token_secret_".$uniek_id.$this->oauth_key.$this->oauth_secret,$access_token["oauth_token_secret"]);
				
				// Create a CommonsenseOAuthClient object with consumer/user tokens.
				$this->oauth_connection = new CommonsenseOAuthClient(
					$this->oauth_key, 
					$this->oauth_secret, 
					$access_token["oauth_token"],
					$access_token["oauth_token_secret"]
				);
				$this->oauth_connection->setResponseFormat("html");
			}
		}

		if(!empty($this->oauth_connection)){
			return true;
		}else{
			return false;
		}
	}

    /**
     * This method will logout the user by destroying its session.
     *
     * @access public
     * @return mixed
     */
    public function logout()
    {
		$data = $this->call(array(), "GET", "logout.json");
		unset($_SESSION['session_id']);
		$this->session_id = null;
		session_destroy();
		return $data;
    }

    /**
     * All the users devices that have sensors will be returned as json object
     *
     * @access public
     * @return Array of Divice objects
     */
    public function listDevices()
    {
		$data = $this->call(array(), "GET", "devices.json");
		$data = $data->{'devices'};
		$deviceArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$deviceArray->append(new Device($data[$i], $this));
		}
		return $deviceArray;
    }

    /**
     * Returns the details of a device that has sensors
     *
     * @access public
     * @param  int id
     * @return Divice object
     */
    public function readDevice( $id)
    {
		$data = $this->call(array(), "GET", "devices/".$id.".json");
		if(isset($data->{'device'}))
			$data = $data->{'device'};
		else {
			return NULL;
		}
		if($data == NULL){
			return NULL;
		}
		return new Device($data, $this);
    }

    /**
     * Returns the sensors that are physically connected to the device
     *
     * @access public
     * @param  int id
	 * @param  int page
	 * @param  int perPage
	 * @param  boolean details
     * @return array of Sensor objects
     */
    public function readDeviceSensors($id, $page, $perPage, $details)
    {
		$data = $this->call(array(), "GET", "devices/".$id."/sensors.json");
		$data = $data->{'sensors'};
		$sensorArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$sensorArray->append(new Sensor($data[$i], $this));
		}
		return $sensorArray;
    }

    /**
     * This method creates a new environment. The gps_outline field should contain a list of latitude longitude points describing the outline of the environment.The list of points should create a polygon. The latitude longitude coordinates are separated by a space and each tuple by a comma. Optionally a third coordinate altitude can be specified after the longitude separated by a space. The gps_outline field can have 8000 characters. The position field should be the center of the environment which is also a gps point in the order latitude longitude altitude separated by spaces. The field floors indicates the amount of floors the environment has.
     *
     * @access public
     * @param  string name
     * @param  int floors
     * @param  string gps_outlines
     * @param  string position
     * @return mixed
     */
    public function addEnvironment($name, $floors, $gps_outlines, $position)
    {
		$data = $this->call(array("name"=>$name, "floors"=>$floors, "gps_outline"=>$gps_outlines, "position"=>$position), "POST", "environments.json");
		return $data;
    }

    /**
     * This method deletes an environment.
     *
     * @access public
     * @param  int id
     * @return mixed
     */
    public function deleteEnvironment( $id)
    {
		$data = $this->call(array(), "DELETE", "environments/".$id.".json");
		return $data;
    }

    /**
     * This method returns a list of environments of the current user.
     *
     * @access public
     * @return Array
     */
    public function listEnvironments()
    {
		$data = $this->call(array(), "GET", "environments.json");
		return $data;
		
		$data = $this->call(array(), "GET", "environments.json");
		$data = $data->{'environments'};
		$environmentArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$environmentArray->append(new Environment($data[$i], $this));
		}
		return $environmentArray;
    }

    /**
     * The method returns the details of the selected environment
     *
     * @access public
     * @param  int id
     * @return Environment
     */
    public function readEnvironment($id)
    {
		$data = $this->call(array(), "GET", "environments/".$id.".json");
		$data = $data->{'environment'};
		return new Environment($data, $this);
    }

    /**
     * This method updates an environment. Only the fields that are send will be updated.
     *
     * @access public
     * @param  int id
     * @param  string name
     * @return mixed
     */
    public function updateEnvironment($id, $name)
    {
		$data = $this->call(array("name"=>$name), "PUT", "environments/".$id.".json");
		return $data;
    }

    /**
     * A list of groups will be returned where the current user is a member of.
     *
     * @access public
     * @return Group Array
     */
    public function listGroups()
    {
		$data = $this->call(array(), "GET", "groups.json");
		$data = $data->{'groups'};
		$groupArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$groupArray->append(new Group($data[$i], $this));
		}
		return $groupArray;
    }

    /**
     * This method will create a group to which the current user will be added. A group can optionally have a username and password which can be used for login. The password must be in md5 format.
     *
     * @access public
     * @param  string email
     * @param  string name
     * @param  string username
     * @param  string password
     * @return JsonObject
     */
    public function createGroup($email, $name, $username, $password)
    {
    	if($password != "")
    		$password = md5($password);
		$data = $this->call(array("group"=>array("email"=>$email, "username"=>$username, "password"=>$password, "name"=>$name)), "POST", "groups.json");
		return $data;
    }

    /**
     * This method returns the details of a group. Only members of a group can see the details of a group.
     *
     * @access public
     * @param  int id
     * @return json object
     */
    public function readGroup($id)
    {
		$data = $this->call(array(), "GET", "groups/".$id.".json");
		$data = $data->{'group'};
		return new Group($data, $this);
    }

    /**
     * This method will update the details of a group. Only the values specified as input will be updates. Every member of the group can update the group details
     *
     * @access public
     * @param  int id
     * @param  string email
     * @param  string username
     * @param  string password
     * @param  string name
     * @return json object
     */
    public function updateGroup($id, $email, $username, $password, $name)
    {
		$data = $this->call(array("email"=>$email, "username"=>$username, "password"=>$password, "name"=>$name), "PUT", "groups/".$id.".json");
		return $data;
    }

    /**
     * This method deletes the group if the group has no other members. If the group has other members then the current user will be removed from the group.
     *
     * @access public
     * @param  int id
     * @return mixed
     */
    public function deleteGroup( $id)
    {
		$data = $this->call(array(), "DELETE", "groups/".$id.".json");
		return $data;
    }

    /**
     * This methods returns the members of the group as a list of users. Only group members can perform this action.
     *
     * @access public
     * @param  int id
     * @return User Array
     */
    public function listUsersOfGroup( $id)
    {
		$data = $this->call(array(), "GET", "groups/".$id."/users.json");
		$data = $data->{'users'};
		$userArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$userArray->append(new User($data[$i], $this));
		}
		return $userArray;
    }

    /**
     * This method will add a user to the group. To add a user at least a username or user_id must be specified. Only members of the group can add a user to the group.
     *
     * @access public
     * @param  int id
     * @param  int userID
     * @param  string userName
     * @return mixed
     */
    public function addUserToGroup($id, $userID, $userName)
    {
		$data = $this->call(array("id"=>$userID, "username"=>$userName), "POST", "groups/".$id."/users.json");
		return $data;
    }

    /**
     * This method returns a list of sensors to which the current user has access.
     *
     * @access public
     * @param  int page
     * @param  int perPage
     * @param  Boolean sharred
     * @param  Boolean owned
     * @param  Boolean physical
     * @param  Boolean details
     * @return mixed
     */
    public function listSensors($page, $perPage, $sharred, $owned, $physical)
    {
    	$parameters = "";
    	if($page != -1){
    		$parameters .= "page=".$page."&";
    	}
		if($perPage != -1){
    		$parameters .= "per_page=".$perPage."&";
    	}
		if($sharred){
    		$parameters .= "shared=1&";
    	}
		if($owned){
    		$parameters .= "owned=1&";
    	}
		if($physical){
    		$parameters .= "physical=1&";
    	}

    	$parameters .= "details=full";

		$data = $this->call(array(), "GET", "sensors.json?".$parameters);
		$data = $data->{'sensors'};
		$sensorArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$sensorArray->append(new Sensor($data[$i], $this));
		}
		return $sensorArray;
    }

    /**
     * This method will create a new sensor. A sensor can optionally have a pager_type which can be 'email' or 'sms'. Based on the pager_type a message with the current sensor value will be send. The data_type of a sensor can either be a value type (e.g. float, string) or json. With a json data_type a data_structure that specifies the structure of the json object is expected.
     *
     * @access public
     * @param  string name
     * @param  string displayName
     * @param  string deviceType
     * @param  string pagerType
     * @param  string dataType
     * @param  string dataStructure
     * @return json object
     */
    public function createSensor( $name, $displayName, $deviceType, $pagerType, $dataType, $dataStructure)
    {
		$data = $this->call(array("sensor"=>array("name"=>$name, "display_name"=>$displayName, "device_type"=>$deviceType, "pager_type"=>$pagerType, "data_type"=>$dataType, "data_structure"=>$dataStructure)), "POST", "sensors.json");
		return $data;
    }

    /**
     * This method will return the details of a sensor.
     *
     * @access public
     * @param  int id
     * @return Sensor
     */
    public function readSensor($id)
    {
		$data = $this->call(array(), "GET", "sensors/".$id.".json");
		$data = $data->{'sensor'};
		return new Sensor($data, $this);
    }

    /**
     * This method will update an existing sensor.
     *
     * @access public
     * @param  int id
     * @param  string name
     * @param  string displayName
     * @param  string deviceType
     * @param  string pagerType
     * @param  string dataType
     * @param  string dataStructure
     * @return mixed
     */
    public function updateSensorDescription($id, $name, $displayName, $deviceType, $pagerType, $dataType, $dataStructure)
    {
		$data = $this->call(array("name"=>$name, "display_name"=>$displayName, "device_type"=>$deviceType, "pager_type"=>$pagerType, "data_type"=>$dataType, "data_structure"=>$dataStructure), "PUT", "sensors/".$id.".json");
		return $data;
    }

    /**
     * This method will delete a sensor. If the current user is the owner of the sensor then the sensor will be removed from the current user and all other users. If the current user is not owner of the sensor then access to the sensor will be removed for this user.
     *
     * @access public
     * @param  int id
     * @return mixed
     */
    public function deleteSensor($id)
    {
		$data = $this->call(array(), "DELETE", "sensors/".$id.".json");
		return $data;
    }

    /**
     * This method will return a list of sensor data. The maximum amount of data points that can be retrieved at once are 1000 items.
     *
     * @access public
     * @param  int id
     * @param  int page
     * @param  int perPage
     * @param  Date startDate
     * @param  Date endDate
     * @param  Date date
     * @param  int next
     * @param  Boolean last
     * @param  string sort
     * @param  Boolean total
     * @return json object (max 1000 items)
     */
    public function listSensorData( $id, $page = -1, $perPage = -1, $startDate = 0, $endDate = 0, $date = 0, $next = 0, $last = 0, $sort = 'DESC', $total = NULL, $interval = 0)
    {
    	$parameters = "";
		if($page != -1){
			$parameters .= "page=".$page."&";
		}
		if($perPage != -1){
			$parameters .= "per_page=".$perPage."&";
		}
		if($startDate != 0){
			$parameters .= "start_date=".$startDate."&";
		}
		if($endDate != 0){
			$parameters .= "end_date=".$endDate."&";
		}
		if($date != 0){
			$parameters .= "date=".$date."&";
		}
		if($next != 0){
			$parameters .= "next=".$next."&";
		}
		if($last != 0){
			$parameters .= "last=".$last."&";
		}
		if($interval != 0){
			$parameters .= "interval=".$interval."&";
		}
		if($total){
			$parameters .= "total=1&";
		}
		
		$parameters .= "sort=".$sort;
			
		$data = $this->call(array(), "GET", "sensors/".$id."/data.json?".$parameters);
		$data = $data->{'data'};
		$dataArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$dataArray->append(new Data($data[$i], $this));
		}
		return $dataArray;
    }

    /**
     * With this method sensor data can be uploaded. The uploaded data can either be a single value or an array.
     *
     * @access public
     * @param  int id
     * @param  string value
     * @param  Date date
     * @return mixed
     */
    public function updateSensorSpecificData( $id, $value, $date)
    {
		$data = $this->call(array("value"=>$value, "date"=>$date), "POST", "sensors/".$id."/data.json");
		return $data;
    }

    /**
     * This method deletes a data point
     *
     * @access public
     * @param  int sensorID
     * @param  int dataID
     * @return mixed
     */
    public function deleteSensorData( $sensorID, $dataID)
    {
		$data = $this->call(array(), "DELETE", "/sensors/".$sensorID."/data/".$dataID.".json");
		return $data;
    }

    /**
     * The response header will contain a location header with the location of the file.
     *
     * @access public
     * @param  int sensorID
     * @param  int dataID
     * @return json object
     */
    public function getFileLocation( $sensorID, $dataID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/data/".$dataID."/file.json");
		return $data;
    }

    /**
     * This method deletes the file that is uploaded and stored under the name given in this sensor data value.
     *
     * @access public
     * @param  int sensorID
     * @param  int dataID
     * @return json object
     */
    public function deleteFile( $sensorID, $dataID)
    {
		$data = $this->call(array(), "DELETE", "sensors/".$sensorID."/data/".$dataID."/file.json");
		return $data;
    }

    /**
     * This method deletes all the uploaded files of the user.
     *
     * @access public
     * @return json object
     */
    public function deleteAllFiles()
    {
		$data = $this->call(array(), "DELETE", "sensors/files.json");
		return $data;
    }

    /**
     * With this method sensor data can be uploaded at once for different sensors. The uploaded data can either be a single value or an array.
     *
     * @access public
     * @param  string json
     * @return json object
     */
    public function uploadSensorData( $json)
    {
		$data = $this->callByJson($json, "POST", "sensors/data.json");
		return $data;
    }

    /**
     * The method returns the details of the environment of this sensor.
     *
     * @access public
     * @param  int id
     * @return mixed
     */
    public function readSensorEnvironment($id)
    {
		$data = $this->call(array(), "GET", "sensors/".$id."/environment.json");
		$data = $data->{'environment'};
		return new Environment($data, $this);
    }

    /**
     * The method adds a sensor to an environment. To connect an individual sensor a sensor object with only the sensor id can be given and to connect a list of sensors a sensors object with an array of sensor ids can be given.
     *
     * @access public
     * @param  int id
     * @param  Array sensorIds
     * @return mixed
     */
    public function addSensorsToEnvironment( $id, $sensorIds)
    {
    	$sensors = array();
    	foreach($sensorIds as $id){
    		$sensors["id"][] = $id;
    	}
		$data = $this->call($sensors, "POST", "environments/".$id."/sensors.json");
		return $data;
    }
	
    /**
     * This method list the sensors which are connected to this environment.
     *
     * @access public
     * @param  int id
     * @return mixed
     */
    public function listEnvironmentSensors($id)
    {
		$data = $this->call(array(), "GET", "environments/".$id."/sensors.json");
		$data = $data->{'sensors'};
		$sensorArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$sensorArray->append(new Sensor($data[$i], $this));
		}
		return $sensorArray;
    }

    /**
     * This method removes the selected sensor from the selected environment.
     *
     * @access public
     * @param  int environmentID
     * @param  int sensorID
     * @return mixed
     */
    public function removeSensorFromEnvironment($environmentID, $sensorID)
    {
		$data = $this->call(array(), "GET", "environments/".$environmentID."/sensors/".$sensorID.".json");
		return $data;
    }

    /**
     * This method returns the details of the device to witch the sensor is connected.
     *
     * @access public
     * @param  int sensorID
     * @return mixed
     */
    public function readParentDevice( $sensorID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/device.json");
		if(isset($data->{'error'})){
			$this->error("No device found!");
			return NULL;
		}
		$data = $data->{'device'};
		return new Device($data, $this);
    }

    /**
     * This method adds a sensor to a device. If the device does not exists then it will be created. Either a device_id or type and uuid combination is needed. The type of the sensor will then be automatically be set to 1.
     *
     * @access public
     * @param  int sensorID
     * @param  int deviceID
     * @param  string type
     * @param  string uuid
     * @return mixed
     */
    public function addToParentDevice( $sensorID, $deviceID, $type, $uuid)
    {
		$data = $this->call(array("id"=>$deviceID, "type"=>$type, "uuid"=>$uuid), "POST", "sensors/".$sensorID."/device.json");
		return $data;
    }

    /**
     * This method will remove a sensor from a device. The type of the device will then automatically be set to 0.
     *
     * @access public
     * @param  int sensorID
     * @return mixed
     */
    public function removeFromParentDevice( $sensorID)
    {
		$data = $this->call(array(), "DELETE", "sensors/".$sensorID."/device.json");
		return $data;
    }

    /**
     * This method will list the users that have access to the sensor.
     *
     * @access public
     * @param  int sensorID
     * @return Array
     */
    public function sharredUsers( $sensorID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/users.json");
		$data = $data->{'users'};
		$userArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$userArray->append(new User($data[$i], $this));
		}
		return $userArray;
    }

    /**
     * This method will add a user to a sensor, giving the user access to the sensor and data. Only the owner of the sensor is able to upload data, mutate sensors and add users to their sensor. To add a user at least a username or user_id must be specified.
     *
     * @access public
     * @param  int sensorID
     * @param  int userID
     * @param  string username
     * @return mixed
     */
    public function addSharredUser( $sensorID, $userID, $username)
    {
		$data = $this->call(array("id"=>$userID, "username"=>$username), "POST", "sensors/".$sensorID."/users.json");
		return $data;
    }

    /**
     * This method removes a users from a sensor, which removes the access to the sensor for this user.
     *
     * @access public
     * @param  int sensorID
     * @param  int userID
     * @return mixed
     */
    public function removeSharredUser( $sensorID, $userID)
    {
		$data = $this->call(array(), "DELETE", "sensors/".$sensorID."/users/".$userID.".json");
		return $data;
    }

    /**
     * This method returns a list of sensors that the sensor with uses.
     *
     * @access public
     * @param  int sensorID
     * @return Array
     */
    public function listConnectedSensors( $sensorID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/sensors.json");
		$data = $data->{'sensors'};
		$sensorArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$sensorArray->append(new Sensor($data[$i], $this));
		}
		return $sensorArray;
    }

    /**
     * This method connects a sensor to the sensor selected with <sensor_id>. The type of the selected sensor will be automatically set to 2 (virtual sensor).
     *
     * @access public
     * @param  int sensorID
     * @param  int connectedSensorID
     * @return mixed
     */
    public function connectSensor( $sensorID, $connectedSensorID)
    {
		$data = $this->call(array("id"=>$connectedSensorID), "POST", "sensors/".$sensorID."/sensors.json");
		return $data;
    }

    /**
     * This method removes a sensor from the parent sensor. If the parent sensor does not have any sensors that it uses, its type will automatically be set to 0. If this parent sensor is also a service, then the connected sensor will also be disconnected from the service.
     *
     * @access public
     * @param  int sensorID
     * @param  int connectedSensor
     * @return mixed
     */
    public function removeConnectedSensor( $sensorID, $connectedSensor)
    {
		$data = $this->call(array(), "DELETE", "sensors/".$sensorID."/sensors/".$connectedSensor.".json");
		return $data;
    }

    /**
     * This method lists all the running services for a sensor. It also lists the data fields of the sensor that are used by each service.
     *
     * @access public
     * @param  int sensorID
     * @return mixed
     */
    public function listRunningServices( $sensorID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/services.json");
		$data = $data->{'services'};
		$serviceArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$serviceArray->append(new Service($data[$i], $this));
		}
		return $serviceArray;
    }

    /**
     * This method lists all the available services for a sensor based on its data fields.
     *
     * @access public
     * @param  int sensorID
     * @return mixed
     */
    public function listAvailableServices( $sensorID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/services/available.json");
		$data = $data->{'services'};
		$serviceArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$serviceArray->append(new Service($data[$i], $this));
		}
		return $serviceArray;
    }

    /**
     * This method connects a sensor to a service. In the POST data a service object is posted with the name of the service that is used. If the id of an existing service object is specified then this sensor will be connected to that service. Otherwise a new service will be created. In the optional array 'data_fields' the data fields of the sensor that should be used by this service can be specified. For every new service a virtual sensor is created. Data send from this service is stored under that virtual sensor. Optionally a sensor object with the name and device_type for the virtual sensor can be posted along with the creation of the service.
     *
     * @access public
     * @param  Interger sensorID
     * @param  string json
     * @return mixed
     */
    public function useService($sensorID, $json)
    {
		$data = $this->callByJson($json, "POST", "sensors/".$sensorID."/services.json");
		return $data;
    }

    /**
     * This method disconnects the parent sensor from the service. The service will be stopped if it's not used by other sensors.
     *
     * @access public
     * @param  int sensorID
     * @param  int serviceID
     * @return mixed
     */
    public function disconnectFromService( $sensorID, $serviceID)
    {
		$data = $this->call(array(), "DELETE", "sensors/".$sensorID."/services/".$serviceID.".json");
		return $data;
    }

    /**
     * This method lists all the available methods of the service selected with . These methods can be accessed to set and retrieve the settings of a service.
     *
     * @access public
     * @param  int sensorID
     * @param  int serviceID
     * @return mixed
     */
    public function listServiceMethods( $sensorID, $serviceID)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/services/".$serviceID."/methods.json");
		return $data;
    }

    /**
     * To retrieve information about a service, one of its 'get_methods' can be accessed by specifying the method name in the request url.
     *
     * @access public
     * @param  int sensorID
     * @param  int serviceID
     * @param  string method
     * @return mixed
     */
    public function runServiceGetMethod( $sensorID, $serviceID, $method)
    {
		$data = $this->call(array(), "GET", "sensors/".$sensorID."/services/".$serviceID."/".$method.".json");
		return $data;
    }

    /**
     * To change specific settings of a service, one of its 'set_methods' can be accessed by specifying the method name in the request url. The parameters for the method are send in a parameters array. The response content is based on the method return type. If the method does not have a return value then it will return an object with result ok if the method succeeds.
     *
     * @access public
     * @param  int sensorID
     * @param  int serviceID
     * @param  string method
     * @param  Array parameters
     * @return mixed
     */
    public function runServiceSetMethod( $sensorID, $serviceID, $method, $parameters)
    {
		$data = $this->call($parameters, "POST", "sensors/".$sensorID."/services/".$serviceID."/".$method.".json");
		return $data;
    }

    /**
     * With this method states can be learned using previously stored data. This method is currently only available for the state_recognition_service and the pose_prediction_service. By giving a class label, start and end date, a state will be learned using the data from all the associated sensors from within the given time range.
     *
     * @access public
     * @param  int SensorID
     * @param  int serviceID
     * @param  Date startData
     * @param  Date endDate
     * @param  string label
     * @return mixed
     */
    public function learnPattern( $SensorID, $serviceID, $startData, $endDate, $label)
    {
    	$parameters = "";
		$parameters .= "start_date=".$startData."&";
		$parameters .= "end_date=".$endDate."&";
		$parameters .= "class_label=".$label;
		
		$data = $this->call(array(), "POST", "sensors/".$SensorID."/services/".$serviceID."/manualLearn.json?".$parameters);
		return $data;
    }

    /**
     * This method lists all the available services for all the sensors. Available services are selected based on their data fields.
     *
     * @access public
     * @return Array
     */
    public function listAllAvailableServices()
    {
		$data = $this->call(array(), "GET", "sensors/services/available.json");
		$data = $data->{'services'};
		$serviceArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$serviceArray->append(new Service($data[$i], $this));
		}
		return $serviceArray;
    }

    /**
     * This method lists all the users in the database with only their user_id, name and surname.
     *
     * @access public
     * @param  int page
     * @param  int perPage
     * @return mixed
     */
    public function listAllUsers( $page, $perPage)
    {
    	$parameters = "";
    	if($page != -1){
    		$parameters .= "page=".$page."&";
    	}
		if($perPage != -1){
    		$parameters .= "per_page=".$perPage;
    	}
		
		$data = $this->call(array(), "GET", "users.json?".$parameters);
		$data = $data->{'users'};
		$userArray = new ArrayObject();
		for($i = 0; $i<count($data);$i++){
			$userArray->append(new User($data[$i], $this));
		}
		return $userArray;
    }

    /**
     * This method will create a user in the database. The username must be unique and the password must be a md5 hashed password. The response content will be contain the created user information. The uuid is a uniquely generated id which can be used to retrieve data without logging in.
     *
     * @access public
     * @param  string email
     * @param  string username
     * @param  string name
     * @param  string surname
     * @param  string mobile
     * @param  string password
     * @return mixed
     */
    public function createUser( $email, $username, $name, $surname, $mobile, $password)
    {
		$data = $this->call(array("email"=>$email, "username"=>$username, "name"=>$name, "surname"=>$surname, "mobile"=>$mobile, "password"=>md5($password)), "POST", "users.json");
		return $data;
    }

    /**
     * This method returns the details of the selected user. Only the current user can be selected. This method gives the same output as /users/current
     *
     * @access public
     * @param  int userID
     * @return mixed
     */
    public function readUser( int $userID)
    {
		$data = $this->call(array(), "GET", "users/".$userID.".json");
		$data = $data->{'user'};
		return new User($data, $this);
    }

    /**
     * This method updates the details of the user. Only the user_id of the current user can be selected.
     *
     * @access public
     * @param  int userID
     * @param  string email
     * @param  string username
     * @param  string name
     * @param  string surname
     * @param  string mobile
     * @param  string password
     * @return mixed
     */
    public function updateUser( $userID, $email, $username, $name, $surname, $mobile, $password)
    {
		$data = $this->call(array("email"=>$email, "username"=>$username, "name"=>$name, "surname"=>$surname, "mobile"=>$mobile, "password"=>md5($password)), "PUT", "users/".$userID.".json");
		return $data;
    }

    /**
     * This method will remove the user from the database together with his external services.
     *
     * @access public
     * @param  int userID
     * @return mixed
     */
    public function deleterUser( $userID)
    {
		$data = $this->call(array(), "DELETE", "users/".$userID.".json");
		return $data;
    }

    /**
     * This method returns the details of the current user.
     *
     * @access public
     * @return User
     */
    public function readCurrentUser()
    {
		$data = $this->call(array(), "GET", "users/current.json");
		if(isset($data->{'error'})){
			die($data->{'error'});
		}
		$data = $data->{'user'};
		return new User($data, $this);
    }

} /* end of class Api */

?>