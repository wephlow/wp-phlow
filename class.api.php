<?php
/**
 * Phlow API class
 */

class phlowAPI {
	private static $instance = null;

    public function __construct() {
    	$isDev = boolval(get_option('phlow_dev'));
    	$this->apiUrl = $isDev ? 'https://api-dev.phlow.com' : 'https://api.phlow.com';
    	$this->session = phlowSession::getInstance();
    }

	// The singleton instance
	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

    private function isFrontRequest() {
        $script_filename = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';

        if ((defined('DOING_AJAX') && DOING_AJAX)) {
            $ref = '';

            if (!empty($_REQUEST['_wp_http_referer'])) {
                $ref = wp_unslash($_REQUEST['_wp_http_referer']);
            }
            elseif (!empty($_SERVER['HTTP_REFERER'])) {
                $ref = wp_unslash($_SERVER['HTTP_REFERER']);
            }

            if (strpos($ref, admin_url()) === false && basename($script_filename) === 'admin-ajax.php') {
                return true;
            }
        }
        elseif (!is_admin()) {
            return true;
        }

        return false;
    }

    private function getUserCredentials() {
        $sess = $this->session->get();

        if ($this->isFrontRequest() && $sess) {
            $sessionKeys = array(
                'privateKey' => $sess['privateKey'],
                'publicKey' => $sess['publicKey']
            );
        }
        else {
            $sessionKeys = array(
                'privateKey' => get_option('phlow_sessionPrivateKey'),
                'publicKey' => get_option('phlow_sessionPublicKey')
            );
        }

        return $sessionKeys;
    }

	private function generateSignature($time, $method, $uri, $body, $isUserSigned=true) {
		$clientKeys = array(
            'privateKey' => get_option('phlow_clientPrivateKey'),
            'publicKey' => get_option('phlow_clientPublicKey')
        );

		if ($isUserSigned){
            $sessionKeys = $this->getUserCredentials();
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

	private function signedRequest($method, $endpoint, $body=null, $isUserSigned=true, $waitForResponse=true, $isAjaxPage=false) {
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

        $httpHeaders[] = 'X-PHLOW:' . $this->generateSignature($time, $method, $uri, $body, $isUserSigned);

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

		return ($status == 200) ? $result : (object) array('error' => $result);
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

    public function access($data = null) {
        return $this->signedRequest('POST', '/access', $data, false);
    }

	public function me() {
		return $this->signedRequest('GET', '/users/me', null);
	}

	public function userMagazines($userId) {
		$endpoint = '/users/' . $userId . '/magazines';
		return $this->signedRequest('GET', $endpoint);
	}

	public function searchMagazines($string) {
		$endpoint = '/search/magazines?q=' . $string;
		return $this->signedRequest('GET', $endpoint);
	}

	public function searchMoments($string) {
		$endpoint = '/events/search?name=' . $string;
		return $this->signedRequest('GET', $endpoint);
	}

	public function streams($queryString, $owned=false) {
		if ($owned) {
			$sess = $this->session->get();
			$queryString .= '&user=' . $sess['userId'];
		}

		$endpoint = '/streams?' . $queryString;
		return $this->signedRequest('GET', $endpoint, null, true, true);
	}

	public function magazines($magazineId, $queryString) {
		$endpoint = '/magazines/' . $magazineId . '?' . $queryString;
		return $this->signedRequest('GET', $endpoint, null, true, true);
	}

	public function moments($momentId, $queryString) {
		$endpoint = '/events/' . $momentId . '?' . $queryString;
		return $this->signedRequest('GET', $endpoint, null, true, true);
	}

	public function generateGuestUser() {
        return $this->signedRequest('POST', '/users/guest', null, false);
    }

	public function seen($photoId, $stream = null, $magazine = null, $moment = null) {
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

        return $this->signedRequest('POST', $endpoint, null, true, true, true);
    }

    public function forgetSeen($context) {
    	$endpoint = '/streams/' . $context . '/forget-seen';
    	return $this->signedRequest('POST', $endpoint, null, true, true, true);
    }
}
