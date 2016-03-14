<?php

namespace Etix\Api;
require_once 'Exception.php';

class Result implements \ArrayAccess, \Iterator, \Countable  {

	/**
	 * @var array $activities Local storage of results (performances and packages)
	 */
	protected $activities = array();

	/**
	 * @var array $response Original response object
	 */
	protected $response;

	/**
	 * @var array $summary A summary of this response object contents
	 */
	protected $summary;

	/**
	 * Parse the plain Object response (converted from JSON)
	 * taken from a \Curl\Curl (http://www.phpcurlclass.com/)
	 * response.
	 *
	 * We collapse the entire response to a flat list of activities
	 * regardless of the venue / org (which are nested within)
	 *
	 * @param \stdClass $resp Response from a \Etix\Api\Connection::fetch() call
	 * @return void
	 */
	public function parseV1ActivitiesResponse( \stdClass $resp ) {

		// Reset
		$this->activities = array();
		$this->response = $resp;
		$this->summary = (object) array(
			'total_activities' => 0,
			'total_performances' => 0,
			'total_packages' => 0,
			'total_venues' => 0,
			'total_orgs' => 0,
			'info_venues' => array(),
			'info_orgs' => array()
		);

		// Parse by Venues
		foreach( $this->response->venues as $venue ) {

			$this->summary->total_venues++;

			$summary = (object) array(
				'name' => $venue->name,
				'id' => $venue->id,
				'count' => 0
			);

			// Rebranch the Venue under the Activity
			$venue_activities = (array) $venue->activities;
			unset($venue->activities);

			foreach( $venue_activities as $activity ) {
				$activity->venue = $venue;
				$this->activities[] = $activity;

				// Add to summary
				$summary->count++;
				$this->summary->total_activities++;

				if( $activity->activityType == 'performance' ) {
					$this->summary->total_performances++;
				} elseif( $activity->activityType == 'package' ) {
					$this->summary->total_packages++;
				}
			}

			$this->summary->info_venues[] = $summary;
		}
	}

	/**
	 * Get a summary of contents
	 *
	 * @return array $summary
	 */
	public function getSummary() {
		return $this->summary;
	}

	/**  ArrayAccess Interface  **/
	public function offsetExists($key){
		return array_key_exists($key, $this->activities);
	}

	public function offsetGet($key){
		return isset($this->activities[$key]) ? $this->activities[$key] : null;
	}

	public function offsetSet($key, $val) {
		if( empty($key) ) {
			$this->activities[] = $val;
		} else {
			$this->activities[$key] = $val;
		}
	}

	public function offsetUnset($key) {
		unset($this->activities[$key]);
	}

	/**  Countable Interface  **/
	public function count() {
		return count($this->activities);
	}

	/** Iterator Interface **/
	// Thanks  http://www.php.net/manual/en/class.iterator.php#90830
	function rewind() {
		reset($this->activities);
	}

	function next() {
		next($this->activities);
	}

	function current() {
		return current($this->activities);
	}

	function key() {
		return key($this->activities);
	}

	function valid() {
		return key($this->activities) !== null;
	}
}


