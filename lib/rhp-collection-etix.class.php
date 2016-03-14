<?php
/**
 * An EventCollection implementation for the Etix Public Activities API
 *
 * @author dliszka
 * @package Rockhouse
 * @since 1.1
 */

// Bring in php-etix-api lib
require_once 'php-etix-api/vendor/autoload.php';
use \Etix\Api as Etix;

// Make sure we have our parent class
require_once 'rhp-collections.class.php';

class EtixCollection extends EventCollection {

	/**
	 * The text of the key that should be considered the absolute
	 * unique key in our K=>V data collection.
	 *
	 * @var string $event_key_id
	 */
	protected $event_key_id = 'etix_activity_id';

	/**
	 * Venues to filter from the API
	 *
	 * @var array $venue_ids
	 */
	protected $venue_ids = array();

	/**
	 * Add our sleepable class attributes for
	 * serializing for storage
	 *
	 * @var array $sleepable
	 */
	public static $sleepable = array(
		'venue_ids',
		'last_result_summary'
	);

	/**
	 * Summary array from \Etix\Api\Result
	 *
	 * @var array $last_result_summary
	 */
	public $last_result_summary = array();

	/**
	 * Etix API connector
	 *
	 * @var \Etix\Api $etix
	 */
	protected $etix;

	/**
	 * Etix API connector
	 *
	 * @var string $etix_api_key
	 */
	private $etix_api_key = '';

	/**
	 * Flag to group Event Series as 1 Event
	 *
	 * @var bool $group_series
	 */
	public $group_series = false;

	/**
	 * Save our key and send construction up the line
	 *
	 * @param string $api_key A string of the Etix API Key to use
	 * @param object $logger A valid logger object to use
	 */
	public function __construct( $api_key, $venues, $group_series = false,  $logger = false ) {

		// Save our key locally
		$this->etix_api_key = $api_key;

		// Move to accessor?  would require moving the fill() below
		$this->group_series = $group_series;

		// Process strings
		// @TODO: Deal with Orgs
		if( stripos( trim($venues), ',' ) ) {
			$this->venue_ids = explode( ',',  strtr($venues,' ','') );
		} else {
			$this->venue_ids[] = trim( $venues );
		}

		parent::__construct( $logger );

		// Not sure if this should be done in the parent...but...maybe later for lazy loads?
		$this->fill();
	}

