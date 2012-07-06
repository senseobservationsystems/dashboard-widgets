<?php 

require_once("OAuthError.class.php");
require_once("OAuthException.class.php");
require_once("OAuthResponse.class.php");
require_once("common/ErrorHandler.class.php");


class OAuthProvider {
	
	// Used to check if timestamp is recentish.
	protected $timestampThreshold = 300; // 5 mins in seconds
	protected $version = "1.0";
	protected $signatureMethods = array();
	protected $ds; // data store
	private $request;
	
	const TOKEN_EXPIR_NEVER = -1;

	/**
	 * Constructor.
	 * 
	 * @param OAuthRequest $request
	 * @param string $requestToCheck (consumer | authorize | request | access)
	 * @throws OAuthException
	 */
	public function __construct(&$request, $requestToCheck) {
		$this->request = $request;

		try {
			// Check required parameters according to the oauth step.		
			$tokenType = $this->checkOAuthRequest($requestToCheck);
			
			// The data store is smart enough to know how to fill its fields
			// (request_token or access_token) with the values given in the 
			// request.
			
			$oauthParams = $request->getParameters();
			$this->ds = new OAuthDataStore($oauthParams, $tokenType);

			// In the API consumption step is checked if the user has granted
			// the access to the consumer application.
			
			if ($requestToCheck == "access")
				$this->isConsumerAllowed();
			
		} catch (DbException $e) {
			$this->dbErrorHandler($e);
		}
	}
	
	/**
	 * Check every OAuth request according to the OAuth step being executed.
	 * Return the token type.
	 * 
	 * @param unknown_type $requestToCheck
	 * @throws OAuthException
	 * @return string (null | request | access)
	 */
	private function checkOAuthRequest($requestToCheck) {
		switch ($requestToCheck) {
			
		case "consumer": 
			// 1st step: consumer credential is checked.
			$this->checkConsumerRequest();
			break;
				
		case "authorize": 
			// 2nd step: Check if temporary credential (oauth_token) exists
			// and request the user to aprove the access for the consumer 
			// app.
			$this->checkAuthorizationRequest();				
			
			// Check if the user has approved the third app access.
			$this->checkUserPermission();

			break;
				
		case "request": 
			// 3rd oauth step: in the access token step, the temporary
			// credential is checked.
			$this->checkTokenRequest();
			return "request";			
				
		case "access":
			// last step: in the resource consumption step, the access 
			// token credential is checked.
			$this->checkAccessRequest();
			return "access";
			
		default:
			throw new OAuthException(OAuthError::ERROR_UNKNOWN, 500, 
				"OAuth step unknown.");
		}
		
		return null;
	}
	
	/**
	 * Throw an exception according to the exception code.
	 *  
	 * @param OAuthException $e
	 * @throws OAuthException
	 */
	private static function dbErrorHandler($e) {
		switch ($e->getCode()) {
			
		case OAuthDataStore::ERR_NO_CONSUMER:			
			// The consumer key and secret don't exist in the db. This problem 
			// might be caused due to the developer didn't generate the 
			// consumer credential.
			throw new OAuthException(OAuthError::CONSUMER_KEY_UNKNOWN, 400,
				"Consumer not registered.");					
					
		case OAuthDataStore::ERR_NO_AUTHORIZED_CONSUMER:			
			throw new OAuthException(OAuthError::PERMISSION_DENIED, 401,
				"Consumer not authorized.");

		case OAuthDataStore::ERR_NO_TOKEN:
			// The token doesn't exist in the db.
			throw new OAuthException(OAuthError::TOKEN_REJECTED, 400, 
				$e->getMessage());
				
		case OAuthDataStore::ERR_NO_CALLBACK:
			// The request token is invalid, so the callback url can't be 
			// retrieved.
			throw new OAuthException(OAuthError::TOKEN_REJECTED, 400, 
				"The request token key is invalid.");
				
		default:
			throw new OAuthException(OAuthError::DB_ERROR, 500, $e->getMessage());
		}
	}

	/**
	 * Add a signature method.
	 * 
	 * @param OAuthSignatureMethod $signatureMethod
	 */
	public function addSignatureMethod($signatureMethod) {
		$this->signatureMethods[$signatureMethod->getName()] = $signatureMethod;
	}

