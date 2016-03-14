<?php

namespace Etix\Api;
require_once 'Exception.php';

use Curl\Curl as Curl;

class Connection {

	/**
	 * @const API Endpoint
	 */
	const API_ENDPOINT = '/v1/public/activities';

	/**
	 * @var \Curl\Curl $handle Curl (php-curl-class) Handle
	 */
	protected $handle;

	/**
	 * @var array $credentials Credentials for API
	 */
	public $apiKey;

	/**
	 * @var string $apiTransport Transport to use
	 */
	public $apiTransport = 'https://';

	/**
	 * @var string $domain API Domain
	 */
	public $apiDomain = 'api.etix.com';

	/**
	 * @var int $apiTimeout Timeout for API requests in seconds (default: 60)
	 */
	public $apiTimeout = 60;

	/**
	 * @var array $organizations One or more Organization IDs to query
	 */
	protected $organizations = array();

	/**
	 * @var array $venues One or more Venue IDs to query
	 */
	protected $venues = array();

	/**
	 * Constructor
	 *
	 * @param array $credentials Credentials to authenticate with
	 * @return void
	 */
	public function __construct() {

		if( ! is_object( $this->handle ) ) {
			$this->handle = new Curl();
		}

	}

	/**
	 * Destructor
	 */
	public function __destruct() {

		if( is_object( $this->handle ) ) {
			$this->handle->close();
		}

	}

	/**
	 * Add an organization or organizations by ID to this request object
	 *
	 * @param array|string|int $venue_id An array or string/int of a organization IDs to add
	 * @param bool $clear Clear the existing organizations (default: false)
	 *
	 */
	public function addOrganization( $orgs, $clear = false ) {
		if( $clear ) {
			$this->organizations = array();
		}

		if( is_int($orgs) || is_numeric($orgs) ) {
			$this->organizations[] = $orgs;
		} elseif( is_array($orgs) ) {
			foreach($orgs as $org) {
				if( is_numeric($org) ) {
					$this->organizations[] = $orgs;
				} else {
					throw new InputException('Invalid Org ID given: ' . $org );
				}
			}
		} else {
			throw new InputException('Invalid Org ID given: ' . $orgs );
		}
		return $this;
	}


	/**
	 * Add a venue or venues by ID to this request object
	 *
	 * @param array|string|int $venue_id An array or string/int of a venue IDs to add
	 * @param bool $clear Clear the existing venues (default: false)
	 *
	 */
	public function addVenue( $venues, $clear = false ) {
		if( $clear ) {
			$this->venues = array();
		}

		if( is_int($venues) || is_numeric($venues) ) {
			$this->venues[] = $venues;
		} elseif( is_array($venues) ) {
			foreach($venues as $venue) {
				if( is_numeric($venue) ) {
					$this->venues[] = $venues;
				} else {
					throw new InputException('Invalid Venue ID given: ' . $venue );
				}
			}
		} else {
			throw new InputException('Invalid Venue ID given: ' . $venues );
		}
		return $this;
	}

	/**
	 * Run the request and return Events
	 *
	 * @return Etix\Api\Result $events Result Set of Events
	 */
	public function fetch() {

		// Prepare cURL timeouts
		$this->setCurlOpt(CURLOPT_CONNECTTIMEOUT,0);
		$this->setCurlOpt(CURLOPT_TIMEOUT,$this->apiTimeout);

		// Setup Header Auth
		if( empty( $this->apiKey ) ) {
			throw new AuthenticationException('API Key empty or not specified');
		} else {
			$this->handle->setHeader('apiKey',$this->apiKey);
		}

		// Prepare request
		$payload = array();

		if( empty($this->organizations) && empty($this->venues) ) {
			throw new InputException('Neither Organization or Venue IDs specified.  One of either or both is required.');
		} else {
			if( !empty($this->organizations) ) {
				$payload['organizationId'] = implode(',',$this->organizations);
			}
			if( !empty($this->venues) ) {
				$payload['venueIds'] = implode(',',$this->venues);
			}
		}

		// Send request
		try {
			$this->handle->get( $this->apiTransport . $this->apiDomain . self::API_ENDPOINT, $payload);
		} catch( Exception $e ) {
			throw new \Exception( 'Unhandled cURL Exception: ' . $e->getMessage() );
		}

		// Inspect the results
		if( $this->handle->error ) {

			switch( (int)$this->handle->error_code ) {
				case 401:
				case 408:
					throw new AuthenticationException( $this->handle->error_message );
					break;

				case 403:
					throw new AuthorizationException( $this->handle->error_message );
					break;

				case 404:
					throw new InputException( $this->handle->error_message );
					break;

				case 405:
					throw new RequestException( $this->handle->error_message );
					break;

				case 429:
				case 500:
				case 503:
					throw new ServerException( $this->handle->error_message );
					break;

				default:
					throw new \Exception( 'Unhandled Response Error Exception: ' . $this->handle->error_code . ' - ' . $this->handle->error_message );
			}

		} else {
			if( is_object( $this->handle->response ) ) {

				$ret = new Result();
				$ret->parseV1ActivitiesResponse( $this->handle->response );
				return $ret;

			} else {
				throw new \Exception( 'Unexpected response from API' );
			}
		}
	}

	/**
	 * Change php-curl-class wrapper options
	 *
	 * @return void
	 */
	public function setCurlOpt($option,$value) {
		$this->handle->setOpt($option,$value);
		return $this;
	}
}
