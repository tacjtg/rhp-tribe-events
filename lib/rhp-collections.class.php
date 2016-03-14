<?php

/**
 * Base class to create a standard set of interactions between
 * sets of events (basically an array) for CRUD operations.
 */
abstract class EventCollection implements ArrayAccess, Iterator, Countable {

	/**
	 * Constants for the status of an event
	 */
	const STATUS_ONSALE = 1;
	const STATUS_OFFSALE = 2;
	const STATUS_SOLDOUT = 3;

	/**
	 * Placeholder for Iterator
	 * @var int $iterator_position
	 */
	protected $iterator_position;

	/**
	 * Primary storage for our Key => Value attributes of a collection item
	 *
	 * @var array $data
	 */
	protected $data = array();

	/**
	 * Initial copy of filled data, used to determine if our
	 * data set has been modified
	 *
	 * @var array $original_data
	 */
	protected $original_data = array();

	/**
	 * Internal flag for pending (syncd but unwritten) changes
	 *
	 * @var bool $modified
	 */

	protected $modified = false;

	/**
	 * The text of the key that should be considered the absolute
	 * unique key in our K=>V data collection.
	 *
	 * It is very important that you set this.
	 *
	 * @var string $event_key_id
	 */
	protected $event_key_id;

	/**
	 * They keys of data that the subclass should manage.  Generally reflects the
	 * available points to the type of collection.
	 *
	 * title and last_update_utc is required, but there should be others
	 *
	 * @var array $event_keys
	 */
	protected $event_keys =
		array(
			'title',
			'image_url',
			'description',
			'announce_date_utc',
			'start_date_utc',
			'end_date_utc',
			'on_sale_date_utc',
			'off_sale_date_utc',
			'purchase_url',
			'cost',
			'fb_url',
			'series_id',
			'series_name',
			'status',
			'last_update_utc'
		);

	/**
	 * Only tabulate changes if it is one of these event_keys
	 *
	 * Subclasses should override this for Write activities to determine
	 * what is important for presistance calculation.
	 *
	 * @var array $watched_event_keys
	 */
	protected $watched_event_keys = array();

	/**
	 * An internal instance of the UTC DateTimeZone
	 *
	 * @var DateTimeZone
	 */
	protected $timezone_utc;

	/**
	 * The internal logger
	 *
	 * @var varies $logger
	 */
	protected $logger;

	/**
	 * String holder for last action logged
	 * @var string $last_log
	 */
	public $last_log = '';

	/**
	 * Attributes for subclasses to override that
	 * will be included when serialized
	 *
	 * @var array $always_sleepable
	 */
	public static $sleepable = array();

	/**
	 * Variables to save when serializing
	 * @var array $always_sleepable
	 */
	protected $always_sleepable = array(
									'data',
									'original_data',
									'modified',
									'event_keys',
									'watched_event_keys',
									'event_key_id'
								);

	/**
	 * Get the party started
	 */
	public function __construct( $logger = false ) {

		// Our internal timezone for UTC
		$this->timezone_utc = new DateTimeZone( 'UTC' );

		// Set our logger
		if( !empty($logger) and is_object($logger) )
			$this->logger = $logger;

		// Wiring for data I/O
		$this->connect();

		// If not specificed match on all key changes
		if( empty($this->watched_event_keys) )
			$this->watched_event_keys = $this->event_keys;

		// Silly way to enforce a member check
		if( empty($this->event_key_id) )
			throw new Exception('You must set an '.get_class($this).'::event_key_id');

		// This is sloppy, but we need to set at least some standard in this abstract
		if( ! in_array( 'last_update_utc', $this->event_keys ) )
			$this->event_keys[]  = 'last_update_utc';

		if( ! in_array( 'title', $this->event_keys ) )
			$this->event_keys[]  = 'title';

		if( ! in_array( 'title', $this->watched_event_keys ) )
			$this->watched_event_keys[]  = 'title';
	}

	/**
	 * Accessor for our event_key_id
	 *
	 * @return string Event Key ID for this Collection
	 */
	public function eventKey() {
		return $this->event_key_id;
	}

	/**
	 * Abstraction for our configured logger (to a homegrown interface)
	 */
	public function log( $msg, $tag = 'general' ) {

		if( !empty($this->logger) and is_object($this->logger) and method_exists($this->logger,'add') ) {
			$this->logger->add( get_class( $this ) . ': ' . $msg, $tag );
			$this->last_log = $msg;
		}

	}

