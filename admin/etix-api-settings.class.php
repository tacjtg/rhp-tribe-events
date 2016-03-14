<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) die( '-1' );

require_once RockhouseEvents::instance()->pluginPath .'lib/rhp-logger.class.php';

if ( ! class_exists( 'EtixAPISettings' ) ) {

	/**
	 * Adding the Etix Settings
	 */
	class EtixAPISettings {

		/**
		 * Singleton instance
		 *
		 * @var null or EtixAPISettings
		 */
		private static $instance = null;

		/**
		 * Slug of the WP admin menu item
		 */
		const MENU_SLUG = "etix-api-settings";

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
								'default' => 'API Settings',
								'performances' => 'Performance Sync',
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
			add_action( 'etix-api-settings-above', array($this,'do_save_options'), 2 );

			// Handle actions prior to headers or content being sent
			add_action( 'admin_init', array($this,'do_preheader_actions') );

			// If we're grouping events to the category, enforce our cats
			if( RockhouseEvents::getOption('etixGroupSeries') ) {
				add_action( 'admin_init', array($this,'enforce_series_cat') );
			}

			// Add our tabs
			add_action( 'etix-api-settings-above', array($this,'do_menu_tabs'), 50 );
			add_action( 'etix-api-settings-content', array($this,'do_menu_content') );

			// Set our current tab early
			$this->admin_tabs = apply_filters('etix_api_admin_tabs', $this->default_tabs);
			$this->current_tab = 'default';

			if( isset($_GET['tab']) and in_array($_GET['tab'], array_keys($this->admin_tabs)) ) {
				$this->current_tab = esc_attr($_GET['tab']);
			}

			// Add handler for AJAX
			add_action('wp_ajax_' . 'etix-api-ajax', array($this,'ajax_handler') );
		}

		/**
		 * Adds the page to the admin menu
		 */
		public function add_menu_page() {
			$page_title = __( 'Etix API', 'tribe-events-calendar' );
			$menu_title = __( 'Etix API', 'tribe-events-calendar' );
			$capability = "edit_tribe_venues";

			// Tribe__Events__Main should be active by now
			$where = 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE;

			$this->admin_page = add_submenu_page( $where, $page_title, $menu_title, $capability, self::MENU_SLUG, array( $this, 'do_menu_page' ) );

		}

		/**
		 * Handle AJAX requests
		 */
		public function ajax_handler() {
			check_ajax_referer('etix-api-ajax-nonce','security');

			if( isset($_POST['etix-ajax-action']) ) {
				switch( isset($_POST['etix-ajax-action']) ) {
					case 'tools':

						// Add some time to chew through results (The AJAX timeout is 60s)
						set_time_limit( 300 );
						$master_timer = microtime(true);

						require_once RockhouseEvents::instance()->pluginPath . '/lib/rhp-sync-manager.class.php';

						$timer = microtime(true);
						$tribe = RockhouseSyncManager::fetchTribeCollection();
						$tribe_time = intval( (microtime(true) - $timer) * 1000 );

						$timer = microtime(true);
						$etix = RockhouseSyncManager::fetchEtixCollection();
						$etix_time = intval( (microtime(true) - $timer) * 1000 );

						if( !empty($etix) ) {
							$timer = microtime(true);
							$tribe->syncWith( $etix );
							$sync_time = intval( (microtime(true) - $timer) * 1000 );

							$master_time = intval( (microtime(true) - $master_timer) * 1000 );
							$timers = "\n Timers: {$master_time}ms  Total (Tribe: {$tribe_time}ms, Etix {$etix_time}ms, Object Merge {$sync_time}ms)";

							$response['payload'] = $tribe->last_log . $timers;
							$response['message'] = 'Request successful';
						} else {
							$response['payload'] = 'API Request Failed.  Please check the logs.';
						}

						break;
				}
			}


			$response['target'] = '#etix-tools-output';

			header('Content-Type: application/json');
			exit( json_encode($response) );
		}

		/**
		 * Simply include our view which will trigger relevant actions
		 */
		public function do_menu_page() {
			include_once RockhouseEvents::instance()->pluginPath . 'src/views/admin/etix-api-settings.php';
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

			$admin_content = RockhouseEvents::instance()->pluginPath . 'src/views/admin/etix-api-settings-tab-' . $this->current_tab . '.php';
			$default_content = RockhouseEvents::instance()->pluginPath . 'src/views/admin/etix-api-settings-tab-default.php';

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
				if( $_POST and !empty($_POST['etix-api-nonce']) ) {
					try {
						if( wp_verify_nonce( $_POST['etix-api-nonce'],'etix-api-settings' ) ) {

							// Loop through opt keys and find things to save
							$opts = RockhouseEvents::getOptions();

							foreach($opts as $k => $v) {

								// Only act by group or risk blanking out other tab data
								switch( $this->current_tab ) {

									case 'performances':

										if( $k == 'etixGroupSeries' or $k == 'etixAutoPublish' or $k == 'etixSetZeroAsFree' ) {
											RockhouseEvents::setOption($k, isset($_POST[$k]) ? true : false );
										} elseif( $k == 'etixApiSyncFields' and isset( $_POST[$k] ) and is_array( $_POST[$k] ) ) {
											$etix_fields = array();
											// Set to checked if in the HTML form, otherwise use the default value
											foreach( RockhouseEvents::$option_defaults['etixApiSyncFields'] as $api_field => $field_value ) {
											$etix_fields[$api_field] = in_array($api_field,$_POST[$k]) ? true : false;
											}
											// Immutable Variables
											$etix_fields['start_date_utc'] = true;
											$etix_fields['purchase_url'] = true;
											$etix_fields['status'] = true;
											$etix_fields['series_id'] = true;
											$etix_fields['series_name'] = true;
											RockhouseEvents::setOption($k,$etix_fields);
										} elseif( $k == 'etixApiExclusions' and !empty( $_POST[$k]) and $_POST[$k] != $opts[$k] ) {
											$ids = $strs = array();
											$exclude = explode("\n", html_entity_decode( $_POST[$k] ) );
											foreach($exclude as $line) {
												// Clear whitespace
												$line = trim($line);

												// skip blanks
												if( empty($line) ) {
													continue;
												}

												// Arrange perf ids at the end
												if( substr($line,0,3) == 'id:' ) {
													$ids[] = $line;
												} else {
													$strs[] = $line;
												}
											}
											RockhouseEvents::setOption($k, array_merge($strs, $ids) );
										}

									break;

									// This is the default TAB, not the default case
									case 'default':

										if( $k == 'etixApiKey' and !empty( $_POST[$k]) and $_POST[$k] != $opts[$k] ) {
											global $current_user;
											$user = is_object($current_user) ? $current_user->user_login : 'system';
											RockhouseEvents::setOption('etixApiKey', $_POST[$k]);
											RockhouseEvents::setOption('etixApiKeyUser', $user);
											RockhouseEvents::setOption('etixApiKeySaved', current_time('mysql') );

											// Test the key right now
											require_once RockhouseEvents::instance()->pluginPath . '/lib/rhp-sync-manager.class.php';
											$log = RockhouseLogger::instance();
											$api_test = RockhouseSyncManager::testEtixConnection( $_POST[$k] );
											$msg = ($api_test == 'Valid') ? 'New API Added and tested successfully' : 'Invalid API Key provided, connections suspended';
											$log::add($msg,'api-auth');
											RockhouseEvents::setOption('etixApiKeyStatus', $api_test);
										} elseif( $k == 'etixApiOrgVenueIds' and !empty( $_POST[$k]) and $_POST[$k] != $opts[$k] ) {
											RockhouseEvents::setOption($k, $_POST[$k]);
										}

									break;

									// Anything else
									default:
										die($k);

										if( array_key_exists($k,$_POST) and $_POST[$k] != $opts[$k] ) {
											RockhouseEvents::setOption($k, $_POST[$k]);
										}

									break;
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

			// Did we do something that needs redirecting??
			// wp_safe_redirect( $_SERVER['REQUEST_URI'] );

			// Pre-content leg work
			switch( $this->current_tab ) {

				case 'default':

					// Complete wipe of flagged events from Etix
					if( isset($_POST['rhp-show-undo']) or isset($_POST['rhp-undo-sync']) ) {
						global $rm_posts;
						$rm_posts = new WP_Query(
								array(
									'post_type' => Tribe__Events__Main::POSTTYPE,
									'nopaging' => true,
									'posts_per_page' => -1,
									'meta_query' => array(
														array(
															'key' => 'etix_activity_id',
															'compare' => '>',
															'value' => 0,
															'type' => 'numeric',
														)
													)
								)
						);

						// Do the deed, after confirmation
						if( isset($_POST['rhp-undo-sync']) ) {

							// Deletes take some time
							set_time_limit( 120 );

							// Wipe posts
							foreach($rm_posts->posts as $post) {
								$r = wp_delete_post($post->ID, true);
							}

							// Series, if we have any
							$series = term_exists('series', Tribe__Events__Main::TAXONOMY );
							if( is_array( $series ) ) {
								$all_series = get_terms( Tribe__Events__Main::TAXONOMY, array( 'parent' => $series['term_id'] ) );
								$all_series = get_term_children( $series['term_id'], Tribe__Events__Main::TAXONOMY );
								foreach( $all_series as $rm_term ) {
									wp_delete_term( $rm_term, Tribe__Events__Main::TAXONOMY );
								}
							}
							update_option( 'etix_api_series', array(), false );

							$log = RockhouseLogger::instance();
							$log::add('Undo Sync Command used to delete ' . $rm_posts->post_count . ' Events');

							// Only show this message
							$this->admin_notices = array( '<div id="message" class="updated"><p>A total of <strong>' . $rm_posts->post_count . '</strong> Events have been deleted.</p></div>' );

						}

					}

				break;

			}
		}

		/**
		 * When our Event Series grouping feature is active make sure
		 * we have our primary 'series' category created.
		 */
		public function enforce_series_cat() {
			$terms = term_exists('series', Tribe__Events__Main::TAXONOMY );
			if( !is_array( $terms ) ) {
				$ins =	wp_insert_term( 'Event Series', Tribe__Events__Main::TAXONOMY, array('description' => 'This is a special category for Event Series. Events will be grouped together for display if they are part of one of the sub-categories under this term. <br/><br/>You must create a sub-category, applying the "Event Series" category alone will have no effect.', 'slug' => 'series') );
			}
		}

		/**
		 * Static Singleton Factory Method
		 *
		 * @return EtixAPISettings
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
	EtixAPISettings::instance();
}
