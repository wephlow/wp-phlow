<?php
/**
 * Phlow session class
 */

class phlowSession {
	private static $instance = null;
	private static $optionName = 'phlow_sessions';
	private static $cookieName = 'phlowUPK';

	// The singleton instance
	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Parse user data
	 * @param [object] $data
	 * @return [array]
	 */
	private function parseUserData($data) {
		$user = $data->user;

		return array(
			'ip' => $_SERVER['REMOTE_ADDR'],
			'browser' => $_SERVER['HTTP_USER_AGENT'],
			// 'referer' => $_SERVER['HTTP_REFERER'],
			'expiration' => time() + (1 * 24 * 60 * 60), // 1 day
			'privateKey' => $data->privateKey,
			'publicKey' => $data->publicKey,
			'isGuest' => boolval($user->meta->isGuest),
			'userId' => $user->userId
		);
	}

	/**
	 * Set session cookie
	 * @param [string] $value
	 */
	private function setCookie($value) {
		if (!isset($value) || empty($value)) {
			return;
		}

		$name = self::$cookieName;
		unset($_COOKIE[$name]);

		$expire = time() + (1 * 24 * 60 * 60); // 1 day
		$path = '/';
		$domain = ''; // restrict the cookie to a single host
		$secure = is_ssl();
		$httponly = true;

		$_COOKIE[$name] = $value;
		setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	}

	/**
	 * Get all sessions
	 * @return [array]
	 */
	private function getSessions() {
		$sessions = get_option(self::$optionName);
		$sessions = $sessions ? unserialize($sessions) : array();
		return $sessions;
	}

	/**
	 * Delete outdated and expired sessions
	 * @param [array] $session
	 */
	private function cleanSessions($session) {
		if (!isset($session)) {
			return;
		}

		$sessions = $this->getSessions();

		$filtered = array_filter($sessions, function($item) use($session) {
			// Check for expired session
			if ($item['expiration'] < time()) {
				return false;
			}
			// Check for outdated session
			elseif ($session['publicKey'] !== $item['publicKey'] &&
				$session['userId'] == $item['userId'] &&
				$session['browser'] == $item['browser'] &&
				$session['ip'] == $item['ip'])
			{
				return false;
			}

			return true;
		});

		update_option(self::$optionName, serialize($filtered));
	}

	/**
	 * Set user session
	 * @param [object] $data
	 */
	public function set($data) {
		$data = $this->parseUserData($data);
		$sessions = $this->getSessions();
		$sessions[] = $data;

		update_option(self::$optionName, serialize($sessions));
		$this->setCookie($data['publicKey']);
		$this->cleanSessions($data);
	}

	/**
	 * Get user session
	 * @return [array]
	 */
	public function get() {
		$userKey = $_COOKIE[self::$cookieName];
		$sessions = $this->getSessions();

		if (!isset($userKey) || !count($sessions)) {
			return null;
		}

		$session = null;

		foreach ($sessions as $item) {
			if ($item['publicKey'] == $userKey && $item['expiration'] > time()) {
				$session = $item;
				break;
			}
		}

		return $session;
	}
}
