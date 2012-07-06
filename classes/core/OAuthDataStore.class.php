<?php

require_once("OAuthToken.class.php");
require_once("OAuthConsumer.class.php");
require_once("OAuthUtil.class.php");

require_once("oauth/db/db.class.php");
require_once("oauth/db/db.conf.php");

class OAuthDataStore {

	public $consumer;
	public $requestToken;
	public $accessToken;
	public $nonce;
	
	private $db;

	// Db errors.
	const ERR_NO_TOKEN 					= 1;
	const ERR_NO_VERIFIER 				= 2;
	const ERR_NO_CALLBACK 				= 3;
	const ERR_NEW_ACCESS_TOKEN 			= 4;
	const ERR_NO_ALLOWED_RETRIEVED 		= 5;
	const ERR_NO_USER_ID				= 6;
	const ERR_INVALID_LOGIN				= 7;
	// Consumer doesn't exist in oauth_consumer table.
	const ERR_NO_CONSUMER 				= 10;
	// Consumer doesn't exist in oauth_credentials table.	
	const ERR_NO_AUTHORIZED_CONSUMER 	= 11;
	// Consumer name can't be retrieved.
	const ERR_NO_CONSUMER_NAME			= 12;
	
	// Expiration time options.
	const EXPIR_TIME_NEVER 		= 0;
	const EXPIR_TIME_IN_DAYS 	= 1;
	const EXPIR_TIME_IN_WEEKS 	= 2;
	const EXPIR_TIME_IN_MONTHS 	= 3;
	const EXPIR_TIME_IN_YEARS 	= 4;
	
	/**
	 * Constructor.
	 * 
	 * @param array $oauthParams
	 * @param string $tokenType
	 */
	public function __construct($oauthParams = null, $tokenType = null) {
		$dbConf = array(
		 	"host" 	  => DB_HOST,
			"user" 	  => DB_USER,
			"passwd"  => DB_PASS,
			"db_name" => DB_NAME
		);

		$this->db = new Db($dbConf);
				
		if (!$oauthParams)
			return;
		
		// Retrieve the consumer credential.
		// This credential is used in request_token step.

		if (isset($oauthParams["oauth_consumer_key"]) 
			&& $oauthParams["oauth_consumer_key"] != null)
		{
			if ($tokenType != "requset" && $tokenType != "access") {
				// Retrieve the consumer from the oauth_consumers table.
				$this->consumer = 
					$this->getConsumer($oauthParams["oauth_consumer_key"]);
					
			} else {
				// Check and retrieve the consumer from the oauth_credentials 
				// table.
				$this->consumer = $this->checkConsumer(
						$oauthParams["oauth_consumer_key"], 
						$oauthParams["oauth_token"]);
			}
		}
				
		if (!$tokenType)
			return;
			
		// Retrieve the user (temporary or access) credential.
		switch ($tokenType) {
			
		case "request":
			// This credential is used in access_token step.
			$this->requestToken = $this->getToken(
					$oauthParams["oauth_consumer_key"], 
					$oauthParams["oauth_token"]);				
			break;

		case "access":
			// This credential is requested in each API request. 
			$this->accessToken = $this->getToken(
				$oauthParams["oauth_consumer_key"], 
				$oauthParams["oauth_token"]);
			break;
		}
	}

	/**
	 * Retrieve a request token from the db giving a consumer key and an access
	 * token giving a consumer key and request token key.
	 * 
	 * @param string $consumerKey
	 * @param string $tokenKey
	 * @throws DbException @@ TODO: change this by DataStoreException
	 * @return OAuthToken
	 */
	private function getToken($consumerKey, $tokenKey = null) {
		// In the request token step, the consumer doesn't send any
		// token key. However, consumer sends it in the access token
		// step. 
		$tokenCond = ($tokenKey) ? " AND token_key = '".$tokenKey."'" : "";
		
		$result = $this->db->query(
			" SELECT".
			"	token_key".
			"	,token_secret".
			"	,expiration".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			" 	consumer_key = '" . $consumerKey . "'".
			$tokenCond
		);

		$data = $this->db->toArray($result);
		
		if (!$data)
			throw new DbException("Token couldn't be retrived.", 
				self::ERR_NO_TOKEN);
			
		return new OAuthToken(
			$data["token_key"], 
			$data["token_secret"], 
			$data["expiration"]);
	}
	