	/**
	 * Checks if this collection has modifications
	 *
	 * @return bool
	 */
	public function hasChanges() {
		return (bool)$this->modified;
	}

	/**
	 * For consistent introspection here or in subclasses
	 *
	 * @return $string Class name
	 */
	protected function collectionType() {
		return get_class($this);
	}

	/**
	 * Superfluous method to access a single member but
	 * to provide pretty calling consistency
	 *
	 * @return string $event_key_id
	 */
	protected function collectionKey() {
		return $this->event_key_id;
	}

	/**
	 * This function should locally handle populating the $this->data
	 * array making sure that the following keys are not empty at
	 * a minimum:
	 *
	 * 		1. $this->event_key_id
	 * 		2. last_update_utc (timestamp)
	 *
	 * 	Remember that empty values in $this->watched_event_keys will
	 * 	count as changed if the receiver has content and will be wiped
	 * 	out.  Make sure they're set if they are truely to be watched.
	 *
	 * 	I know this is long, but you are also responsible for persisting
	 * 	a backup of your newly hydrated data set before you're done:
	 *
	 *	eg.  $this->original_data = $this->data;
	 */
	abstract protected function fill();


	/**
	 * Here is the moneymaker.
	 *   1. Always assume this is called by a secondary source with the GM passed;
	 *       Passed data ALWAYS clobbers caller data
	 *
	 * @TODO: Does not handle flagging local items for Deletion
	 * 	Need to figure out of to make NOT PRESENT in $collection mean ignore or delete semantically
	 *
	 * @param EventCollection $collection Traverse both collections and reconcile left-to-right (this to given)
	 */
	public function reconcile( EventCollection $collection ) {

		$return = array('create'=>array(),'update'=>array(),'unchanged'=>array());

		$lookup_key = $collection->collectionKey();

		// Dictionary for their keys against ours, Somehow we got to O(n)
		$crossref_keys = array();
		foreach($this->data as $idx => $our_event) {
			if( array_key_exists( $lookup_key, $our_event) ) {
				$crossref_keys[$our_event[$lookup_key]] = $idx;
			}
		}

		// Loop through Their Events to reconcile with Our Events
		foreach($collection as $that_event_key => $that_event) {
			$updated = $new = false;

			// If our timestamp is newer, don't do anything!
			if( isset($that_event['last_update_utc']) and isset($this->data[$that_event_key]['last_update_utc']) and ($that_event['last_update_utc'] <= $this->data[$that_event_key]['last_update_utc']) ) {
				continue;
			}

			$log = RockhouseLogger::instance();
			// We have a local data entry that matches on the cross-matched event key
			if( isset( $crossref_keys[$that_event_key] ) ) {

				// Hack function to allow a pass on certain WEKs (mods on LEFT shouldn't trigger write of RIGHT)
				if( method_exists( $this, 'preReconcileEvent' ) ) {
					$that_event = $this->preReconcileEvent( $crossref_keys[$that_event_key] , $that_event );
				}

				// Merge changes from THAT to THIS
				$updated = $this->reconcileEvent($this->data[$crossref_keys[$that_event_key]],$that_event);

				// We need to add the foreign Collection key so the simple array_diff works
				$updated[$lookup_key] = $that_event_key;
				$is_updated = array_diff($updated,$this->data[$updated[$this->event_key_id]]);

				if( empty($is_updated) ) {
					$return['unchanged'][] = $that_event;
				} else {
					$return['update'][$updated[$this->event_key_id]] = $updated;
				}

			} else {
				// No key match, so I suppose we have to create an entry
				$new = $that_event;
				$new[$this->event_key_id] = NULL;
				$new[$lookup_key] = $that_event_key;

				$return['create'][] = $new;
			}
			//@TODO: what about deletes?
		}

		//$this->log( 'Reconcile from ' . get_class($collection) . ' result: '. count($return['create']) . ' new, ' . count($return['update']) . ' updates, '. count($return['unchanged']) . ' unchanged', 'reconcile' );

		return $return;
	}