	/**
	 * Process a request_token request.
	 * 
	 * @return OAuthToken
	 */
	public function fetchRequestToken() {
		$this->getVersion();
		$consumer = $this->getConsumer();

		// No token required for the initial token request.
		$this->checkSignature(null);
		
		// Rev A change.
		$callback = $this->request->getParameter("oauth_callback");	
		$newToken = $this->newRequestToken($consumer, $callback);
		
		// Insert the nonce for the first time. 
		$this->ds->insertNonce(
			$this->request->getParameter("oauth_nonce"), 
			$newToken->key, 
			$consumer->key);
		
		return $newToken;
	}

	/**
	 * Process an access_token request.
	 *
	 * @return OAuthToken 
	 */
	public function fetchAccessToken() {
		$this->getVersion();
		$consumer = $this->getConsumer();
		
		// Compare the request token with the one stored in the db.
		$token = $this->getToken("request");
		
		// Build the signature based on the oauth params and compare it with
		// oauth_signature value.
		$this->checkSignature($token);
		
		// Rev A change
		$verifier = $this->request->getParameter("oauth_verifier");
		$newToken = $this->newAccessToken($token, $consumer, $verifier);
		
		return $newToken;
	}

	/**
	 * 
	 * @param OAuthConsumer $consumer
	 * @param string $callback
	 */
	private function newRequestToken($consumer, $callback) {
		return $this->ds->newRequestToken($consumer, $callback);
	}
	
	/** 
	 * 
	 * @param OAuthToken $token
	 * @param OAuthConsumer $consumer
	 * @param string $verifier
	 */
	private function newAccessToken($token, $consumer, $verifier) {
		return $this->ds->newAccessToken($token, $consumer, $verifier);
	}
	
	/**
	 * This method is used only to check the temporary and access credentials,
	 * (access token and API consumption steps). It can't be used in the
	 * request token and authorization steps.
	 * 
	 * @param string $tokenType 
	 * @throws OAuthException
	 */
	public function verifyRequest($tokenType = "access") {
		$this->getVersion();
		$consumer = $this->getConsumer();
		$token = $this->getToken($tokenType);
		$this->checkSignature($token);
				
		switch ($tokenType) {
		case "request":
			// In the access token step, the temporary credential is checked. As
			// the user is redirected to the callback in the authorization step, it
			// has to be checked also the verifier code.			
			if (!$this->checkVerifier())
				throw new OAuthException(OAuthError::VERIFIER_INVALID);
			break;
				
		case "access":
			// In the API consumption step, the access token's expiration is 
			// checked.
			if ($this->isTokenExpired($token))
				throw new OAuthException(OAuthError::TOKEN_EXPIRED, 401);			
			break;
		}
	}

	/**
	 * Check if the given token has expired.
	 * 
	 * @param OAuthToken $token
	 * @return boolean
	 */
	private function isTokenExpired($token) {
		if ($token->expiration == self::TOKEN_EXPIR_NEVER)
			return false;
			
		return (time() > $token->expiration) ? true : false;
	}
	
	/**
	 * Check required parameters in the request token step.
	 * 
	 * @throws OAuthException
	 * @return boolean
	 */
	public function checkConsumerRequest() {
		$requiredParams = array(
			"oauth_consumer_key",
			"oauth_signature_method",
			"oauth_signature",
			"oauth_timestamp",
			"oauth_nonce",
			"oauth_callback");

		OAuthProvider::checkRequiredParams($this->request, $requiredParams);
	}
	
	/**
	 * Check required parameters in the authorization oauth step.
	 * 
	 * @throws OAuthException
	 */
	public function checkAuthorizationRequest() {
		$params = $this->request->getParameters();
		
		if (!array_key_exists("oauth_token", $params)) {
			throw new OAuthException(OAuthError::PARAMETER_ABSENT, 400,
				"oauth_token is a required parameter.", "oauth_token");
		}

		$oauth_token = $this->request->getParameter("oauth_token");
		if (!$oauth_token) {
			throw new OAuthException(OAuthError::PARAMETER_ABSENT, 400, 
				"oauth_token is a required parameter.", "oauth_token");
		}
	}
	
