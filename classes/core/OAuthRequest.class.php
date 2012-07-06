<?php

require_once("OAuthUtil.class.php");

class OAuthRequest {

	private $parameters;
	private $httpMethod;
	private $httpUrl;
	// for debug purposes
	public $baseString;
	public static $version = "1.0";
	public static $POST_INPUT = "php://input";

	function __construct($httpMethod, $httpUrl, $parameters = null) {
		@$parameters or $parameters = array();
		
		$parameters = array_merge(
			OAuthUtil::parseParameters(parse_url($httpUrl, PHP_URL_QUERY)), 
			$parameters
		);

		// Sanitize the input data.		
		$this->parameters = $parameters;
		
		$this->httpMethod = $httpMethod;
		$this->httpUrl = $httpUrl;
	}


	/**
	 * Creates an OAuthRequest from the request passed to the server.
	 * 
	 * attempt to build up a request from what was passed to the server
	 * 
	 * @return OAuthRequest
	 */
	public static function fromRequest($httpMethod = null, $httpUrl = null, $parameters = null) {
		$scheme = (!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on")
					? "http"
					: "https";
					
		@$httpUrl or $httpUrl = $scheme .
        			"://" . $_SERVER["HTTP_HOST"] .
                    ":" .
					$_SERVER["SERVER_PORT"] .
					$_SERVER["REQUEST_URI"];

		@$httpMethod or $httpMethod = $_SERVER["REQUEST_METHOD"];

		// We weren't handed any parameters, so let's find the ones relevant to
		// this request.
		// If you run XML-RPC or similar you should use this to provide your own
		// parsed parameter-list
		if (!$parameters) {
			// Find request headers
			$requestHeaders = OAuthUtil::getHeaders();

			// Parse the query-string to find GET parameters
			$parameters = OAuthUtil::parseParameters($_SERVER["QUERY_STRING"]);

			// It's a POST request of the proper content-type, so parse POST
			// parameters and add those overriding any duplicates from GET
			if ($httpMethod == "POST"
			&& @strstr($requestHeaders["Content-Type"],
                     "application/x-www-form-urlencoded")) 
			{
				$postData = OAuthUtil::parseParameters(
					file_get_contents(self::$POST_INPUT)
				);
				$parameters = array_merge($parameters, $postData);
			}

			// We have a Authorization-header with OAuth data. Parse the header
			// and add those overriding any duplicates from GET or POST
			if (@substr($requestHeaders["Authorization"], 0, 6) == "OAuth ") {
				$headerParameters = OAuthUtil::splitHeader(
					$requestHeaders["Authorization"]
				);
				$parameters = array_merge($parameters, $headerParameters);				
			}

		}

		return new OAuthRequest($httpMethod, $httpUrl, $parameters);
	}

	/**
	 * pretty much a helper function to set up the request
	 */
	public static function fromConsumerAndToken($consumer, $token, $httpMethod, $httpUrl, $parameters = null) {
		@$parameters or $parameters = array();

		$defaults = array(
			"oauth_version" => OAuthRequest::$version,
            "oauth_nonce" => OAuthRequest::generateNonce(),
            "oauth_timestamp" => OAuthRequest::generateTimestamp(),
            "oauth_consumer_key" => $consumer->key
		);		
		if ($token)
			$defaults["oauth_token"] = $token->key;

		$parameters = array_merge($defaults, $parameters);

		return new OAuthRequest($httpMethod, $httpUrl, $parameters);
	}

	public function setParameter($name, $value, $allow_duplicates = true) {
		if ($allow_duplicates && isset($this->parameters[$name])) {
			// We have already added parameter(s) with this name, so add to the list
			if (is_scalar($this->parameters[$name])) {
				// This is the first duplicate, so transform scalar (string)
				// into an array so we can add the duplicates
				$this->parameters[$name] = array($this->parameters[$name]);
			}

			$this->parameters[$name][] = $value;
		} else {
			$this->parameters[$name] = $value;
		}
	}

	public function getParameter($name) {
		return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
	}

	public function getParameters() {
		return $this->parameters;
	}

	public function unsetParameter($name) {
		unset($this->parameters[$name]);
	}

	/**
	 * The request parameters, sorted and concatenated into a normalized string.
	 * @return string
	 */
	public function getSignableParameters() {
		// Grab all parameters
		$params = $this->parameters;

		// Remove oauth_signature if present
		// Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
		if (isset($params["oauth_signature"])) {
			unset($params["oauth_signature"]);
		}

		return OAuthUtil::buildHttpQuery($params);
	}

	/**
	 * Returns the base string of this request.
	 *
	 * The base string defined as the method, the url and the parameters 
	 * (normalized), each urlencoded and concated with &.
	 */
	public function getSignatureBaseString() {
		$parts = array(
			$this->getNormalizedHttpMethod(),
			$this->getNormalizedHttpUrl(),
			$this->getSignableParameters()
		);

		$parts = OAuthUtil::urlEncodeRfc3986($parts);

		return implode("&", $parts);
	}

	/**
	 * just uppercases the http method
	 */
	public function getNormalizedHttpMethod() {
		return strtoupper($this->httpMethod);
	}

	/**
	 * parses the url and rebuilds it to be
	 * scheme://host/path
	 */
	public function getNormalizedHttpUrl() {
		$parts = parse_url($this->httpUrl);

		$port = @$parts["port"];
		$scheme = $parts["scheme"];
		$host = $parts["host"];
		$path = @$parts["path"];

		$port or $port = ($scheme == "https") ? "443" : "80";

		if (($scheme == "https" && $port != "443")
		|| ($scheme == "http" && $port != "80")) 
		{
			$host = "$host:$port";
		}
		return "$scheme://$host$path";
	}

	/**
	 * builds a url usable for a GET request
	 */
	public function toUrl() {
		$postData = $this->toPostData();
		$out = $this->getNormalizedHttpUrl();
		if ($postData) {
			$out .= "?".$postData;
		}
		return $out;
	}

	/**
	 * builds the data one would send in a POST request
	 */
	public function toPostData() {
		return OAuthUtil::buildHttpQuery($this->parameters);
	}

	/**
	 * builds the Authorization: header
	 */
	public function toHeader($realm=null) {
		$first = true;
		if($realm) {
			$out = 'Authorization: OAuth realm="' . OAuthUtil::urlEncodeRfc3986($realm) . '"';
			$first = false;
		} else {
			$out = "Authorization: OAuth";
		}

		$total = array();
		foreach ($this->parameters as $k => $v) {
			if (substr($k, 0, 5) != "oauth") continue;
			
			if (is_array($v)) {
				// $k is the oauth parameter name.
				throw new OAuthException(OAuthError::PARAMETER_REJECTED, 400, 
					"Multiple values in $k are not supported.", $k);
			}
			
			$out .= ($first) ? " " : ",";			
			$out .= OAuthUtil::urlEncodeRfc3986($k) . '="' .
					OAuthUtil::urlEncodeRfc3986($v) . '"';
					
			$first = false;
		}
		return $out;
	}

	public function __toString() {
		return $this->toUrl();
	}


	public function signRequest($signatureMethod, $consumer, $token) {
		$this->setParameter(
	      	"oauth_signature_method",
			$signatureMethod->getName(),
			false
		);
		$signature = $this->buildSignature($signatureMethod, $consumer, $token);
		$this->setParameter("oauth_signature", $signature, false);
	}

	public function buildSignature($signatureMethod, $consumer, $token) {
		$signature = $signatureMethod->buildSignature($this, $consumer, $token);
		return $signature;
	}

	/**
	 * util function: current timestamp
	 */
	private static function generateTimestamp() {
		return time();
	}

	/**
	 * util function: current nonce
	 */
	private static function generateNonce() {
		$mt = microtime();
		$rand = mt_rand();
		return md5($mt . $rand); // md5s look nicer than numbers
	}
}

?>