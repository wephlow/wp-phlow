<?php
/**
 * Plugin Name: wp-phlow
 * Description: phlow allows you to embed a carousel or a widget of photographs relevant to a specific theme or context. Be it #wedding#gowns, #portraits#blackandwhite or #yoga, phlow provides you with images that are fresh and relevant. To get started, log through a phlow account (it is 100% free) and either embed the stream in your WYSIWYG editor or add a widget to your blog.
 * Version: 1.3.5
 * Author: phlow
 * Author URI: http://phlow.com
 */

define('PHLOW__PLUGIN_DIR', plugin_dir_path(__FILE__));
require_once(PHLOW__PLUGIN_DIR . 'class.api.php');
require_once(PHLOW__PLUGIN_DIR . 'BFIGitHubPluginUpdater.php');
require_once(PHLOW__PLUGIN_DIR . 'libs/twitteroauth/autoload.php');

use Abraham\TwitterOAuth\TwitterOAuth;

class phlow {
	protected $_plugin_id = 'wp-phlow';
	protected $_plugin_dir ;
	public static $activation_transient;
	public static $plugin_folder = 'wp-phlow';
	public $shortcode_tag = 'phlow_stream';

	public function __construct() {
		$this->addActions();
		$this->addShortcodes();
		$this->_plugin_dir = dirname(__FILE__);
		$this->_plugin_url = get_site_url(null, 'wp-content/plugins/' . basename($this->_plugin_dir));
		$this->ajax_url = admin_url('admin-ajax.php');
		$this->api = api::getInstance();
	}

	protected function addActions() {
	    add_action( 'init', array($this, 'phlow_localize'));
		add_action( 'admin_enqueue_scripts', array($this,'enqueue') );
		add_action( 'widgets_init', 'phlow_register_widget' );
		add_action( 'wp_enqueue_scripts', array($this,'enqueue') );

		if( is_admin() ) {
			add_action('admin_head', array( $this, 'admin_head') );
			add_action('admin_menu', array($this,'phlow_menu'));

            new BFIGitHubPluginUpdater( __FILE__, 'wephlow', "wp-phlow" );
		}

		// async actions
		add_action('wp_ajax_phlow_auth_check', array($this, 'phlow_ajax_check_auth'));
		add_action('wp_ajax_phlow_source_get', array($this, 'phlow_ajax_get_source'));
		add_action('wp_ajax_phlow_type_get', array($this, 'phlow_ajax_get_type'));
		add_action('wp_ajax_phlow_magazines_get', array($this, 'phlow_ajax_get_magazines'));
		add_action('wp_ajax_phlow_magazines_search', array($this, 'phlow_ajax_search_magazines'));
		add_action('wp_ajax_phlow_moments_search', array($this, 'phlow_ajax_search_moments'));
        add_action('wp_ajax_nopriv_phlow_images_get', array($this, 'phlow_ajax_get_images'));
        add_action('wp_ajax_phlow_images_get', array($this, 'phlow_ajax_get_images'));
        add_action('wp_ajax_nopriv_phlow_photo_seen', array($this, 'phlow_ajax_photo_seen'));
        add_action('wp_ajax_phlow_photo_seen', array($this, 'phlow_ajax_photo_seen'));
        add_action('wp_ajax_nopriv_phlow_user_create', array($this, 'phlow_ajax_create_user'));
        add_action('wp_ajax_phlow_user_create', array($this, 'phlow_ajax_create_user'));
        add_action('wp_ajax_nopriv_phlow_user_social_create', array($this, 'phlow_ajax_create_user_social'));
        add_action('wp_ajax_phlow_user_social_create', array($this, 'phlow_ajax_create_user_social'));
        add_action('wp_ajax_nopriv_phlow_twitter_request_token', array($this, 'phlow_ajax_twitter_request_token'));
        add_action('wp_ajax_phlow_twitter_request_token', array($this, 'phlow_ajax_twitter_request_token'));
        add_action('wp_ajax_nopriv_phlow_twitter_access_token', array($this, 'phlow_ajax_twitter_access_token'));
        add_action('wp_ajax_phlow_twitter_access_token', array($this, 'phlow_ajax_twitter_access_token'));
	}

    public function addShortcodes()
    {
        if(get_option('phlow_clientPublicKey') != null || get_option('phlow_clientPublicKey') != '' ){
            add_shortcode('phlow_stream', array($this, 'shortcode_phlow_page'));
            add_shortcode('phlow_group', array($this, 'shortcode_groups_image'));
            add_shortcode('phlow_line', array($this, 'shortcode_line_images'));
            add_shortcode('phlow_registration', array($this, 'shortcode_registration'));
        }
    }

	public function phlow_localize() {
        // Localization
		load_plugin_textdomain('phlow', false, dirname(plugin_basename(__FILE__)). "/languages" );

        if (!isset($_COOKIE['phlow_sessionPrivateKey'])) {
            $return = $this->api->generateGuestUser();

            $this->api->setKeys($return->privateKey, $return->publicKey);

            setcookie("phlow_sessionPrivateKey",$return->privateKey,time()+60*60*24*30, '/');
            setcookie("phlow_sessionPublicKey",$return->publicKey,time()+60*60*24*30, '/');
        } else {
            $this->api->setKeys($_COOKIE['phlow_sessionPrivateKey'], $_COOKIE['phlow_sessionPublicKey']);
        }
    }

