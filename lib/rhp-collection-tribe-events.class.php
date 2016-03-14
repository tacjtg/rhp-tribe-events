<?php
/**
 * An EventCollection implementation for the Tribe Events Calendar WP Custom Post Types
 *
 * @author dliszka
 * @package Rockhouse
 * @since 1.1
 */

// Make sure we have our parent class
require_once 'rhp-collections.class.php';

class TribeCollection extends EventCollection {

	/**
	 * The query string for our WP_Query
	 */
	private $query_params = '';

	/**
	 * The text of the key that should be considered the absolute
	 * unique key in our K=>V data collection.
	 *
	 * @var string $event_key_id
	 */
	protected $event_key_id = 'post_id';

	/**
	 * Add our sleepable class attributes for
	 * serializing for storage
	 *
	 * @var array $sleepable
	 */
	public static $sleepable = array(
		'query_params'
	);

	/**
	 * Only trigger changes to WP Posts if these keys are modified
	 */
	protected $watched_event_keys =
		array(
			'title',
			'start_date_utc',
			'end_date_utc',
			'on_sale_date_utc',
			'off_sale_date_utc',
			'purchase_url',
			'cost',
			'fb_url',
			'status',
			'series_id',
			'series_name'
		);

	/**
	 * Our WP Query object
	 *
	 * @var WP_Query
	 */
	public $wp_query;

	/**
	 * This WP Instances's Time Zone
	 *
	 * @var DateTimeZone
	 */
	private $timezone_wp;

	/**
	 * Our custom ACF fields with Keys (that is required to update fields)
	 *
	 * @var array
	 */
	private $acf_map = array();

	/**
	 * Flag to auto publish new events when Sync'd()
	 *
	 * @var bool $auto_publush
	 */
	public $auto_publish = true;

	/**
	 * Get things set up
	 *
	 * @param object $logger A valid logger object to use
	 */
	public function __construct( $logger = false, $auto_pub = true) {

		if(! class_exists( 'Tribe__Events__Main' ) ) {
			throw new Exception('Modern Tribe: The Events Calendar is not installed or active.');
		}

		// Set our autopub setting
		$this->auto_publish = $auto_pub;

		// We should only ever need upcoming events
		// this is taken from the-events-calendar/lib/tribe-event-query.class.php
		$this->query_params = array(
				'post_type' => Tribe__Events__Main::POSTTYPE,
				'eventDisplay' => 'custom',
				'posts_per_page' => -1,
				'hide_upcoming' => false,
				'start_date' => tribe_event_beginning_of_day( date('Y-m-d') ),
				'end_date' => '',
				'order' => 'ASC'
			);

		// When querying, be sure to include drafts if we are NOT auto publishing
		if( ! $this->auto_publish ) {
			$this->query_params['post_status'] = array( 'pending', 'draft', 'future' );
		}

		// Prep our TimeZone object
		$this->setupTimeZone();

		// Create our internal mapping of our ACF Fields keys
		$this->setupACFMap();

		// Get our parent things ready
		parent::__construct( $logger );

		// Again, should we do this here?
		$this->fill();

	}

