<?php
/**
 * Phlow API wrapper
 */

class api {
    // The singleton instance
	private static $instance = null;
	private static $apiUrl = 'https://api.phlow.com';

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

		$checksum = hash_hmac('SHA256', join("\n", $strings), $clientKeys['privateKey'], false);
		$sessionPublicKey = empty($sessionKeys['publicKey']) ? '' : $sessionKeys['publicKey'];

		return $clientKeys['publicKey'] . $sessionPublicKey . $time . $checksum;
	}

	private function signedRequest($method, $endpoint, $body) {
		$uri = '/v1' . $endpoint;
		$url = self::$apiUrl . $uri;
		$time = $this->time();

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HTTPHEADER => array(
				'X-PHLOW:' . $this->generateSignature($time, $method, $uri, $body)
			),
			CURLOPT_URL => $url
		));

		$result = json_decode(curl_exec($curl));
		curl_close($curl);

		return $result;
	}

	public function time() {
		$curl = curl_init();
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => self::$apiUrl . '/v1/time'
		));

		$time = json_decode(curl_exec($curl))->time;
		curl_close($curl);

		return $time;
	}

	public function me() {
		return $this->signedRequest('GET', '/users/me');
	}

	public function userMagazines($userId) {
		return $this->signedRequest('GET', '/users/' . $userId . '/magazines');
	}

	public function searchMagazines($string) {
		return $this->signedRequest('GET', '/search/magazines?q=' . $string);
	}

	public function searchMoments($string) {
		return $this->signedRequest('GET', '/events/search?name=' . $string);
	}

	public function streams($queryString) {
		return $this->signedRequest('GET', '/streams?' . $queryString);
	}

	public function magazines($magazineId, $queryString) {
		return $this->signedRequest('GET', '/magazines/' . $magazineId . '?' . $queryString);
	}

	public function moments($momentId, $queryString) {
		return $this->signedRequest('GET', '/events/' . $momentId . '?' . $queryString);
	}
}