    public function enqueue() {
    	// styles
    	wp_register_style('ph_css', $this->_plugin_url . '/css/tipped/tipped.css', false, '1.0.0');
        wp_enqueue_style('ph_css');
        wp_enqueue_style('phlow_shortcode', $this->_plugin_url .'/mce_plugin/css/mce-button.css' );
        wp_enqueue_style('phlow_autocomplete', $this->_plugin_url .'/css/autocomplete/easy-autocomplete.min.css');
        wp_enqueue_style('phlow', $this->_plugin_url .'/css/phlow.css');

        // scripts
        wp_register_script('ph_script', $this->_plugin_url .'/js/tipped/tipped.js', array('jquery'), null, false);
        wp_enqueue_script('ph_script');
        wp_register_script('phlow', $this->_plugin_url . '/js/generator.js', array('jquery'), null, false);
        wp_enqueue_script('phlow');
        wp_register_script('phlow_autocomplete', $this->_plugin_url . '/js/autocomplete/jquery.easy-autocomplete.min.js', array('jquery'), null, false);
        wp_enqueue_script('phlow_autocomplete');
        wp_register_script('phlow_clipboard', $this->_plugin_url . '/js/clipboard.min.js', array(), null, false);
        wp_enqueue_script('phlow_clipboard');
        wp_register_script('phlow_visible', $this->_plugin_url . '/js/jquery-visible/jquery.visible.min.js', array('jquery'), null, false);
        wp_enqueue_script('phlow_visible');
        wp_register_script('phlow_loader', $this->_plugin_url . '/js/loader.js', array('jquery'), null, false);
        wp_register_script('phlow_registration', $this->_plugin_url . '/js/registration.js', array('jquery'), null, false);

		// js variables
		wp_localize_script('phlow', 'phlowAjax', array(
			'url' => $this->ajax_url,
			'facebook_app_id' => get_option('phlow_facebook_app_id'),
			'google_client_id' => get_option('phlow_google_client_id')
		));
    }

    private function generatePoweredByMessage($atts){
        $source = $atts['source'];
        $context = $atts['context'];

        $returnValue = ' powered by ';

        switch (strtolower($source)){
            case 'magazine':
                $returnValue = 'Magazine ' .$returnValue;
                break;
            case 'moment':
                $returnValue = 'Moment ' .$returnValue;
                break;
            default:
                $returnContext = '';
                $contexts = explode(',', $context);
                foreach ($contexts as $singleContext){
                    $returnContext = '#'.$singleContext . ' ';
                }
                $returnValue = $returnContext.$returnValue;
                break;
        }

        return $returnValue;
    }

    /**
     * Load images
     * make sure that the request for the images contains ?size=150x150c
     */
    private function phlowLoadImages($atts, $limit = 10) {
    	$source = $atts['source'];
		$context = $atts['context'];
		$clean = $atts['clean'];
		$nudity = $atts['nudity'];
		$violence = $atts['violence'];
		$owned = $atts['owned'];

		$images = array();
		$counter = 0;

        $queryString = 'size=150x150c&nudity='.$nudity.'&violence='.$violence;

		// magazine
		if ($source == 'magazine') {
			$photos = $this->api->magazines($context, $queryString)->photos;

			foreach ($photos as $photo) {
				$images[] = array(
					'id' => $photo->photoId,
					'url' => 'https://app.phlow.com/magazine/' . $context . '?autoscroll=1',
					'src' => $photo->url
				);

				if ($counter++ >= ($limit-1)) {
					break;
				}
			}
		}
		// moment
		else if ($source == 'moment') {
			$photos = $this->api->moments($context, $queryString)->photos;

			foreach ($photos as $photo) {
				$images[] = array(
					'id' => $photo->photoId,
					'url' => 'https://app.phlow.com/moment/' . $context . '?autoscroll=1',
					'src' => $photo->url
				);

				if ($counter++ >= ($limit-1)) {
					break;
				}
			}
		}
		// streams
		else {
		    $queryString = 'context=' . $context . '&' . $queryString;

            $owned = (isset($owned) && $owned==1) ? true : false;

		    $photos = $this->api->streams($queryString, $owned)->photos;

		    if (isset($photos) && sizeof($photos)>0) {
                foreach ($photos as $photo) {
                    $images[] = array(
                    	'id' => $photo->photoId,
                        'url' => 'https://app.phlow.com/stream/' . $context . '/photo/' . $photo->photoId . '?autoscroll=1',
                        'src' => $photo->url
                    );

                    if ($counter++ >= ($limit - 1)) {
                        break;
                    }
                }
            }
		}

		return $images;
    }

    // phlow group widget
    public function shortcode_groups_image($atts) {
		wp_enqueue_script('phlow_loader');

        $nudity = $atts['nudity'];
		$violence = $atts['violence'];
		$owned = $atts['owned'];

		$dataParams = array(
    		'data-type=group',
    		'data-source=' . $atts['source'],
			'data-context=' . $atts['context'],
			// 'data-clean=' . $atts['clean'],
			'data-nudity=' . ((isset($nudity) && !empty($nudity)) ? $nudity : 0),
			'data-violence=' . ((isset($violence) && !empty($violence)) ? $violence : 0),
			'data-owned=' . ((isset($owned) && !empty($owned)) ? $owned : 0)
    	);

    	$dataParams = join(' ', $dataParams);
    	$poweredBy = $this->generatePoweredByMessage($atts);
    	$widgetId = 'phlow_widget_' . (time() + rand(1, 1000));

		ob_start();

    	echo '
    	<div class="image-list" id="' . $widgetId . '" ' . $dataParams . '>
    		<ul class="groups-images">
	    		<div class="powered-by">
	    			<span class="first-child">' . __($poweredBy) . '</span>
	    			<span> </span>
	    			<a class="plugin-url" target="_blank" href="https://app.phlow.com">
	    				<span class="phlow-red">phlow</span>
	    				<span> </span>
	    				<i class="icon-logo-small"></i>
	    			</a>
	    		</div>
    		</ul>
    	</div>
    	';

    	return ob_get_clean();
    }

