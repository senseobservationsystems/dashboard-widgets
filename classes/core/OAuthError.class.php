<?php 

class OAuthError {
	
	const PARAMETER_ABSENT = 			"parameter_absent";
	const PARAMETER_REJECTED = 			"parameter_rejected";
	
	const SIGNATURE_METHOD_REJECTED = 	"signature_method_rejected";
	const SIGNATURE_INVALID = 			"signature_method_invalid";
	
	const CONSUMER_KEY_UNKNOWN = 		"consumer_key_unknown";
	const CONSUMER_KEY_REJECTED = 		"consumer_key_rejected";
	const CONSUMER_KEY_REFUSED = 		"consumer_key_refused";
	
	const TOKEN_USED = 					"token_used";
	const TOKEN_EXPIRED = 				"token_expired";
	const TOKEN_REVOKED = 				"token_revoked";
	const TOKEN_REJECTED = 				"token_rejected";
	
	const PERMISSION_UNKNOWN = 			"permission_unknown";
	const PERMISSION_DENIED = 			"permission_denied";
	
	const VERSION_REJECTED = 			"version_rejected";
	
	const TIMESTAMP_REFUSED = 			"timestamp_refused";
	
	const NONCE_USED = 					"nonce_used";
	
	const VERIFIER_INVALID = 			"verifier_invalid";
	
	const ADDITIONAL_AUTHORIZATION_REQUIRED = "additional_authorization_required";
	
	const USER_REFUSED = 				"user_refused";
	
	const ERROR_UNKNOWN = 				"unknown_error";
	
	const DB_ERROR = 					"db_error";
	
}

?>