	/**
	 * Run our WP_Query and populate our data array
	 */
	public function fill() {
		$this->wp_query = new WP_Query( $this->query_params );

		$now = time();

		// Marshal our Tribe Event data to the EventCollection container
		foreach($this->wp_query->posts as $event) {
			$wp_event = array();

			$wp_event[$this->event_key_id] = $event->ID;
			$wp_event['title'] = $event->post_title;
			$wp_event['last_update_utc'] = strtotime($event->post_modified_gmt);

			// Get our custom ACF data
			$acf = get_fields($event->ID);
			$wp_event['start_date_utc'] = $this->convertDateToTimestampUTC( $acf['rhp_event_start_date'] );
			// Note: end_date will be empty when not set (Jan 1, 1970)
			$wp_event['end_date_utc'] = $this->convertDateToTimestampUTC( $acf['rhp_event_end_date'] );
			$wp_event['on_sale_date_utc'] = $this->convertDateToTimestampUTC( $acf['rhp_event_on_sale_date'] );
			$wp_event['off_sale_date_utc'] = $this->convertDateToTimestampUTC( $acf['rhp_event_off_sale_date'] );
			$wp_event['purchase_url'] = $acf['rhp_event_cta_url'];
			$wp_event['cost'] = $acf['rhp_event_cost'];
			$wp_event['fb_url'] = $acf['rhp_event_facebook_event_url'];

			// Status
			if( $acf['rhp_event_sold_out'] ) {
				$wp_event['status'] = self::STATUS_SOLDOUT;
			} elseif( (!empty($wp_event['end_date_utc']) and $wp_event['end_date_utc'] < $now ) or ($wp_event['on_sale_date_utc'] > $now ) or ($wp_event['off_sale_date_utc'] < $now) ) {
				$wp_event['status'] = self::STATUS_OFFSALE;
			} else {
				$wp_event['status'] = self::STATUS_ONSALE;
			}

			// Add series from meta storage (may be redundant with full meta below0
			$wp_event['series_id'] = get_post_meta($event->ID,'series_id',true);
			$wp_event['series_name'] = get_post_meta($event->ID,'series_name',true);

			// Images
			$wp_event['image_url'] = null;
			if( has_post_thumbnail($event->ID) ) {
				$img = wp_get_attachment_image_src( get_post_thumbnail_id($event->ID) );
				$wp_event['image_url'] =  $img[0];
			} else {
				// Fall back to alt image url
				$alt_img = get_post_meta($event->ID,'alt_event_img',true);
				if( !empty( $alt_img ) ) {
					$wp_event['image_url'] = $alt_img;
				}
			}

			// Metadata as a first-level
			$meta = get_post_custom($event->ID);

			// This may be a giant mistake, but is here to allow writeEvent to
			// write a Collection key without a hard coded value for Etix.
			foreach($meta as $key => $val) {
				if( count($val) == 1 ) {
					$metadata = maybe_unserialize( $val[0] );
					// Our collections only deal with key-value pairs well, skip exotic types like arrays
					if( !is_array( $metadata ) ) {
						$wp_event[$key] = $metadata;
					}
				} else {
					// Not sure what to do with complex meta variables
				}
			}

			// And done
			$this->data[$event->ID] = $wp_event;

			// TODO: Venues
		}
	}

	/**
	 * Convert a given date in WP Local and convert to UTC
	 */
	public function convertDateToTimestampUTC( $date ) {

		// Convert to a timestamp if we weren't given one
		if( empty( $date ) ) {
			return 0;
		}
		if( is_string( $date ) and is_numeric( $date ) ) {
			$date = (int) $date;
		}
		if( !is_int( $date ) ) {
			$date = strtotime( $date );
		}

		$date_formatted = date( DateTime::ISO8601, $date );
		$start_dt = new DateTime( $date_formatted, $this->timezone_wp);
		return $start_dt->getTimestamp();
	}


	/**
	 * Create a DateTimeZone object for our WP Instance
	 */
	public function setupTimeZone() {

		// WP will have an empty timezone_string when a manual offset is specified
		$wp_tzs = get_option('timezone_string');
		$wp_gos = get_option('gmt_offset');
		$wp_tzid = empty( $wp_tzs ) ? $wp_gos : $wp_tzs;

		// Confirm we have a valide TZID
		$tzids = timezone_identifiers_list();
		if( strpos($wp_tzid,'/') === false or !in_array( $wp_tzs, $tzids ) ) {
			// Go hunting for a string matching the manual offset options
			$wp_tzid = '';
			$wpos = (int) $wp_gos * 3600;
			foreach( timezone_abbreviations_list() as $tzabbr ) {
				foreach( $tzabbr as $tzcityabbr ) {
					if( $tzcityabbr['offset'] == $wpos and !empty($tzcityabbr['timezone_id']) ) {
						$wp_tzid = $tzcityabbr['timezone_id'];
						break(2);
					}
				}
			}

			// Total failure
			if( empty($wp_tzid) ) {
				throw new Exception('Unable to determine the Timezone for this WordPress Instance');
			}
		}

		$this->timezone_wp = new DateTimeZone( $wp_tzid );

	}

