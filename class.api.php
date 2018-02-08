<?php
/**
 * Phlow API class
 */

class phlowAPI {
	private static $instance = null;
    private $phlow_sessionPrivateKey;
    private $phlow_sessionPublicKey;

    public function __construct() {
    	$isDev = boolval(get_option('phlow_dev'));
    	$this->apiUrl = $isDev ? 'https://api-dev.phlow.com' : 'https://api.phlow.com';
    }

	// The singleton instance
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
	    if ($isReader){
            $sessionKeys = array(
                'privateKey' => $this->phlow_sessionPrivateKey,
                'publicKey' => $this->phlow_sessionPublicKey
            );
        }
        else {
	        $sessionKeys = array(
                'privateKey' => get_option('phlow_sessionPrivateKey'),
                'publicKey' => get_option('phlow_sessionPublicKey')
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

	private function signedRequest($method, $endpoint, $body=null, $isReader=false, $isUserSigned=true, $waitForResponse=true, $isAjaxPage=false) {
		$uri = '/v1' . $endpoint;
		$url = $this->apiUrl . $uri;
		$time = $this->time();
        $httpHeaders = array();

		$curl = curl_init();

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            $httpHeaders[] = 'Content-Type:application/json';

            if (is_array($body)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
            }
            else {
            	$httpHeaders[] = 'Content-Length:0';
            }
        }

        $httpHeaders[] = 'X-PHLOW:' . $this->generateSignature($time, $method, $uri, $body, $isReader, $isUserSigned);

        $xPhlowGateway = $this->getPageURL($isAjaxPage);

        if (isset($xPhlowGateway)) {
            $httpHeaders[] = 'X-PHLOW-GATEWAY:wordpress-embedded:'.$xPhlowGateway;
        }

        curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HTTPHEADER => $httpHeaders,
			CURLOPT_URL => $url
		));

		if (!$waitForResponse) {
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 100);
        }

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$result = json_decode($response);
		curl_close($curl);

		return ($status == 200) ? $result : array('error' => $response);
	}

	private function getPageURL($isAjaxCall = false){
        if ($isAjaxCall){
            $returnValue = $_SERVER['HTTP_REFERER'];
        } else {
            $returnValue = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        }

        return($returnValue);
    }

	public function time() {
		$curl = curl_init();
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => $this->apiUrl . '/v1/time'
		));

		$time = json_decode(curl_exec($curl))->time;
		curl_close($curl);

		return $time;
	}

	public function login($data = null) {
		return $this->signedRequest('POST', '/auth', $data, false, false);
	}

    public function register($data = null) {
        return $this->signedRequest('POST', '/users', $data, false, false);
    }

    public function registerSocial($data = null) {
        return $this->signedRequest('POST', '/users/social', $data, false, false);
    }

	public function me($isReader = false) {
		return $this->signedRequest('GET', '/users/me', null, $isReader);
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

	public function streams($queryString, $owned=false) {
		if ($owned) {
			$user = $this->me();
			$queryString .= '&user=' . $user->userId;
		}

		return $this->signedRequest('GET', '/streams?' . $queryString, null, false, true, true);
	}

	public function magazines($magazineId, $queryString) {
		return $this->signedRequest('GET', '/magazines/' . $magazineId . '?' . $queryString, null, true, true, true);
	}

	public function moments($momentId, $queryString) {
		return $this->signedRequest('GET', '/events/' . $momentId . '?' . $queryString, null, true, true, true);
	}

	public function generateGuestUser() {
        return $this->signedRequest('POST', '/users/guest', null, false, false);
    }

	public function seen($photoId, $isReader, $stream = null, $magazine = null, $moment = null) {
        /** add caller page */
        $queryParams = array();

        if (isset($stream)) {
            $queryParams[] = 'context=' . $stream;
        }
        if (isset($magazine)) {
            $queryParams[] = 'magazineId=' . $magazine;
        }
        if (isset($moment)) {
            $queryParams[] = 'eventId=' . $moment;
        }

        $endpoint = '/photos/' . $photoId . '/activity/seen/?';
        $endpoint .= implode('&', $queryParams);

        return $this->signedRequest('POST', $endpoint, null, $isReader, true, true, true);
    }
}