	/**
	 * Retrive a consumer credential from the db giving a consumer key.
	 * 
	 * @param string $consumerKey
	 * @throws DbException
	 * @return OAuthConsumer
	 */
	private function getConsumer($consumerKey) {
		$result = $this->db->query(
			" SELECT".
			"	consumer_key".
			"	,consumer_secret".
			"	,callback".
			"	,name".
			" FROM". 
			"	oauth_consumers".
			" WHERE".
			" 	consumer_key = '{$consumerKey}'"
		);

		$data = $this->db->toArray($result);

		if (!$data)
			throw new DbException("Consumer not registered.", 
				self::ERR_NO_CONSUMER);

		return new OAuthConsumer(
			$data["consumer_key"], 
			$data["consumer_secret"], 
			$data["callback"], 
			$data["name"]);
	}
	
	/**
	 * Check if the consumer exists in oauth_credentials. If so, return the 
	 * credential.
	 *   
	 * @param string $consumerKey
	 * @param string $tokenKey
	 * @return OAuthConsumer
	 * @throws DbException
	 */
	public function checkConsumer($consumerKey, $tokenKey) {
		$result = $this->db->query(
			" SELECT".
			"	consumer_key,".
			"	consumer_secret".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			" 	consumer_key = '{$consumerKey}'".
			"	AND token_key = '{$tokenKey}'"
		);

		$data = $this->db->toArray($result);

		if (!$data)
			throw new DbException("Consumer couldn't be retrived.", 
				self::ERR_NO_AUTHORIZED_CONSUMER);

		return new OAuthConsumer($data["consumer_key"], $data["consumer_secret"]);
	}

	/**
	 * Retrieve the callback url from the db. 
	 * 
	 * @param string $tokenKey
	 * @throws DbException
	 * @return string
	 */
	public function getCallbackUrl($tokenKey) {
		$result = $this->db->query(
			" SELECT".
			" 	callback".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			"	token_key = '" . $tokenKey . "'"
		);

		$data = $this->db->toArray($result);
		
		if (!$data) {
			throw new DbException("Callback url couldn't be retrieved.", 
				self::ERR_NO_CALLBACK);
		}
		
		return $data[0];		
	}
	
	/**
	 * Retrieve the verifier code. 
	 * 
	 * @param string $tokenKey
	 * @param string $consumerKey 
	 * @throws DbException
	 * @return string
	 */
	public function getVerifier($tokenKey, $consumerKey) {
		$result = $this->db->query(
			" SELECT".
			" 	verifier".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			"	token_key = '" . $tokenKey . "'" .
			"	AND consumer_key = '" . $consumerKey . "'" 
		);

		$data = $this->db->toArray($result);
		
		if (!$data) {
			throw new DbException("Verifier code couldn't be retrieved.", 
				self::ERR_NO_VERIFIER);
		}
		
		return $data[0];		
	}	
	
	public function lookupConsumer($consumerKey) {
		return ($this->consumer->key == $consumerKey) ? $this->consumer : null;
	}

	public function lookupToken($consumer, $tokenType, $token) {
		$tokenAttrib = $tokenType . "Token";

		return ($consumer->key == $this->consumer->key
			&& $token == $this->$tokenAttrib->key)
			? $this->$tokenAttrib : null;
	}

	/**
	 * Check if a nonce has been used before, if it has not been used is
	 * stored in the db.
	 * 
	 * @param OAuthConsumer $consumer
	 * @param OAuthToken $token
	 * @param string $nonce
	 * @param string $timestamp
	 */
	public function lookupNonce($consumer, $token, $nonce, $timestamp) {
		$data = $this->getNonces($consumer->key, $token->key);
		
		// If there isn't any nonce stored.
		if (!$data) {
			return null;
		}

		$rows = $this->db->getResultNumRows($data);
		
		if ($rows > 1) {
			// If the nonce has been used before, returns the used nonce.
			if ($consumer->key == $this->consumer->key
				&& $token && $token->key == $this->accessToken->key)
			{
				// Look for an used nonce.
				for ($i = 0; $i < $rows; $i++) {
					$row = $this->db->toAssocArray($data);
					
					if ($row["nonce"] == $nonce) { 
						return $nonce;
					}					
				}
			}			

		} else {
			// If there's only one stored nonce.
			
			// If the nonce has been used before, returns the used nonce.
			if ( $consumer->key == $this->consumer->key
				&& (($token && $token->key == $this->requestToken->key)
				|| ($token && $token->key == $this->accessToken->key))
				&& ($nonce == $data[0]["nonce"]) )
			{
				return $nonce;
			}			
		}
		
		// If the nonce has never been used, it's added to the db.
		$this->insertNonce($nonce, $token->key, $consumer->key);
		return null;
	}
	
