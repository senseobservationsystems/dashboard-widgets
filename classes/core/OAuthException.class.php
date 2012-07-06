<?php 

require_once("OAuthUtil.class.php");
require_once("OAuthError.class.php");

class OAuthException extends Exception {

	protected $type = "oauth_problem=";
		
	public function __construct(
		$oauthErrType, 
		$oauthErrCode = 400, 
		$oauthErrMsg = "",
		$extraValues = "") 
	{
		$this->setError($oauthErrType, $oauthErrCode, $oauthErrMsg, $extraValues);
	}
	
	/**
	 * 
	 * @return encoded message
	 */
	public function getEncodedMsg() {
		//return OAuthUtil::urlEncodeRfc3986($this->message);
		return $this->message;
	}
	
	/**
	 * Set the oauth_problem parameter, a code and an error message.
	 * 
	 * @param integer $type
	 * @param integer $code
	 * @param string $message
	 * @param string $extraValues	  
	 */
	private function setError($type, $code, $message, $extraValues) {
		$this->code = $code;		
		
		switch ($type) {
		case OAuthError::VERSION_REJECTED:
			// extraValues should contain the acceptable version.
			$this->type .= $type;			
			$this->type .= 
				"&oauth_acceptable_versions=".
				OAuthUtil::urlEncodeRfc3986($extraValues);
			break;
			
		case OAuthError::PARAMETER_ABSENT:
			// extraValues should contain the absent parameters separated by 
			// '&' enconded twice, which means that it will have the value 
			// '%26'.
			$this->type .= $type;			
			$this->type .= 
				"&oauth_parameters_absent=".
				OAuthUtil::urlEncodeRfc3986($extraValues);
			break;
			
		case OAuthError::PARAMETER_REJECTED:
			// extraValues should contain the absent parameters separated 
			// by '&'.
			$this->type .= $type;			
			$this->type .= 
				"&oauth_parameters_rejected=".
				OAuthUtil::urlEncodeRfc3986($extraValues);
			break;
			
		case OAuthError::TIMESTAMP_REFUSED:
			$this->type .= $type;			
			$this->type .= 
				"&oauth_acceptable_timestamps=".
				//OAuthUtil::urlEncodeRfc3986($extraValues);
				OAuthUtil::urlEncodeRfc3986($text);
			break;
			
		case OAuthError::NONCE_USED:		

		case OAuthError::SIGNATURE_METHOD_REJECTED:
		case OAuthError::SIGNATURE_INVALID:
						
		case OAuthError::CONSUMER_KEY_UNKNOWN:
		case OAuthError::CONSUMER_KEY_REJECTED:
		case OAuthError::CONSUMER_KEY_REFUSED:
						
		case OAuthError::TOKEN_USED:
		case OAuthError::TOKEN_EXPIRED:
		case OAuthError::TOKEN_REVOKED:
		case OAuthError::TOKEN_REJECTED:
			
		case OAuthError::VERIFIER_INVALID:
			
		case OAuthError::ADDITIONAL_AUTHORIZATION_REQUIRED:
			
		case OAuthError::PERMISSION_UNKNOWN:
		case OAuthError::PERMISSION_DENIED:
			
		case OAuthError::USER_REFUSED:
			$this->type .= $type;
			
			if ($message) {
				$this->type .= "&oauth_problem_advice=".
					OAuthUtil::urlEncodeRfc3986($message);
			}

			break;
		
		case OAuthError::DB_ERROR:	
			$this->type .= "db_error&oauth_problem_advice=".
				OAuthUtil::urlEncodeRfc3986($message);
			break;
								
		case OAuthError::ERROR_UNKNOWN:
			$this->type .= "unknown_error";
			break;
			
		default:
			$this->type .= "unknown";
		}

		// The message will have the following format:
		// oauth_problem=xxxxx&oauth_problem_advice=xxxxx
		$this->message = $this->type;
	}
}

?>