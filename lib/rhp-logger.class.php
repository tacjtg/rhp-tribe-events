<?php
// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if ( !class_exists( 'RockhouseLogger' ) ) {

	/**
	 * The Rockhouse Logger Class for WordPress
	 *
	 * @since 1.1
	 * @author dliszka
	 * @package Rhino
	 */
	class RockhouseLogger {

		const MAX_LENGTH = 500;

		protected static $instance;

		/**
		 * Static Singleton Factory Method
		 * @return RockhouseLogger
		 */
		public static function instance() {
			if (!isset(self::$instance)) {
				$className = __CLASS__;
				self::$instance = new $className;
			}
			return self::$instance;
		}


		/**
		 * Add log entry
		 */
		public static function add($entry,$tag = 'general') {
			$log = get_option('rhplive_log',false);

			if( empty($log) )
				$log = array();

			if( !isset($log[$tag]) )
				$log[$tag] = array();

			if( count($log[$tag]) > self::MAX_LENGTH )
				$log = array_slice($log[$tag],0,self::MAX_LENGTH);

			$stamp = current_time('mysql');
			if( isset( $log[$tag][$stamp] ) ) {
				$log[$tag][$stamp] .= "\n" . $entry;
			} else {
				$log[$tag][$stamp] = $entry;
			}
			update_option('rhplive_log',$log);
		}

		/**
		 * Return array of type / limit log entries
		 */
		public static function fetch($tag = 'all',$limit = 50) {
			$log = get_option('rhplive_log',false);

			if( empty($log) )
				return array('No log entries');

			if( empty($limit) )
				$limit = self::MAX_LENGTH;

			global $current_user;
			$user = is_object($current_user) ? $current_user->user_login : 'system';

			$report = array();
			if( $tag == 'all' ) {
				// This is stupid and inefficient
				foreach($log as $the_tag => $the_entries) {
					foreach($the_entries as $time => $line) {
						if( isset($report[$time]) ) {
							$report[$time] = "[{$the_tag}] ({$user}) {$line}\n\t" . $report[$time];
						} else {
							$report[$time] = "[{$the_tag}] ({$user}) {$line}";
						}
					}
				}
			} elseif( empty($log[$tag]) ) {
				return array('No log entries');
			} else {
				$report = $log[$tag];
			}

			krsort($report);
			return array_slice($report,0,$limit,TRUE);
		}

		/**
		 * Return array of tags
		 */
		public static function tags() {
			$log = get_option('rhplive_log',false);
			return is_array($log) ? array_keys($log) : array();
		}

		/**
		 * Knock out a specific log entry
		 */
		public static function clear($stamp,$tag) {
			$log = get_option('rhplive_log',false);

			if( isset($log[$tag]) and isset($log[$tag][$stamp]) ) {
				unset($log[$tag][$stamp]);
			}

			update_option('rhplive_log',$log);
		}

	}
}