	/**
	 * Store a local copy of the ACF Field map for our Tribe
	 * Event fields
	 */
	public function setupAcfMap() {
		// See v4.4.2: https://plugins.trac.wordpress.org/browser/advanced-custom-fields/trunk/core/api.php
		if( function_exists('api_acf_field_group_get_fields') ) {

			$acf = api_acf_field_group_get_fields( array(), 'acf_event-settings' ); // hard coded from resources/acf-default-event-fields.php

			foreach($acf as $field) {
				if( !empty( $field['name'] ) ) {
					$this->acf_map[$field['name']] = $field['key'];
					$this->acf_map[$field['name']] = $field;
				}
			}
		}
	}

	/**
	 * Convert a given UTC Timestamp to be offset by our
	 * local WP Timezone and format
	 *
	 * @param int $ts Timestamp
	 * @param string $format date() compatible format, defaults to ISO8601
	 * @return int Timestamp
	 */
	private function convertTimestampToDate( $ts, $format = DateTime::ISO8601 ) {
		// Date should now be a timestamp already in UTC
		$dt = DateTime::createFromFormat('U',$ts);
		$dt->setTimezone($this->timezone_wp);
		return $dt->format($format);
	}

	/**
	 * Hack to allow certain fields to pass through
	 */
	protected function preReconcileEvent( $event_key, $their_event ) {

		// Apply our plugin filters to the URL
		$rhp_events = RockhouseEvents::instance();
		$their_event['purchase_url'] = $rhp_events->filterCtaUrl( $their_event['purchase_url'], null, null );

		return $their_event;
	}