	/**
	 * Retrieve the nonce and the timestamp for given consumer and user
	 * credentials.
	 * 
	 * @param string $consumerKey
	 * @param string $tokenKey
	 * @return array
	 */
	private function getNonces($consumerKey, $tokenKey) {
		$result = $this->db->query(
			" SELECT".
			" 	n.timestamp as timestamp".
			"	,n.nonce as nonce".
			" FROM". 
			"	oauth_nonces n".
			" INNER JOIN oauth_credentials c".
			"	ON c.id = n.oauth_credentials_id".
			" WHERE".
			" 	c.consumer_key = '{$consumerKey}'".
			"	AND c.token_key = '{$tokenKey}'"
		);
		
		return (!$result) ? null : $result;
	}
		
	/** 
	 * This method is executed from the provider, in the request
	 * token step.
	 *  
	 * @param string $nonce
	 * @param string $tokenKey
	 * @param string $consumerKey
	 */
	public function insertNonce($nonce, $tokenKey, $consumerKey) {
		$timestamp = time();
		
		$this->db->query(
			" INSERT INTO oauth_nonces ".
			"	(oauth_credentials_id, timestamp, nonce)".
			" VALUES (".
			"	(".
			"		SELECT id ".
			"		FROM oauth_credentials".
			" 		WHERE".
			"			token_key = '{$tokenKey}'".
			"			AND consumer_key = '{$consumerKey}'".
			"	)".
			"	,'{$timestamp}'".
			"	,'{$nonce}'".
			" )"
		);		
	}

	/**
	 * Create a new temporary token and insert it into the db.
	 * 
	 * @param OAuthConsumer $consumer
	 * @param string $callback
	 * @return OAuthToken
	 */
	public function newRequestToken($consumer, $callback = null) {
		// return a new token attached to this consumer
		if ($consumer->key == $this->consumer->key) {
			$tempCredentials = OAuthUtil::generateKeyAndSecret();
			
			$this->requestToken = new OAuthToken(
				$tempCredentials["key"], 
				$tempCredentials["secret"]);
				
			$this->insertRequestToken($this->requestToken, $callback);				

			return $this->requestToken;
		}

		return null;
	}

	/**
	 * Create a new access token and insert it into the db.
	 * 
	 * @param OAuthToken $token
	 * @param OAuthConsumer $consumer
	 * @param string $verifier
	 * @throws DbException
	 * @return OAuthToken
	 */
	public function newAccessToken($token, $consumer, $verifier = null) {
		// return a new access token attached to this consumer
		// for the user associated with this token if the request token
		// is authorized
		// should also invalidate the request token
		if ($consumer->key == $this->consumer->key
			&& $token->key == $this->requestToken->key) 
		{
			$accessCredentials = OAuthUtil::generateKeyAndSecret();
			
			$this->accessToken = new OAuthToken(
				$accessCredentials["key"], 
				$accessCredentials["secret"]);			
			
			$this->insertAccessToken($this->accessToken);
						
			return $this->accessToken;
		}				
		
		throw new DbException("New access token couldn't be generated.", 
			self::ERR_NEW_ACCESS_TOKEN);
	}
	
	/**
	 * Insert the verification code in the db.
	 * 
	 * @param string $verifier
	 * @param string $tokenKey
	 */
	public function insertVerifier($verifier, $tokenKey) {
		$this->db->query(
			" UPDATE".
			"	oauth_credentials".
			" SET".
			"	verifier = '" . $verifier . "'".
			" WHERE".
			"	token_key = '" . $tokenKey . "'"
		);
	}
	
	/**
	 * Insert the temporary token key (request token) and secret in the db.
	 * 
	 * @param OAuthToken $token
	 * @param string $callback
	 */
	private function insertRequestToken($token, $callback = null) {
		$callbackUrl = ($callback) 
			? " ,'{$callback}'" 
			: " ,'{$this->consumer->callbackUrl}'";
		
		$currTime = time();
			
		$this->db->query(
			" INSERT INTO oauth_credentials ".
			"	(consumer_key, consumer_secret, token_key, token_secret, expiration, callback)".
			" VALUES (".
			"	'{$this->consumer->key}'".
			"	,'{$this->consumer->secret}'".
			"	,'{$token->key}'".
			"	,'{$token->secret}'".
			"	,'{$currTime}'".
			$callbackUrl.
			" )"
		);
	}

