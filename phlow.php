<?php
/**
 * Plugin Name: phlow
 * Description: phlow allows you to embed a carousel of photographs relevant to a specific theme or context. Be it #wedding#gowns, #portraits#blackandwhite or #yoga, phlow provides you with images that are fresh and relevant. To get started, log through a phlow account (it is 100% free) and either embed the stream in your WYSIWYG editor or add a widget to your blog.
 * Version: 1.0.6
 * Author: phlow
 * Author URI: http://phlow.com
 */

define('PHLOW__PLUGIN_DIR', plugin_dir_path(__FILE__));
require_once(PHLOW__PLUGIN_DIR . 'class.api.php');

class phlow {
	protected $_plugin_id = 'phlow';
	protected $_plugin_dir ;
	public static $activation_transient;
	public static $plugin_folder = 'phlow';
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
		}

		// async actions
		add_action('wp_ajax_phlow_source_get', array($this, 'phlow_ajax_get_source'));
		add_action('wp_ajax_phlow_magazines_search', array($this, 'phlow_ajax_search_magazines'));
		add_action('wp_ajax_phlow_moments_search', array($this, 'phlow_ajax_search_moments'));
	}

    public function addShortcodes()
    {
        if(get_option('phlow_clientPublicKey') != null || get_option('phlow_clientPublicKey') != '' ){
            add_shortcode('phlow_stream', array($this, 'shortcode_phlow_page'));
            add_shortcode('phlow_group', array($this, 'shortcode_groups_image'));
            add_shortcode('phlow_line', array($this, 'shortcode_line_images'));
        }
    }

	public function phlow_localize() {
        // Localization
		load_plugin_textdomain('phlow', false, dirname(plugin_basename(__FILE__)). "/languages" );
    }

    public function enqueue() {
    	wp_register_style( 'ph_css', $this->_plugin_url . '/css/tipped/tipped.css', false, '1.0.0' );
        wp_enqueue_style( 'ph_css' );
        wp_enqueue_style('phlow_shortcode', $this->_plugin_url .'/css/mce-button.css' );
        wp_enqueue_style('phlow', $this->_plugin_url .'/css/autocomplete/easy-autocomplete.min.css');
        wp_enqueue_script( 'ph_jquery_script', 'http://code.jquery.com/jquery-1.12.2.min.js', null, false);
        wp_register_script( 'ph_script', $this->_plugin_url .'/js/tipped/tipped.js',array('jquery'), null, false);
        wp_enqueue_script( 'ph_script');
        wp_register_script('phlow', $this->_plugin_url . '/js/generator.js', array('jquery'), null, false);
        wp_enqueue_script('phlow');
        wp_register_script('phlow_autocomplete', $this->_plugin_url . '/js/autocomplete/jquery.easy-autocomplete.min.js', array('jquery'), null, false);
        wp_enqueue_script('phlow_autocomplete');

		wp_localize_script('phlow', 'phlowAjax', array(
			'url' => $this->ajax_url
		));
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

		$images = array();
		$counter = 0;

		// magazine
		if ($source == 'magazine') {
			$queryString = 'size=150x150c';
			$photos = $this->api->magazines($context, $queryString)->photos;

			foreach ($photos as $photo) {
				$images[] = array(
					'url' => 'https://app.phlow.com/magazine/' . $context,
					'src' => $photo->url
				);

				if ($counter++ >= ($limit-1)) {
					break;
				}
			}
		}
		// moment
		else if ($source == 'moment') {
			$queryString = 'size=150x150c';
			$photos = $this->api->moments($context, $queryString)->photos;

			foreach ($photos as $photo) {
				$images[] = array(
					'url' => 'https://app.phlow.com/moment/' . $context,
					'src' => $photo->url
				);

				if ($counter++ >= ($limit-1)) {
					break;
				}
			}
		}
		// streams
		else {
			$queryString = 'context=' . $context . '&size=150x150c';
			$photos = $this->api->streams($queryString)->photos;

			foreach ($photos as $photo) {
				$images[] = array(
					'url' => 'https://app.phlow.com/stream/' . $context . '/photo/' . $photo->photoId,
					'src' => $photo->url
				);

				if ($counter++ >= ($limit-1)) {
					break;
				}
			}
		}

		return $images;
    }

    // phlow group widget
    public function shortcode_groups_image($atts) {
    	$imageList = $this->phlowLoadImages($atts, 9);

    	ob_start();

    	$images_html = '';

    	foreach ($imageList as $image) {
			$images_html .= '
				<li>
					<a target="_blank"href="' . $image['url'] . '">
						<img class="images-view" src="' . $image['src'] . '" />
					</a>
				</li>
			';
		}

    	echo '
    		<div class="image-list">
				<ul class="groups-images">
					' . $images_html . '
					<div class="powered-by">
						<span class="first-child">' . __('Powered by') . '</span>
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
		$imageList = $this->phlowLoadImages($atts);

		ob_start();

		if (isset($imageList) && sizeof($imageList) > 0) {
			$images_html = '';

			foreach ($imageList as $image) {
				$images_html .= '
					<li>
						<a target="_blank "href="' . $image['url'] . '">
							<img class="images-view" src="' . $image['src'] . '" />
						</a>
					</li>
				';
			}

			echo '
				<div class="image-list-horizontal">
					<ul class="line-images">
						' . $images_html . '
						<div class="powered-by">
							<span class="first-child">' . __('Powered by') . '</span>
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
		}

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
		$violence = $a['violence'];

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
			$context = str_replace(',', '-', trim($query['tags']));
			$context = str_replace(' ', '', $context);
			$context = str_replace('#', '', $context);
		}

		// type
		$type = $query['type'];

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

		if (isset($width)) {
			$shortcode .= ' width="' . $width . '"';
		}

		if (isset($height)) {
			$shortcode .= ' height="' . $height . '"';
		}

		$shortcode .= ']';

		return $shortcode;
	}

	public function phlow_menu() {
		add_submenu_page( 'options-general.php', 'phlow-settings', 'phlow', 'manage_options', 'phlow-settings.php', array($this, 'phlow_settings') );
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

			if (count($errors)) {
				// Save data for correction
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

				echo phlow_message_error($errors);
			}
			else {
				$shortcode = $this->phlow_generate_shortcode($_POST);
				$this->phlow_generator_reset();
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
				<a
					href="http://cp.phlow.com/clients/new?type=wordpress&redirectUrl=' . $url . '"
					class="button"
				>' . __('Log in to phlow') . '</a>
			';

			update_option('phlow_clientPublicKey','');
			update_option('phlow_clientPrivateKey','');
			update_option('phlow_sessionPrivateKey','');
			update_option('phlow_sessionPublicKey','');
		}
		elseif (get_option('phlow_clientPublicKey') == null || get_option('phlow_clientPublicKey') == '' ) {
			echo '
				<h1>' . __('phlow settings') . '</h1>
				<a
					href="http://cp.phlow.com/clients/new?type=wordpress&redirectUrl=' . $url . '"
					class="button"
				>' . __('Log in to phlow') . '</a>
			';
		}
		else {
			self::phlow_settings_html();
		}

		echo '</div>';
	}

	/**
	 * clear saved options of generator form
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
				$(document).ready(function() {
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

		$nudity = is_numeric(get_option('nudity'))
			? get_option('nudity')
			: get_option('default_nudity');

		$violence = is_numeric(get_option('violence'))
			? get_option('violence')
			: get_option('default_violence');

		// nudity and violence values
		$checked_nudity = ($nudity == '1') ? 'checked' : '';
		$checked_violence = ($violence == '1') ? 'checked' : '';

		// source html
		$source_html = '<select name="source" id="phlow_source">';

		foreach ($data['source_options'] as $key => $value) {
			$selected = ($source == $key) ? 'selected' : '';
			$source_html .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
		}

		$source_html .= '</select>';

		// types html
		$type_html = '<select name="type" id="phlow_type">';

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
				<textarea cols="60" rows="3" disabled>' . $shortcode . '</textarea>
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
				<div id="phlow_source_box">' . $this->phlow_source_blocks($source, $type) . '</div>
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
						name="submit_generator"
						value="' . __('Generate shortcode') . '"
					/>
				</p>
			</form>
			' . $shortcode_html . '
			<script type="text/javascript">
				$(document).ready(function() {
					Tipped.create(".box");
				});
			</script>
		';

		// clear saved options
		$this->phlow_generator_reset();

		return $html;
	}

	/**
	 * phlow sources forms
	 */
	private function phlow_source_blocks($source, $type) {
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
					<select name="mymagazine">' . $options . '</select>
				</p>
			';
		}
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
					<input name="magazine_name" id="phlow_magazine_name" type="text" value="' . $name . '" />
					<input name="magazine_id" id="phlow_magazine_id" type="hidden" value="' . $id . '" />
				</p>
			';
		}
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
					<input name="moment_name" id="phlow_moment_name" type="text" value="' . $name . '" />
					<input name="moment_id" id="phlow_moment_id" type="hidden" value="' . $id . '" />
				</p>
			';
		}
		else {
			$tags = get_option('tags');
			$tags = (isset($tags) && !empty($tags)) ? $tags : '';

			$html = '
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Comma separated streams') . '</label>
					</p>
					<input name="tags" type="text" value="' . $tags . '" />
				</p>
			';
		}

		if ($type == 2) {
			$width = get_option('width');
			$width = (isset($width) && is_numeric($width)) ? $width : 320;

			$height = get_option('height');
			$height = (isset($height) && is_numeric($height)) ? $height : 640;

			$html .= '
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

	public function phlow_ajax_get_source() {
		$this->query = $_GET;
		$source = $this->query['source'];
		$type = $this->query['type'];

		$response = array(
            'success' => true,
            'html' => $this->phlow_source_blocks($source, $type)
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

	public function admin_head() {
		$plugin_url = plugins_url('/', __FILE__);
		$data = $this->phlow_data();

		$nudity = (get_option('nudity') == '1') ? true : false;
		$violence = (get_option('violence') == '1') ? true : false;
		$source = get_option('source');
		$type = get_option('type');

        // TinyMCE Shortcode Plugin
		echo '
			<script type="text/javascript">
			var phlow_plugin = {
				url: "' . $plugin_url . '",
				nudity: "' . $nudity . '",
				violence: "' . $violence . '"
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
    	// $plugin_array[$this->shortcode_tag] = plugins_url( 'js/mce-button.js' , __FILE__ );
    	$plugin_array[$this->shortcode_tag] = plugins_url('js/mce-button.js?t=' . time() , __FILE__);
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
		$clean = 0;
		$width = 320;
		$height = 640;

		echo $before_widget;
		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;
        ?>
        <iframe src="http://app.phlow.com/stream/<?php print $tags ; ?>?cleanstream=<?php print $clean ; ?>&nudity=<?php print $nudity ; ?>&violence=<?php print $violent ; ?>"width="320"height="640"frameborder="0"></iframe>
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

		$default_settings = array(
      		'title' => 'phlow',
      		'tags' => '',
      		'nudity' => $nude,
      		'violent' => $vio
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

function phlow_admin_notice__success() {
	if (get_option('phlow_clientPublicKey') == null ||
		get_option('phlow_clientPublicKey') == '')
	{
		echo phlow_message_success(__('phlow is activated! Visit the plugin settings page to start using the plugin'));
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
