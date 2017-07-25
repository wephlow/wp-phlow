<?php
/**
 * Phlow API wrapper
 */

class api {
    // The singleton instance
	private static $instance = null;
	private static $url = 'https://api.phlow.com';

	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function generateSignature($time, $method, $uri, $body) {
		$sessionKeys = array(
			'privateKey' => get_option('phlow_sessionPrivateKey'),
			'publicKey' => get_option('phlow_sessionPublicKey')
		);

		$clientKeys = array(
			'privateKey' => get_option('phlow_clientPrivateKey'),
			'publicKey' => get_option('phlow_clientPublicKey')
		);

		$strings = array($method, $uri, $time);

		if (isset($body) && count($body)) {
			$body_json = json_encode($body);
			array_push($strings, md5($body_json));
		}

		if (!empty($sessionKeys['privateKey'])) {
			array_push($strings, $sessionKeys['privateKey']);
		}

		$checksum = hash_hmac('SHA256', $clientKeys['privateKey'], join("\n", $strings));
		$sessionPublicKey = empty($sessionKeys['publicKey']) ? '' : $sessionKeys['publicKey'];

		return $clientKeys['publicKey'] . $sessionPublicKey . $time . $checksum;
	}

	private function signedRequest($method, $endpoint, $body) {
		$uri = '/v1' . $endpoint;
		$url = $this->url . $uri;

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HTTPHEADER => array(
				'X-PHLOW:' . $this->generateSignature(time(), $method, $body)
			),
			CURLOPT_URL => $url
		));

		$result = json_decode(curl_exec($curl));
		curl_close($curl);

		return $result;
	}
}
