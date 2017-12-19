<?php
/**
 * MailChimp API wrapper
 */

class mailchimp {
	// The singleton instance
	private static $instance = null;

	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function sendRequest($method, $endpoint, $body) {
		$apiKey = get_option('phlow_mailchimp_api_key');
    	$dc = substr($apiKey, strpos($apiKey, '-') + 1);
    	$apiUrl = 'https://' . $dc . '.api.mailchimp.com/3.0';
    	$endpointUrl = $apiUrl . $endpoint;

    	$args = array(
    		'method' => $method,
    		'headers' => array(
    			'Authorization' => 'Basic ' . base64_encode('user:' . $apiKey)
    		),
    		'body' => json_encode($body)
    	);

    	$req = wp_remote_post($endpointUrl, $args);
    	$res = json_decode(wp_remote_retrieve_body($req));

    	return $res;
	}

	public function addMember($email, $listId, $interestId, $mergeFields) {
		if (!isset($email) || !isset($listId)) {
    		return null;
    	}

    	$endpoint = '/lists/' . $listId . '/members';

    	$body = array(
    		'email_address' => $email,
    		'status' => 'subscribed'
    	);

    	if (isset($interestId) && !empty($interestId)) {
    		$body['interests'] = array();
    		$body['interests'][$interestId] = true;
    	}

    	if (isset($mergeFields) && !empty($mergeFields)) {
    		$body['merge_fields'] = $mergeFields;
    	}

    	return $this->sendRequest('POST', $endpoint, $body);
	}
}
