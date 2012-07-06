<?php 

require_once("OAuthException.class.php");

class OAuthConsumer {
	
	public $key;
	public $secret;
	public $callbackUrl;
	public $name;

	function __construct($key, $secret = "", $callbackUrl = "", $name = null) {
		$this->key = $key;
		$this->secret = $secret;
		$this->callbackUrl = $callbackUrl;
		$this->name = $name;
	}

	function __toString() {
		return "OAuthConsumer[key=$this->key,secret=$this->secret]";
	}
}

?>