	/**
	 * Check our Collections watched_event_keys for the incoming (left) event
	 * against our collection's (right) event.
	 *
	 * When changes are found copy all self::watched_event_keys from LEFT to RIGHT
	 *
	 * @param $left array Our Collection's Event
	 * @param $right array The other Collection's Event.
	 */
	protected function reconcileEvent($left, $right) {

		foreach($this->watched_event_keys as $wek) {
			// Check for a change in watched_event_keys
			if( isset($right[$wek]) and ( $left[$wek] != $right[$wek] or !isset($left[$wek]) ) ) {
				//echo "$wek - |{$left[$wek]}| |{$right[$wek]}|\n";
				$left[$wek] = $right[$wek];
			}
		}

		return $left;

	}

	/**
	 * Sync our collection of events with another EventCollection.  Automatically
	 * calls self::write() to persist all changes.
	 *
	 *   Don't forget to set your data[][self::event_key_id] used in reconcileWrite()
	 */
	public function syncWith( EventCollection $collection ) {

		$reconcile_set = $this->reconcile( $collection );

		foreach( $reconcile_set['create'] as $event ) {
			$this->writeEvent( $event, $collection );
		}

		foreach( $reconcile_set['update'] as $event ) {
			$this->writeEvent( $event, $collection );
		}

		$this->log( 'Sync from ' . get_class($collection) . ' complete: '. count($reconcile_set['create']) . ' created, '. count($reconcile_set['update']) . ' updated, '. count($reconcile_set['unchanged']) . ' unchanged', 'sync' );

		// There used to be a post-write call that would allow the remote Collection
		// to update itself after a Sync was called.  This was in order to allow
		// recording of the $collection data with a new this::event_key_id on
		// entries that had been part of $reconcile_set['created']
		// $collection->crossLinkIds($reconcile_status);
		//
		// Now the writeEvent() call should use the passed Collection to persist
		// the $collection->event_key_id to their local data store.  But that also
		// requires it to be loaded as a first-class element when fill() is called.
		// Outlook is murky on this implementation.

	}

	/**
	 * Add a watched event key
	 *
	 * @param string $key Event Key to add for watching
	 */
	public function addWatchedKey( $key ) {

		if( ! in_array( $key, $this->watched_event_keys ) ) {
			$this->watched_event_keys[] = $key;
		}

	}

	/**
	 * Remove a watched event key
	 *
	 * @param string $key Event Key to remove from watching
	 */
	public function rmWatchedKey( $key ) {

		if( in_array( $key, $this->watched_event_keys ) ) {
			$idx = array_search( $key, $this->watched_event_keys );
			unset( $this->watched_event_keys[$idx] );
		}

	}

	/**
	 * Set all watched event keys in bulk
	 *
	 * @param array $keys Event Keys to Watch
	 */
	public function setWatchedKeys( $keys ) {

		if( is_array( $keys ) and !empty( $keys ) ) {
			$this->watched_event_keys = array_values( $keys );
		}

	}

	/**
	 * The function for a subclass to deal with persisting data changes
	 * through to whatever source it is based on.
	 *
	 * We pass the originating collection in case the destination needs to
	 * manage origin data or flagging.
	 *
	 * @param array $event Array of event_key normalized data
	 * @param EventCollection $source The source EventCollection this update is coming from
	 */
	abstract public function writeEvent( $event, EventCollection $source );

	/**
	 * Method for subclass to setup data I/O for this collection
	 */
	abstract public function connect();

	/**
	 * Method for subclass to cleanup data I/O systems when
	 * serializing or destruction
	 */
	abstract public function disconnect();

	/**
	 * Safe serializing of Objects for staging changes
	 */
	public function __sleep() {
		$this->disconnect();
		return array_unique(
					array_merge(
						self::$always_sleepable,
						self::$sleepable
					)
			);
	}

	/**
	 * Manage internal connections when unserializing an object
	 */
	public function __wakeup() {
		$this->connect();
	}

	/**  ArrayAccess Interface  **/
    public function offsetExists($key){
        return array_key_exists($key, $this->data);
    }

    public function offsetGet($key){
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function offsetSet($key, $val) {
		$this->modified = true;
		if( empty($key) )
			$this->data[] = $val;
		else
			$this->data[$key] = $val;
    }

    public function offsetUnset($key) {
		$this->modified = true;
        unset($this->data[$key]);
    }

	/**  Countable Interface  **/
    public function count() {
        return count($this->data);
    }

	/** Iterator Interface **/
	function rewind() {
		reset($this->data);
	}

	function next() {
		next($this->data);
	}

	function current() {
		return current($this->data);
	}

	function key() {
		return key($this->data);
	}

	function valid() {
		return key($this->data) !== null;
	}
}
