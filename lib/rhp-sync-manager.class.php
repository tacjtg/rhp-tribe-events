<?php
/**
 * A general management class for Event Synchronization.
 * Uses the RHP Collections and Etix API implementations
 * with WordPress friendly wrappings.
 *
 * Also makes use of the RHP Tribe Events plugin interfaces
 * and storage within the WP Admin.
 *
 * @since 1.1.0
 * @author dliszka
 * @package Rhino
 */

// don't load directly
if ( ! defined( 'ABSPATH' ) ) die( '-1' );

if ( ! class_exists( 'RockhouseSyncManager' ) ) {

	// Add our dependencies
	require_once RockhouseEvents::instance()->pluginPath . '/lib/rhp-collection-tribe-events.class.php';
	require_once RockhouseEvents::instance()->pluginPath . '/lib/rhp-collection-etix.class.php';
	require_once RockhouseEvents::instance()->pluginPath . '/lib/rhp-logger.class.php';

	class RockhouseSyncManager {
		/**
		 * Use the Etix Collection class to retrieve a set of Activitites
		 * via the API.
		 *
		 * @return WordPressCollection
		 */
		public static function fetchTribeCollection() {
			$log = RockhouseLogger::instance();
			$auto_publish = (bool) RockhouseEvents::getOption('etixAutoPublish');
			$wpe = new TribeCollection( $log, $auto_publish );

			// Add our watched keys to prevent updates to fields we don't want to watch
			$watch_opts = RockhouseEvents::getOption('etixApiSyncFields');
			$watch_keys = array();
			foreach( $watch_opts as $key => $enable ) {
				if( $enable ) {
					$watch_keys[] = $key;
				}
			}
			$wpe->setWatchedKeys( $watch_keys );

			return $wpe;
		}

		/**
		 * Run a test connection against the Etix API to see
		 * if the key is valid
		 *
		 * @param string $api_key An Etix API Key
		 * @return bool True if successful
		 */
		public static function testEtixConnection( $api_key ) {
			try {

				// Bring in php-etix-api lib
				require_once 'php-etix-api/vendor/autoload.php';

				$etix = new \Etix\Api\Connection();

				// Set credentials
				$etix->apiKey = $api_key;

				// Set mock org for testing
				$etix->addOrganization( '-11111', true );

				$result = $etix->fetch();
				return 'Valid';

			} catch( Exception $e ) {

				return $e->getMessage();

			}
		}

		/**
		 * Use the Etix Collection class to retrieve a set of Activitites
		 * via the API.
		 *
		 * @return EtixCollection
		 */
		public static function fetchEtixCollection() {

			$keystatus = RockhouseEvents::getOption('etixApiKeyStatus');
			if( $keystatus !== 'Invalid' ) {

				try {

					// Try connecting, figure out what to do with API key
					$key = RockhouseEvents::getOption('etixApiKey');
					$venue = RockhouseEvents::getOption('etixApiOrgVenueIds');
					$log = RockhouseLogger::instance();
					// We handle grouping of Performances to a Category in WP instead of a unified listing
					$group_series = false; // RockhouseEvents::getOption('etixGroupSeries');

					$timer = microtime(true);
					$etix = new EtixCollection( $key, $venue, $group_series, $log );
					$etix_time = intval( (microtime(true) - $timer) * 1000 );

					// With no exceptions, we should be ok to use this key going forward
					if( $keystatus !== 'Valid' ) {

						$log::add('New API Key added and tested','api-auth');
						RockhouseEvents::setOption('etixApiKeyStatus','Valid');

					}

					// Filter any Performances we've been told not to sync
					$excludes = RockhouseEvents::getOption('etixApiExclusions');
					$ex_ids = $ex_titles = array();
					foreach( $excludes as $exclude ) {
						if( substr($exclude,0,3) == 'id:' ) {
							$ex_ids[] = substr($exclude,3);
						} else {
							$ex_titles[] = $exclude;
						}
					}

					$filter = array();
					// Build list of keys to wipe (without disturbing object inline as a foreach)
					foreach( $etix as $pid => $perf) {
						if( in_array( $pid, $ex_ids ) ) {
							$filter[] = $pid;
						}
						foreach( $ex_titles as $ex_str ) {
							if( stripos( html_entity_decode($perf['title']), $ex_str) !== false ) {
								$filter[] = $pid;
							}
						}
					}

					// Clean 'em out
					$rm_tot = 0;
					if( !empty( $filter ) ) {
						$pre_tot = count($etix);
						foreach( $filter as $pid ) {
							unset($etix[$pid]);
						}
						$rm_tot = $pre_tot - count($etix);
					}

					// Write log summary
					$s = $etix->last_result_summary;
					$v = array();
					foreach($s->info_venues as $i) {
						$v[] = $i->name . ' [' . $i->id . ', ' . $i->count . ' items]';
					}
					$vinfo = implode(',',$v);
					$rm_info = empty( $rm_tot ) ? '' : " ({$rm_tot} exclusions)";
					$log::add( 'Fetched ' . $s->total_activities . $rm_info . ' items in ' . $etix_time . 'ms (' . $s->total_performances . ' performances / ' . $s->total_packages . ' packages) for ' . $vinfo, 'api' );


					// Update Org/Venue Info
					$voptinfo = implode("\n",$v) . "\n  Time: " . current_time('mysql');
					RockhouseEvents::setOption('etixApiOrgVenueInfo', $voptinfo);

					// And pass it back
					return $etix;

				} catch(\Etix\Api\AuthenticationException $e) {

					$log::add('API Key failed Authentication, suspending this key','api-auth');
					RockhouseEvents::setOption('etixApiKeyStatus','Invalid');

				} catch(Exception $e) {
					$log::add( get_class($e) . ' ' . $e->getMessage(),'api-error');

					if( defined('DOING_CRON') ) {
						$body = $e->getMessage();
						$body .= "\n\n--------------------\n\n";
						$body .= $e->getTraceAsString();
						$body .= "\n\n--------------------\n\n";
						$body .= var_export($_SERVER,true);
						@wp_mail('dale@rockhousepartners.com','RHP Tribe Events AddOn - Etix API Exception',$body);
					}
				}

			}

		}

		/**
		 * Temporarily hold a collection in a Transient. Stored for 30 minutes
		 * on a per-user basis, and you can only have 1 at a time.
		 *
		 * This works because the EventCollection class has tooling to allow it
		 * to be serialized and 'frozen' for thawing out later.
		 *
		 * @param EventCollection $c
		 */
		public static function holdCollection( $c ) {
			global $current_user;
			if( is_admin() ) {
				set_transient( 'rhp-sync-collection-' . $current_user->user_login, serialize($c), HOUR_IN_SECONDS );
			}
		}

		/**
		 * Dump the current users's stored Collection
		 */
		public static function clearCollection() {
			delete_transient( 'rhp-sync-collection-' . $current_user->user_login );
		}

		/**
		 * Get current users's stored Collection, if it exists
		 *
		 * @return null|EventCollection
		 */
		public static function fetchCollection() {
			if( is_admin() ) {
				$c = get_transient( 'rhp-sync-collection-' . $current_user->user_login );
				if( $c !== false ) {
					return unserialize( $c );
				}
			}
		}

	}
}