	// phlow line widget
	public function shortcode_line_images($atts) {
        wp_enqueue_script('phlow_loader');

		$nudity = $atts['nudity'];
		$violence = $atts['violence'];
		$owned = $atts['owned'];

		$dataParams = array(
    		'data-type=line',
    		'data-source=' . $atts['source'],
			'data-context=' . $atts['context'],
			// 'data-clean=' . $atts['clean'],
			'data-nudity=' . ((isset($nudity) && !empty($nudity)) ? $nudity : 0),
			'data-violence=' . ((isset($violence) && !empty($violence)) ? $violence : 0),
			'data-owned=' . ((isset($owned) && !empty($owned)) ? $owned : 0)
    	);

    	$dataParams = join(' ', $dataParams);
    	$poweredBy = $this->generatePoweredByMessage($atts);
		$widgetId = 'phlow_widget_' . (time() + rand(1, 1000));

		ob_start();

    	echo '
		<div class="image-list-horizontal phlows" id="' . $widgetId . '" ' . $dataParams . '>
			<ul class="line-images">
				<div class="powered-by">
					<span class="first-child">' . __($poweredBy) . '</span>
					<span> </span>
					<a class="plugin-url" target="_blank" href="https://app.phlow.com">
						<span class="phlow-red">phlow</span>
						<span> </span>
						<i class="icon-logo-small"></i>
					</a>
				</div>
			</ul>
		</div>
		';

		return ob_get_clean();
	}

	// phlow stream widget
	public function shortcode_phlow_page($atts) {
		$source = $atts['source'];
		$context = $atts['context'];
		$width = $atts['width'];
		$height = $atts['height'];
		$clean = $atts['clean'];
		$nudity = $atts['nudity'];
		$violence = $atts['violence'];
        $owned = $atts['owned'];

		$url = 'http://app.phlow.com';

		if ($source == 'magazine') {
			$url .= '/magazine/' . $context;
		}
		else if ($source == 'moment') {
			$url .= '/moment/' . $context;
		}
		else {
			$url .= '/stream/' . $context;
		}

		$url .= '?cleanstream=' . $clean;
		$url .= '&nudity=' . $nudity;
		$url .= '&violence=' . $violence;
		$url .= '&owned=' . $owned;

		ob_start();
		echo '<iframe src="' . $url . '" width="' . $width . '" height="' . $height . '" frameborder="0"></iframe>';
		return ob_get_clean();
	}

	/**
	 * generate shortcode
	 */
	public function phlow_generate_shortcode($query) {
		// source
		$source = $query['source'];

        // type
        $type = $query['type'];

		if ($source == 1) {
			$src_val = 'magazine';
			$context = trim($query['mymagazine']);
		}
		else if ($source == 2) {
			$src_val = 'magazine';
			$context = trim($query['magazine_id']);
		}
		else if ($source == 3) {
			$src_val = 'moment';
			$context = trim($query['moment_id']);
		}
		else {
			$src_val = 'streams';
            if ( $type != 2 ) {
                $context = str_replace(',', '-', trim($query['tags']));
            } else {
                $context = str_replace('-', ',', trim($query['tags']));
            }
			$context = str_replace(' ', '', $context);
			$context = str_replace('#', '', $context);

            $owned = isset($query['owned']) ? 1 : 0;
		}

		if ($type == 1) {
			$type_val = 'phlow_line';
		}
		else if ($type == 2) {
			$type_val = 'phlow_stream';
			$width = $query['width'];
			$height = $query['height'];
		}
		else {
			$type_val = 'phlow_group';
		}

		// nudity
		$nudity = isset($query['nudity']) ? 1 : 0;

		// violence
		$violence = isset($query['violence']) ? 1 : 0;

		// build shortcode
		$shortcode = '[' . $type_val;
		$shortcode .= ' source="' . $src_val . '"';
		$shortcode .= ' context="' . $context . '"';
		$shortcode .= ' nudity="' . $nudity . '"';
		$shortcode .= ' violence="' . $violence . '"';
		if (isset($owned)) $shortcode .= ' owned="' . $owned . '"';

		if (isset($width)) {
			$shortcode .= ' width="' . $width . '"';
		}

		if (isset($height)) {
			$shortcode .= ' height="' . $height . '"';
		}

		$shortcode .= ']';

		return $shortcode;
	}

	// phlow registration widget
	public function shortcode_registration($atts) {
        wp_enqueue_script('phlow_registration');

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

		$widgetId = 'phlow_registration_' . (time() + rand(1, 1000));
        $dataParams = implode(' ', $dataParams);

        ob_start();

		echo '
			<div id="' . $widgetId . '" ' . $dataParams . ' class="phlow-reg">
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
		';

		return ob_get_clean();
	}

	public function phlow_menu() {
		add_submenu_page( 'options-general.php', 'phlow-settings', 'phlow', 'manage_options', 'phlow-settings.php', array($this, 'phlow_settings') );
		add_submenu_page( 'options-general.php', 'phlow-auth', null, 'manage_options', 'phlow-auth.php', array($this, 'phlow_auth_settings') );
	}