	/**
	 * Replace the temporary credential (request token key and secret) by the 
	 * access credential (access token key and secret) in the oauth_credentials
	 * table.  
	 *  
	 * @param OAuthToken $token
	 */
	private function insertAccessToken($token) {
		$this->db->query(
			" UPDATE".
			"	oauth_credentials".
			" SET".
			"	token_key = '{$token->key}'".
			"	,token_secret = '{$token->secret}'".
			" WHERE".
			"	consumer_key = '{$this->consumer->key}'".
			"	AND token_key = '{$this->requestToken->key}'"
		);
	}	
	
	/**
	 * Get the temporary token expiration time (5 minutes).
	 */
	private function getTempTokExpirTime() {
		// 5 minutes.
		return time() + (5 * 60);
	}
	
	/**
	 * Calculate the token expiration time that will be used for the access 
	 * credential.
	 * 
	 * @param integer $expirTime
	 * @param integer $expirTimeUnit
	 */
	private function getAccessTokExpirTime($expirTime, $expirTimeUnit) {
		$expirFactor = 1;
		$expirTime = (!$expirTime) ? 1 : abs($expirTime);
		
		// Convert to seconds.
		switch ($expirTimeUnit) {
			
		case self::EXPIR_TIME_NEVER:
			return -1;

		case self::EXPIR_TIME_IN_DAYS:
			$expirFactor = 24 * 60 * 60;
			break;
				
		case self::EXPIR_TIME_IN_WEEKS:
			$expirFactor = 7 * 24 * 60 * 60;
			break;
				
		case self::EXPIR_TIME_IN_MONTHS:
			$expirFactor = 30 * 24 * 60 * 60;
			break;
			
		case self::EXPIR_TIME_IN_YEARS:
			$expirFactor = 365 * 24 * 60 * 60;
			break;			
		}
		
		return time() + ($expirTime * $expirFactor);
	}
	
	/**
	 *
	 * @param OAuthConsumer $consumer
	 */
	public function setConsumer($consumer, $callback = null) {
		$this->consumer = new OAuthConsumer(
			$consumer->key, 
			$consumer->secret, 
			$callback);
	}
	
	/**
	 * Set any kind of token, request or access token.
	 * 
	 * @param OAuthToken $token
	 * @param string $tokenType request|access
	 */
	public function setToken($token, $tokenType = "request") {
		$tokenAttr = $tokenType . "Token";
		// tokenAttr can be requestToken or accessToken
		$this->$tokenAttr = new OAuthToken($token->key, $token->secret);		
	}
	
	/**
	 *  
	 * @param string $consumerKey
	 * @param string $tokenKey
	 * @param string $secretType consumer|token
	 * 
	 * @return string
	 */
	// @@ TODO: remove this method.
	public function getSecret($consumerKey, $tokenKey, $secretType = "consumer") {
		$result = $this->db->query(
			" SELECT".
			" 	{$secretType}_secret".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			" 	consumer_key = '{$consumerKey}'".
			"	AND token_key = '{$tokenKey}'"
		);
		
		$data = $this->db->toArray($result);
		return $data[0];
	}
	
	/**
	 * In the authorization step, the user allows or disallows the consumer app
	 * to access their data. If the user allows the consumer app, it's also set 
	 * the expiration time in which the credential will be valid.
	 * 
	 * @param string $tokenKey
	 * @param boolean $value
	 * @param integer $userId
	 * @param integer $expirTime
	 * @param integer $expirTimeUnit
	 */
	public function setAllowed($tokenKey, $value, $userId, $expirTime, $expirTimeUnit) {
		$this->db->query(
			" UPDATE".
			"	oauth_credentials".
			" SET".
			"	allowed = {$value}" .
			"	,user_id = {$userId}" .
			"	,expiration = '{$this->getAccessTokExpirTime($expirTime, $expirTimeUnit)}'".
			" WHERE".
			"	token_key = '{$tokenKey}'"
		);
	}
	
