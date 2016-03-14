<?php
/*
Plugin Name: The Events Calendar: Rockhouse Enhancements
Plugin URI: http://rockhousepartners.com
Description: Rockhouse Add-On for The Events Calendar
Version: 0.1.3
Author: Rockhouse Partners
Author URI: http://rockhousepartners.com
*/

// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if ( !class_exists( 'RockhouseEvents' ) ) {

	/**
	 * The Rockhouse Events Class
	 */
	class RockhouseEvents {
		protected static $instance;

		public $pluginDir;
		public $pluginPath;
		public $pluginUrl;
		public $pluginSlug;

		const VERSION = '0.1.3';

		const OPTIONSMETANAME = 'rhp_event_options';
		/**
		 * Sitewide plugin defaults
		 */
		public static $option_defaults = array(
								'eventArchiveWindow' 	=> 30,
								'multipleVenues' 	=> false,
								'eventBarViews' 	=> array('list'),
								'urlCobrandFilter' 	=> '',
								'urlPartnerFilter' 	=> '240',
								'ctaTextFreeShow' 	=> 'Free Show',
								'ctaTextSoldOut' 	=> 'Sold Out',
								'ctaTextComingSoon' => 'Coming Soon',
								'ctaTextOnSale' 	=> 'Buy Tickets',
								'ctaTextOffSale' 	=> 'Not Available Online',
								'ctaWidgetTextFreeShow' 	=> 'Free',
								'ctaWidgetTextSoldOut' 		=> 'Sold Out',
								'ctaWidgetTextComingSoon' 	=> 'Coming Soon',
								'ctaWidgetTextOnSale' 		=> 'Tickets',
								'ctaWidgetTextOffSale'		=> 'Unavailable',
								'etixApiKey'		=> '',
								'etixApiKeyStatus'	=> '',
								'etixApiKeyUser'	=> '',
								'etixApiKeySaved'	=> '',
								'etixApiOrgVenueIds'	=> '',
								'etixApiOrgVenueInfo'	=> '',
								'etixGroupSeries'		=> false,
								'etixAutoPublish'		=> true,
								'etixSetZeroAsFree'		=> false,
								'etixApiExclusions'	=> array(),
								'etixApiSyncFields'	=> array(
									'title' 				=> false,
									'image_url'				=> false,
									'description'			=> false,
									'start_date_utc'		=> true, // immutable
									'on_sale_date_utc'		=> true,
									'off_sale_date_utc'		=> true,
									'end_date_utc'			=> true,
									'purchase_url'			=> true,
									'cost'					=> false,
									'fb_url'				=> false,
									'status'				=> true, // immutable
									'series_id'				=> true, // immutable
									'series_name'			=> true  // immutable
								)
							);

		/**
		 * @var array Plugin Options
		 */
		public $options = false;

		/**
		 * Static Singleton Factory Method
		 * @return RockhouseEvents
		 */
		public static function instance() {
			if (!isset(self::$instance)) {
				$className = __CLASS__;
				self::$instance = new $className;
			}
			return self::$instance;
		}

		/**
		 * Initializes plugin variables and sets up WordPress hooks/actions.
		 */
		protected function __construct( ) {
			$this->pluginDir = trailingslashit( basename( dirname( __FILE__ ) ) );
			$this->pluginPath = trailingslashit( dirname( __FILE__ ) );
			$this->pluginUrl = WP_PLUGIN_URL.'/'.$this->pluginDir;
			$this->pluginSlug = 'rhp-tribe-events';

			// Add logger class in WP Admin
			if( is_admin() or defined('DOING_CRON') ) {
				require_once $this->pluginPath . 'lib/rhp-logger.class.php';
			}

			// Add Admin Notices early
			add_action( 'init', array( $this, 'adminNotices' ) );

			// Move our includes after plugins_loaded
			add_action( 'plugins_loaded', array( $this, 'pluginsLoaded') );

			// Update checker
			add_action( 'rhp_tribe_updates', array($this,'selfUpdates') );

			if( is_admin() and isset($_GET['force-check']) ) {
				$this->selfUpdates();
			}

			if( is_admin() ) {
				// Modify the default Event sorting to be sensible (Admin only)
				require_once $this->pluginPath . 'lib/rhp-tribe-wpadmin.class.php';
			} else {
				// Add our custom filterin of the Events Bar (Frontend only)
				require_once $this->pluginPath . 'lib/rhp-tribe-eventsbar.class.php';
				// Add our Google Knowledge Graph JSON+LD (Frontend only)
				require_once $this->pluginPath . 'lib/rhp-tribe-googlekg.class.php';
			}


		}

		/**
		 * Load all the required library files.
		 */
		protected function loadLibraries() {
			// Abstraction for meta save/load
			require_once $this->pluginPath . 'admin/rhp-events-settings.class.php';
			require_once $this->pluginPath . 'admin/etix-api-settings.class.php';

			// Add our helper template tags
			require_once $this->pluginPath . 'public/template-tags/events.php';

			// Add our Events Widget mods
			require_once $this->pluginPath . 'lib/rhp-tribe-widget-eventslist.class.php';
			// Replace Tribe Widget
			add_action( 'widgets_init', array( 'RHPTribeEventsListWidget', 'swap_tribe_widget' ), 100 );
		}

		/**
		 * Add filters and actions
		 */
		protected function addHooks() {
			// Add Filters for ACF CTA Labels
			add_filter( 'acf/load_value', array( $this, 'defaultCtaLabels'), 10, 3 );

			// Add Filters for ACF URL Updates
			add_filter( 'acf/update_value/name=rhp_event_cta_url', array( $this, 'filterCtaUrl'), 50, 3 );

			// Restrict the ACF field match for our custom Event Series taxonomy on Tribe
			add_filter( 'acf/location/rule_match/ef_taxonomy', array($this, 'filterAcfTaxLocation'), 50, 3);

			// Modify the main Tribe Query if needed
			add_action( 'tribe_events_pre_get_posts', array( $this, 'pre_get_posts' ), 100, 1 );
			add_action( 'posts_results', array( $this, 'posts_results' ), 10, 2 );

			// Add our /rhp/ AddOn templates to the search paths
			add_filter( 'tribe_events_template_paths', array( $this, 'template_paths' ), 10, 1 );

			// Make room for the times we need to alter template includes
			add_filter( 'tribe_get_template_part_templates', array( $this, 'template_part_include' ), 10, 3 );

			// Modify Admin Metaboxes we may not want
			add_filter( 'admin_menu', array( $this, 'adminMetaBoxes' ), 110, 0 );

			// Handle Past Events
			add_action( 'wp', array( $this, 'checkPastEvents' ), 100, 1);

			// Substitute our alternate image if we have one
			add_filter( 'tribe_event_featured_image', array( $this, 'altEventImage' ), 50, 3 );
			add_filter( 'tribe_events_template_data_array', array( $this, 'altEventImageTooltip' ), 50, 3 );

			// Add our 10m Cron
			add_filter( 'cron_schedules', array( $this, 'addTenMinuteCron' ), 10, 1 );

			// Etix API Sync Cron Job (Must be added every load since $wp_filters is built dynamically)
			$etix_key = RockhouseEvents::getOption('etixApiKeyStatus');
			if( $etix_key == 'Valid' ) {
				add_action( 'rhp_tribe_sync', array('RockhouseEvents','cronEtixSync'), 123 );
			}

		}

		/**
		 * Modify Meta Boxes
		 *
		 * Action: admin_menu
		 */
		public function adminMetaBoxes() {
			// Hide the TEC Meta Box for Event Options
			// alternative: return null for hook: tribe_events_meta_box_template
			remove_meta_box('tribe_events_event_details',Tribe__Events__Main::POSTTYPE,'normal');

			// Hide the Tribe App Shop
			remove_submenu_page( 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE , Tribe__Events__App_Shop::MENU_SLUG );

			global $submenu;
			// Drop Venue editor off if we don't need it
			if( ! self::getOption( 'multipleVenues' ) )
				remove_submenu_page( 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE, 'edit.php?post_type=' . Tribe__Events__Main::VENUE_POST_TYPE );

			// At this time we aren't using Organizers
			remove_submenu_page( 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE, 'edit.php?post_type=' . Tribe__Events__Main::ORGANIZER_POST_TYPE );
		}

		/**
		 * Hook in to admin notices if validations failed, these are set with an option
		 * that can only be used with the save_post_tribe_events hook, so there
		 * isn't a need for much type checking.
		 *
		 * action: admin_head-post.php
		 */
		public function adminNotices() {
			$notice = get_option( __CLASS__ . '_admin_notices' );
			if( ! empty( $notice ) )
				add_action('admin_notices', array( $this, 'displayNotices' ) );
		}

		/**
		 * Show admin notices if validations failed
		 * action: admin_notices
		 */
		public function displayNotices() {
			$notice = get_option( __CLASS__ . '_admin_notices' );
			echo '<div class="error"> <p>'. $notice . '</p> </div>';
			update_option( __CLASS__ . '_admin_notices', false );
		}

		/**
		 * Add a Cron entry for 10 minute intervals
		 *
		 * @filter cron_schedules
		 */
		public function addTenMinuteCron( $schedules ) {

			$schedules['rhp_ten_minutes'] = array(
				'interval' => 600,
				'display' => __('Once Every 10 Minutes')
			);

			return $schedules;

		}

		/**
		 * Filter the Query for pre_get_posts
		 *
		 * @action tribe_events_pre_get_posts
		 * @param WP_Query $query
		 */
		public function pre_get_posts( $query ) {

			// Always show ALL posts on the primary /events/ archive or other archive pages (not tax)
			// Note: The tribe widget does this via a filter since it is not a main loop query
			if( $query->tribe_is_event and !is_admin() ) {
				set_query_var( 'posts_per_page', -1 );
				set_query_var( 'nopaging', true );
			}

		}

		/**
		 * Manipulate the posts result when we need to
		 *
		 * @filter posts_results
		 * @param array $posts
		 * @param WP_Query $query
		 */
		public function posts_results( $posts, $query ) {

			// Qualify that we on the Events Archive view, or a Taxonomy view for 'series'
			if( $query->tribe_is_event and !is_admin() and count( $posts ) and self::getOption( 'etixGroupSeries' ) ) {

				//$qo = get_queried_object();
				// ( is_tax( Tribe__Events__Main::TAXONOMY ) and ( $qo->slug == 'series' or $qo->parent == $series_cat->term_id ) )
				// ( is_tax( Tribe__Events__Main::TAXONOMY ) and ( $qo->slug == 'series' ) )

				if(
					// Main Events List View (Archive)
					( is_archive() and !is_tax() and get_query_var( 'eventDisplay' ) == 'list' )
					// Series Category Archive
					or ( is_tax( Tribe__Events__Main::TAXONOMY ) and ( get_queried_object()->slug == 'series' ) )
					// Widget listing like the Homepage with Group Series option set
					or ( isset( $query->query['rhptribe_group_widget_list'] ) )
				 ) {
					// Loop through events and make our Series appear as a single entry, chronologically
					// We will tamper with the WP_Query object to append a reference of the sub-events

					// First gather all our known series and data
					$all_series = array();
					$series_cat = get_term_by( 'slug', 'series', Tribe__Events__Main::TAXONOMY );
					$current_series = get_terms( Tribe__Events__Main::TAXONOMY, array( 'child_of' => $series_cat->term_id ) );
					foreach( $current_series as $series ) {
						$acf_term = "{$series->taxonomy}_{$series->term_id}";
						$cat_fields = get_fields( $acf_term );

						// This isn't returning in get_fields all the time?
						$series_id = (int)get_field('rhp_series_etix_event_id',$acf_term);

						// Check for locally created series
						if( empty( $series_id ) ) {
							// There is a real possibility that an Etix Event ID may collide with a Term ID....but very small
							$series_id = $series->term_id;
						}

						// Let's calculate these below instead of assigning
						//$cat_fields['start_ts'] = strtotime( $cat_fields['rhp_series_start_date'] );
						//$cat_fields['end_ts'] = strtotime( $cat_fields['rhp_series_end_date'] );
						$cat_fields['start_ts'] = 0;
						$cat_fields['end_ts'] = 0;

						$cat_fields['posts'] = array();

						if( empty( $cat_fields['rhp_series_cta_url'] ) ) {
							$cat_fields['rhp_series_cta_url'] = '';
						}

						$cat_link = get_term_link( $series );
						$cat_fields['series_link'] = is_wp_error( $cat_link ) ? '' : $cat_link;

						$cat_fields['term'] = $series;
						$all_series[$series_id] = $cat_fields;
					}

					// Remove series events from the first class Events
					$posts_events = array();
					foreach( $posts as $evt ) {
						$start = strtotime( $evt->EventStartDate );

						// Etix Series via API?
						$evt_series = (int)get_post_meta( $evt->ID, 'series_id', true );

						// Not an API based series, is it a locally created series?
						if( empty( $evt_series ) ) {
							$cat_terms = get_the_terms( $evt->ID, Tribe__Events__Main::TAXONOMY );
							if( !empty( $cat_terms ) ) {
								foreach( $cat_terms as $cat_term ) {
									// Is a nested Subcat of Event Series
									if( $cat_term->parent == $series_cat->term_id ) {
										$evt_series = $cat_term->term_id;
									}
								}
							}

						}

						if( empty( $evt_series ) ) {
							$evt->is_series = false;
							$rand = rand(5,6); // randomize to avoid collisions on same start date
							$posts_events[ $start . $rand ] = $evt;
						} else {
							if( !isset( $all_series[$evt_series] ) ) {
								$all_series[$evt_series] = array('start_ts' => 0, 'end_ts' => 0, 'posts' => array() );
							}

							if( empty($all_series[$evt_series]['start_ts']) or $all_series[$evt_series]['start_ts'] > $start ) {
								$all_series[$evt_series]['start_ts'] = $start;
							}

							if( empty($all_series[$evt_series]['end_ts']) or $all_series[$evt_series]['end_ts'] < $start ) {
								$all_series[$evt_series]['end_ts'] = $start;
							}

							// Try extra hard to see that we get an image, on the first post in a series
							if( empty( $all_series[$evt_series]['rhp_series_page_image'] ) ) {
								$post_img = get_the_post_thumbnail( $evt->ID, 'full' );
								if( empty( $post_img ) ) {
									// Try the Etix alt image
									$alt_img = get_post_meta( $evt->ID, 'alt_event_img', true );
									if( !empty( $alt_img ) ) {
										$all_series[$evt_series]['rhp_series_page_image'] = $alt_img;
									}
								} else {
									// Pick apart the HTML from get_post_thumbnail (maybe use wp_get_attachment_image_src?)
									preg_match( '/src="(.+)"/i', $post_img, $src_match );
									if( isset( $src_match[1] ) ) {
										$all_series[$evt_series]['rhp_series_page_image'] = $src_match[1];
									}
								}
							}

							$all_series[$evt_series]['posts'][] = $evt;
						}
					}

					// Make sure array is ordered correctly by start date of Perf or earliest series date
					foreach( $all_series as $id => $series ) {
						// Do not include series that don't have events (happens with filtered widgets)
						if( !empty( $series['posts'] ) ) {
							$post_series = new stdClass();
							$post_series->ID = (int)$id;
							$post_series->is_series = true;

							$post_series->series_link = $series['series_link'];
							$post_series->series_cta_url = $series['rhp_series_cta_url'];
							$post_series->series_title = $series['term']->name;

							// Sanity check
							if( empty( $series['start_ts'] ) and !empty( $series['rhp_series_start_date'] ) ) {
								$series['start_ts'] = strtotime( $series['rhp_series_start_date'] );
							}

							if( empty( $series['end_ts'] ) and !empty( $series['rhp_series_end_date'] ) ) {
								$series['end_ts'] = strtotime( $series['rhp_series_end_date'] );
							}

							$rand = rand(5,6); // randomize to avoid collisions on same start date
							$posts_events[ $series['start_ts'] . $rand ] = $post_series;
						}
					}

					ksort( $posts_events );

					// Store this for use on the Archive page
					$query->event_series = $all_series;

					// Trim events if we're on the homepage widget
					if( isset( $query->query['rhptribe_group_widget_list'] ) ) {
						$posts_slice = array_slice( $posts_events, 0, $query->query['widget_num_posts'] );
						$posts_events = $posts_slice;

						// Also, tribe does a strange thing where they strip the WP_Query to an array
						// with widget output, so we have to tuck our series info elsewhere
						global $rhptribe_series;
						$rhptribe_series = $all_series;
					}

					// String our index order key
					return array_values( $posts_events );

				}
			}

			return $posts;

		}

		/**
		 * Add Rockhouse AddOn paths to the templates array
		 *
		 * @param $template_paths array
		 * @return array
		 */
		public function template_paths( $template_paths = array() ) {
			$template_paths['rhp'] =  $this->pluginPath;
			return $template_paths;
		}

		/**
		 * Alter template selection on tribe_get_template_part()
		 *
		 * @filter tribe_get_template_part_templates
		 * @param array $templates List of templates
		 * @param string $slug
		 * @param null|string $name
		 */
		public function template_part_include( $templates = array(), $slug = null, $name = null ) {

			// Strike the navbar and footer on taxonomy queries
			if( self::getOption( 'etixGroupSeries' ) and is_tax( Tribe__Events__Main::TAXONOMY ) ) {
				$series_cat = get_term_by( 'slug', 'series', Tribe__Events__Main::TAXONOMY );
				$event_cat = get_queried_object();
				if( $event_cat->term_id == $series_cat->term_id or $event_cat->parent == $series_cat->term_id ) {
					if( $slug == 'modules/bar' or ( $slug = 'list/nav' and $name == 'footer') ) {
						$templates = array();
					}
				}
			}

			return $templates;
		}

		/**
		 * Add our Plugin Dependent hooks
		 *
		 * Action: plugins_loaded
		 */
		public function pluginsLoaded() {
			$errors = array();
			if( class_exists( 'Tribe__Events__Main' )  ) {
				if( version_compare( Tribe__Events__Main::VERSION, '3.10','>=') ) {

					$this->addHooks();
					$this->loadLibraries();

					// Default ACF Fields
					if( function_exists( 'register_field_group' ) ) {
						require_once 'resources/rhp-events-acf-defaults.php';

						// The Plan:  Remove the default Tribe__Events__API::saveEventMeta hooked to save_post
						// and extend the ACF save_post hook to utilize the advanced API interface of TEC
						// located in the-events-calendar/public/advanced-functions.php

						// Note to future Dale: For reasons that remain unexplained the TEC Tribe__Events__Main object
						// definitely adds its addEventMeta to 'save_post_tribe_events' however if you
						// var_dump($wp_filter) global as of v3.9.1 you will see it under 'save_post' instead
						// ... so I'm running both remove_actions to be sure
						$tec = Tribe__Events__Main::instance();
						remove_action( 'save_post', array( $tec, 'addEventMeta' ), 15, 2 );
						remove_action( 'save_post_' . Tribe__Events__Main::POSTTYPE, array( $tec, 'addEventMeta' ), 15, 2 );

						// Now let's handle the saving ourselves
						// Ref: http://www.advancedcustomfields.com/resources/acfsave_post/
						add_action('save_post', array( $this, 'convertAcfToTecFields' ), 90, 3 );

						// Add Venue select if we need it
						if( self::getOption( 'multipleVenues' ) )
							require_once 'resources/rhp-events-acf-venue.php';

						// Add Event Series Category fields if we nee dit
						if( self::getOption( 'etixGroupSeries' ) )
							require_once 'resources/rhp-events-acf-series-category.php';

						// Set our active flag for other plugins/themes to consume
						if( !defined( 'RHPTRIBE_ACTIVE' ) ) {
							define( 'RHPTRIBE_ACTIVE', true );
						}

					} else {
						$errors[] = 'WARNING: Advanced Custom Fields Plugin not active';
					}
				} else {
					$errors[] = 'WARNING: The Rockhouse AddOn requires The Events Calendar 3.10 or above. Please update to enable full use of this AddOn.';
				}

				// Sanity check for Location data
				if( is_admin() and ! self::getOption( 'multipleVenues' ) ) {
					$tec_default_addr = tribe_get_option('eventsDefaultAddress',false);
					$tec_default_city = tribe_get_option('eventsDefaultCity',false);
					$tec_default_state = tribe_get_option('eventsDefaultState',false);

					if( empty($tec_default_addr) or empty($tec_default_city) or empty($tec_default_state) ) {
						$errors[] = 'WARNING: Your venue needs to have an address, city, and state set under <a href="'.admin_url('edit.php?post_type=tribe_events&page=tribe-events-calendar&tab=defaults').'">Events Settings > Default Content</a>';
					}
				}

			} else {
				$errors[] = 'WARNING: Modern Tribe: The Event Calendar Plugin needs to be activated';
			}

			if( ! empty( $errors ) ) {
				update_option( __CLASS__ . '_admin_notices', implode('<br/>',$errors) );
			}
		}

		/**
		 * Validate required variables on save and transmogrify from our ACF
		 * to the TEC $_POST variables it execpts.
		 *
		 * Filter: save_post
		 */
		public function convertAcfToTecFields($post_id, $post, $update) {
			// Ignore operations that aren't a real insert or update
			if(
				( ( isset( $_POST['post_type']) and $_POST['post_type'] != Tribe__Events__Main::POSTTYPE ) || defined( 'DOING_AJAX' ) ) or
				( isset( $_POST['post_ID'] ) && $post_id != $_POST['post_ID'] ) or
				( wp_is_post_autosave( $post_id ) || (isset( $_POST['post_status']) and $_POST['post_status'] == 'auto-draft') || isset( $_GET['bulk_edit'] ) || ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'inline-save' ) ) or
				( ! current_user_can( 'edit_tribe_events' ) )
			) {
				return;
			}

			$errors = array();

			// Avoid loops
			remove_action('save_post', array( $this, 'convertAcfToTecFields' ), 90, 3 );

			// This is run after ACF has saved
			$acf = get_fields($post_id);

			// Start. Setting. Fields.
			$evt = array(
				'ID' 		=> $post_id,
				'post_ID'	=> $post_id,
				'post_type' => Tribe__Events__Main::POSTTYPE
			);

			$start_ts = strtotime($acf['rhp_event_start_date']);
			$end_ts = strtotime( tribe_event_end_of_day( $acf['rhp_event_start_date'] ) );

			// Standard TEC Fields
			$evt['EventStartDate']		= date( Tribe__Events__Date_Utils::DBDATEFORMAT, $start_ts );
			$evt['EventEndDate'] 		= date( Tribe__Events__Date_Utils::DBDATEFORMAT, $end_ts );

			$evt['EventAllDay'] 		= $acf['rhp_event_all_day'];

			$evt['EventStartHour']		= date( Tribe__Events__Date_Utils::HOURFORMAT , $start_ts );
			$evt['EventStartMinute']	= date( Tribe__Events__Date_Utils::MINUTEFORMAT , $start_ts );
			$evt['EventStartMeridian']	= date( Tribe__Events__Date_Utils::MERIDIANFORMAT , $start_ts );

			$evt['EventEndHour']		= date( Tribe__Events__Date_Utils::HOURFORMAT, $end_ts );
			$evt['EventEndMinute']		= date( Tribe__Events__Date_Utils::MINUTEFORMAT , $end_ts );
			$evt['EventEndMeridian']	= date( Tribe__Events__Date_Utils::MERIDIANFORMAT , $end_ts );

			$evt['EventHideFromUpcoming'] = (isset($_POST['EventHideFromUpcoming'])) ? true : false;
			$evt['EventShowInCalendar'] = (isset($_POST['EventShowInCalendar'])) ? true : false;

			$evt['EventURL'] 			= $acf['rhp_event_cta_url'];

			// Figure out what we can from the text field
			$vals = explode('#', str_replace( array('-','$','/',' '),'#',$acf['rhp_event_cost']) );
			$price = 0;
			foreach($vals as $val) {
				if( is_numeric($val) and ($price == 0 or $price > (float)$val ) ) {
					$price = (float)$val;
				}
			}

			$evt['EventCost'] 			= $price;
			$evt['EventCurrencySymbol'] = '$';

			// Pro TEC Fields
			$evt['EventShowMapLink']	= false;
			$evt['EventShowMap']		= false;

			// @TODO: Must deal with Event Recurrence


			// If the Tribe interfaces for adding Organizers / Venues has been set, pass that through
			if( isset($acf['rhp_event_venue']) and is_object($acf['rhp_event_venue']) )
				$evt['Venue']['VenueID'] = $acf['rhp_event_venue']->ID;

			if( isset($_POST['Organizer']['OrganizerID']) )
				$evt['Organizer']['OrganizerID'] = (int)$_POST['Organizer']['OrganizerID'];


			// Here we are unsetting Tribe events fields in $_POST since we are using their
			// internal API to create/update events in the ACF save hook.  This is just a
			// precaution in case the TribeEvent hooks somehow fire.

			// Standard TEC
			unset( $_POST['EventAllDay'] );
			unset( $_POST['EventStartDate'] );
			unset( $_POST['EventStartHour'] );
			unset( $_POST['EventStartMinute'] );
			unset( $_POST['EventStartMeridian'] );
			unset( $_POST['EventEndDate'] );
			unset( $_POST['EventEndHour'] );
			unset( $_POST['EventEndMinute'] );
			unset( $_POST['EventEndMeridian'] );
			unset( $_POST['EventURL'] );
			unset( $_POST['EventCurrencySymbol'] );
			unset( $_POST['EventCurrencyPosition'] );
			unset( $_POST['EventShowInCalendar'] );
			unset( $_POST['EventCost'] );
			unset( $_POST['EventShowMap'] );
			unset( $_POST['EventHideFromUpcoming'] );
			unset( $_POST['EventShowMapLink'] );
			// TEC Pro
			unset( $_POST['EventConference'] );
			unset( $_POST['EventVenueID'] );
			unset( $_POST['EventOrganizerID'] );
			unset( $_POST['EventRecurrence'] );


			// Do the business
			// We don't use the tribe_create_event() "api" function since it invokes several
			// save_post actions designed to create an entire post object.  We are merely saving
			// the Event Calendar meta on an already saved post.  So let's skip the runaround
			// and use Tribe__Events__API::saveEventMeta() directly.  This function only requires
			// the actual Event data above to be passed and saved to the postmeta.

			// This will also run the action 'tribe_events_update_meta'
			Tribe__Events__API::saveEventMeta($post_id,$evt);

			// Side note: saveEventMeta calls for the $post obj in arg 3, which is only
			// passed to Tribe__Events__Main::saveEvent[Venue|Organizer] where it isn't even used

			if( ! empty( $errors ) )
				update_option( __CLASS__ . '_admin_notices', implode('<br/>',$errors) );

			// OK, loops avoided
			add_action('save_post', array( $this, 'convertAcfToTecFields' ), 90, 3 );

			return $post_id;
		}

		/**
		 * Apply our custom Attributes to any Etix CTAs
		 */
		public function filterCtaUrl( $value, $post_id, $field ) {
			if( ( is_admin() or defined('DOING_CRON') )  and stripos($value,'etix.com') > 0 ) {

				// Old urlparm type Etix URLs
				if( stripos($value,'?') > 0 ) {
					list($uri,$vars) = explode('?',$value);
				} else {
					$uri = $value;
					$vars = '';
				}

				$url_parts = array();
				if( !empty($vars) ) {
					$var_pairs = explode( '&', htmlspecialchars_decode( strtolower($vars) ) );
					foreach( $var_pairs as $var_pair ) {
						list($key,$value) = explode('=',$var_pair);
						$url_parts[$key] = $value;
					}
				}

				// Only overwrite our vars if not present
				$cbd = RockhouseEvents::getOption( 'urlCobrandFilter' );
				if( !empty($cbd) and !isset( $url_parts['cobrand'] ) )
					$url_parts['cobrand'] = $cbd;

				$pid = RockhouseEvents::getOption( 'urlPartnerFilter' );
				if( !empty($pid) and !isset( $url_parts['partner_id'] ) )
					$url_parts['partner_id'] = $pid;

				// Reconstruct the URL
				$new_url = $uri;
				if( !empty($url_parts) ) {
					$new_url .= '?';
					foreach( $url_parts as $vark => $varv ) {
						$new_url .= $vark . '=' . $varv . '&';
					}
					$value = rtrim($new_url,'&');
				}
			}
			return $value;
		}

		/**
		 * ACF has this annoying quirk where programatically altering or updating
		 * fields doesn't work correctly unless you "prime the pump" and set a value
		 * for all fields in the group via their field ids.
		 *
		 * But you can't access field IDs programatically unless it has been written
		 * already.  Chicken <=> Egg.
		 *
		 * So looking at the register_group_field() shows that they just drop the field list
		 * as a global var, since it includes the default value and field_id we can go ahead
		 * and prepare fields generically.
		 *   https://github.com/elliotcondon/acf/blob/master/core/api.php#L806
		 *
		 * @param $post_id string|int Post ID or the special taxonomy/user key for ACF
		 * @param $acf_group_id string The string id of the field group to prep
		 */
		public function primeAcfGroup( $post_id, $acf_group_id ) {
			if( isset( $GLOBALS['acf_register_field_group'] ) and is_array( $GLOBALS['acf_register_field_group'] ) ) {
				foreach( $GLOBALS['acf_register_field_group'] as $acf_group ) {
					if( $acf_group['id'] == $acf_group_id ) {
						foreach( $acf_group['fields'] as $acf_field ) {
							update_field( $acf_field['key'], false, $post_id );
						}
					}
				}
			}
		}

		/**
		 * This filter allows the use of the standard ACF get_field / the_field
		 * calls in the child theme but will take care of placing the default
		 * text when a new Event is created in the WP Admin ACF Fields
		 */
		public function defaultCtaLabels( $value, $post_id, $field ) {

			if( ( is_admin() or defined('DOING_CRON') ) and empty($value) and ( substr($field['name'],0,4) == 'rhp_' ) ){
				$value = self::acfCtaLabelByField( $field['name'] );
			}

			return $value;
		}

		/**
		 * Generic function to get default CTA label for a given ACF field name
		 *
		 * @param $field_name
		 */
		public static function acfCtaLabelByField( $name ) {

			$value = '';
			switch ( $name ) {
				case 'rhp_event_cta_label_free_show':
					$value = RockhouseEvents::getOption( 'ctaTextFreeShow' );
					break;
				case 'rhp_event_cta_label_sold_out':
					$value = RockhouseEvents::getOption( 'ctaTextSoldOut' );
					break;
				case 'rhp_event_cta_label_coming_soon':
					$value = RockhouseEvents::getOption( 'ctaTextComingSoon' );
					break;
				case 'rhp_event_cta_label_on_sale':
					$value = RockhouseEvents::getOption( 'ctaTextOnSale' );
					break;
				case 'rhp_event_cta_label_off_sale':
					$value = RockhouseEvents::getOption( 'ctaTextOffSale' );
					break;
			}

			return $value;
		}

		/**
		 * Rhino Event CTA Button
		 *
		 * Step 1: Yield to CTA Modifiers (hide, sold out, free show)
		 * Step 2: Walk through CTAs for Event Lifecycle
		 *
		 * @param $type string Type of CTA label (event|widget, default event)
		 * @param $post varies Post Object or Post ID to use (optional, defaults to global $post object)
		 *
		 * Filter: rhp_event_cta_content
		 */
		public static function getEventCtaContent( $type = 'event', $post_id = null) {

			$rhp_cta = array(
								'classes' => array('rhp-event-cta'),
								'href' => '',
								'label' => ''
							);

			if( is_object($post_id) )
				$post_id = $post_id->ID;

			if( empty( $post_id ) )
				$post_id = get_the_ID();

			if( is_numeric($post_id) )
				$post_id = (int)$post_id;

			if( get_post_type($post_id) == Tribe__Events__Main::POSTTYPE ) {

				$label = '';

				if( get_field('rhp_event_hide_cta',$post_id) ) {
					// Do nothing!
				} elseif( get_field('rhp_event_sold_out',$post_id) ) {
					$rhp_cta['classes'][] = 'sold-out';
					$label = 'rhp_event_cta_label_sold_out';
				} elseif( get_field('rhp_event_free_show',$post_id) ) {
					$rhp_cta['classes'][] = 'free';
					$label = 'rhp_event_cta_label_free_show';
				} else {
					// Event Lifecycle CTAs
					$status = rhp_event_status( $post_id );
					switch( $status ) {
						case 'comingsoon':
							$rhp_cta['classes'][] = 'coming-soon';
							$label = 'rhp_event_cta_label_coming_soon';
							break;
						case 'offsale':
							$rhp_cta['classes'][] = 'off-sale';
							$label = 'rhp_event_cta_label_off_sale';
							break;
						case 'past':
							// No CTAs for archived events
							break;
						case 'onsale':
						default:
							$rhp_cta['classes'][] = 'on-sale';
							$label = 'rhp_event_cta_label_on_sale';
							$rhp_cta['href'] = get_field('rhp_event_cta_url',$post_id);
					}
				}

				// Get the Label
				if( $label ) {
					if( $type == 'event' ) {
						// Events use the override with filter fallback defaultCtaLabel
						$rhp_cta['label'] = get_field($label,$post_id);

						// Fallback when default may not be set (happens if client blanks field or API sync doesn't get applied)
						if( empty( $rhp_cta['label'] ) ) {
							$rhp_cta['label'] = self::acfCtaLabelByField( $label );
						}

					} else {
						// Must be a widget, use global defaults
						switch($label) {
							case 'rhp_event_cta_label_free_show':
								$rhp_cta['label'] = RockhouseEvents::getOption( 'ctaWidgetTextFreeShow' );
								break;
							case 'rhp_event_cta_label_sold_out':
								$rhp_cta['label'] = RockhouseEvents::getOption( 'ctaWidgetTextSoldOut' );
								break;
							case 'rhp_event_cta_label_coming_soon':
								$rhp_cta['label'] = RockhouseEvents::getOption( 'ctaWidgetTextComingSoon' );
								break;
							case 'rhp_event_cta_label_on_sale':
								$rhp_cta['label'] = RockhouseEvents::getOption( 'ctaWidgetTextOnSale' );
								break;
							case 'rhp_event_cta_label_off_sale':
								$rhp_cta['label'] = RockhouseEvents::getOption( 'ctaWidgetTextOffSale' );
								break;
						}
					}
				}
			}
			// Add filter for tweaks
			return apply_filters('rhp_event_cta_content',$rhp_cta,$type,$post_id);
		}

		/**
		 * Rhino Event Series CTA Button
		 *
		 * Filter: rhp_event_series_cta_content
		 */
		public static function getEventSeriesCtaContent() {

			$rhp_cta = array(
								'classes' => array('rhp-event-cta'),
								'href' => '',
								'label' => 'Find Tickets'
							);

			// This should only be called on our mock Series $post in grouped queries (archive, homepage widget)
			global $post;
			if( !empty( $post->series_cta_url ) ) {
				$rhp_cta['href'] = $post->series_cta_url;
				$rhp_cta['classes'][] = 'on-sale';
			}

			// Fix up CTA for Events Taxonomy view for Series
			if( is_tax( Tribe__Events__Main::TAXONOMY ) ) {
				$series_term = get_term_by( 'slug', 'series', Tribe__Events__Main::TAXONOMY );
				$rhp_cta_term = get_queried_object();
				if( $series_term->term_taxonomy_id == $rhp_cta_term->parent ) {
					$acf_id = Tribe__Events__Main::TAXONOMY.'_'.$rhp_cta_term->term_taxonomy_id;
					$acf_url = get_field( 'rhp_series_cta_url', $acf_id );
					if( !empty( $acf_url ) ) {
						$rhp_cta['href'] = $acf_url;
						$rhp_cta['classes'][] = 'on-sale';
					}
				}
			}

			// We don't have a lifecycle for this CTA type yet
			if( empty( $rhp_cta['href'] ) ) {
				$rhp_cta['label'] = '';
			}

			return apply_filters( 'rhp_event_series_cta_content', $rhp_cta );
		}


		/**
		 * A filter to hide our custom Event Series ACF fields on
		 * all Tribe Categories except those under Series
		 *
		 * @filter acf/location/rule_match/ef_taxonomy
		 */
		public function filterAcfTaxLocation( $match, $rule, $options ) {
			if( !empty( $_GET['taxonomy'] ) and $_GET['taxonomy'] == Tribe__Events__Main::TAXONOMY and self::getOption( 'etixGroupSeries' ) and $rule['value'] == Tribe__Events__Main::TAXONOMY ) {
				// Ignore on the top level Category
				if( empty( $_GET['tag_ID'] ) ) {
					$match = false;
				} else {
					// Do not display if this isn't a child of the 'series' term
					$series_cat = get_term_by( 'slug', 'series', Tribe__Events__Main::TAXONOMY );
					$current_cat = get_term_by( 'id', $_GET['tag_ID'], Tribe__Events__Main::TAXONOMY );
					if( $current_cat->parent != $series_cat->term_id ) {
						$match = false;
					}
				}
			}
			return $match;
		}

		/**
		 * Check for a past event and handle optimal SEO headers
		 * and experience
		 *
		 * @filter wp
		 */
		public function checkPastEvents($wp) {

			global $post;

			if( class_exists('Tribe__Events__Main') and is_singular(Tribe__Events__Main::POSTTYPE ) and rhp_event_status_is('past',$post) ) {
				// Are we past the archive window?
				$window = (int) RockhouseEvents::getOption('eventArchiveWindow') * DAY_IN_SECONDS;
				$end_ts = (int) strtotime( tribe_get_end_date($post->ID,true,'Y-m-d H:i:s') );
				$now = current_time('timestamp');

				if( ( $now - $end_ts ) < $window ) {
					// Display event with 410 headers
					nocache_headers();
					status_header( 410 );
				} else {
					// Stop and redirect to events archive
					wp_redirect( tribe_get_events_link() , 301);
					exit;
				}
			}
		}

		/**
		 * Trick the tribe_event_featured_image() function to use an alternate
		 * resource stored in the postmeta (used with Etix event Sync)
		 *
		 * @param string $featured_image HTML for a Featured Image
		 * @param int $post_id Post ID
		 * @param array $size Array of image dimensions requested
		 * @filter tribe_event_featured_image
		 */
		public function altEventImage( $featured_image, $post_id, $size ) {
			if( empty( $featured_image ) ) {
				$alt_img = get_post_meta($post_id,'alt_event_img',true);
				if( !empty( $alt_img ) ) {
					$featured_image = '<div class="tribe-events-event-image rhp-event-image-offsite"><img src="' . esc_url( $alt_img ) . '" title="' . get_the_title( $post_id ) . '" /></div>';
				}
			}
			return $featured_image;
		}

		/**
		 * Add our alternate image for Tooltips in Calendar view.  The normal code
		 * relies on wp_get_attachment_image_src which doesn't have any intemediare
		 * filters to use.
		 *
		 * See: /src/functions/template-tags/general.php
		 * 				tribe_events_template_data()
		 *
		 * @param array $json
		 * @param WP_Post $event
		 * @param array $additional
		 * @filter tribe_events_template_data_array
		 */
		public function altEventImageTooltip( $json, $event, $additional ) {

			if( is_object( $event ) and empty( $json['imageTooltipSrc'] ) ) {
				$alt_img = get_post_meta($event->ID,'alt_event_img',true);
				if( !empty( $alt_img ) ) {
					$json['imageTooltipSrc'] = esc_url( $alt_img );
				}
			}

			return $json;
		}

		/**
		 * Static access for single option
		 */
		public static function getOption($name) {
			$opts = RockhouseEvents::getOptions();

			if( !isset($opts[$name] ) ) {
				trigger_error('Requested Event option does not exist: '.$name);
				return false;
			}

			return $opts[$name];
		}

		/**
		 * Static setter for single option
		 */
		public static function setOption($name,$value) {
			// Sanity Check
			$rhp = RockhouseEvents::instance();
			if( ! is_object($rhp) )
				throw new Exception('Whatever you did was extremely bad');

			if( $rhp->options == false )
				$rhp->options = $rhp::getOptions();

			$opts = $rhp->options;

			if( !isset($opts[$name] ) )
				throw new Exception('Attempted to save Event option that does not exist: '.$name);
			else {
				$rhp->options[$name] = $value;
				// Option checks, defaults, and singleton variable management done in getOptions()
				update_option( RockhouseEvents::OPTIONSMETANAME, $rhp->options);
			}
		}

		/**
		 * Static access for all options with merge for defaults
		 * Everything should use string keys!
		 */
		public static function getOptions() {
			// Sanity Check
			$rhp = RockhouseEvents::instance();
			if( ! is_object($rhp) )
				throw new Exception('Whatever you did was extremely bad');

			// Load events if not done already
			if( $rhp->options == false ) {
				$opts = get_option(RockhouseEvents::OPTIONSMETANAME,false);

				// Create our option if this is the first time, no autoload plz
				if( $opts === false ) {
					$opts = RockhouseEvents::$option_defaults;
					add_option(RockhouseEvents::OPTIONSMETANAME, $opts, null, 'no');
					$rhp->options = $opts;
				} else {
					// Merge options, 2 levels deep
					$merged_opts = array();
					foreach( RockhouseEvents::$option_defaults as $optk => $default ) {
						if( is_array($default) ) {
							if( !isset( $opts[$optk] ) or empty( $opts[$optk] ) ) {
								$merged_opts[$optk] = $default;
							} elseif( empty( $default ) ) {
								$merged_opts[$optk] = array_merge( $opts[$optk], $default );
							} else {
								$sub_opt = array();
								foreach( $default as $defk => $defv ) {
									$sub_opt[$defk] = isset( $opts[$optk][$defk] ) ? $opts[$optk][$defk] : $defv;
								}
								$merged_opts[$optk] = $sub_opt;
							}
						} else {
							$merged_opts[$optk] = isset( $opts[$optk] ) ? $opts[$optk] : $default;
						}
					}
					$rhp->options = $merged_opts;
				}
			}
			return $rhp->options;
		}

		/**
		 * Run an unmanned sync against the Etix API
		 *
		 * @filter: rhp_tribe_updates
		 */
		public static function cronEtixSync() {

			$venue = RockhouseEvents::getOption('etixApiOrgVenueIds');
			if( !empty( $venue ) ) {
				try {

					// Add some time to chew through results
					set_time_limit( 300 );

					require_once 'lib/rhp-sync-manager.class.php';

					$log = RockhouseLogger::instance();
					$log::add('WP_CRON Etix API Sync initiated','cron');

					$tribe = RockhouseSyncManager::fetchTribeCollection();
					$etix = RockhouseSyncManager::fetchEtixCollection();

					if( !empty($etix) ) {
						$tribe->syncWith( $etix );
					}

				} catch( Exception $e ) {

					if( class_exists('RockhouseLogger') ) {
						$log = RockhouseLogger::instance();
						$log::add('Disaster in WP_CRON: ' . $e->getMessage(),'critical');
					}

				}
			}
		}

		/**
		* Handle updates from our repo
		*
		* @TODO: When this plugin is not active on the root site of a
		* WP Multisite install, it won't fire updates to get upgraded
		* in the Network Admin.
		*
		*/
		public static function selfUpdates() {

			if( defined( 'WP_INSTALLING' ) )
				return false;

			// Precationary check for 0.1.3 upgrade to add our cron without running install function
			if ( ! wp_next_scheduled( 'rhp_tribe_sync' ) ) {
				wp_schedule_event( time(), 'rhp_ten_minutes', 'rhp_tribe_sync' );
			}

			// Run the check for updates
			$options = array(
				'timeout'    => ( ( defined( 'DOING_CRON' ) && DOING_CRON ) ? 30 : 10)
			);

			$rhp_repo = wp_remote_get('https://s3.amazonaws.com/rockhouse/wp/plugins/rhp-tribe-events/version.txt', $options);

			if( is_wp_error($rhp_repo) or !isset($rhp_repo['body']) ) {
				$body = var_export($rhp_repo,true);
				$body .= "\n\n--------------------\n\n";
				$body .= var_export($_SERVER,true);
				@wp_mail('admin@rockhousepartners.com','RHP Tribe Events AddOn - Update Check Failure',$body);
			} else {
				// Check our version
				$ver = trim($rhp_repo['body']);
				if( version_compare(self::VERSION,$ver,'<') ) {

					$update_plugins = get_site_transient('update_plugins');

					if ( ! isset( $update_plugins->response ) || ! is_array( $update_plugins->response ) )
						$update_plugins->response = array();

					$update_plugins->response['rhp-tribe-events/rhp-tribe-events.php'] = (object) array(
						'slug' 			=>	'rhp-tribe-events',
						'plugin'		=>	'rhp-tribe-events/rhp-tribe-events.php',
						'new_version' 	=>	$ver,
						'url' 			=>	'https://bitbucket.org/rhprocks/rhp-tribe-events',
						'package' 		=>	'https://s3.amazonaws.com/rockhouse/wp/plugins/rhp-tribe-events/latest.zip'
					);

					set_site_transient('update_plugins', $update_plugins);

				}
			}
		}

		/**
		 * Installation function
		 */
		public static function install() {

			// Extend an Editor role with ability to manage Tribe venues / organizers
			$box_office_caps =
				array(
					//Events
					'edit_tribe_event' => true,
					'read_tribe_event' => true,
					'delete_tribe_event' => true,
					'delete_tribe_events' => true,
					'edit_tribe_events' => true,
					'edit_others_tribe_events' => true,
					'delete_others_tribe_events' => true,
					'publish_tribe_events' => true,
					'edit_published_tribe_events' => true,
					'delete_published_tribe_events' => true,
					'delete_private_tribe_events' => true,
					'edit_private_tribe_events' => true,
					'read_private_tribe_events' => true,

					//Venues
					'edit_tribe_venue' => true,
					'read_tribe_venue' => true,
					'delete_tribe_venue' => true,
					'delete_tribe_venues' => true,
					'edit_tribe_venues' => true,
					'edit_others_tribe_venues' => true,
					'delete_others_tribe_venues' => true,
					'publish_tribe_venues' => true,
					'edit_published_tribe_venues' => true,
					'delete_published_tribe_venues' => true,
					'delete_private_tribe_venues' => true,
					'edit_private_tribe_venues' => true,
					'read_private_tribe_venues' => true,

					//Organizers
					'edit_tribe_organizer' => true,
					'read_tribe_organizer' => true,
					'delete_tribe_organizer' => true,
					'delete_tribe_organizers' => true,
					'edit_tribe_organizers' => true,
					'edit_others_tribe_organizers' => true,
					'delete_others_tribe_organizers' => true,
					'publish_tribe_organizers' => true,
					'edit_published_tribe_organizers' => true,
					'delete_published_tribe_organizers' => true,
					'delete_private_tribe_organizers' => true,
					'edit_private_tribe_organizers' => true,
					'read_private_tribe_organizers' => true,

					// Widgets
					'edit_theme_options' => true,

					//MeteorSlides
					'meteorslides_manage_options' => true
				);

			if( !is_multisite() ) {
				$box_office_caps['unfiltered_html'] = true;
			}

			// Setup our custom Box Office Role, based on Editor
			$editor = get_role('editor');
			$caps = array_merge( $editor->capabilities, $box_office_caps);
			$result = add_role( 'box_office', __( 'Box Office' ), $caps );

			// Prevent Editors from modifying Venues / Organizers
			$editor->remove_cap('edit_tribe_venues');
			$editor->remove_cap('edit_tribe_organizers');

			// Reset WP rewrite rules...just to be sure
			global $wp_rewrite;
			$wp_rewrite->flush_rules(false);

			// Schedule our updates
			if ( ! wp_next_scheduled( 'rhp_tribe_updates' ) ) {
				wp_schedule_event( time(), 'hourly', 'rhp_tribe_updates' );
			}

			if ( ! wp_next_scheduled( 'rhp_tribe_sync' ) ) {
				wp_schedule_event( time(), 'rhp_ten_minutes', 'rhp_tribe_sync' );
			}

		}


		/**
		 * Uninstall cleanup
		 */
		public static function uninstall() {

			// Get rid of Box Office
			remove_role( 'box_office' );

			// Restore Editors to Venues / Organizers
			$editor = get_role('editor');
			$editor->add_cap('edit_tribe_venues');
			$editor->add_cap('edit_tribe_organizers');

			// Unschedule our updates
			$next_update = wp_next_scheduled( 'rhp_tribe_updates' );
			if ( $next_update ) {
				wp_unschedule_event( $next_update, 'hourly', 'rhp_tribe_updates');
			}

		}

	}

	// Fire it up, boss!
	RockhouseEvents::instance();
	register_activation_hook( __FILE__, array( 'RockhouseEvents','install' ) );
	register_deactivation_hook( __FILE__, array( 'RockhouseEvents','uninstall' ) );
}
