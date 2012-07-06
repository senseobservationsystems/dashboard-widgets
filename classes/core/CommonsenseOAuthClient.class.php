<?php
/**
 * This class is based on the Abraham Williams' TwitterOAuth class 
 * (the first PHP Library to support OAuth for Twitter's REST API).
 * Abraham Williams (abraham@abrah.am)
 */

require_once("OAuthUtil.class.php");
require_once("OAuthConsumer.class.php");
require_once("OAuthToken.class.php");
require_once("OAuthRequest.class.php");
require_once("signature/OAuthSignatureMethodHmacSha1.class.php");


class CommonsenseOauthClient {
	/* Contains the last HTTP status code returned. */
	public $httpCode;
	/* Contains the last API call. */
	public $url;
	/* Set up the API root URL. */
	// @@ public $host = "https://api.twitter.com/1/";
	public $host;
	public $requestTokenUrl;
	public $authorizeUrl;
	public $accessTokenUrl;

	/* Set timeout default. */
	public $timeout = 30;
	/* Set connect timeout. */
	public $connectTimeout = 30;
	/* Verify SSL Cert. */
	public $sslVerifyPeer = true;
	/* Respons format. */
	public $format = "json";
	/* Decode returned json data. */
	public $decodeJson = true;
	/* Contains the last HTTP headers returned. */
	public $httpInfo;
	/* http header */
	public $httpHeader;
	/* Set the useragnet. */
	public $userAgent = "CSenseOAuthClient v0.1.4";
	/* Immediately retry the API call if the response was not successful. */
	//public $retry = true;
	public $token;

	/**
	 * Set API URLS
	 */
	function requestTokenURL() { return $this->requestTokenUrl; }	
	function accessTokenURL()  { return $this->accessTokenUrl; }
	function authorizeURL()    { return $this->authorizeUrl; }

	/**
	 * Debug helpers
	 */
	function lastStatusCode() { return $this->httpStatus; }
	function lastAPICall() { return $this->lastApiCall; }

	/**
	 * construct TwitterOAuth object
	 * 
	 * @param string $consumerkey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 */
	function __construct(
		$consumerkey, 
		$consumerSecret, 
		$oauthToken = NULL, 
		$oauthTokenSecret = NULL) 
	{

		$this->host = "http://api.sense-os.nl/";
		$this->requestTokenUrl = "http://api.sense-os.nl/oauth/request_token";
		$this->authorizeUrl = "http://api.sense-os.nl/oauth/authorize";
		$this->accessTokenUrl = "http://api.sense-os.nl/oauth/access_token";

		$this->sha1Method = new OAuthSignatureMethodHmacSha1();
		$this->consumer = new OAuthConsumer($consumerkey, $consumerSecret);

		if (!empty($oauthToken) && !empty($oauthTokenSecret)) {
			$this->token = new OAuthToken($oauthToken, $oauthTokenSecret);
		} else {
			$this->token = NULL;
		}
	}

	public function setResponseFormat($format) {
		$this->format = $format;
	}

	/**
	 * Get a request_token from Twitter
	 *
	 * @return a key/value array containing oauth_token and oauth_token_secret
	 */
	function getRequestToken($oauthCallback = NULL) {
		$parameters = array();
		if (!empty($oauthCallback)) {
			$parameters["oauth_callback"] = $oauthCallback;
		}

		$request = $this->oAuthRequest($this->requestTokenURL(), "GET", $parameters);

		$token = OAuthUtil::parseParameters($request);

		$this->token = new OAuthToken($token["oauth_token"], $token["oauth_token_secret"]);
		return $token;
	}

	/**
	 * Get the authorize URL
	 *
	 * @returns a string
	 */
	//function getAuthorizeURL($token, $sign_in_with_twitter = true) {
	function getAuthorizeURL($token, $sid="", $sessionid="") {
		if (is_array($token)) {
			$token = $token["oauth_token"];
		}
		
		$sidParam = ($sid != "") ? "&sid={$sid}" : "";
		$sidParam .= ($sessionid != "") ? "&session_id={$sessionid}" : "";
		
		return $this->authorizeURL() . "?oauth_token=" . $token . $sidParam;
	}

	/**
	 * Exchange request token and secret for an access token and
	 * secret, to sign API calls.
	 *
	 * @returns array("oauth_token" => "the-access-token",
	 *                "oauth_token_secret" => "the-access-secret",
	 *                "user_id" => "9436992",
	 *                "screen_name" => "abraham")
	 */
	function getAccessToken($request) {
		//$parameters = array();
		
		//if (!empty($request["oauth_verifier"])) {
		if (isset($request["oauth_verifier"]) && 
			isset($request["oauth_token"])) 
		{
			$response = $this->oAuthRequest(
				$this->accessTokenURL(), 
				"GET", 
				//$parameters
				$request
			);
			
			//syslog(LOG_DEBUG, "OAUTH RESP: ".$response);
			
			// Parse the OAuth Provider's reponse to retrieve the access token
			// key and secret.
			$token = OAuthUtil::parseParameters($response);
			
			if (!is_array($token)) {
				//syslog(LOG_ERR, "OAUTH ERROR: it couldn't be retrieved the access credentials");
				throw new Exception("OAuth ERROR: it couldn't be retrieved the access credentials");
			}
			
			$this->token = new OAuthConsumer(
				$token["oauth_token"], 
				$token["oauth_token_secret"]
			);
			
			return $token;
		}
		
		//syslog(LOG_ERR, "OAUTH ERROR: emtpy oauth_verifier or oauth_token.");
		
		// @@ TODO: I should throw an exception at this point with a proper
		// oauth error description.		
		throw new Exception("OAUTH ERROR: emtpy oauth_verifier or oauth_token.");
	}

