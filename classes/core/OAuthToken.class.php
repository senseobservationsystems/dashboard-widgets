<?php 

require_once("OAuthUtil.class.php");

class OAuthToken {
	// access tokens and request tokens
	public $key;
	public $secret;
	public $expiration;

	/**
	 * Constructor. 
	 * 
	 * @param string $key
	 * @param string $secret
	 * @param string $expiration
	 */
	function __construct($key, $secret, $expiration = "") {
		$this->key = $key;
		$this->secret = $secret;
		$this->expiration = $expiration;
	}

	/**
	 * generates the basic string serialization of a token that a server
	 * would respond to request_token and access_token calls with
	 */
	function toString() {
		return "oauth_token=" .
		OAuthUtil::urlEncodeRfc3986($this->key) .
           "&oauth_token_secret=" .
		OAuthUtil::urlEncodeRfc3986($this->secret);
	}

	function __toString() {
		return $this->toString();
	}
}

?>