	public function phlow_settings() {
		// Save default settings
		if (isset($_POST['submit_settings'])) {
			echo phlow_message_success(__('Settings updated successfully'));

			$nudity = isset($_POST['nudity']) ? '1' : '0';
			update_option('default_nudity', $nudity);

			$violence = isset($_POST['violence']) ? '1' : '0';
			update_option('default_violence', $violence);
		}

		// Generate shortcode
		if (isset($_POST['submit_generator'])) {
			$nudity = isset($_POST['nudity']) ? 1 : 0;
			$violence = isset($_POST['violence']) ? 1 : 0;
			$source = (int) $_POST['source'];
			$type = (int) $_POST['type'];
			$errors = array();

			// stream validation
			if ($source == 0) {
				$tags = trim($_POST['tags']);

				if (empty($tags)) {
					$errors[] = __('Streams is required field');
				}
			}

			// my magazine validation
			if ($source == 1) {
				$context = trim($_POST['mymagazine']);

				if (empty($context)) {
					$errors[] = __('Magazine is required field');
				}
			}

			// public magazine validation
			if ($source == 2) {
				$magazine_name = trim($_POST['magazine_name']);
				$magazine_id = trim($_POST['magazine_id']);

				if (empty($magazine_id)) {
					$errors[] = __('Magazine is required field');
				}
			}

			// moment validation
			if ($source == 3) {
				$moment_name = trim($_POST['moment_name']);
				$moment_id = trim($_POST['moment_id']);

				if (empty($moment_id)) {
					$errors[] = __('Moment is required field');
				}
			}

			// stream type
			if ($type == 2) {
				// width and height validation
				$width = (int) trim($_POST['width']);
				$height = (int) trim($_POST['height']);

				if (empty($width)) {
					$errors[] = __('Width is required field');
				}

				if (empty($height)) {
					$errors[] = __('Height is required field');
				}
			}

			// Update form data
			update_option('nudity', $nudity);
			update_option('violence', $violence);
			update_option('source', $source);
			update_option('type', $type);

			if (isset($tags)) {
				update_option('tags', $tags);
			}

			if (isset($magazine_name)) {
				update_option('magazine_name', $magazine_name);
			}

			if (isset($magazine_id)) {
				update_option('magazine_id', $magazine_id);
			}

			if (isset($moment_name)) {
				update_option('moment_name', $moment_name);
			}

			if (isset($moment_id)) {
				update_option('moment_id', $moment_id);
			}

			if (isset($width)) {
				update_option('width', $width);
			}

			if (isset($height)) {
				update_option('height', $height);
			}

			// Check errors
			if (count($errors)) {
				echo phlow_message_error($errors);
			}
			else {
				$shortcode = $this->phlow_generate_shortcode($_POST);
				$this->phlow_shortcode_reset();
				update_option('shortcode', $shortcode);

				echo phlow_message_success(__('Shortcode generated successfully'));
			}
		}
		
		$url = admin_url('options-general.php?page=phlow-settings.php');
		echo '<div class="wrap" style="margin-top:30px">';

		if (isset($_GET['clientPublicKey']) &&
			isset($_GET['clientPrivateKey']) &&
			isset($_GET['sessionPrivateKey']) &&
			isset($_GET['sessionPublicKey']))
		{
			$clientPublicKey = $_GET['clientPublicKey'];
			$clientPrivateKey = $_GET['clientPrivateKey'];
			$sessionPrivateKey = $_GET['sessionPrivateKey'];
			$sessionPublicKey = $_GET['sessionPublicKey'];

			update_option('phlow_clientPublicKey', $clientPublicKey);
			update_option('phlow_clientPrivateKey', $clientPrivateKey);
			update_option('phlow_sessionPrivateKey', $sessionPrivateKey);
			update_option('phlow_sessionPublicKey', $sessionPublicKey);

			self::phlow_settings_html();
		}
		elseif (isset($_POST['log_out'])) {
			echo '
				<h1>' . __('phlow settings') . '</h1>
				<p>' . phlow_message_success(__('You have successfully logged out of phlow')) . '</p>
				<p>
					<a
						href="http://cp.phlow.com/clients/new?type=wordpress&redirectUrl=' . $url . '"
						class="button"
					>' . __('Log in to phlow') . '</a>
				</p>
			';

			update_option('phlow_clientPublicKey','');
			update_option('phlow_clientPrivateKey','');
			update_option('phlow_sessionPrivateKey','');
			update_option('phlow_sessionPublicKey','');
		}
		elseif (get_option('phlow_clientPublicKey') == null || get_option('phlow_clientPublicKey') == '' ) {
			echo '
				<h1>' . __('phlow settings') . '</h1>
				<p>
					<a
						href="http://cp.phlow.com/clients/new?type=wordpress&redirectUrl=' . $url . '"
						class="button"
					>' . __('Log in to phlow') . '</a>
				</p>
			';
		}
		else {
			if (!count($_POST)) {
				$this->phlow_generator_reset();
				$this->phlow_shortcode_reset();
			}

			self::phlow_settings_html();
		}

		echo '</div>';
	}

	/**
	 * clear saved data of generator form
	 */
	private function phlow_generator_reset() {
		delete_option('source');
		delete_option('tags');
		delete_option('mymagazine');
		delete_option('magazine_name');
		delete_option('magazine_id');
		delete_option('moment_name');
		delete_option('moment_id');
		delete_option('width');
		delete_option('height');
		delete_option('type');
		delete_option('nudity');
		delete_option('violence');
	}

	/**
	 * clear saved shortcode
	 */
	private function phlow_shortcode_reset() {
		delete_option('shortcode');
	}

	/**
	 * settings form data
	 */
	private function phlow_data() {
		return array(
			'tabs' => array(
				'settings' => __('Settings'),
				'generator' => __('Widget generator')
			),
			'source_options' => array(
				0 => __('Embed a stream'),
				1 => __('Embed one of your magazines'),
				2 => __('Embed a public magazine'),
				3 => __('Embed a moment')
			),
			'type_options' => array(
				0 => __('phlow group'),
				1 => __('phlow line'),
				2 => __('phlow stream')
			)
		);
	}

