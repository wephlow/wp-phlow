<?php
/**
 * Phlow API wrapper
 */

class api {
    // The singleton instance
	private static $instance = null;
	private static $apiUrl = 'https://api.phlow.com';
    private $phlow_sessionPrivateKey;
    private $phlow_sessionPublicKey;

	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function setKeys($private, $public){
	    $this->phlow_sessionPrivateKey = $private;
	    $this->phlow_sessionPublicKey = $public;
    }

    private function getUserCredentials($isReader){
	    if (!$isReader){
            $sessionKeys = array(
                'privateKey' => get_option('phlow_sessionPrivateKey'),
                'publicKey' => get_option('phlow_sessionPublicKey')
            );
        } else {
	        $sessionKeys = array(
                'privateKey' => $this->phlow_sessionPrivateKey,
                'publicKey' => $this->phlow_sessionPublicKey
            );
        }

        return ($sessionKeys);
    }

	private function generateSignature($time, $method, $uri, $body, $isReader, $isUserSigned=true) {
		$clientKeys = array(
            'privateKey' => get_option('phlow_clientPrivateKey'),
            'publicKey' => get_option('phlow_clientPublicKey')
        );

		if ($isUserSigned){
            $sessionKeys = $this->getUserCredentials($isReader);
        }

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

	private function signedRequest($method, $endpoint, $body=null, $isReader=false, $isUserSigned=true, $waitForResponse=true) {
		$uri = '/v1' . $endpoint;
		$url = self::$apiUrl . $uri;
		$time = $this->time();

		$curl = curl_init();
		if ($method == 'POST'){
            curl_setopt($curl, CURLOPT_POST, 1);
        }

		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HTTPHEADER => array(
				'X-PHLOW:' . $this->generateSignature($time, $method, $uri, $body, $isReader, $isUserSigned)
			),
			CURLOPT_URL => $url
		));

		if (!$waitForResponse) {
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 100);
        }

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
		return $this->signedRequest('GET', '/streams?' . $queryString, null, true);
	}

	public function magazines($magazineId, $queryString) {
		return $this->signedRequest('GET', '/magazines/' . $magazineId . '?' . $queryString, null, true);
	}

	public function moments($momentId, $queryString) {
		return $this->signedRequest('GET', '/events/' . $momentId . '?' . $queryString, null, true);
	}

	public function generateGuestUser(){
        return $this->signedRequest('POST', '/users/guest', null, false, false);
    }

	public function seen($photoId, $stream = null, $magazine = null, $moment = null){
        $endpoint = '/photos/' . $photoId . '/activity/seen/?';
        if (isset($stream)){
            $endpoint .= 'context='.$stream;
        }
        if (isset($magazine)){
            $endpoint .= 'magazineId='.$magazine;
        }
        if (isset($moment)){
            $endpoint .= 'eventId='.$moment;
        }

        return $this->signedRequest('POST', $endpoint, null, true, true, true);
    }
}
