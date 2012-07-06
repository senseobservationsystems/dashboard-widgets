<?php 

require_once("../core/OAuthSignatureMethod.class.php");

/**
 * The RSA-SHA1 signature method uses the RSASSA-PKCS1-v1_5 signature algorithm as defined in
 * [RFC3447] section 8.2 (more simply known as PKCS#1), using SHA-1 as the hash function for
 * EMSA-PKCS1-v1_5. It is assumed that the Consumer has provided its RSA public key in a
 * verified way to the Service Provider, in a manner which is beyond the scope of this
 * specification.
 *   - Chapter 9.3 ("RSA-SHA1")
 */
abstract class OAuthSignatureMethodRsaSha1 extends OAuthSignatureMethod {
	
	public function getName() {
		return "RSA-SHA1";
	}

	// Up to the SP to implement this lookup of keys. Possible ideas are:
	// (1) do a lookup in a table of trusted certs keyed off of consumer
	// (2) fetch via http using a url provided by the requester
	// (3) some sort of specific discovery code based on request
	//
	// Either way should return a string representation of the certificate
	protected abstract function fetchPublicCert(&$request);

	// Up to the SP to implement this lookup of keys. Possible ideas are:
	// (1) do a lookup in a table of trusted certs keyed off of consumer
	//
	// Either way should return a string representation of the certificate
	protected abstract function fetchPrivateCert(&$request);

	public function buildSignature($request, $consumer, $token) {
		$baseString = $request->getSignatureBaseString();
		$request->baseString = $baseString;

		// Fetch the private key cert based on the request
		$cert = $this->fetchPrivateCert($request);

		// Pull the private key ID from the certificate
		$privateKeyId = openssl_get_privatekey($cert);

		// Sign using the key
		$ok = openssl_sign($baseString, $signature, $privateKeyId);

		// Release the key resource
		openssl_free_key($privateKeyId);

		return base64_encode($signature);
	}

	public function checkSignature($request, $consumer, $token, $signature) {
		$decoded_sig = base64_decode($signature);

		$baseString = $request->getSignatureBaseString();

		// Fetch the public key cert based on the request
		$cert = $this->fetchPublicCert($request);

		// Pull the public key ID from the certificate
		$publicKeyId = openssl_get_publickey($cert);

		// Check the computed signature against the one passed in the query
		$ok = openssl_verify($baseString, $decoded_sig, $publicKeyId);

		// Release the key resource
		openssl_free_key($publicKeyId);

		return $ok == 1;
	}
}

?>