	/**
	 * Save our local data set to the WP DB
	 *
	 * @param array $event Array of event_key normalized data
	 * @param EventCollection $source The source EventCollection this update is coming from
	 */
	public function writeEvent( $event, EventCollection $source ) {

		// Build the array of WP compatible items
		$wp_event = array();
		$wp_event['post_type'] = Tribe__Events__Main::POSTTYPE;

		// The pattern here is to write all fields when creating a new Post
		// but to only update a field if it one of our Watched Keys.
		//
		// Purchase URL is a special case, and should only be written on create
		// but is not selectable in the sync options.
		$is_new_post = empty($event[$this->event_key_id]) ? true : false;

		// Add in Tribe Events fields (immutable field)
		$wp_event['EventStartDate']		= $this->convertTimestampToDate( $event['start_date_utc'], Tribe__Events__Date_Utils::DBDATEFORMAT );
		$wp_event['EventStartHour']		= $this->convertTimestampToDate( $event['start_date_utc'], Tribe__Events__Date_Utils::HOURFORMAT );
		$wp_event['EventStartMinute']	= $this->convertTimestampToDate( $event['start_date_utc'], Tribe__Events__Date_Utils::MINUTEFORMAT );
		$wp_event['EventStartMeridian']	= $this->convertTimestampToDate( $event['start_date_utc'], Tribe__Events__Date_Utils::MERIDIANFORMAT );

		if( $is_new_post or in_array('end_date_utc', $this->watched_event_keys) ) {
			// There is a complex relationship with End Date and Tribe's End of day cutoff calculations
			// that can compound with Performance Dates that are on the same day.  Eg. same day performance
			// could have a Start of 5pm and End of 6pm, but with cutoff it will show as +X hours and hence
			// list as a Past Event.  To preserve shows always use Tribe EOD for <24h (single day events)

			if( $event['end_date_utc'] and ($event['start_date_utc'] - $event['end_date_utc']) > 86400 ) {
				$wp_event['EventEndDate'] 		= $this->convertTimestampToDate( $event['end_date_utc'], Tribe__Events__Date_Utils::DBDATEFORMAT );
				$wp_event['EventEndHour']		= $this->convertTimestampToDate( $event['end_date_utc'], Tribe__Events__Date_Utils::HOURFORMAT );
				$wp_event['EventEndMinute']		= $this->convertTimestampToDate( $event['end_date_utc'], Tribe__Events__Date_Utils::MINUTEFORMAT );
				$wp_event['EventEndMeridian']	= $this->convertTimestampToDate( $event['end_date_utc'], Tribe__Events__Date_Utils::MERIDIANFORMAT );
			} else {
				$wp_event['EventEndDate'] 		= tribe_event_end_of_day( $wp_event['EventStartDate'], Tribe__Events__Date_Utils::DBDATEFORMAT );
				$wp_event['EventEndHour']		= tribe_event_end_of_day( $wp_event['EventStartDate'], Tribe__Events__Date_Utils::HOURFORMAT );
				$wp_event['EventEndMinute']		= tribe_event_end_of_day( $wp_event['EventStartDate'], Tribe__Events__Date_Utils::MINUTEFORMAT );
				$wp_event['EventEndMeridian']	= tribe_event_end_of_day( $wp_event['EventStartDate'], Tribe__Events__Date_Utils::MERIDIANFORMAT );

				// For sanity with ACF later let's set this to what we computed
				$event['end_date_utc'] = tribe_event_end_of_day( $wp_event['EventStartDate'], 'U' );
			}
		}

		if( $is_new_post or in_array('cost', $this->watched_event_keys) ) {
			$wp_event['EventCost'] 			= $this->decipherCost( $event['cost'], 'min' );
			$wp_event['EventCurrencySymbol'] = '$';
		}

		// Now process the WP required fields of:
		//   post_name, post_title, post_content, and post_excerpt
		//   See: https://codex.wordpress.org/Function_Reference/wp_insert_post

		if( $is_new_post or in_array('title', $this->watched_event_keys) ) {
			$wp_event['post_title'] = $event['title'];
			$wp_event['post_name'] = sanitize_title_with_dashes($event['title'],null,'save');
		}

		if( $is_new_post or in_array('description', $this->watched_event_keys) ) {
			if( empty($event['description']) ) {
				$wp_event['post_content'] = '';
				$wp_event['post_excerpt'] = '';
			} else {
				$wp_event['post_content'] = $event['description'];
				// insert/update requires this, create excerpt manually
				$excerpt_length = apply_filters( 'excerpt_length', 55 );
				$wp_event['post_excerpt'] = wp_trim_words($event['description'],$excerpt_length,'');
			}
		}

		// Map post_date to announce_date (Announce date is not used for publish logic because if available via the API it has been announced)
		if( isset( $event['announce_date_utc'] ) ) {
			$wp_event['post_date_gmt'] = date('Y-m-d H:i:s', $event['announce_date_utc']);
			$wp_event['post_date'] = $this->convertTimestampToDate( $event['announce_date_utc'], 'Y-m-d H:i:s' );
		}

		// Manipulate post status
		if( $is_new_post and !$this->auto_publish ) {
			$wp_event['post_status'] = 'draft';
		} else {
			$wp_event['post_status'] = 'publish';
		}

		// See if we need to get or create post
		//
		// Tribe's "api" does a good job of calling WP internals to create/update a post
		// just like wp_insert_post / wp_update_post, all our WP specific fields should be
		// updated just like our Tribe fields and post transitions handled
		// (but the custom ACF fields are not...see below.)

		// Remove hooked functions that cause issues (WooFramework in Canvas)
		if( class_exists('WF') ) {
			$wf = WF::instance();
			remove_action( 'post_updated', array($wf->meta_boxes, 'meta_box_save') ); // not working....
		}

		// Avoid double set of ACF
		$rhp = RockhouseEvents::instance();
		remove_action('save_post', array( $rhp, 'convertAcfToTecFields' ), 90, 3 );

		if( $is_new_post ) {

			// Add fields for a new event
			$wp_event['EventURL'] 				= $event['purchase_url'];
			$wp_event['EventAllDay'] 			= false;
			$wp_event['EventHideFromUpcoming'] 	= false;
			$wp_event['EventShowInCalendar'] 	= true;

			// Pro TEC Fields
			$wp_event['EventShowMapLink']	= false;
			$wp_event['EventShowMap']		= false;

			// WP Fields
			$wp_event['ping_status'] = 'closed';
			$wp_event['comment_status'] = 'closed';
			// Post Author, configurable? Should always be 2=rhprhino on WP Engine
			$wp_event['post_author']	= 2;

			// Create the event
			$event[$this->event_key_id] = tribe_create_event( $wp_event );

			// All our CTAs are populated from defaults to a post ACF field for overriding
			$cta_labels = array(
					'rhp_event_cta_label_free_show',
					'rhp_event_cta_label_sold_out',
					'rhp_event_cta_label_coming_soon',
					'rhp_event_cta_label_on_sale',
					'rhp_event_cta_label_off_sale'
				);
			// Reuse the RockhouseEvents::defaultCtaLabels to populate this
			$rhp = RockhouseEvents::instance();
			foreach( $cta_labels as $label_field ) {
				$default_text = $rhp->defaultCtaLabels( '', $event[$this->event_key_id], array('name'=>$label_field) );
				$this->acfUpdateField($label_field, $default_text, $event[$this->event_key_id] );
			}

		} else {

			// Update the event
			tribe_update_event( $event[$this->event_key_id], $wp_event );

		}

		$post_id = $event[$this->event_key_id];

		// Store our Source Collection key reference in a Meta field
		update_post_meta( $post_id, $source->eventKey(), $event[$source->eventKey()] );

		if( $is_new_post or in_array('image_url', $this->watched_event_keys) ) {
			// Put our remote image URL in a postmeta, which we'll slip in with a filter on post_thumbnail()
			update_post_meta( $post_id, 'alt_event_img', $event['image_url'] );
		}

		// Now lets also update our RHP + Tribe ACF Fields (relying on the
		// RockhouseEvents::convertAcfToTecFields doesn't work so well here
		// since all the _POST variables are not in place (filter removed above)
		// NOTE: ACF Seems to store dates in UTC, so we should write that

		// Prime all fields if this is a new insertion
		if( $is_new_post ) {
			RockhouseEvents::instance()->primeAcfGroup( $post_id, 'acf_event-settings' );
		}

		// All the date fields below are stored in ACF, which also reads the timestamps
		// from their data field as being offset to the local timezone.
		// Did it this way because using get_option(gmt_offset) fails with DST
		$event['start_date_local'] 		= strtotime( $this->convertTimestampToDate( $event['start_date_utc'], 'Y-m-d H:i:s' ) );
		$event['end_date_local'] 		= strtotime( $this->convertTimestampToDate( $event['end_date_utc'], 'Y-m-d H:i:s' ) );
		$event['on_sale_date_local'] 	= strtotime( $this->convertTimestampToDate( $event['on_sale_date_utc'], 'Y-m-d H:i:s' ) );
		$event['off_sale_date_local'] 	= strtotime( $this->convertTimestampToDate( $event['off_sale_date_utc'], 'Y-m-d H:i:s' ) );

		// Immutable Items
		$this->acfUpdateField('rhp_event_start_date', $event['start_date_local'],$post_id);
		$this->acfUpdateField('rhp_event_cta_url', $event['purchase_url'], $post_id);

		$is_soldout = $event['status'] == self::STATUS_SOLDOUT ? true : false;
		$this->acfUpdateField( 'rhp_event_sold_out', $is_soldout, $post_id);

		// Conditional Items
		if( $is_new_post or in_array('end_date_utc', $this->watched_event_keys) ) {
			$this->acfUpdateField('rhp_event_end_date', $event['end_date_local'], $post_id);
		}

		if( $is_new_post or in_array('on_sale_date_utc', $this->watched_event_keys) ) {
			$this->acfUpdateField('rhp_event_on_sale_date', $event['on_sale_date_local'], $post_id);
		}

		if( $is_new_post or in_array('off_sale_date_utc', $this->watched_event_keys) ) {
			$this->acfUpdateField('rhp_event_off_sale_date', $event['off_sale_date_local'], $post_id);
		}

		if( $is_new_post or in_array('fb_url', $this->watched_event_keys) ) {
			$this->acfUpdateField('rhp_event_facebook_event_url', $event['fb_url'], $post_id);
		}

		if( $is_new_post or in_array('cost', $this->watched_event_keys) ) {
			$this->acfUpdateField('rhp_event_cost', $event['cost'], $post_id);
			if( RockhouseEvents::getOption('etixSetZeroAsFree') and empty( $event['cost'] ) ) {
				$this->acfUpdateField('rhp_event_free_show', true, $post_id);
			}
		}

		// Deal with Series categorization (will happen when Group Series is set)
		if( $is_new_post or in_array('series_name', $this->watched_event_keys) ) {

			if( isset( $event['series_id'] ) and !empty( $event['series_name'] ) ) {
				// If 'series_name' is set to be watched we know the 'series' parent cat exists under Tribe__Events__Main::TAXONOMY
				$series_term = get_term_by( 'slug', 'series', Tribe__Events__Main::TAXONOMY );

				// First, check by series_name
				$this_series = term_exists( $event['series_name'], Tribe__Events__Main::TAXONOMY, $series_term->term_id);
				if( !is_array( $this_series ) ) {

					// It may have been renamed, lets see if we have it in our sync'd array too
					$etix_series = get_option( 'etix_api_series', array() );
					if( in_array( $event['series_id'], $etix_series ) ) {
						// Ugly way to dig this up
						$all_series = get_terms( Tribe__Events__Main::TAXONOMY, array( 'parent' => $series_term->term_id ) );
						foreach( $all_series as $cat_series ) {
							$etix_series_id = get_field( 'rhp_series_etix_event_id', Tribe__Events__Main::TAXONOMY.'_'.$cat_series->term_id );
							if( $etix_series_id == $event['series_id'] ) {
								$this_series = $cat_series;
							}
						}
					}

					// Create it!
					if( empty( $this_series ) ) {
						$this_series = wp_insert_term( $event['series_name'], Tribe__Events__Main::TAXONOMY, array('parent' => $series_term->term_id) );

						// Always force YOAST SEO to index this
						$yoast_tax = get_option( 'wpseo_taxonomy_meta', array() );
						if( !isset( $yoast_tax[Tribe__Events__Main::TAXONOMY] ) ) {
							$yoast_tax[Tribe__Events__Main::TAXONOMY] = array();
						}
						$yoast_tax[Tribe__Events__Main::TAXONOMY][(int)$this_series['term_id']] = array( 'wpseo_noindex' => 'index' );
						update_option( 'wpseo_taxonomy_meta', $yoast_tax );
					}
				}

				// Check for errors
				if( !is_array( $this_series ) or is_wp_error($this_series) ) {
					$this->log( 'Error creating or accessing the Event Series subcategory for "'.$event['series_name'].'"', 'tribe' );
				} else {
					// Append or update the series for this event
					$set_term = wp_set_object_terms( $post_id, (int)$this_series['term_id'], Tribe__Events__Main::TAXONOMY, true);
					if( is_wp_error( $set_term ) ) {
						$this->log( 'Error setting Event Series subcategory for "'.$event['series_name'].'" on #'.$post_id, 'tribe' );
					}
					// Update the Category ACF Fields as needed
					$acf_id = Tribe__Events__Main::TAXONOMY.'_'.$this_series['term_id'];
					$series_acf = get_fields( $acf_id );

					// If we just created this series we must prime ACF before it works right
					if( $series_acf == false ) {
						RockhouseEvents::instance()->primeAcfGroup( $acf_id, 'acf_event-series-category-page' );
						$series_acf = get_fields( $acf_id );
					}

					if( empty( $series_acf['rhp_series_etix_event_id'] ) ) {
						$this->acfUpdateOption( 'rhp_series_etix_event_id', $event['series_id'], $acf_id);
					}

					if( empty( $series_acf['rhp_series_cta_url'] ) ) {
						$etix_series_url = RockhouseEvents::instance()->filterCtaUrl( 'https://www.etix.com/ticket/e/' . $event['series_id'] . '/', null, null );
						$this->acfUpdateOption( 'rhp_series_cta_url', $etix_series_url, $acf_id);
					}

					// Use the Start Date to set the date range of this Series
					if( empty( $series_acf['rhp_series_start_date'] ) or strtotime( $series_acf['rhp_series_start_date'] ) > $event['start_date_local'] ) {
						$this->acfUpdateOption( 'rhp_series_start_date', $event['start_date_local'], $acf_id);
					}

					if( empty( $series_acf['rhp_series_end_date'] ) or strtotime( $series_acf['rhp_series_end_date'] ) < $event['start_date_local'] ) {
						$this->acfUpdateOption( 'rhp_series_end_date', $event['start_date_local'], $acf_id);
					}

					$min_cost = isset( $wp_event['EventCost'] ) ? $wp_event['EventCost'] : $this->decipherCost( $event['rhp_event_cost'], 'min' );
					if( empty( $series_acf['rhp_series_min_price'] ) or $min_cost < intval( $series_acf['rhp_series_min_price'] ) ) {
						$this->acfUpdateOption( 'rhp_series_min_price', (int)$min_cost, $acf_id);
					}

					$max_cost = isset( $wp_event['EventCost'] ) ? $wp_event['EventCost'] : $this->decipherCost( $event['rhp_event_cost'], 'max' );
					if( empty( $series_acf['rhp_series_max_price'] ) or $max_cost > intval( $series_acf['rhp_series_max_price'] ) ) {
						$this->acfUpdateOption( 'rhp_series_max_price', (int)$max_cost, $acf_id);
					}

					// Store these locally on the post for easy access
					update_post_meta( $post_id, 'series_id', $event['series_id'] );
					update_post_meta( $post_id, 'series_name', $event['series_name'] );

					// Keep a list of all series/subcat entries we've created for pruning
					$etix_series = get_option( 'etix_api_series', array() );
					$etix_series[] = $event['series_id'];
					update_option( 'etix_api_series', array_unique($etix_series), false );
				}
			}

		} else {

			// Just write these down individually, not sure why
			if( isset( $event['series_id'] ) and !empty( $event['series_name'] ) ) {
				update_post_meta( $post_id, 'series_id', $event['series_id'] );
				update_post_meta( $post_id, 'series_name', $event['series_name'] );
			}

		}
	}

