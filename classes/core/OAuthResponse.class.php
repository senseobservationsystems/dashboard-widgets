<?php 

class OAuthResponse {
	
	private $token; // OAuthToken
	
	public function __construct($tokenKey, $tokenSecret) {
		$this->token = new OAuthToken($tokenKey, $tokenSecret);
	}
	
	public function getRequestTokenResp() {
		return "oauth_token=" . $this->token->key  . 
			"&oauth_token_secret=" . $this->token->secret .
			"&oauth_callback_confirmed=true";
	}
	
	public function getAccessTokenResp() {
		return 	"oauth_token=" . $this->token->key .
			"&oauth_token_secret=" . $this->token->secret;
	}
}

?>