	/**
	 * Read from the Etix API Connection to fill out our
	 * internal ::data array.
	 *
	 * @throws \Etix\Api\InputException
	 * @throws \Etix\Api\AuthenticationException
	 */
	protected function fill() {

		$events = $this->etix->fetch();

		$series = array();
		$series_name = array();

		// We don't have a last updated time so we'll assume
		// our events are always fresh
		$now = time();

		$this->data = array();

		foreach($events as $event) {
			// We only deal with Performances right now
			if( $event->activityType == 'performance' ) {
				// Setup the event as a Collection item
				$cevent = array();

				$cevent[$this->event_key_id] = $event->id;

				$cevent['title'] = $event->name;
				$cevent['image_url'] = $event->performanceImage;
				$cevent['description'] = $event->description;

				$cevent['announce_date_utc'] = $this->convertDateToTimestampUTC( $event->publicAnnounceTime );
				$cevent['start_date_utc'] = $this->convertDateToTimestampUTC( $event->startTime );
				$cevent['end_date_utc'] = $this->convertDateToTimestampUTC( $event->endTime );
				$cevent['on_sale_date_utc'] = $this->convertDateToTimestampUTC( $event->onSaleTime );
				$cevent['off_sale_date_utc'] = $this->convertDateToTimestampUTC( $event->offSaleTime );

				$cevent['purchase_url'] = $event->purchaseURL;

				// Price description
				if( empty( $event->minPrice ) and empty( $event->maxPrice ) ) {
					$cevent['cost'] = 0;
				} elseif( $event->minPrice == $event->maxPrice ) {
					$cevent['cost'] = '$' . $event->minPrice;
				} else {
					$cevent['cost'] = '$' . $event->minPrice . ' to $' . $event->maxPrice;
				}

				// Build FB URL from ID if given
				$cevent['fb_url'] = empty( $event->facebookEventId ) ? '' : 'https://www.facebook.com/events/' . $event->facebookEventId . '/';

				$cevent['last_update_utc'] = $now;

				$cevent['status'] = $this->convertStatus( $event->status );

				// TODO: Venues

				// Do we need to roll up Series?
				if( $this->group_series and !empty($event->eventSeriesId) ) {
					$series[ $event->eventSeriesId ][] = $cevent;
					$series_name[ $event->eventSeriesId ] = $event->eventSeriesName;
				} else {
					// Nope, all singles
					$cevent['series_id'] = $event->eventSeriesId;
					$cevent['series_name'] = $event->eventSeriesName;
					$this->data[$event->id] = $cevent;
				}
			}
		}

		// Roll up our Series if we need to
		if( $this->group_series and !empty($series) ) {

			// Determine our Series dates
			foreach($series_name as $series_id => $name) {

				// Create our singular Series Event
				$sevent[$this->event_key_id] = $series_id;

				// Hack to pass list of perfs, please undo
				$sevent['perf_list'] = '';

				$sevent['title'] = $name;
				// Steal these from the first item
				$sevent['image_url'] = $series[$series_id][0]['image_url'];
				$sevent['description'] = $series[$series_id][0]['description'];


				// Figure out our earliest announce, start, end, on, off sale dates
				$last_day = 0; // End Date isn't always sent
				$on_sale = false; // Assume we are offsale unless we find 1 performance that is

				foreach($series[$series_id] as $single) {

					if( !isset($sevent['announce_date_utc']) or $single['announce_date_utc'] < $sevent['announce_date_utc'] ) {
						$sevent['announce_date_utc'] = $single['announce_date_utc'];
					}

					if( !isset($sevent['start_date_utc']) or $single['start_date_utc'] < $sevent['start_date_utc'] ) {
						$sevent['start_date_utc'] = $single['start_date_utc'];
					}

					if( !isset($sevent['end_date_utc']) or $single['end_date_utc'] > $sevent['end_date_utc'] ) {
						$sevent['end_date_utc'] = $single['end_date_utc'];
					}

					if( !isset($sevent['on_sale_date_utc']) or $single['on_sale_date_utc'] < $sevent['on_sale_date_utc'] ) {
						$sevent['on_sale_date_utc'] = $single['on_sale_date_utc'];
					}

					if( !isset($sevent['off_sale_date_utc']) or $single['off_sale_date_utc'] > $sevent['off_sale_date_utc'] ) {
						$sevent['off_sale_date_utc'] = $single['off_sale_date_utc'];
					}

					if( $single['start_date_utc'] > $last_day ) {
						$last_day = $single['start_date_utc'];
					}

					if( $single['status'] == self::STATUS_ONSALE ) {
						$on_sale = true;
					}

					// Tuck this away in packed format
					$sevent['perf_list'] .= $single['start_date_utc'] . '*' . $single['purchase_url'] . '|';
				}

				if( empty($sevent['end_date_utc']) or $sevent['end_date_utc'] < $last_day ) {
					$sevent['end_date_utc'] = $last_day;
				}

				// Statically code this for now
				$sevent['purchase_url'] = 'https://www.etix.com/ticket/e/'.$series_id.'/';

				// Price description
				if( empty( $event->minPrice ) and empty( $event->maxPrice ) ) {
					$sevent['cost'] = 0;
				} elseif( $event->minPrice == $event->maxPrice ) {
					$sevent['cost'] = '$' . $event->minPrice;
				} else {
					$sevent['cost'] = '$' . $event->minPrice . ' to $' . $event->maxPrice;
				}

				// This doesn't exist as a concept at Etix
				$sevent['fb_url'] = '';

				$sevent['last_update_utc'] = $now;

				// Maybe these rollups always need to be onsale?
				$sevent['status'] = $on_sale ? self::STATUS_ONSALE : self::STATUS_OFFSALE;

				// And add as a single
				$this->data[$series_id] = $sevent;

			}

		}

		$this->last_result_summary = $events->getSummary();

	}

	/**
	 * Convert Etix event status to Collection Contant type
	 *
	 * @param string $status Etix API activity.status
	 * @return int STATUS
	 */
	private function convertStatus( $status ) {
		switch( $status ) {

			case 'onSale':
				return self::STATUS_ONSALE;

			case 'soldOut':
				return self::STATUS_SOLDOUT;

			case 'noInventoryCurrentlyAvailable':
			case 'notOnSale':
			default:
				return self::STATUS_OFFSALE;

		}
	}

	/**
	 * Convert our native data set time to UTC Timestamp
	 *
	 * @param string $date The Date
	 * @return int UTC Timestamp
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

		// Date should now be a timestamp
		$dt = new DateTime();
		$dt->setTimezone($this->timezone_utc); // Etix API ALWAYS sends dates in UTC
		$dt->setTimestamp($date);
		return $dt->getTimestamp();
	}

	/**
	 * The write-through method for Etix
	 *
	 * Since we are implementing a read-only API, there
	 * isn't a whole lot to do.
	 *
	 * @param array $event Array of event_key normalized data
	 * @param EventCollection $source The source EventCollection this update is coming from
	 */
	public function writeEvent( $event, EventCollection $source ) {}


	/**
	 * Standup connection to data sources, the lovely \Etix\Api\Connection
	 */
	public function connect(){

		if(! is_object($this->etix) ) {
			$this->etix = new Etix\Connection();

			// Set credentials
			$this->etix->apiKey = $this->etix_api_key;

			// Set Venues (only, for now)
			$this->etix->addVenue( implode(',',$this->venue_ids), true );
		}

	}

	/**
	 * Destructor
	 */
	public function disconnect(){

		if( is_object($this->etix) ) {
			// Call destructor
			unset( $this->etix );
		}

	}

}
