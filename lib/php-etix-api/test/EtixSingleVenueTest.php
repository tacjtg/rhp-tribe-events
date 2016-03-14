<?php

require_once 'EtixTestHelper.php';
use EtixTest\Configuration;

use Etix\Api;

class EtixSingleVenueTest extends PHPUnit_Framework_TestCase {

	protected $org = '-11111';
	protected $venue = '99999';
	protected $etix;

	public function setUp() {
		$this->etix = new Etix\Api\Connection();
		EtixTest\Configuration::setup( $this->etix );
	}

	public function tearDown() {
		unset( $this->etix );
	}

	public function testSingleVenueResponse() {
		$this->etix->addOrganization( $this->org );
		$events = $this->etix->fetch();
		$summary = $events->getSummary();

		$this->assertEquals( 1, $summary->total_venues );

		$this->assertEquals( 3, $summary->total_activities );

		$this->assertEquals( 3, $summary->total_performances );

		$this->assertEquals( $this->venue, $summary->info_venues[0]->id );

		$this->assertEquals( 'Frank Turner & The Sleeping Souls', $events[0]->name );
        $this->assertEquals( '2015-06-29T04:00:00Z', $events[0]->startTime );
		$this->assertEquals( '2015-06-30T04:00:00Z', $events[0]->endTime );
        $this->assertEquals( '2015-04-30T04:00:00Z', $events[0]->publicAnnounceTime );
        $this->assertEquals( '2015-05-10T04:00:00Z', $events[0]->onSaleTime );
        $this->assertEquals( '2015-06-28T04:00:00Z', $events[0]->offSaleTime );
        $this->assertEquals( '2015-06-29T03:00:00Z', $events[0]->doorsOpenTime );
        $this->assertEquals( 10, $events[0]->minPrice );
        $this->assertEquals( 15, $events[0]->maxPrice );
        $this->assertEquals( 'USD', $events[0]->currency );
        $this->assertEquals( 'onSale', $events[0]->status );
        $this->assertEquals( 333, $events[0]->eventSeriesId );
        $this->assertEquals( 1576149585956570, $events[0]->facebookEventId );
	}

}