	/**
	 *  
	 * @param string $tokenKey
	 * @param string $consumerKey
	 * @throws DbException
	 */
	public function getAllowed($tokenKey, $consumerKey) {
		$result = $this->db->query(
			" SELECT".
			" 	allowed".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			"	token_key = '{$tokenKey}'" .
			"	AND consumer_key = '{$consumerKey}'" 
		);

		$data = $this->db->toArray($result);
		
		if (!$data) {
			throw new DbException("'allowed' field value couldn't be retrieved.", 
				self::ERR_NO_ALLOWED_RETRIEVED);
		}
		
		return $data["allowed"];		
	}
	
/**
	 *  
	 * @param string $tokenKey
	 * @param string $consumerKey
	 * @throws DbException
	 */
	public function getValidUserID($tokenKey, $consumerKey) {
		
		$result = $this->db->query(
			" SELECT".
			" 	user_id".
			" FROM". 
			"	oauth_credentials".
			" WHERE".
			"	token_key = '{$tokenKey}'" .
			"	AND consumer_key = '{$consumerKey}'" .
			"   AND allowed = 1");

		$data = $this->db->toArray($result);
		
		if (!$data) {
			throw new DbException("'user_id' field value couldn't be retrieved.", 
				self::ERR_NO_ALLOWED_RETRIEVED);
		}		
		return $data["user_id"];		
	}

	/**
	 * Return the consumer name.
	 * 
	 * @param string $tokenKey
	 * @throws DbException
	 * @return string
	 */
	public function getConsumerName($tokenKey) {
		$result = $this->db->query(
			" SELECT name".
			" FROM".
			"	oauth_consumers co".
			" INNER JOIN oauth_credentials cr".
			"	ON co.consumer_key = cr.consumer_key".
			" WHERE".
			"	cr.token_key = '{$tokenKey}'"
		);
		
		$data = $this->db->toArray($result);
		
		if (!$data) {
			throw new DbException("Consumer name couldn't be retrieved.", 
				self::ERR_NO_CONSUMER_NAME);
		}
		
		return $data["name"];
	}
	
	/**
	 * Insert the user id and session id in user_session table.  
	 * 
	 * @param integer $userId
	 * @param string $sessionId
	 * @param integer $timestamp
	 * @param string $ip 
	 */
	public function insertUserSessionId($userId, $sessionId, $timestamp, $ip = "") {
		$this->db->query(
			" INSERT INTO user_session ".
			"	(user_id, session_id, ip_address, timestamp)".
			" VALUES (".
			"	{$userId}".
			"	,'{$sessionId}'".
			"	,'{$ip}'".
			"	,{$timestamp}".
			" )"
		);
	}
	
	/**
	 * Return the user id giving the session id. 
	 * 
	 * @param string $sessionId
	 * @return integer
	 */
	public function getUserId($sessionId) {
		$result = $this->db->query(
			" SELECT user_id".
			" FROM".
			"	user_session".
			" WHERE".
			"	session_id = '{$sessionId}'"
		);
		
		$data = $this->db->toArray($result);
		
		if (!$data) {
			throw new DbException("User id couldn't be retrieved.", 
				self::ERR_NO_USER_ID);
		}
		
		return $data["user_id"];
	}
	
	/**
	 * Check the user and password and return the user id.
	 * 
	 * @param string $user
	 * @param string $passwd
	 * @throws DbException
	 * @return integer
	 */
	public function checkLogin($user, $passwd) {
	    if (!$user && !$passwd) {
	    	throw new DbException("Invalid user name or password.", 
	    		self::ERR_INVALID_LOGIN);
	    }
	    
	    // To protect SQL injections.
	    $user 	= stripslashes($user);
	    $passwd = stripslashes($passwd);
	    $user 	= mysql_real_escape_string($user);
	    $passwd = mysql_real_escape_string($passwd);
	    $user 	= filter_var($user, FILTER_SANITIZE_EMAIL);
	    $passwd = filter_var($passwd, FILTER_SANITIZE_STRING |
	    	FILTER_SANITIZE_MAGIC_QUOTES);

	    // MD5 hash
	    $passwd = md5($passwd);

	    // Check the login credentials
	    $sql = "SELECT id FROM user WHERE username='{$user}' and password='{$passwd}'";
	    $result	= mysql_query($sql);

	    if (!$result) {
	    	throw new DbException("Invalid user name or password.",
	    	self::ERR_INVALID_LOGIN);
	    }

	    // If result matched $user and $passwd, table row must be 1 row
	    $count	= mysql_num_rows($result);

	    if ($count != 1) {
	    	throw new DbException("Invalid user name or password.", 
	    		self::ERR_INVALID_LOGIN);	    	
	    }
	    
	    // Register id
	    $row = mysql_fetch_assoc($result);
	    return $row['id'];
	}
}

?>