	/**
	 * Check the required parameters in the access token request step.
	 * 
	 * @throws OAuthException
	 * @return boolean
	 */
	public function checkTokenRequest() {
		$requiredParams = array(
			"oauth_consumer_key",
			"oauth_token",
			"oauth_signature_method",
			"oauth_signature",
			"oauth_timestamp",
			"oauth_nonce",
			"oauth_verifier");

		OAuthProvider::checkRequiredParams($this->request, $requiredParams);
	}	
	
	/**
	 * Check the required parameters each time the provider gets a resource 
	 * consumption request.
	 * 
	 * @throws OAuthException
	 * @return boolean
	 */
	public function checkAccessRequest() {
		$requiredParams = array(
			"oauth_consumer_key",
			"oauth_token",
			"oauth_signature_method",
			"oauth_signature",
			"oauth_timestamp",
			"oauth_nonce");

		OAuthProvider::checkRequiredParams($this->request, $requiredParams);
	}
		
	/**
	 * Check the required oauth parameters. If anyone has an emtpy value or is
	 * missing, an exception is thrown.
	 * 
	 * @param OAuthRequest $request
	 * @param array $requiredParams
	 * @throws OAuthException
	 * @return boolean
	 */
	private function checkRequiredParams($request, $requiredParams) {
		$params = $request->getParameters();
		
		// Look for an emtpy required parameter to throw an exception.
		foreach ($params as $k => $v) {
			if ($v) 
				continue;			

			if (in_array($k, $requiredParams)) {
				throw new OAuthException(OAuthError::PARAMETER_ABSENT, 400, 
					"{$k} is a required parameter.", $k);
			}
		}
		
		// Look for a missing required param.
		foreach ($requiredParams as $k) {
			if (!array_key_exists($k, $params)) {
				throw new OAuthException(OAuthError::PARAMETER_ABSENT, 400, 
					"{$k} is a required parameter.", $k);				
			}
		}
	}
	
	/**
	 * Sends the response to the consumer (require pecl_http to work).
	 * 
	 * @param OAuthResponse $response
	 * @param string $responseType
	 */
	public static function sendResponse($response, $responseType = "request") {
		$message = ($responseType == "request") 
			? $response->getRequestTokenResp()
			: $response->getAccessTokenResp(); 
		
		HttpResponse::status(200);
		HttpResponse::setContentType("application/x-www-form-urlencoded");
		HttpResponse::setData($message);
		HttpResponse::send();
	}

	/**
	 * Sends the response to the consumer (require pecl_http to work).
	 * 
	 * @param string $response
	 * @param integer $statusCode
	 */
	public static function reportProblem($response, $statusCode = 400) {
		HttpResponse::status($statusCode);
		HttpResponse::setContentType("application/x-www-form-urlencoded");
		HttpResponse::setData($response);
		HttpResponse::send();
	}	
	
	/** 
	 * Get the oauth version.
	 * 
	 * @throws OAuthException
	 * @return string
	 */
	private function getVersion() {
		$version = $this->request->getParameter("oauth_version");
		
		if (!$version) {
			// Service Providers MUST assume the protocol version to be 1.0 if
			// this parameter is not present.
			// Chapter 7.0 ("Accessing Protected Ressources")
			$version = "1.0";
		}
		
		if ($version !== $this->version) {
			// Throw an exception with the oauth problem to be sent to
			// the consumer application.
			throw new OAuthException(OAuthError::VERSION_REJECTED, 400, 
				"Invalid oauth version.", "1.0");			
		}
		
		return $version;
	}

	/**
	 * Figure out the signature with some defaults.
	 * 
	 * @throws OAuthException
	 * @return OAuthSignatureMethod
	 */
	private function getSignatureMethod() {
		$signatureMethod = 
			$this->request->getParameter("oauth_signature_method");

		if (!in_array($signatureMethod,
			array_keys($this->signatureMethods))) 
		{
			$oauthErrText = 
				"Signature method '$signatureMethod' not supported, " .
				"try one of the following: " .
				implode(", ", array_keys($this->signatureMethods));

			throw new OAuthException(OAuthError::SIGNATURE_METHOD_REJECTED, 
				400, $oauthErrText);			
		}
		return $this->signatureMethods[$signatureMethod];
	}

