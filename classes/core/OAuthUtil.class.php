<?php 

class OAuthUtil {
	
	/**
	 * 
	 * @param mixed $input
	 * 
	 * @return
	 */
	public static function urlEncodeRfc3986($input) {
		if (is_array($input)) {
			return array_map(array("OAuthUtil", "urlEncodeRfc3986"), $input);
		} else if (is_scalar($input)) {
			return str_replace(
		      	"+",
      			" ",
				str_replace("%7E", "~", rawurlencode($input))
			);
		} else {
			return "";
		}
	}

	/**
	 * This decode function isn't taking into consideration the above
	 * modifications to the encoding process. However, this method doesn't
	 * seem to be used anywhere so leaving it as is.
	 * 
	 * @param string $string
	 * 
	 * @return
	 */
	public static function urlDecodeRfc3986($string) {
		return urldecode($string);
	}

	/**
	 * Utility function for turning the Authorization: header into
	 * parameters, has to do some unescaping.
	 * Can filter out any non-oauth parameters if needed (default behaviour).
	 * 
	 * @param unknown_type $header
	 * @param boolean $onlyAllowOauthParameters
	 * 
	 * @return
	 */
	public static function splitHeader($header, $onlyAllowOauthParameters = true) {
		$pattern = '/(([-_a-z]*)=("([^"]*)"|([^,]*)),?)/';
		$offset = 0;
		$params = array();
		
		while (preg_match($pattern, $header, $matches, PREG_OFFSET_CAPTURE, $offset) > 0) {
			$match = $matches[0];
			$header_name = $matches[2][0];
			$header_content = (isset($matches[5])) ? $matches[5][0] : $matches[4][0];
			
			if (preg_match("/^oauth_/", $header_name) || !$onlyAllowOauthParameters) {
				
				$header_content = str_replace("\\\"", "", $header_content);
				$params[$header_name] = OAuthUtil::urlDecodeRfc3986($header_content);				
			}
			
			$offset = $match[1] + strlen($match[0]);
		}

		if (isset($params["realm"])) {
			unset($params["realm"]);
		}

		return $params;
	}


	/**
	 * Helper to try to sort out headers for people who aren't running apache.
	 * 
	 * @return
	 */
	public static function getHeaders() {
		$filter = new InputFilter();
				
		if (function_exists("apache_request_headers")) {
			// we need this to get the actual Authorization: header
			// because apache tends to tell us it doesn't exist
			$headers = apache_request_headers();
			
			// Sanitize headers.
			$headers = $filter->process($headers);
			$headers = filter_var_array($headers, FILTER_SANITIZE_STRING | 
				FILTER_SANITIZE_MAGIC_QUOTES);

			// sanitize the output of apache_request_headers because
			// we always want the keys to be Cased-Like-This and arh()
			// returns the headers in the same case as they are in the
			// request
			$out = array();
			foreach( $headers as $key => $value ) {
				$key = str_replace(
		            " ",
		            "-",
					ucwords(strtolower(str_replace("-", " ", $key)))
				);
				$out[$key] = $value;
			}
		} else {
			//syslog(LOG_ALERT, "APACHE_REQUEST_HEADERS doesn't exist.");
				
			// otherwise we don't have apache and are just going to have to 
			// hope that $_SERVER actually contains what we need
			$out = array();
			
			// Filter the content-type header.
			if(isset($_SERVER["CONTENT_TYPE"]))
				$out["Content-Type"] = $filter->process($_SERVER["CONTENT_TYPE"]);
				
			if( isset($_ENV["CONTENT_TYPE"]) )
				$out["Content-Type"] = $filter->process($_ENV["CONTENT_TYPE"]);

			// Sanitize the server variables.
			$serverVars = $filter->process($_SERVER);	
			
			foreach ($serverVars as $key => $value) {
				if (substr($key, 0, 5) == "HTTP_") {
					// this is chaos, basically it is just there to capitalize 
					// the first letter of every word that is not an initial 
					// HTTP and strip HTTP code from przemek
					$key = str_replace(
			            " ",
			            "-",
						ucwords(strtolower(str_replace("_", " ", 
							substr($key, 5))))
					);
					$out[$key] = $value;
				}
			}
		}
		return $out;
	}
 
