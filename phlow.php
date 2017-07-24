<?php
/**
 * Plugin Name: phlow
 * Description: phlow allows you to embed a carousel of photographs relevant to a specific theme or context. Be it #wedding#gowns, #portraits#blackandwhite or #yoga, phlow provides you with images that are fresh and relevant. To get started, log through a phlow account (it is 100% free) and either embed the stream in your WYSIWYG editor or add a widget to your blog.
 * Version: 1.0.6
 * Author: phlow
 * Author URI: http://phlow.com
 */

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
        wp_enqueue_script( 'ph_jquery_script', 'http://code.jquery.com/jquery-1.12.2.min.js', null, false);
        wp_register_script( 'ph_script', $this->_plugin_url .'/js/tipped/tipped.js',array('jquery'), null, false);
        wp_enqueue_script( 'ph_script');
    }

    private function phlowLoadImages($atts, $limit=10){
        /** make sure that the request for the images contains ?size=150x150c */

        $sources = shortcode_atts( array(
			'source' => 'stream',
			'context'  => '',
			'clean' => '0',
			'nudity'  => get_option('nudity'),
			'violence'  => get_option('violence'),
			), $atts );


        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://api.phlow.com/v1/time'
        ));

        $time = json_decode(curl_exec($curl))->time;
        curl_close($curl);

        $apiUri = 'https://api.phlow.com';
        $uri = '';
        $target = '';

        //$sources['source'], $sources['context'], $sources['clean'], $sources['nudity'], $sources['violence']
        switch (strtolower($sources['source'])){
            case "streams":
                $uri = "/v1/streams/?context=".$sources['context']."&size=150x150c";
                $target = 'stream/'.$sources['context'].'/';
                break;
        }

        /** the HTTP verb being used, */
        $checksumData = "GET\n";
        /** the URI being called (request URI utf-8 encoded), */
        $checksumData .= utf8_encode($uri)."\n";
        /** the current Unix timestamp in seconds. (See Time stamping). */
        $checksumData .= $time."\n";
        /** (optional) the MD5 checksum of the body (if it’s present), */
        /** (optional) session’s private key */
        $checksumData .= get_option('phlow_sessionPrivateKey');

        $checksum = hash_hmac("SHA256", $checksumData, get_option('phlow_clientPrivateKey'), false);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array(
                    'X-PHLOW:'.get_option('phlow_clientPublicKey').get_option('phlow_sessionPublicKey').$time.$checksum
                    ),
            CURLOPT_URL => $apiUri.$uri
        ));

        $a = curl_exec($curl);
        $return = json_decode($a);
        curl_close($curl);

        $returnValue = array();
        $imageCount = 0;
        foreach ($return->photos as $photo){
            $returnPhoto = array(
                    'URL' => 'https://app.phlow.com/'.$target.'photo/'.$photo->photoId,
                    'imageLink' => $photo->url
            );
            $returnValue[] = $returnPhoto;

            if ($imageCount++ >= ($limit-1)) break;
        }

        return($returnValue);
    }

    /*
     * shortcode is use for display images in Groups
     */
    public function shortcode_groups_image($atts){

        $imageList = $this->phlowLoadImages($atts,9);


        ob_start();
        echo '<div class="image-list">';
        echo '<ul class="groups-images">';
        foreach ($imageList as $image){
            echo '<li><a target="_blank" href="'.$image['URL'].'"><img class="images-view" src="'.$image['imageLink'].'" /></a></li>';
        }
        echo '<div class="powered-by">'
            . '<span class="first-child">Powered by</span>'
            . '<span> </span>'
            . '<a class="plugin-url" target="_blank" href="https://app.phlow.com"><span class="phlow-red">phlow</span><span> </span><i class="icon-logo-small"></i></a></div>';
        echo '</ul>';
        echo '</div>';
        return ob_get_clean();
        }

    /*
     * shortcode is use for display images in single line
     */
    public function shortcode_line_images($atts){

        $imageList = $this->phlowLoadImages($atts);

        ob_start();
	    if (isset($imageList) && sizeof($imageList) > 0){
	        echo '<div class="image-list-horizontal">';
	        echo '<ul class="line-images">';
            foreach ($imageList as $image){
                echo '<li><a target="_blank" href="'.$image['URL'].'"><img class="images-view" src="'.$image['imageLink'].'"/></a></li>';
            }

            echo '<div class="powered-by">'
            . '<span class="first-child">Powered by</span>'
            . '<span> </span>'
            . '<a class="plugin-url" target="_blank" href="https://app.phlow.com"><span class="phlow-red">phlow</span><span> </span><i class="icon-logo-small"></i></a></div>';
            echo '</ul>';
            echo "</div>";
	    }
        return ob_get_clean();

        }

    public function shortcode_phlow_page($atts) {
		$a = shortcode_atts( array(
			'width' => '320',
			'height'  => '640',
			'clean' => '0',
			'nudity'  => get_option('nudity'),
			'tags'  => '',
			'violence'  => get_option('violence'),
			), $atts );
		$width = $a['width'];
		$height = $a['height'];
		$clean = $a['clean'];
		$nudity = $a['nudity'];
		$tags = str_replace(',', '-', $a['tags']);
		$tags = str_replace(' ', '', $tags);
		$tags = str_replace('#', '', $tags);
		$violence = $a['violence'];
		ob_start();
		?>
		<iframe src="http://app.phlow.com/stream/<?php print $tags ; ?>?cleanstream=<?php print $clean ; ?>&nudity=<?php print $nudity ; ?>&violence=<?php print $violence ; ?>"width="<?php print $width ; ?>"height="<?php print $height ; ?>"frameborder="0"></iframe>
		<?php
		return ob_get_clean();
	}

	public function phlow_menu() {
		add_submenu_page( 'options-general.php', 'phlow-settings', 'phlow', 'manage_options','phlow-settings.php', array($this,'phlow_settings') );
	}

	public function phlow_settings() {
		if(isset($_POST['submit_main']))
		{
			echo '<div><strong>settings updated successfully !</strong></div>';
			if(isset($_POST['nudity'])) {
				update_option('nudity', '1');
			} else {
				update_option('nudity', '0');
			}

			if(isset($_POST['violence'])) {
				update_option('violence', '1');
			} else {
				update_option('violence', '0');
			}
		}
		$url = admin_url('options-general.php?page=phlow-settings.php');
		echo '<div class="wrap" style="margin-top:30px">';

		if(isset($_GET['clientPublicKey']) && isset($_GET['clientPrivateKey']) && isset($_GET['sessionPrivateKey']) && isset($_GET['sessionPublicKey'])) {
			$clientPublicKey = $_GET['clientPublicKey'];
			$clientPrivateKey = $_GET['clientPrivateKey'];
			$sessionPrivateKey = $_GET['sessionPrivateKey'];
			$sessionPublicKey = $_GET['sessionPublicKey'];

			update_option('phlow_clientPublicKey',$clientPublicKey);
			update_option('phlow_clientPrivateKey',$clientPrivateKey);
			update_option('phlow_sessionPrivateKey',$sessionPrivateKey);
			update_option('phlow_sessionPublicKey',$sessionPublicKey);

			self::phlow_screen();
		} elseif(isset($_POST['log_out'])) {
			echo '<h2>user settings</h2>';
			echo '<div>You have successfully logged out of phlow</div>';
			echo '<a href="http://cp.phlow.com/clients/new?type=wordpress&redirectUrl='.$url.'">Log in to phlow</a>';
			update_option('phlow_clientPublicKey','');
			update_option('phlow_clientPrivateKey','');
			update_option('phlow_sessionPrivateKey','');
			update_option('phlow_sessionPublicKey','');
		} elseif(get_option('phlow_clientPublicKey') == null || get_option('phlow_clientPublicKey') == '' ) {
			echo '<h2>user settings</h2>';
			echo '<a href="http://cp.phlow.com/clients/new?type=wordpress&redirectUrl='.$url.'">Log in to phlow</a>';
		} else {
			self::phlow_screen();
		}
		echo '</div>';
	}

	/**
	 * phlow settings page
	 */
	public function phlow_screen() {
		$url = admin_url('options-general.php?page=phlow-settings.php');
		$nudity = get_option('nudity');
		$violence = get_option('violence');

		$checked_nudity = ($nudity == '1') ? 'checked' : '';
		$checked_violence = ($violence == '1') ? 'checked' : '';

		$html = '
			<h1>' . __('phlow settings') . '</h1>
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
					<select name="embed">
						<option value="0">' . __('Embed a stream') . '</option>
						<option value="1">' . __('Embed one of your magazines') . '</option>
						<option value="2">' . __('Embed a public magazine') . '</option>
						<option value="3">' . __('Embed a moment') . '</option>
					</select>
				</p>
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Widget width') . '</label>
					</p>
					<input type="text" name="width" />
				</p>
				<p>
					<p class="post-attributes-label-wrapper">
						<label>' . __('Widget height') . '</label>
					</p>
					<input type="text" name="height" />
				</p>
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
						name="submit_main"
						value="' . __('Save changes') . '"
					/>
				</p>
			</form>
			<script type="text/javascript">
				$(document).ready(function() {
					Tipped.create(".box");
				});
			</script>
		';

		echo $html;
	}

	public function admin_head() {
		$plugin_url = plugins_url( '/', __FILE__ );
		$nudity = (get_option('nudity') == '1') ? true : false;
		$violence = (get_option('violence') == '1') ? true : false;
?>
        <!-- TinyMCE Shortcode Plugin -->
        <script type='text/javascript'>
        var my_plugin = {
            'url': '<?php echo $plugin_url; ?>',
            'nudity': '<?php echo $nudity; ?>',
            'violence': '<?php echo $violence; ?>',
        };
        </script>;
 <?php

        if(get_option('phlow_clientPublicKey') == null || get_option('phlow_clientPublicKey') == '' ) {
            return;
        }

        if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
            return;
        }

        // check if WYSIWYG is enabled
        if ( 'true' == get_user_option( 'rich_editing' ) ) {
            add_filter( 'mce_external_plugins', array( $this ,'mce_external_plugins' ) );
            add_filter( 'mce_buttons', array($this, 'mce_buttons' ) );
        }
    }

	/**
     * mce_external_plugins
     * Adds our tinymce plugin
     * @param  array $plugin_array
     * @return array
     */
	public function mce_external_plugins( $plugin_array ) {
    	$plugin_array[$this->shortcode_tag] = plugins_url( 'js/mce-button.js' , __FILE__ );
    	return $plugin_array;
    }

    /**
     * mce_buttons
     * Adds our tinymce button
     * @param  array $buttons
     * @return array
     */
    public function mce_buttons( $buttons ) {
    	array_push( $buttons, $this->shortcode_tag );
    	return $buttons;
    }
}
$wc_phlow = new phlow();