	/**
	 * Try to find the consumer for the provided request's consumer key.
	 * 
	 * @throws OAuthException
	 * @return OAuthConsumer
	 */
	private function getConsumer() {		
		// Compare the given consumer key with the one stored in the db.
		$consumerKey = $this->request->getParameter("oauth_consumer_key");
		$consumer = $this->ds->lookupConsumer($consumerKey);
		
		if (!$consumer) {
			throw new OAuthException(OAuthError::CONSUMER_KEY_UNKNOWN, 400,
				"Invalid consumer.");
		}

		return $consumer;
	}

	/**
	 * Try to find the token for the provided request's token key.
	 * 
	 * @param string $tokenType
	 * @throws OAuthException
	 * @return OAuthToken
	 */
	private function getToken($tokenType = "access") {
		// Compare the given consumer key with the one stored in the db.
		$oauthToken = $this->request->getParameter("oauth_token");
		$consumer = $this->getConsumer();
		$token = $this->ds->lookupToken($consumer, $tokenType, $oauthToken);
		
		if (!$token) {
			throw new OAuthException(OAuthError::TOKEN_EXPIRED, 400,
				"Invalid {$tokenType} token: {$tokenField}");
		}
		return $token;
	}

	/**
	 * All-in-one function to check the signature on a request.
	 * Should guess the signature method appropriately.
	 * 
	 * @param OAuthToken $token
	 * @throws OAuthException
	 */
	private function checkSignature($token) {
		// Retrieve the parameters to check the signature.
		$timestamp = $this->request->getParameter("oauth_timestamp");
		$nonce = $this->request->getParameter("oauth_nonce");
		$consumer = $this->getConsumer();

		$this->checkTimestamp($timestamp);
		$this->checkNonce($consumer, $token, $nonce, $timestamp);

		$signatureMethod = $this->getSignatureMethod();
		$signature = $this->request->getParameter("oauth_signature");

		$validSig = $signatureMethod->checkSignature(
			$this->request,
			$consumer,
			$token,
			$signature);

		if (!$validSig) {
			throw new OAuthException(OAuthError::SIGNATURE_INVALID, 401,
				"Invalid signature.");
		}
	}

	/**
	 * Check that the timestamp is new enough.
	 * 
	 * @param string timestamp
	 * @throws OAuthException
	 */
	private function checkTimestamp($timestamp) {
		// Verify that timestamp is recentish.
	    $now = time();
	    if (abs($now - $timestamp) > $this->timestampThreshold) {
			throw new OAuthException(OAuthError::TIMESTAMP_REFUSED, 400,
				"Expired timestamp, yours {$timestamp}, ours {$now}");
		}
	}

	/**
	 * Check that the nonce is not repeated.
	 * 
	 * @param OAuthConsumer $consumer
	 * @param OAuthToken $token
	 * @param string $nonce
	 * @param string $timestamp
	 * @throws OAuthException
	 */
	private function checkNonce($consumer, $token, $nonce, $timestamp) {
		// If it's the request token step, the nonce is not checked.
		if ($token == null) {
			return;
		}
		
		// Verify that the nonce is uniqueish.		
		$found = $this->ds->lookupNonce(
			$consumer,
			$token,
			$nonce,
			$timestamp);
		
		if ($found) {
			throw new OAuthException(OAuthError::NONCE_USED, 400, 
				"Nonce already used.");
		}
	}
	
	/**
	 * Generate and store a verifier code.
	 * 
	 * @return string
	 */
	public function generateVerifier() {
		// Generate the verification code.
		$verifier = OAuthUtil::generateVerifier();
		
		// Put the verification code in the db.
		$tokenKey = $this->request->getParameter("oauth_token");
		$this->ds->insertVerifier($verifier, $tokenKey);

		return $verifier;
	}
	
