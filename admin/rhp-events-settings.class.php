<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) die( '-1' );

require_once RockhouseEvents::instance()->pluginPath .'lib/rhp-logger.class.php';

if ( ! class_exists( 'RockhouseEventsSettings' ) ) {

	/**
	 * Adding the Rockhouse Settings
	 */
	class RockhouseEventsSettings {

		/**
		 * Singleton instance
		 *
		 * @var null or RockhouseEventsSettings
		 */
		private static $instance = null;

		/**
		 * Slug of the WP admin menu item
		 */
		const MENU_SLUG = "rhp-events-settings";

		/**
		 * The slug for the new admin page
		 *
		 * @var string
		 */
		private $admin_page = null;

		/**
		 * Current tab
		 *
		 * @var string
		 */
		public $current_tab = '';

		/**
		 * Our default defined tabs
		 * (arranged in slug => title)
		 *
		 * @var array
		 */
		public $default_tabs = array(
								'default' => 'General',
								'logs' => 'Logs'
							);

		/**
		 * Our instance defined tabs
		 *
		 * @var array
		 */
		public $admin_tabs = array();

		/**
		 * Admin notices holder
		 *
		 * @var array
		 */
		public $admin_notices = array();

		/**
		 * Class constructor
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_menu_page' ), 100 );

			// Save Settings during top rendering
			add_action( 'rhp-tribe-settings-above', array($this,'do_save_options'), 2 );

			// Handle actions prior to headers or content being sent i
			add_action( 'admin_init', array($this,'do_preheader_actions') );

			// Add our tabs
			add_action( 'rhp-tribe-settings-above', array($this,'do_menu_tabs'), 50 );
			add_action( 'rhp-tribe-settings-content', array($this,'do_menu_content') );

			// Set our current tab early
			$this->admin_tabs = apply_filters('rhp_tribe_admin_tabs', $this->default_tabs);
			$this->current_tab = 'default';

			if( isset($_GET['tab']) and in_array($_GET['tab'], array_keys($this->admin_tabs)) ) {
				$this->current_tab = esc_attr($_GET['tab']);
			}

			// Add handler for AJAX
			add_action('wp_ajax_' . 'rhp-tribe-ajax', array($this,'ajax_handler') );
		}

		/**
		 * Adds the page to the admin menu
		 */
		public function add_menu_page() {
			$page_title = __( 'Rockhouse Settings', 'tribe-events-calendar' );
			$menu_title = __( 'Rockhouse Settings', 'tribe-events-calendar' );
			$capability = "edit_tribe_venues";

			// Tribe__Events__Main should be active by now
			$where = 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE;

			$this->admin_page = add_submenu_page( $where, $page_title, $menu_title, $capability, self::MENU_SLUG, array( $this, 'do_menu_page' ) );
		}

		/**
		 * Handle AJAX requests
		 */
		public function ajax_handler() {
			check_ajax_referer('rhp-tribe-ajax-nonce','security');

			$response = array();
			$response['payload'] = 'Hello.';
			$response['message'] = 'Request successful';
			$response['target'] = '#rhp-tools-output';

			header('Content-Type: application/json');
			exit( json_encode($response) );
		}

		/**
		 * Simply include our view which will trigger relevant actions
		 */
		public function do_menu_page() {
			include_once RockhouseEvents::instance()->pluginPath . 'src/views/admin/rhp-events-settings.php';
		}

		/**
		 * Render our tabs
		 */
		public function do_menu_tabs() {

			echo '<h2 id="tribe-settings-tabs" class="nav-tab-wrapper">';

			foreach( $this->admin_tabs as $tab => $title ) {
				$link = 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE . '&page=' . self::MENU_SLUG;
				if( $tab !== 'default' ) {
					$link .= '&tab=' . urlencode($tab);
				}
				echo '<a id="' . $tab . '" class="nav-tab'. ( $tab == $this->current_tab ? ' nav-tab-active' : '' ) . '" href="' . admin_url($link) . '">' . $title . '</a>';
			}

			echo '</h2>';

		}

		/**
		 * Handle the setup and render of our current options page / tab
		 */
		public function do_menu_content() {

			$admin_content = RockhouseEvents::instance()->pluginPath . 'src/views/admin/rhp-events-settings-tab-' . $this->current_tab . '.php';
			$default_content = RockhouseEvents::instance()->pluginPath . 'src/views/admin/rhp-events-settings-tab-default.php';

			include_once file_exists( $admin_content ) ? $admin_content : $default_content;

		}

		/**
		 * Saves our settings and handles error messaging / cap check
		 */
		public function do_save_options() {

			if( current_user_can( "edit_posts" ) ) {

				// Show our notices, good or bad
				if( !empty( $this->admin_notices ) ) {
					echo implode("\n",$this->admin_notices);
				}

			} else {
				echo <<<MSG
        <div class="wrap">
                <p>
                        You do not have permission to change these settings.  Please contact your site administrator.
                </p>
        </div>
MSG;
			}
		}

		/**
		 * Handle POST and potential redirects before the page is rendered
		 *
		 * @filter admin_init
		 */
		public function do_preheader_actions() {

			if( current_user_can( "edit_posts" ) ) {

				// This should only be present on POSTed forms, not AJAX requests.  May need to check DOING_AJAX though
				if( $_POST and !empty($_POST['rhp-events-nonce']) ) {
					try {
						if( wp_verify_nonce( $_POST['rhp-events-nonce'],'rhp-events-settings' ) ) {

							// Loop through opt keys and find things to save
							$opts = RockhouseEvents::getOptions();

							foreach($opts as $k => $v) {
								// Horrible one-off for checkboxes
								if( $k == 'multipleVenues' ) {
									RockhouseEvents::setOption($k, isset($_POST[$k]) ? true : false );
								} elseif( array_key_exists($k,$_POST) and $_POST[$k] != $opts[$k] ) {
									// Filter this, dummy!  Or break to a group save to collapse all these UPDATES
									RockhouseEvents::setOption($k, $_POST[$k]);
								}
							}
							$this->admin_notices[] = '<div id="message" class="updated"><p><strong>Settings Saved</strong></p></div>';
						} else {
							throw new Exception('Your session is invalid, I could\'t save those settings.');
						}
					} catch( Exception $e ) {
						$this->admin_notices[] = '<div id="message" class="updated"><p><strong>' . $e->getMessage() . '</strong></p></div>';
					}
				}

			}

		}

		/**
		 * Static Singleton Factory Method
		 *
		 * @return RockhouseEventsSettings
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				$className      = __CLASS__;
				self::$instance = new $className;
			}

			return self::$instance;
		}

	}

	// Fire it up, boss!
	RockhouseEventsSettings::instance();
}