	/**
	 * phlow settings html
	 */
	public function phlow_settings_html() {
		$data = $this->phlow_data();

		// Tabs
		$tabs = $data['tabs'];
		$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';

		$tabs_html = '<h2 class="nav-tab-wrapper wp-clearfix">';

		foreach ($tabs as $key => $value) {
			$url = admin_url('options-general.php?page=phlow-settings.php&tab=' . $key);
			$classes = array('nav-tab');

			if ($key == $active_tab) {
				$classes[] = 'nav-tab-active';
			}

			$tabs_html .= '
				<a
					class="' . join(' ', $classes) . '"
					href="' . $url . '"
				>' . $value . '</a>';
		}

		$tabs_html .= '</h2>';

		// Tab content
		if ($active_tab == 'generator') {
			$content = $this->phlow_generator_tab();
		}
		else {
			$content = $this->phlow_settings_tab();
		}

		echo '
			<h1>' . __('phlow') . '</h1>
			' . $tabs_html . '
			' . $content . '
		';
	}

	/**
	 * phlow authentication settings
	 */
	public function phlow_auth_settings() {
		$url = admin_url('options-general.php?page=phlow-auth.php');

		// Save settings
		if (isset($_POST['submit_auth_settings'])) {
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
					<p class="submit">
						<input
							type="submit"
							name="submit_auth_settings"
							class="button button-primary"
							value="' . __('Save Changes') . '"
						/>
					</p>
				</form>
			</div>
		';
	}

	/**
	 * phlow settings tab content
	 */
	public function phlow_settings_tab() {
		$url = admin_url('options-general.php?page=phlow-settings.php');
		$nudity = get_option('default_nudity');
		$violence = get_option('default_violence');

		// nudity and violence values
		$checked_nudity = ($nudity == '1') ? 'checked' : '';
		$checked_violence = ($violence == '1') ? 'checked' : '';

		// page html
		$html = '
			<form method="post" action="' . $url . '">
				<p>
					<input
						class="button"
						type="submit"
						name="log_out"
						value="' . __('log out from phlow') . '"
					/>
					<div>
						<strong>' . __('You are logged in to phlow') . '</strong>
					</div>
				</p>
				<hr />
				<h2 class="title">' . __('Default settings') . '</h2>
				<p>
					<label>
						<input type="checkbox" name="streams" disabled />
						' . __('Require clean streams') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to force phlow to manually validate every image to avoid images containing nudity and or violence before allowing it on your website. This option is currently unavailable') . '"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="nudity" ' . $checked_nudity . ' />
						' . __('Allow images containing nudity') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to allow phlow to show images marked as containing nudity') .'"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="violence" ' . $checked_violence . ' />
						' . __('Allow violent images') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to allow phlow to show images marked as containing violence') . '"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<input
						class="button button-primary"
						type="submit"
						name="submit_settings"
						value="' . __('Save defaults') . '"
					/>
				</p>
			</form>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					Tipped.create(".box");
				});
			</script>
		';

		return $html;
	}

	/**
	 * phlow generator tab content
	 */
	public function phlow_generator_tab() {
		$data = $this->phlow_data();
		$url = admin_url('options-general.php?page=phlow-settings.php&tab=generator');
		$source = get_option('source');
		$type = get_option('type');
		$shortcode = get_option('shortcode');

		// nudity and violence values
		$nudity = is_numeric(get_option('nudity'))
			? get_option('nudity')
			: get_option('default_nudity');

		$violence = is_numeric(get_option('violence'))
			? get_option('violence')
			: get_option('default_violence');

        $owned = is_numeric(get_option('owned'))
            ? get_option('owned')
            : 0;

		$checked_nudity = ($nudity == '1') ? 'checked' : '';
		$checked_violence = ($violence == '1') ? 'checked' : '';
		$checked_owned = ($owned == '1') ? 'checked' : '';

		// source html
		$source_html = '<select name="source" id="phlow_source" class="form-control">';

		foreach ($data['source_options'] as $key => $value) {
			$selected = ($source == $key) ? 'selected' : '';
			$source_html .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
		}

		$source_html .= '</select>';

		// types html
		$type_html = '<select name="type" id="phlow_type" class="form-control">';

		foreach ($data['type_options'] as $key => $value) {
			$selected = ($type == $key) ? 'selected' : '';
			$type_html .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
		}

		$type_html .= '</select>';

		// shortcode html
		$shortcode_html = '';

		if (isset($shortcode) && !empty($shortcode)) {
			$shortcode_html = '
				<br /><hr />
				<h2 class="title">' . __('Shortcode') . '</h2>
				<p id="phlow_shortcode_box">
					<textarea id="phlow_shortcode" cols="60" rows="3" readonly>' . $shortcode . '</textarea>
				</p>
			';
		}

		// page html
		$html = '
			<form method="post" action="' . $url . '">
				<h2 class="title">' . __('Widget generator') . '</h2>
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Type of widget') . '</label>
					</p>
					' . $type_html . '
				</p>
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Sources') . '</label>
					</p>
					' . $source_html . '
				</p>
				<div id="phlow_source_box">' . $this->phlow_source_blocks($source) . '</div>
				<div id="phlow_type_box">' . $this->phlow_type_blocks($type) . '</div>
				<p>
					<label>
						<input type="checkbox" name="streams" disabled />
						' . __('Require clean streams') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to force phlow to manually validate every image to avoid images containing nudity and or violence before allowing it on your website. This option is currently unavailable') . '"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="nudity" ' . $checked_nudity . ' />
						' . __('Allow images containing nudity') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to allow phlow to show images marked as containing nudity') .'"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="violence" ' . $checked_violence . ' />
						' . __('Allow violent images') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to allow phlow to show images marked as containing violence') . '"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="owned" ' . $checked_owned . ' />
						' . __('Limit photos to your published ones') . '
						<a
							class="box"
							data-tipped-options="position: top"
							title="' . __('Activate this option to allow phlow to show only photos you published') . '"
							href="#"
						>  ?</a>
					</label>
				</p>
				<p>
					<input
						class="button button-primary"
						type="submit"
						name="submit_generator"
						value="' . __('Generate shortcode') . '"
					/>
				</p>
			</form>
			' . $shortcode_html . '
			<script type="text/javascript">
				jQuery(document).ready(function() {
					Tipped.create(".box");
				});
			</script>
		';

		// clear saved shortcode
		$this->phlow_shortcode_reset();

		return $html;
	}

	/**
	 * phlow sources forms
	 */
	private function phlow_type_blocks($type) {
		$html = '<div></div>';

		// phlow stream
		if ($type == 2) {
			$width = get_option('width');
			$width = (isset($width) && is_numeric($width)) ? $width : 320;

			$height = get_option('height');
			$height = (isset($height) && is_numeric($height)) ? $height : 640;

			$html = '
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Widget width') . '</label>
					</p>
					<input name="width" type="number" value="' . $width . '" />
				</p>
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Widget height') . '</label>
					</p>
					<input name="height" type="number" value="' . $height . '" />
				</p>
			';
		}

		return $html;
	}

	/**
	 * phlow sources forms
	 */
	private function phlow_source_blocks($source) {
		// my magazine
		if ($source == 1) {
			$user = $this->api->me();
			$magazines = $this->api->userMagazines($user->userId)->magazines;
			$options = '';

			foreach ($magazines as $index => $item) {
				$options .= '<option value="' . $item->magazineId . '">' . $item->title . '</option>';
			}

			$html = '
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Select one of your magazines') . '</label>
					</p>
					<select name="mymagazine" class="form-control">' . $options . '</select>
				</p>
			';
		}
		// public magazine
		else if ($source == 2) {
			$name = get_option('magazine_name');
			$name = (isset($name) && !empty($name)) ? $name : '';

			$id = get_option('magazine_id');
			$id = (isset($id) && !empty($id)) ? $id : '';

			$html = '
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Search for a public magazine') . '</label>
					</p>
					<input
						name="magazine_name"
						id="phlow_magazine_name"
						class="form-control"
						type="text"
						value="' . $name . '"
					/>
					<input
						name="magazine_id"
						id="phlow_magazine_id"
						type="hidden"
						value="' . $id . '"
					/>
				</p>
			';
		}
		// moment
		else if ($source == 3) {
			$name = get_option('moment_name');
			$name = (isset($name) && !empty($name)) ? $name : '';

			$id = get_option('moment_id');
			$id = (isset($id) && !empty($id)) ? $id : '';

			$html = '
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Search for a moment') . '</label>
					</p>
					<input
						name="moment_name"
						id="phlow_moment_name"
						 class="form-control"
						type="text"
						value="' . $name . '"
					/>
					<input
						name="moment_id"
						id="phlow_moment_id"
						type="hidden"
						value="' . $id . '"
					/>
				</p>
			';
		}
		// streams
		else {
			$tags = get_option('tags');
			$tags = (isset($tags) && !empty($tags)) ? $tags : '';

			$html = '
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Comma separated streams') . '</label>
					</p>
					<input
						name="tags"
						type="text"
						class="form-control"
						value="' . $tags . '"
					/>
				</p>
			';
		}

		return $html;
	}

	public function phlow_ajax_get_type() {
		$this->query = $_GET;
		$type = $this->query['type'];

		$response = array(
            'success' => true,
            'html' => $this->phlow_type_blocks($type)
        );

        echo json_encode($response);
        wp_die();
	}

	public function phlow_ajax_check_auth() {
		$check = (
			get_option('phlow_clientPublicKey') != null ||
			get_option('phlow_clientPublicKey') != ''
		);

		$response = array(
            'success' => $check,
            'url' => admin_url('options-general.php?page=phlow-settings.php')
        );

        echo json_encode($response);
        wp_die();
	}

	public function phlow_ajax_get_source() {
		$this->query = $_GET;
		$source = $this->query['source'];

		$response = array(
            'success' => true,
            'html' => $this->phlow_source_blocks($source)
        );

        echo json_encode($response);
        wp_die();
	}

	public function phlow_ajax_get_magazines() {
		$user = $this->api->me();
		$magazines = $this->api->userMagazines($user->userId)->magazines;

		$response = array(
            'success' => true,
            'data' => $magazines
        );

        echo json_encode($response);
        wp_die();
	}

	public function phlow_ajax_search_magazines() {
		$this->query = $_GET;
		$string = $this->query['string'];
		$data = $this->api->searchMagazines($string);

        echo json_encode($data);
        wp_die();
	}

	public function phlow_ajax_search_moments() {
		$this->query = $_GET;
		$string = $this->query['string'];
		$data = $this->api->searchMoments($string);

        echo json_encode($data);
        wp_die();
	}

	public function phlow_ajax_get_images() {
		$this->query = $_GET;
		$errors = array();
		$params = array();

		// Validate type
		if (isset($this->query['type'])) {
			$type = trim($this->query['type']);

			if ($type != 'group' && $type != 'line') {
				$errors[] = __('Invalid type parameter');
			}
		}
		else {
			$errors[] = __('Please provide type parameter');
		}

		// Validate source
		if (isset($this->query['source'])) {
			$source = trim($this->query['source']);

			if ($source != 'streams' && $source != 'magazine' && $source != 'moment') {
				$errors[] = __('Invalid source parameter');
			}
			else {
				$params['source'] = $source;
			}
		}
		else {
			$errors[] = __('Please provide source parameter');
		}

		// Validate context
		if (isset($this->query['context'])) {
			$context = trim($this->query['context']);
			$context = preg_replace('/[^0-9a-zA-Z,:]/', '', $context);
			$params['context'] = $context;
		}
		else {
			$errors[] = __('Please provide context parameter');
		}

		// Violence
		$violence = $this->query['violence'];
		$violence = (isset($violence) && !empty($violence)) ? 1 : 0;
		$params['violence'] = $violence;

		// Nudity
		$nudity = $this->query['nudity'];
		$nudity = (isset($nudity) && !empty($nudity)) ? 1 : 0;
		$params['nudity'] = $nudity;

		// Owned
		$owned = $this->query['owned'];
		$owned = (isset($owned) && !empty($owned)) ? 1 : 0;
		$params['owned'] = $owned;

		// Check errors
		if (count($errors)) {
			$response = array(
				'success' => false,
				'errors' => $errors
			);
		}
		else {
			if ($type == 'group') {
				$images = $this->phlowLoadImages($params, 9);
			}
			else if ($type == 'line') {
				$images = $this->phlowLoadImages($params);
			}

			$response = array(
				'success' => true,
				'data' => $images
			);
		}

		echo json_encode($response);
		wp_die();
	}

	public function phlow_ajax_photo_seen() {
		$this->query = $_POST;
		$errors = array();

		// Validate photoId
		if (isset($this->query['photoId'])) {
			$photoId = trim($this->query['photoId']);
			$photoId = preg_replace('/[^0-9a-zA-Z]/', '', $photoId);
		}
		else {
			$errors[] = __('Please provide photo id parameter');
		}

		// Validate source
		if (isset($this->query['source'])) {
			$source = trim($this->query['source']);

			if ($source != 'streams' && $source != 'magazine' && $source != 'moment') {
				$errors[] = __('Invalid source parameter');
			}
		}
		else {
			$errors[] = __('Please provide source parameter');
		}

		// Validate context
		if (isset($this->query['context'])) {
			$context = trim($this->query['context']);
			$context = preg_replace('/[^0-9a-zA-Z,:]/', '', $context);
		}
		else {
			$errors[] = __('Please provide context parameter');
		}

		// Check errors
		if (count($errors)) {
			$response = array(
				'success' => false,
				'errors' => $errors
			);
		}
		else {
			if ($source == 'streams') {
				$this->api->seen($photoId, $context, null, null);
			}
			else if ($source == 'magazine') {
				$this->api->seen($photoId, null, $context, null);
			}
			else if ($source == 'moment') {
				$this->api->seen($photoId, null, null, $context);
			}

			$response = array(
				'success' => true
			);
		}

		echo json_encode($response);
		wp_die();
	}

    public function phlow_ajax_create_user() {
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

        // Send request
        $req = $this->api->register($params);

        if (isset($req->status) && $req->status != 200) {
            $response = array(
                'success' => false,
                'errors' => array($req->message)
            );
        }
        else {
            $response = array(
                'success' => true
            );

            // Add user to MailChimp
        	$this->mailchimp_member_add($req->user->email, $list_id, $interest_id);
        }

        echo json_encode($response);
        wp_die();
    }

    public function phlow_ajax_create_user_social() {
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
            $response = array(
                'success' => false,
                'errors' => array('Please provide access token')
            );
        }
        else {
            $req = $this->api->registerSocial($params);

            if (isset($req->status) && $req->status != 200) {
                $response = array(
                    'success' => false,
                    'errors' => array($req->message)
                );
            }
            else {
                $response = array(
                    'success' => true
                );

                // Add user to MailChimp
        		$this->mailchimp_member_add($req->user->email, $list_id, $interest_id);
            }
        }

        echo json_encode($response);
        wp_die();
    }

    public function phlow_ajax_twitter_request_token() {
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

    public function phlow_ajax_twitter_access_token() {
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

    /**
     * Add a MailChimp new list member
     */
    private function mailchimp_member_add($email, $list_id, $interest_id) {
    	if (!isset($email) || !isset($list_id)) {
    		return null;
    	}

    	$api_key = get_option('phlow_mailchimp_api_key');
    	$dc = substr($api_key, strpos($api_key, '-') + 1);
    	$api_url = 'https://' . $dc . '.api.mailchimp.com/3.0';

    	$body = array(
    		'email_address' => $email,
    		'status' => 'subscribed'
    	);

    	if (isset($interest_id) && !empty($interest_id)) {
    		$body['interests'] = array();
    		$body['interests'][$interest_id] = true;
    	}

    	$args = array(
    		'method' => 'POST',
    		'headers' => array(
    			'Authorization' => 'Basic ' . base64_encode('user:' . $api_key)
    		),
    		'body' => json_encode($body)
    	);

    	$req = wp_remote_post($api_url . '/lists/' . $list_id . '/members', $args);
    	$res = json_decode(wp_remote_retrieve_body($req));

    	return $res;
    }

	public function admin_head() {
		$plugin_url = plugins_url('/', __FILE__);
		$nudity = (get_option('default_nudity') == '1') ? true : false;
		$violence = (get_option('default_violence') == '1') ? true : false;

        // TinyMCE Shortcode Plugin
		echo '
			<script type="text/javascript">
			var phlow_plugin = {
				url: "' . $plugin_url . '",
				ajax_url: "' . $this->ajax_url . '",
				nudity: "' . $nudity . '",
				violence: "' . $violence . '",
				owned: ""
			};
			</script>
		';

        if (get_option('phlow_clientPublicKey') == null ||
        	get_option('phlow_clientPublicKey') == '')
        {
            return;
        }

        if (!current_user_can('edit_posts') &&
        	!current_user_can( 'edit_pages'))
        {
            return;
        }

        // check if WYSIWYG is enabled
        if ('true' == get_user_option('rich_editing')) {
            add_filter('mce_external_plugins', array($this, 'mce_external_plugins'));
            add_filter('mce_buttons', array($this, 'mce_buttons'));
        }
    }

	/**
     * mce_external_plugins
     * Adds our tinymce plugin
     * @param  array $plugin_array
     * @return array
     */
	public function mce_external_plugins( $plugin_array ) {
    	$plugin_array[$this->shortcode_tag] = plugins_url('mce_plugin/js/mce-button.js', __FILE__);
    	return $plugin_array;
    }

    /**
     * mce_buttons
     * Adds our tinymce button
     * @param  array $buttons
     * @return array
     */
    public function mce_buttons($buttons) {
    	array_push($buttons, $this->shortcode_tag);
    	return $buttons;
    }
}
$wc_phlow = new phlow();

class phlow_placer extends WP_Widget {

    function __construct() {
		// Instantiate the parent object
		parent::__construct('phlow', __('phlow Stream', 'phlow'));
	}

	public function widget($args, $instance) {
        extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$tags = str_replace(',', '-', $instance['tags']);
		$tags = str_replace(' ', '', $tags);
		$nudity = $instance['nudity'];
		$violent = $instance['violent'];
		$owned = $instance['owned'];
		$clean = 0;
		$width = 320;
		$height = 640;

		echo $before_widget;
		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;
        ?>
        <iframe src="http://app.phlow.com/stream/<?php print $tags ; ?>?cleanstream=<?php print $clean ; ?>&nudity=<?php print $nudity ; ?>&violence=<?php print $violent ; ?>&owned=<?php print $owned ; ?>"width="320"height="640"frameborder="0"></iframe>
        <?php
		echo $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['tags'] = strip_tags( $new_instance['tags'] );
		$instance['nudity'] =  isset($new_instance['nudity'] ) ? 1 : 0;
		$instance['violent'] =  isset($new_instance['violent'] ) ? 1 : 0;
		return $instance;
	}

	function form($instance) {
		$nude = get_option('nudity');
		$vio = get_option('violence') ;
		$owned = get_option('owned') ;

		$default_settings = array(
      		'title' => 'phlow',
      		'tags' => '',
      		'nudity' => $nude,
      		'violent' => $vio,
            'owned' => $owned
      	);

    	$instance = wp_parse_args((array) $instance, $default_settings);

		$title = $instance['title'] ;
		$tags = $instance['tags'];
		$nudity = $instance['nudity'];// == 'yes') ? 'on' : 'no' ;
		$violent = $instance['violent'];// == 'yes') ? 'on' : 'no' ;
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'title:' , 'wcw'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('tags') ?>"><?php _e( 'tags:' , 'wcw'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('tags') ?>" name="<?php echo $this->get_field_name('tags') ?>" type="text" value="<?php echo esc_attr( $tags ); ?>" />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('nudity') ?>"><?php _e( 'allow images containing nudity' , 'wcw'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('nudity') ?>"  name="<?php echo $this->get_field_name('nudity') ?>" type="checkbox" <?php checked( $nudity); ?>  />
		</p>
		<p>
		<label for="<?php echo $this->get_field_id('violent') ?>"><?php _e( 'allow violent images' , 'wcw'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('violent') ?>" name="<?php echo $this->get_field_name('violent') ?>" type="checkbox" <?php checked( $violent ); ?> />
		</p>
        <p>
            <label for="<?php echo $this->get_field_id('owned') ?>"><?php _e( 'show only the photos you published' , 'wcw'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('owned') ?>" name="<?php echo $this->get_field_name('owned') ?>" type="checkbox" <?php checked( $owned ); ?> />
        </p>
		<label for="<?php echo $this->get_field_id('streams') ?>"><?php _e( 'require clean streams' , 'wcw'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('streams') ?>" name="<?php echo $this->get_field_name('violent') ?>" type="checkbox" value="" disabled />
		</p>
		<?php
	}
}

function plugin_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=phlow-settings.php">' . __( 'Settings' ) . '</a>';
    array_push($links, $settings_link);
  	return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'plugin_add_settings_link');

// Activation
register_activation_hook(__FILE__, 'phlow_activation_hook');

function phlow_activation_hook() {
	delete_option('show_activation_message');
}

function phlow_admin_notice__success() {
	$show_once = get_option('show_activation_message');

	if (!$show_once) {
		$url = admin_url('options-general.php?page=phlow-settings.php');
		echo phlow_message_success(__('<b>phlow</b> is activated! Visit <a href="' . $url . '">the plugin settings page</a> to start using the plugin'));
		update_option('show_activation_message', true);
	}
}
add_action('admin_notices', 'phlow_admin_notice__success');

function phlow_register_widget() {
	if (get_option('phlow_clientPublicKey') != null ||
		get_option('phlow_clientPublicKey') != '')
	{
        register_widget( 'phlow_placer' );
	}
}

// Messages
function phlow_message_success($msg) {
	if (is_array($msg)) {
		$msg = join('<br />', $msg);
	}

	return '
		<div class="notice notice-success is-dismissible">
			<p>' . $msg . '</p>
		</div>
	';
}

function phlow_message_error($msg) {
	if (is_array($msg)) {
		$msg = join('<br />', $msg);
	}

	return '
		<div class="notice notice-error">
			<p>' . $msg . '</p>
		</div>
	';
}