	/**
	 * Retrieve the callback URL for a consumer.
	 * 
	 * @throws OAuthException
	 * @return string
	 */
	public function getCallbackUrl() {
		try {
			$tokenKey = $this->request->getParameter("oauth_token");
			$consumerKey = $this->request->getParameter("oauth_consumer_key");
			
			return $this->ds->getCallbackUrl($tokenKey, $consumerKey);
			
		} catch (DbException $e) {
			throw new OAuthException(OAuthError::TOKEN_REJECTED, 400, 
				"The request token key is invalid.");
		}
	}
	
	/**
	 * Redirect the consumer to the callback URL.
	 * 
	 * @param string $callback
	 * @param string $verifier
	 */
	public function redirectConsumer($callback, $verifier) {
		header("Location: " . $callback .
			"?oauth_token=" . $this->request->getParameter("oauth_token") .
			"&oauth_verifier=" . $verifier);
		exit();
	}

	/**
	 * Check the verifier code.
	 * @return boolean
	 */
	private function checkVerifier() {
		try {
			$verifier = $this->ds->getVerifier(
				$this->request->getParameter("oauth_token"), 
				$this->request->getParameter("oauth_consumer_key")
			);
		} catch (DbException $e) {
			return false;
		}
		
		return ($this->request->getParameter("oauth_verifier") == $verifier)
			? true : false;
	}
	
	/**
	 * Redirect the browser to a given url. It could be set a message to send
	 * as GET parameter.
	 * 
	 * @param string $url
	 * @param string $msg
	 */
	public static function redirect($url, $msg) {
		header("Location: " . $url . $msg);
		exit();		
	}
	
	/**
	 * Check if the user has granted the access to the consumer app, if not,
	 * it redirects the user to an error page.
	 * 
	 * @param integer $userId
	 * @throws OAuthException
	 */
	private function checkUserPermission($userId = 0) {
		
		$action = @$this->request->getParameter("action");
		if (!$action)
			throw new OAuthException(OAuthError::PERMISSION_UNKNOWN, 401, 
				"Third party application not authorized.");

		if ($action != "ALLOW")
			throw new OAuthException(OAuthError::PERMISSION_DENIED, 401,
				"The access to the third party application has been denied.");
			
		$tokenKey = @$this->request->getParameter("oauth_token");
		if (!$tokenKey) {
			throw new OAuthException(OAuthError::TOKEN_REJECTED, 401,			
				"Missing request token key.");
		}
		
		// It might be: days, weeks, months or 'for ever'.
		$tokExpirUnit = @$this->request->getParameter("tok_expir_unit");
		
		// Token expiration time.
		$tokExpir = @$this->request->getParameter("tok_expir"); 
		
		try {
			// If this method is executed, it means that the user has granted 
			// the access to the consumer app, so 'allowed' field is set to 
			// true.
			$ds = new OAuthDataStore();
			
			// Retrieve the user id from user_sessions table giving the 
			// session id.
			// Comment: What is this? why use a php session in a stateless REST architecture?
			$userId = $ds->getUserId(session_id());
			
			// Allow the consumer application for the userId.
			// Set the token expiration time that will be used for the access token.			
			$ds->setAllowed($tokenKey, "true", $userId, $tokExpir, $tokExpirUnit);
			
		} catch (DbException $e) {
			throw new OAuthException(OAuthError::DB_ERROR, 500, $e->getMessage());
		}
	}
	
	/**
	 * Check if the consumer app has been approved to consume resources.
	 * 
	 * @throws OAuthException
	 */
	public function isConsumerAllowed() {
		$tokenKey = $this->request->getParameter("oauth_token");
		$consumerKey = $this->request->getParameter("oauth_consumer_key");
		
		if (!$this->ds->getAllowed($tokenKey, $consumerKey)) {
			throw new OAuthException(OAuthError::PERMISSION_DENIED, 401);
		}	
	}
	
	/**
	 * Get the user id.
	 * @return The userID 
	 * @throws OAuthException
	 */
	public function getUserID() {
		
		$tokenKey = $this->request->getParameter("oauth_token");
		$consumerKey = $this->request->getParameter("oauth_consumer_key");
		
		if ($userID = $this->ds->getValidUserID($tokenKey, $consumerKey)) 
			return $userID;		
		else
			throw new OAuthException(OAuthError::PERMISSION_DENIED, 401);			
		
	}
			
}

?>