	/**
	 * One time exchange of username and password for access token and secret.
	 *
	 * @returns array("oauth_token" => "the-access-token",
	 *                "oauth_token_secret" => "the-access-secret",
	 *                "user_id" => "9436992",
	 *                "screen_name" => "abraham",
	 *                "x_auth_expires" => "0")
	 */
	function getXAuthToken($username, $password) {
		$parameters = array();
		$parameters["x_auth_username"] = $username;
		$parameters["x_auth_password"] = $password;
		$parameters["x_auth_mode"] = "client_auth";
		$request = $this->oAuthRequest($this->accessTokenURL(), "POST", $parameters);
		$token = OAuthUtil::parseParameters($request);
		$this->token = new OAuthConsumer($token["oauth_token"], $token["oauth_token_secret"]);
		return $token;
	}

	/** 
	 * GET wrapper for oAuthRequest.
	 * 
	 * @param string_type $url
	 * @param array $parameters
	 * 
	 * @return
	 */
	function get($url, $parameters = array()) {
		$uri = $this->host . $url;
		$response = $this->oAuthRequest($uri, "GET", $parameters);
		if ($this->format === "json" && $this->decodeJson) {
			return json_decode($response);
		}
		return $response;
	}
	

	/** 
	 * POST wrapper for oAuthRequest.
	 * 
	 * @param string $url
	 * @param array $parameters
	 * 
	 * @return
	 */
	function post($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, "POST", $parameters);
		if ($this->format === "json" && $this->decodeJson) {
			return json_decode($response);
		}
		return $response;
	}

	/** 
	 * DELETE wrapper for oAuthRequest.
	 * 
	 * @param string $url
	 * @param array $parameters
	 */
	function delete($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, "DELETE", $parameters);
		if ($this->format === "json" && $this->decodeJson) {
			return json_decode($response);
		}
		return $response;
	}

	/** 
	 * Format and sign an OAuth / API request.
	 * 
	 * @param string $url
	 * @param string $method
	 * @param array $parameters
	 * 
	 * @return
	 */
	function oAuthRequest($url, $method, $parameters) {
		/*
		 if (strrpos($url, "https://") !== 0 && strrpos($url, "http://") !== 0) {
		 // @@ commented to work with commeonsense.
		 //$url = "{$this->host}{$url}.{$this->format}";
		 $url = "{$this->host}{$url}";
		 }
		 */
		$request = OAuthRequest::fromConsumerAndToken(
			$this->consumer, 
			$this->token, 
			$method, 
			$url, 
			$parameters
		);
		
		$request->signRequest($this->sha1Method, $this->consumer, $this->token);

		switch ($method) {
		case "GET":
			return $this->http($request->toUrl(), "GET");
			
		default:
			return $this->http(
				$request->getNormalizedHttpUrl(), 
				$method, 
				$request->toPostData()
			);
		}
	}

	/**
	 * Make an HTTP request
	 *
	 * @param string $url
	 * @param string $method
	 * @param boolean $postFields
	 * 
	 * @return API results
	 */
	function http($url, $method, $postFields = NULL) {
		$this->httpInfo = array();
		$ci = curl_init();
		
		// Curl settings
		curl_setopt($ci, CURLOPT_USERAGENT, $this->userAgent);
		curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ci, CURLOPT_HTTPHEADER, array("Expect:"));
		// @@ FIXME: Verifierpeer should be true. It has been set to false
		// just to test the oauth communcation with wireshark.
		//curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->sslVerifyPeer);
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, "getHeader"));
		curl_setopt($ci, CURLOPT_HEADER, false);

		switch ($method) {
		case "POST":
			curl_setopt($ci, CURLOPT_POST, true);
			if (!empty($postFields)) {
				curl_setopt($ci, CURLOPT_POSTFIELDS, $postFields);
			}
			break;
			
		case "DELETE":
			curl_setopt($ci, CURLOPT_CUSTOMREQUEST, "DELETE");
			if (!empty($postFields)) {
				$url = "{$url}?{$postFields}";
			}
		}

		curl_setopt($ci, CURLOPT_URL, $url);
		$response = curl_exec($ci);

		//syslog(LOG_INFO, "OAUTH_RESP: ".$response);

		$this->httpCode = curl_getinfo($ci, CURLINFO_HTTP_CODE);
		$this->httpInfo = array_merge($this->httpInfo, curl_getinfo($ci));
		$this->url = $url;

		curl_close($ci);

		return $response;
	}

	/**
	 * Get the header info to store.
	 *
	 * @param string $header
	 */
	function getHeader($ch, $header) {
		$i = strpos($header, ":");
		
		if (!empty($i)) {
			$key = str_replace("-", "_", strtolower(substr($header, 0, $i)));
			$value = trim(substr($header, $i + 2));
			$this->httpHeader[$key] = $value;
		}
		
		return strlen($header);
	}
}
