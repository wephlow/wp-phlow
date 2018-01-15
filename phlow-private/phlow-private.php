<?php
/**
 * Plugin Name: phlow private
 * Description: phlow user management
 * Version: 1.4.1
 * Author: phlow
 * Author URI: http://phlow.com
 */

define('PHLOW_PRIV__PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(PHLOW_PRIV__PLUGIN_DIR . 'class.mailchimp.php');
require_once(PHLOW_PRIV__PLUGIN_DIR . 'libs/twitteroauth/autoload.php');
require_once(PHLOW_PRIV__PLUGIN_DIR . 'libs/ssga/ss-ga.class.php');

use Abraham\TwitterOAuth\TwitterOAuth;

class phlowPrivate {
	protected $plugin_dir;
	protected $api;

	public function __construct() {
		$this->plugin_dir = dirname(__FILE__);
		$this->plugin_url = get_site_url(null, 'wp-content/plugins/' . basename($this->plugin_dir));
		$this->ajax_url = admin_url('admin-ajax.php');
		$this->app_url = 'https://app.phlow.com';
		$this->cp_url = 'https://cp.phlow.com';
		$this->mailchimp = phlowMailChimp::getInstance();
		$this->ssga = new ssga(get_option('phlow_google_analytics_ua'));
		$this->add_actions();
		$this->add_shortcodes();
	}

	/**
	 * Check user access
	 */
	protected function has_access() {
		$public_key = get_option('phlow_clientPublicKey');
		return isset($public_key) && !empty($public_key);
	}

	/**
	 * Actions initialization
	 */
	protected function add_actions() {
		add_action('plugins_loaded', array($this, 'deps_init'));
		add_action('init', array($this, 'plugin_init'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue'));

		// Admin actions
		if (is_admin()) {
			add_action('admin_menu', array($this, 'admin_menu'));
		}

		// Async actions
		add_action('wp_ajax_nopriv_phlow_user_create', array($this, 'ajax_create_user'));
		add_action('wp_ajax_phlow_user_create', array($this, 'ajax_create_user'));
		add_action('wp_ajax_nopriv_phlow_user_login', array($this, 'ajax_login_user'));
		add_action('wp_ajax_phlow_user_login', array($this, 'ajax_login_user'));
		add_action('wp_ajax_nopriv_phlow_user_social_create', array($this, 'ajax_create_user_social'));
		add_action('wp_ajax_phlow_user_social_create', array($this, 'ajax_create_user_social'));
		add_action('wp_ajax_nopriv_phlow_twitter_request_token', array($this, 'ajax_twitter_request_token'));
		add_action('wp_ajax_phlow_twitter_request_token', array($this, 'ajax_twitter_request_token'));
		add_action('wp_ajax_nopriv_phlow_twitter_access_token', array($this, 'ajax_twitter_access_token'));
		add_action('wp_ajax_phlow_twitter_access_token', array($this, 'ajax_twitter_access_token'));
	}

	/**
	 * Shortcodes initialization
	 */
	protected function add_shortcodes() {
		if (!$this->has_access()) {
			return;
		}

		add_shortcode('phlow_registration', array($this, 'shortcode_registration'));
	}

	/**
	 * Dependencies initialization
	 */
	public function deps_init() {
		// phlow API
		if (class_exists('phlowAPI')) {
			$this->api = phlowAPI::getInstance();
		}
	}

	/**
	 * Plugin initialization
	 */
	public function plugin_init() {
		// localization
		load_plugin_textdomain('phlow_priv', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue() {
		wp_register_script('phlow_auth', $this->plugin_url . '/js/auth.js', array('jquery'), null, false);

		// js variables
		wp_localize_script('phlow_auth', 'phlowAuthAjax', array(
			'url' => $this->ajax_url,
			'facebook_app_id' => get_option('phlow_facebook_app_id'),
			'google_client_id' => get_option('phlow_google_client_id')
		));
	}

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		add_submenu_page('options-general.php', 'phlow-private', 'phlow private', 'manage_options', 'phlow-private.php', array($this, 'settings_page'));
	}

	/**
	 * Plugin settings page
	 */
	public function settings_page() {
		$url = admin_url('options-general.php?page=phlow-private.php');

		// Save settings
		if (isset($_POST['submit_phlow_auth_settings'])) {
			$facebookAppId = $_POST['phlow_facebook_app_id'];
			$facebookAppId = isset($facebookAppId) ? sanitize_text_field($facebookAppId) : '';
			update_option('phlow_facebook_app_id', $facebookAppId);

			$googleClientId = $_POST['phlow_google_client_id'];
			$googleClientId = isset($googleClientId) ? sanitize_text_field($googleClientId) : '';
			update_option('phlow_google_client_id', $googleClientId);

			$twitterSecret = $_POST['phlow_twitter_consumer_secret'];
			$twitterSecret = isset($twitterSecret) ? sanitize_text_field($twitterSecret) : '';
			update_option('phlow_twitter_consumer_secret', $twitterSecret);

			$twitterKey = $_POST['phlow_twitter_consumer_key'];
			$twitterKey = isset($twitterKey) ? sanitize_text_field($twitterKey) : '';
			update_option('phlow_twitter_consumer_key', $twitterKey);

			$mailchimpApiKey = $_POST['phlow_mailchimp_api_key'];
			$mailchimpApiKey = isset($mailchimpApiKey) ? sanitize_text_field($mailchimpApiKey) : '';
			update_option('phlow_mailchimp_api_key', $mailchimpApiKey);

			$googleAnalyticsUA = $_POST['phlow_google_analytics_ua'];
			$googleAnalyticsUA = isset($googleAnalyticsUA) ? sanitize_text_field($googleAnalyticsUA) : '';
			update_option('phlow_google_analytics_ua', $googleAnalyticsUA);

			echo phlow_priv_message_success(__('Settings have been saved successfully'));
		}

		echo '
			<div class="wrap" style="margin-top:30px">
				<h1>' . __('phlow authentication settings') . '</h1>
				<form method="post" action="' . $url . '">
					<h2 class="title" style="margin-bottom: 0;">' . __('Facebook') . '</h2>
					<table class="form-table">
						<tr>
							<th>
								<label>' . __('App ID') . '</label>
							</th>
							<td>
								<input
									type="text"
									name="phlow_facebook_app_id"
									class="regular-text"
									value="' . get_option('phlow_facebook_app_id') . '"
								/>
							</td>
						</tr>
					</table>
					<hr />
					<h2 class="title" style="margin-bottom: 0;">' . __('Google') . '</h2>
					<table class="form-table">
						<tr>
							<th>
								<label>' . __('Client ID') . '</label>
							</th>
							<td>
								<input
									type="text"
									name="phlow_google_client_id"
									class="regular-text"
									value="' . get_option('phlow_google_client_id') . '"
								/>
							</td>
						</tr>
					</table>
					<hr />
					<h2 class="title" style="margin-bottom: 0;">' . __('Twitter') . '</h2>
					<table class="form-table">
						<tr>
							<th>
								<label>' . __('Consumer Key') . '</label>
							</th>
							<td>
								<input
									type="text"
									name="phlow_twitter_consumer_key"
									class="regular-text"
									value="' . get_option('phlow_twitter_consumer_key') . '"
								/>
							</td>
						</tr>
						<tr>
							<th>
								<label>' . __('Consumer Secret') . '</label>
							</th>
							<td>
								<input
									type="text"
									name="phlow_twitter_consumer_secret"
									class="regular-text"
									value="' . get_option('phlow_twitter_consumer_secret') . '"
								/>
							</td>
						</tr>
					</table>
					<hr />
					<h2 class="title" style="margin-bottom: 0;">' . __('MailChimp') . '</h2>
					<table class="form-table">
						<tr>
							<th>
								<label>' . __('API Key') . '</label>
							</th>
							<td>
								<input
									type="text"
									name="phlow_mailchimp_api_key"
									class="regular-text"
									value="' . get_option('phlow_mailchimp_api_key') . '"
								/>
							</td>
						</tr>
					</table>
					<hr />
					<h2 class="title" style="margin-bottom: 0;">' . __('Google Analytics') . '</h2>
					<table class="form-table">
						<tr>
							<th>
								<label>' . __('UA-XXXXX-Y') . '</label>
							</th>
							<td>
								<input
									type="text"
									name="phlow_google_analytics_ua"
									class="regular-text"
									value="' . get_option('phlow_google_analytics_ua') . '"
								/>
							</td>
						</tr>
					</table>
					<hr />
					<p class="submit">
						<input
							type="submit"
							name="submit_phlow_auth_settings"
							class="button button-primary"
							value="' . __('Save Changes') . '"
						/>
					</p>
				</form>
			</div>
		';
	}

	/**
	 * Authentication shortcode
	 * @param [array] $atts
	 */
	public function shortcode_registration($atts) {
		wp_enqueue_script('phlow_auth');

        $dataParams = array();

        // Tags
        $tags = $atts['tags'];

        if (isset($tags) && !empty($tags)) {
            $tags = preg_replace('/[^0-9a-zA-Z,:]/', '', $tags);
            $tags = strtolower($tags);
            $dataParams[] = 'data-tags=' . $tags;
        }

        // MailChimp list id
        $list_id = $atts['list'];

        if (isset($list_id) && !empty($list_id)) {
        	$dataParams[] = 'data-list=' . sanitize_text_field($list_id);
        }

        // MailChimp group id
        $group_id = $atts['group'];

        if (isset($group_id) && !empty($group_id)) {
        	$dataParams[] = 'data-group=' . sanitize_text_field($group_id);
        }

        // MailChimp merge field
        $merge_field = $atts['referralcode'];

        if (isset($merge_field) && !empty($merge_field)) {
        	$dataParams[] = 'data-field=' . sanitize_text_field($merge_field);
        }

        // Redirect URL
        $redirect_url = $atts['redirection'];

        if (isset($redirect_url) && !empty($redirect_url)) {
        	$dataParams[] = 'data-redirection=' . sanitize_text_field($redirect_url);
        }

		$widgetId = 'phlow_registration_' . (time() + rand(1, 1000));
        $dataParams = implode(' ', $dataParams);

        ob_start();

		echo '
			<div id="' . $widgetId . '" ' . $dataParams . ' class="phlow-reg">
                <ul class="phlow-reg-tabs">
                	<li data-tab="register" class="active">Register</li>
                	<li data-tab="login">Already have account</li>
                </ul>
                <div class="phlow-reg-box">
                	<ul class="phlow-reg-errors"></ul>
					<div class="field-block">
						<input type="text" placeholder="Email" class="phlow-reg-email" />
					</div>
					<div class="field-block">
						<input type="password" placeholder="Password" class="phlow-reg-passwd" />
					</div>
	                <div class="phlow-reg-buttons">
					    <button class="phlow-reg-submit">Register</button>
	                    <button class="phlow-reg-facebook">Sign up with Facebook</button>
	                    <button class="phlow-reg-google">Sign up with Google</button>
	                    <button class="phlow-reg-twitter">Sign up with Twitter</button>
	                </div>
	                <div class="phlow-reg-loader">
	                	<div class="spin">
	                    	<svg width="32px" height="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid"><rect x="0" y="0" width="100" height="100" fill="none"></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(0 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(30 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.08333333333333333s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(60 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.16666666666666666s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(90 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.25s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(120 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.3333333333333333s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(150 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.4166666666666667s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(180 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.5s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(210 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.5833333333333334s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(240 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.6666666666666666s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(270 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.75s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(300 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.8333333333333334s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(330 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.9166666666666666s" repeatCount="indefinite"/></rect></svg>
	                    </div>
	                </div>
                </div>
			</div>
		';

		return ob_get_clean();
	}

	/**
	 * Sending register event to Google Analytics
	 */
	public function ga_send_register_event($data) {
		if (!is_array($data)) {
			return;
		}

		if (!isset($data['utm_campaign']) ||
			!isset($data['utm_content']) ||
			!isset($data['utm_source']) ||
			!isset($data['utm_medium']) ||
			!isset($data['utm_term']))
		{
			return;
		}

		$label = 'utm_source=' . $data['utm_source'] . '&' .
			'utm_medium=' . $data['utm_medium'] . '&' .
			'utm_campaign=' . $data['utm_campaign'] . '&' .
			'utm_term=' . $data['utm_term'] . '&' .
			'utm_content=' . $data['utm_content'];

		$this->ssga->set_event('phlow', 'register', $label);
        $this->ssga->send();
	}

	/**
	 * User creating request
	 */
	public function ajax_create_user() {
        $this->query = $_POST;

        $params = array(
            'email' => trim($this->query['email']),
            'username' => ''
        );

        // Prepare password
        $password = $this->query['password'];

        if (isset($password) && !empty($password)) {
            $password = hash('sha256', $password);
        }
        else {
            $password = '';
        }

        $params['password'] = $password;

        // Prepare favorite tags
        $tags = $this->query['tags'];

        if (isset($tags) && !empty($tags)) {
            $tags = preg_replace('/[^0-9a-zA-Z,:]/', '', $tags);
            $tags = strtolower($tags);
            $tags = explode(',', $tags);

            $favoriteTags = array();

            foreach ($tags as $value) {
                if (empty($value) || strlen($value) < 3) {
                    continue;
                }

                $favoriteTags[] = array(
                    'tag' => $value,
                    'score' => 1
                );
            }

            if (count($favoriteTags)) {
                $params['favoriteTags'] = $favoriteTags;
            }
        }

        // Prepare referral code
        $referralCode = $this->query['referralcode'];

        if (isset($referralCode) && !empty($referralCode)) {
        	$params['invitationCode'] = $referralCode;
        }

        // Prepare MailChimp list id
        $list_id = $this->query['list'];

        if (isset($list_id) && !empty($list_id)) {
        	$list_id = sanitize_text_field($list_id);
        }

        // Prepare MailChimp interest id
        $interest_id = $this->query['group'];

        if (isset($interest_id) && !empty($interest_id)) {
        	$interest_id = sanitize_text_field($interest_id);
        }

        // Register user
        $req = $this->api->register($params);

        if (isset($req->status) && $req->status != 200) {
        	echo json_encode(array(
        		'success' => false,
        		'errors' => array($req->message)
        	));
        	wp_die();
        }

        // Send GA event
        $ga_data = $this->query['ga'];
        $this->ga_send_register_event($ga_data);

        // Set private and public keys
        $this->api->setKeys($req->privateKey, $req->publicKey);

        // Get user's data
        $req_me = $this->api->me(true);

        // Prepare MailChimp merge fields
        $field_name = $this->query['field'];
        $merge_fields = array();

        if (isset($field_name) && !empty($field_name)) {
        	$field_name = sanitize_text_field($field_name);
    		$merge_fields[$field_name] = $req_me->meta->invitationCode;
        }

        // Add user to MailChimp
        $this->mailchimp->addMember($req->user->email, $list_id, $interest_id, $merge_fields);

        echo json_encode(array(
        	'success' => true
        ));
        wp_die();
    }

    /**
     * User authorization request
     */
    public function ajax_login_user() {
        $this->query = $_POST;

        $params = array(
            'email' => trim($this->query['email'])
        );

        // Prepare password
        $password = $this->query['password'];

        if (isset($password) && !empty($password)) {
            $password = hash('sha256', $password);
        }
        else {
            $password = '';
        }

        $params['password'] = $password;

        // Prepare referral code
        $referralCode = $this->query['referralcode'];

        if (isset($referralCode) && !empty($referralCode)) {
        	$params['invitationCode'] = $referralCode;
        }

        // Prepare MailChimp list id
        $list_id = $this->query['list'];

        if (isset($list_id) && !empty($list_id)) {
        	$list_id = sanitize_text_field($list_id);
        }

        // Prepare MailChimp interest id
        $interest_id = $this->query['group'];

        if (isset($interest_id) && !empty($interest_id)) {
        	$interest_id = sanitize_text_field($interest_id);
        }

        // Login user
        $req = $this->api->login($params);

        if (isset($req->status) && $req->status != 200) {
        	echo json_encode(array(
        		'success' => false,
        		'errors' => array($req->message)
        	));
        	wp_die();
        }

        // Set private and public keys
        $this->api->setKeys($req->privateKey, $req->publicKey);

        // Get user's data
        $req_me = $this->api->me(true);

        // Prepare MailChimp merge fields
        $field_name = $this->query['field'];
        $merge_fields = array();

        if (isset($field_name) && !empty($field_name)) {
        	$field_name = sanitize_text_field($field_name);
    		$merge_fields[$field_name] = $req_me->meta->invitationCode;
        }

        // Add user to MailChimp
        $this->mailchimp->addMember($req->user->email, $list_id, $interest_id, $merge_fields);

        echo json_encode(array(
        	'success' => true
        ));
        wp_die();
    }

    /**
     * User social creating request
     */
    public function ajax_create_user_social() {
        $this->query = $_POST;
        $params = array();

        // Facebook token
        if (isset($this->query['facebookToken'])) {
            $params['facebookToken'] = trim($this->query['facebookToken']);
        }

        // Google token
        if (isset($this->query['googleToken'])) {
            $params['googleToken'] = trim($this->query['googleToken']);
        }

        // Twitter tokens
        if (isset($this->query['twitterTokenSecret']) &&
        	isset($this->query['twitterToken']))
        {
        	$params['twitter'] = array(
        		'token_secret' => trim($this->query['twitterTokenSecret']),
        		'token' => trim($this->query['twitterToken'])
    		);
        }

        // Prepare favorite tags
        $tags = $this->query['tags'];

        if (isset($tags) && !empty($tags)) {
            $tags = preg_replace('/[^0-9a-zA-Z,:]/', '', $tags);
            $tags = strtolower($tags);
            $tags = explode(',', $tags);

            $favoriteTags = array();

            foreach ($tags as $value) {
                if (empty($value) || strlen($value) < 3) {
                    continue;
                }

                $favoriteTags[] = array(
                    'tag' => $value,
                    'score' => 1
                );
            }

            if (count($favoriteTags)) {
                $params['favoriteTags'] = $favoriteTags;
            }
        }

        // Prepare referral code
        $referralCode = $this->query['referralcode'];

        if (isset($referralCode) && !empty($referralCode)) {
        	$params['invitationCode'] = $referralCode;
        }

        // Prepare MailChimp list id
        $list_id = $this->query['list'];

        if (isset($list_id) && !empty($list_id)) {
        	$list_id = sanitize_text_field($list_id);
        }

        // Prepare MailChimp interest id
        $interest_id = $this->query['group'];

        if (isset($interest_id) && !empty($interest_id)) {
        	$interest_id = sanitize_text_field($interest_id);
        }

        if (!isset($params['facebookToken']) &&
        	!isset($params['googleToken']) &&
        	!isset($params['twitter']))
        {
            echo json_encode(array(
                'success' => false,
                'errors' => array('Please provide access token')
            ));
            wp_die();
        }

        // Create/login via social account
        $req = $this->api->registerSocial($params);

        if (isset($req->status) && $req->status != 200) {
        	echo json_encode(array(
        		'success' => false,
        		'errors' => array($req->message)
        	));
        	wp_die();
        }

        // Send GA event
        $ga_data = $this->query['ga'];
        $this->ga_send_register_event($ga_data);

        // Set private and public keys
        $this->api->setKeys($req->privateKey, $req->publicKey);

        // Get user's data
        $req_me = $this->api->me(true);

        // Prepare MailChimp merge fields
        $field_name = $this->query['field'];
        $merge_fields = array();

        if (isset($field_name) && !empty($field_name)) {
        	$field_name = sanitize_text_field($field_name);
    		$merge_fields[$field_name] = $req_me->meta->invitationCode;
        }

        // Add user to MailChimp
        $this->mailchimp->addMember($req->user->email, $list_id, $interest_id, $merge_fields);

        echo json_encode(array(
        	'success' => true
        ));
        wp_die();
    }

    /**
     * Obtaining Twitter request token
     */
    public function ajax_twitter_request_token() {
    	$consumerSecret = get_option('phlow_twitter_consumer_secret');
    	$consumerKey = get_option('phlow_twitter_consumer_key');

    	$conn = new TwitterOAuth($consumerKey, $consumerSecret);
    	$res = $conn->oauth('oauth/request_token', array(
    		'oauth_callback' => get_site_url() . '/twitter/auth'
    	));

    	echo json_encode(array(
    		'token' => $res['oauth_token']
    	));
        wp_die();
    }

    /**
     * Obtaining Twitter access token
     */
    public function ajax_twitter_access_token() {
    	$this->query = $_GET;
		$errors = array();

		// Validate oauth verifier
		$verifier = $this->query['verifier'];

		if (!isset($verifier) ||
			strlen($verifier) > 32)
		{
			$errors[] = __('Invalid verifier parameter');
		}
		else {
			$verifier = trim($this->query['verifier']);
		}

		// Validate oauth token
		$token = $this->query['token'];

		if (!isset($token) ||
			strlen($token) > 32)
		{
			$errors[] = __('Invalid token parameter');
		}
		else {
			$token = trim($this->query['token']);
		}

		// Check errors
		if (count($errors)) {
			$response = array(
				'success' => false,
				'errors' => $errors
			);
		}
		else {
			$consumerSecret = get_option('phlow_twitter_consumer_secret');
    		$consumerKey = get_option('phlow_twitter_consumer_key');

			$conn = new TwitterOAuth($consumerKey, $consumerSecret);
	    	$res = $conn->oauth('oauth/access_token', array(
	    		'oauth_verifier' => $verifier,
	    		'oauth_token' => $token
	    	));

	    	$response = array(
	    		'success' => true,
	    		'data' => array(
	    			'token_secret' => $res['oauth_token_secret'],
	    			'token' => $res['oauth_token']
    			)
			);
		}

    	echo json_encode($response);
        wp_die();
    }
}

$phlow_priv = new phlowPrivate();

/**
 * Plugin activation
 */
function phlow_priv_activate() {
	// check dependencies
	if (!is_plugin_active('phlow/phlow.php')) {
		$url = wp_get_referer();
		$msg = __("Sorry, but this plugin requires <b>phlow plugin</b> to be installed and active. <a href=$url>Get back</a>");
		wp_die($msg);
	}

	delete_option('phlow_priv_show_activation_message');
}

register_activation_hook(__FILE__, 'phlow_priv_activate');

/**
 * Admin notices
 */
function phlow_priv_admin_notice() {
	$show_once = get_option('phlow_priv_show_activation_message');

	if (!$show_once) {
		$url = admin_url('options-general.php?page=phlow-settings.php');
		$msg = __("<b>phlow private</b> is activated! Visit <a href=$url>the plugin settings page</a> to start using the plugin");
		echo phlow_priv_message_success($msg);
		update_option('phlow_priv_show_activation_message', true);
	}
}

add_action('admin_notices', 'phlow_priv_admin_notice');

/**
 * Success message
 */
function phlow_priv_message_success($msg) {
	if (is_array($msg)) {
		$msg = join('<br />', $msg);
	}

	return '
		<div class="notice notice-success is-dismissible">
			<p>' . $msg . '</p>
		</div>
	';
}

/**
 * Error message
 */
function phlow_priv_message_error($msg) {
	if (is_array($msg)) {
		$msg = join('<br />', $msg);
	}

	return '
		<div class="notice notice-error">
			<p>' . $msg . '</p>
		</div>
	';
}