class phlow_placer extends WP_Widget {

    function __construct() {
		// Instantiate the parent object
		parent::__construct( 'phlow', __('phlow Stream', 'phlow') );
	}

	public function widget( $args, $instance ) {
        extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$tags = str_replace(',', '-', $instance['tags']);
		$tags = str_replace(' ', '', $tags);
		$nudity = $instance['nudity'] ;
		$violent = $instance['violent'] ;
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

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['tags'] = strip_tags( $new_instance['tags'] );
		$instance['nudity'] =  isset($new_instance['nudity'] ) ? 1 : 0;
		$instance['violent'] =  isset($new_instance['violent'] ) ? 1 : 0;
		return $instance;
	}

	function form( $instance )
	{
		$nude = get_option('nudity');
		$vio = get_option('violence') ;

		$default_settings = array(
      'title' => 'phlow',
      'tags'=>'',
      'nudity'=>$nude,
      'violent'=>$vio,
      );
    $instance = wp_parse_args((array)$instance,$default_settings );

		$title = $instance['title'] ;
		$tags = $instance['tags'];
		$nudity = $instance[ 'nudity'];// == 'yes') ? 'on' : 'no' ;
		$violent = $instance[ 'violent'];// == 'yes') ? 'on' : 'no' ;
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
    array_push( $links, $settings_link );
  	return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'plugin_add_settings_link' );

function phlow_admin_notice__success() {
	if(get_option('phlow_clientPublicKey') == null || get_option('phlow_clientPublicKey') == '' ){
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e( 'phlow is activated ! Visit the plugin settings page to start using the plugin', 'phlow' ); ?></p>
    </div>
    <?php
    }
}
add_action( 'admin_notices', 'phlow_admin_notice__success' );

function phlow_register_widget() {
	if(get_option('phlow_clientPublicKey') != null || get_option('phlow_clientPublicKey') != '' ) {
        register_widget( 'phlow_placer' );
	}
}