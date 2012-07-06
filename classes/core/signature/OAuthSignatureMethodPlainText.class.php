<?php 

require_once("../core/OAuthUtil.class.php");
require_once("../core/OAuthSignatureMethod.class.php");

/**
 * The PLAINTEXT method does not provide any security protection and SHOULD only be used
 * over a secure channel such as HTTPS. It does not use the Signature Base String.
 *   - Chapter 9.4 ("PLAINTEXT")
 */
class OAuthSignatureMethodPlainText extends OAuthSignatureMethod {
	
	public function getName() {
		return "PLAINTEXT";
	}

	/**
	 * oauth_signature is set to the concatenated encoded values of the Consumer Secret and
	 * Token Secret, separated by a '&' character (ASCII code 38), even if either secret is
	 * empty. The result MUST be encoded again.
	 *   - Chapter 9.4.1 ("Generating Signatures")
	 *
	 * Please note that the second encoding MUST NOT happen in the SignatureMethod, as
	 * OAuthRequest handles this!
	 */
	public function buildSignature($request, $consumer, $token) {
		$key = ($token) ? $consumer->secret.$token->secret : $consumer->secret;
		$request->baseString = $key;
		return $key;
	}
}

?>