	/**
	 * Taken from the ACF call for update_value in core/fields/_functions.php
	 *   See: https://github.com/elliotcondon/acf/blob/master/core/fields/_functions.php#L181
	 *
	 * Neither the ACF API call of update_field() or acf_field_functions::update_value
	 * seem to work.  So we just use plain update_metadata()
	 *
	 * @var string $field_name ACF Field Name
	 * @var mixed $value new value
	 * @var int $post_id WP Post ID
	 */
	public function acfUpdateField( $field_name, $value, $post_id ) {
		update_metadata('post', $post_id, $field_name, $value);
		wp_cache_replace('load_value/post_id=' . $post_id . '/name=' . $field_name, $value, 'acf' );
	}

	/**
	 * Just as the update_field doesn't work above for ACF data on Posts (stored in postmeta)
	 * the function doesn't work for the special cases such as taxonomies, users, and
	 * other types (stored in options).
	 *
	 * Again, we'll manually update the value and clear the group cache.  Sigh.
	 *
	 * @var string $field_name ACF Field Name
	 * @var mixed $value new value
	 * @var int $opt_id Option name used by ACF (ususally a token like term-name_term-id or user-name_user-id)
	 */
	public function acfUpdateOption( $field_name, $value, $opt_id ) {
		update_option( $opt_id . '_' . $field_name, $value );
		wp_cache_replace('load_value/post_id=' . $opt_id . '/name=' . $field_name, $value, 'acf' );
	}

	/**
	 * Costs can come across as a range in text format, used some parsing hacks to figure it out
	 *
	 * @param $cost string Cost as a string eg. "$3 DOS / $1 ADV"
	 * @param $compar string Type of compare to derive: min, max
	 * @return int value of cost
	 */
	public function decipherCost( $cost, $compare = 'max' ) {
		$vals = explode('#', str_replace( array('-','$','/',' '),'#',$cost) );
		$price = 0;
		foreach($vals as $val) {
			if( is_numeric($val) and $compare == 'max' and ($price == 0 or $price < (float)$val ) ) {
				$price = (float)$val;
			}
			if( is_numeric($val) and $compare == 'min' and ($price == 0 or $price > (float)$val ) ) {
				$price = (float)$val;
			}
		}

		return (float)$price;
	}

	/**
	 * Connector, nothing to do as WP is already hooked up
	 */
	public function connect() {}

	/**
	 * Destructor
	 */
	public function disconnect(){}

}