	/**
	 * This function takes a input like a=b&a=c&d=e and returns the parsed
	 * parameters like this:
	 * 
	 * 		array('a' => array('b','c'), 'd' => 'e')
	 * 
	 * @param string $input
	 * 
	 * @return array
	 */
	public static function parseParameters($input) {
		if (!isset($input) || !$input) return array();

		$input = filter_var($input, FILTER_SANITIZE_STRING | FILTER_SANITIZE_MAGIC_QUOTES);
		
		$pairs = explode("&", $input);

		$parsedParameters = array();
		foreach ($pairs as $pair) {
			$split = explode("=", $pair, 2);
			$parameter = OAuthUtil::urlDecodeRfc3986($split[0]);
			$value = isset($split[1]) ? 
				OAuthUtil::urlDecodeRfc3986($split[1]) : "";

			if (isset($parsedParameters[$parameter])) {
				// We have already recieved parameter(s) with this name, so add
				// to the list of parameters with this name
				if (is_scalar($parsedParameters[$parameter])) {
					// This is the first duplicate, so transform scalar 
					// (string) into an array so we can add the duplicates
					$parsedParameters[$parameter] = 
						array($parsedParameters[$parameter]);
				}

				$parsedParameters[$parameter][] = $value;
			} else {
				$parsedParameters[$parameter] = $value;
			}
		}
		return $parsedParameters;
	}

	/**
	 * 
	 * @param unknown_type $params
	 * 
	 * @return
	 */
	public static function buildHttpQuery($params) {
		if (!$params) 
			return "";

		// Urlencode both keys and values
		$keys = OAuthUtil::urlEncodeRfc3986(array_keys($params));
		$values = OAuthUtil::urlEncodeRfc3986(array_values($params));
		$params = array_combine($keys, $values);

		// Parameters are sorted by name, using lexicographical byte value 
		// ordering. Ref: Spec: 9.1.1 (1)
		uksort($params, "strcmp");

		$pairs = array();
		foreach ($params as $parameter => $value) {
			if (is_array($value)) {
				// If two or more parameters share the same name, they are 
				// sorted by their value. Ref: Spec: 9.1.1 (1)				
				natsort($value);
				foreach ($value as $duplicate_value) {
					$pairs[] = $parameter . "=" . $duplicate_value;
				}
			} else {
				$pairs[] = $parameter . "=" . $value;
			}
		}
		// For each parameter, the name is separated from the corresponding 
		// value by an "=" character (ASCII code 61)
		// Each name-value pair is separated by an "&" character (ASCII code 
		// 38)
		return implode("&", $pairs);
	}
	
	/**
	 * util function: current timestamp
	 * 
	 * @return
	 */
	public static function generateTimestamp() {
		return time();
	}

	/**
	 * util function: current nonce
	 */
	public static function generateNonce() {
		$mt = microtime();
		$rand = mt_rand();
		return md5($mt . $rand); // md5s look nicer than numbers
	}

	/**
	 * Util method to generate a key and a secret.
	 * @return array 
	 */
	public static function generateKeyAndSecret() {
		$fp = fopen("/dev/urandom", "rb");
		$entropy = fread($fp, 42);
		fclose($fp);
	
		// In case /dev/urandom is reusing entropy from its pool, let's add a
		// bit more entropy.

		$entropy .= uniqid(mt_rand(), true);
		$hash = sha1($entropy);  // sha1 gives us a 40-byte hash
		
		// The first 20 bytes should be plenty for the consumer_key.
		// We use the last 20 for the shared secret.
		
		// base64 is used just to randomize a little more the previously 
		// generated hash. 
		
		$appCredentials = array(
			"key" => rtrim(base64_encode(substr($hash, 0, 20)), "="),
			"secret" => rtrim(base64_encode(substr($hash, 20, 20)), "=")
		);

		return $appCredentials;
	}

	/**
	 * Util method to generate the verification code.
	 * @return string
	 */
	public static function generateVerifier() {
		$fp = fopen("/dev/urandom", "rb");
		$entropy = fread($fp, 42);
		fclose($fp);
	
		// In case /dev/urandom is reusing entropy from its pool, let's add
		// a bit more entropy.

		$entropy .= uniqid(mt_rand(), true);
		$hash = sha1($entropy);  // sha1 gives us a 40-byte hash

		//return rtrim(base64_encode(substr($hash, 0, 20)), "=");
		return substr($hash, 0, 20);
	}	
	
}

?>