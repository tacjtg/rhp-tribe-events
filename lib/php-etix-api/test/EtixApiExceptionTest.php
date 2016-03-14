<?php
/**
 * Test for the common Exceptions in the Etix API Wrapper
 *
 *
 * TODO:
 * class AuthorizationException extends Exception {}
 * class RequestException extends Exception {}
 * class ServerException extends Exception {}
 */

require_once 'EtixTestHelper.php';
use EtixTest\Connection;

use Etix\Api;

class EtixApiExceptionTest extends PHPUnit_Framework_TestCase {

	protected $etix;

	public function setUp() {
		$this->etix = new Etix\Api\Connection();

		EtixTest\Configuration::setup( $this->etix );
	}

	public function tearDown() {
		unset( $this->etix );
	}

	/**
	 * @expectedException \Etix\Api\InputException
	 */
	public function testInvalidOrgStringException() {
		$this->etix->addOrganization('string');
	}

	/**
	 * @expectedException \Etix\Api\InputException
	 */
	public function testInvalidOrgArrayException() {
		$this->etix->addOrganization( array('string') );
	}

	/**
	 * @expectedException \Etix\Api\InputException
	 */
	public function testInvalidVenueStringException() {
		$this->etix->addVenue('string');
	}

	/**
	 * @expectedException \Etix\Api\InputException
	 */
	public function testInvalidVenueArrayException() {
		$this->etix->addVenue( array('string') );
	}

	/**
	 * @expectedException \Etix\Api\AuthenticationException
	 */
	public function testNoCredentialException() {
		$this->etix->apiKey = null;
		$this->etix->fetch();
	}

	/**
	 * @expectedException \Etix\Api\InputException
	 */
	public function testNoVenueOrOrgException() {
		$this->etix->fetch();
	}
}
