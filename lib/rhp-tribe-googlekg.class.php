<?php

// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if ( !class_exists( 'RockhouseKnowledgeGraph' ) ) {

/**
 * Tribe Events Calendar - Schema & Google Knowledge Graph Overrides
 *
 * @since  	1.1.1
 * @package rhp
 * @author 	Rockhouse
 */

	class RockhouseKnowledgeGraph {

		/**
		 * @var $instance Static singleton for this class
		 */
		protected static $instance;

		/**
		 * Constructor to get this moving
		 */
		public function __construct( ) {

			// Set up our filters real late after other plugins have been loaded
			add_action( 'plugins_loaded', array( $this, 'pluginsLoaded'), 100 );

		}

		/**
		 * Adjust the Event Filter Bar using Tribe hooks
		 *
		 * @action plugins_loaded
		 */
		public function pluginsLoaded() {

			if( class_exists( 'Tribe__Events__Main' ) and defined( 'RHPTRIBE_ACTIVE' )  ) {
				add_filter( 'tribe_google_event_data', array($this,'clearTribeMeta'), 10, 1 );
				add_action( 'wp_head', array($this,'addSchemaMarkup'), 90 );
			}

		}


		/**
		 * Null out the return of data to the Tribe Google_Data_Markup class
		 * so their JSON+LD doesn't get output
		 *
		 * @param $events array
		 * @filter tribe_google_event_data
		 */

		public function clearTribeMeta( $events ) {
			return array();
		}

		/**
		 * Add OFFICIAL Venue markup for Events, supercedes inline schema.org microdata
		 * See: https://developers.google.com/webmasters/structured-data/events/venues
		 *
		 * Filter: wp_head
		 *
		 * TODO: Consider playing nicely with Tribe's JSON
		 */

		public function addSchemaMarkup() {
			if( is_single() and Tribe__Events__Main::POSTTYPE == get_post_type() ) {
				// Determine Location
				$loc = array();

				// Try a venue on the event or the default venue
				$venue_id = tribe_has_venue() ? tribe_get_venue_id() : tribe_get_option('eventsDefaultVenueID',false);
				if( $venue_id and tribe_is_venue($venue_id) ) {

					$venue_loc = tribe_get_address($venue_id) . ', ' . tribe_get_city($venue_id) . ', ' . tribe_get_region($venue_id);
					if( $venue_loc != ', , ' ) {
						$loc['addr'] = $venue_loc;
						$loc['name'] = tribe_get_venue($venue_id);
					}

				} else {

					// Fall back to TEC Defaults
					$tec_default_addr = tribe_get_option('eventsDefaultAddress',false);
					$tec_default_city = tribe_get_option('eventsDefaultCity',false);
					$tec_default_state = tribe_get_option('eventsDefaultState',false);

					if( $tec_default_addr and $tec_default_city and $tec_default_state ) {
						$loc['addr'] = $tec_default_addr . ', ' . $tec_default_city . ', ' . $tec_default_state;
						$loc['name'] = get_bloginfo('name');
					}

				}

				// None of this works for Google without a valid location
				if( !empty( $loc ) ) {
					global $post;
					$url = get_permalink();
					$title = get_the_title();

					// Getting a DateTimeZone compatible abbreviation can be tricky
					// WordPress permits the use of Timezone strings that are not
					// valid PHP DateTimeZone identifiers
					$rhino_tzs = get_option('rhp_tribe_wp_timezone', array('wp'=>'','dtzid'=>'') );

					// WP will have an empty timezone_string when a manual offset is specified
					$wp_tzs = get_option('timezone_string');
					$wp_gos = get_option('gmt_offset');
					$wp_tzid = empty( $wp_tzs ) ? $wp_gos : $wp_tzs;

					// Check if anything has changed
					if( $rhino_tzs['wp'] !== $wp_tzid ) {
						$rhino_tzs['wp'] = $wp_tzid;
						$tzids = timezone_identifiers_list();
						// Valid tz string
						if( strpos($wp_tzid,'/') and in_array( $wp_tzs, $tzids ) ) {
							$rhino_tzs['dtzid'] = $wp_tzs;
						} else {
							// Go hunting for a string matching the manual offset options
							$wpos = (int) $wp_gos * 3600;
							foreach( timezone_abbreviations_list() as $tzabbr ) {
								foreach( $tzabbr as $tzcityabbr ) {
									if( $tzcityabbr['offset'] == $wpos and !empty($tzcityabbr['timezone_id']) ) {
										$rhino_tzs['dtzid'] = $tzcityabbr['timezone_id'];
										break(2);
									}
								}
							}

							// Total failure
							if( empty($rhino_tzs['dtzid']) ) {
								$rhino_tzs['dtzid'] = 'UTC';
							}
						}
						update_option('rhp_tribe_wp_timezone',$rhino_tzs);
					}
					$start = '';
					try {
						$tz = new DateTimeZone( $rhino_tzs['dtzid'] );

						$start_ts = date( 'Y-m-d H:i:s', get_field('rhp_event_start_date',null,false) );
						$start_dt = new DateTime( $start_ts, $tz );
						$start = $start_dt->format( DateTime::ISO8601 );
					} catch( Exception $e ) {
						// Not sure what to do here, aside from avoiding an E_FATAL
						$start = date( 'c', get_field('rhp_event_start_date',null,false) );
					}

					// Official Types generally used:
					// Event, TheaterEvent, ComedyEvent, MusicEvent, Festival

					echo <<<HTML

<!-- Event Markup for Official Venue Sites -->
<script type="application/ld+json">
{
  "@context": "http://schema.org",
  "@type": "Event",
  "name": "{$title}",
  "startDate": "{$start}",
  "url": "{$url}"
HTML;
		// Add Image
		if( has_post_thumbnail() ) {
			$thumb_id = get_post_thumbnail_id();
			$thumb_url = wp_get_attachment_image_src($thumb_id,'full',true);
			echo <<<HTML
,
  "image": "{$thumb_url[0]}"
HTML;
					}

					// Add Location info
					echo <<<HTML
,
  "location": {
    "@type": "Place",
    "name": "{$loc['name']}",
    "address": "{$loc['addr']}"
  }
HTML;

					$ctaurl = get_field('rhp_event_cta_url');
					if( !empty( $ctaurl ) ) {
						// Add Offer URL (as secondary)
						// This is saved to TEC fields by the RHP AddOn as a clean float from rhp_event_cost
						$price = tribe_get_cost() + 0;
						echo <<<HTML
,
  "offers": {
    "@type": "Offer",
	"url": "{$ctaurl}",
	"price": "{$price}"
  }
HTML;
						}
					echo <<<HTML

}
</script>


HTML;
				} else {
					echo "\n\n<!-- Event Schema disabled: No location configured -->\n\n";
				}
			}

		}


		/**
		 * Static Singleton Factory Method
		 * @return RockhouseKnowledgeGraph
		 */
		public static function instance() {
			if (!isset(self::$instance)) {
				$className = __CLASS__;
				self::$instance = new $className;
			}
			return self::$instance;
		}

	}

	// Fire it up, boss!
	RockhouseKnowledgeGraph::instance();
}
