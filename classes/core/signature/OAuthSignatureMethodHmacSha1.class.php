<?php 

require_once("classes/core/OAuthUtil.class.php");
require_once("classes/core/OAuthSignatureMethod.class.php");

/**
 * The HMAC-SHA1 signature method uses the HMAC-SHA1 signature algorithm as defined in [RFC2104]
 * where the Signature Base String is the text and the key is the concatenated values (each first
 * encoded per Parameter Encoding) of the Consumer Secret and Token Secret, separated by an '&'
 * character (ASCII code 38) even if empty.
 *   - Chapter 9.2 ("HMAC-SHA1")
 */
class OAuthSignatureMethodHmacSha1 extends OAuthSignatureMethod {
	
	function getName() {
		return "HMAC-SHA1";
	}

	/** 
	 * @see OAuthSignatureMethod::buildSignature()
	 */
	public function buildSignature($request, $consumer, $token) {
		$baseString = $request->getSignatureBaseString();
		$request->baseString = $baseString;
		$key = ($token) ? $consumer->secret."&".$token->secret : $consumer->secret."&";
		return base64_encode(hash_hmac("sha1", $baseString, $key, true));
	}
}

?>