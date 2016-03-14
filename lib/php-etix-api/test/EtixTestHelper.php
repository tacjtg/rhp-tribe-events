<?php

namespace EtixTest;

use Etix\Api;

class Configuration {

	const API_KEY = '2F1B57CC2073820F0D74E43501841C8ABFBD9226BB9C9682DBF54110D4FCA1E9';

	public static function setup( \Etix\Api\Connection $api ) {

		// Set test credentials
		$api->apiKey = self::API_KEY;

	}
}
