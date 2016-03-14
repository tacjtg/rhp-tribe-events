<?php

// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if ( !class_exists( 'RockhouseTribeWPAdmin' ) ) {

	/**
	 * Modifier for default sorting behavior and listing of
	 * Tribe Events in WP Admin
	 *
	 * @since 1.1
	 * @author dliszka
	 * @package Rhino
	 */
	class RockhouseTribeWPAdmin {

		/**
		 * @var $instance Static singleton for this class
		 */
		protected static $instance;

		/**
		 * @var $filterType string
		 */
		public $filterType;

		/**
		 * @var $skipFilter book
		 */
		public $skipFilter = false;


		/**
		 * Constructor to get this moving
		 */
		protected function __construct( ) {

			// Set up our filters real late after other plugins have been loaded
			add_action( 'plugins_loaded', array( $this, 'pluginsLoaded'), 100 );

			if( isset( $_GET['rhp-tribe-filter'] ) ) {
				$this->filterType = $_GET['rhp-tribe-filter'];
			} else {
				$this->filterType = 'upcoming';
			}

		}

		/**
		 * Throw our own changes into the ring, but only if our RHPTRIBE_ACTIVE
		 * constant has been set.  No need to filter if the AddOn isn't running.
		 *
		 * @action plugins_loaded
		 */
		public function pluginsLoaded() {

			add_action( 'restrict_manage_posts', array($this,'eventsFilter') );

			if( class_exists( 'Tribe__Events__Main' ) and defined( 'RHPTRIBE_ACTIVE' )  ) {
				add_action( 'manage_edit-'.Tribe__Events__Main::POSTTYPE.'_columns', array($this,'removeColumns'), 100, 1 );
				add_action( 'manage_edit-'.Tribe__Events__Main::POSTTYPE.'_sortable_columns', array($this,'removeColumns'), 100, 1 );
				add_action( 'manage_'.Tribe__Events__Main::POSTTYPE.'_posts_columns', array($this,'removeColumns'), 100, 1 );

				add_filter( 'posts_orderby', array($this,'sortOrder'), 5, 2 );
				add_filter( 'posts_where', array($this,'sortWhere'), 500, 2 );


				// Skip filtering if needed
				if( is_admin() and $_SERVER['SCRIPT_NAME'] == '/wp-admin/edit.php' and isset( $_GET['post_type'] ) and $_GET['post_type'] == Tribe__Events__Main::POSTTYPE ) {

					// Bulk Requests
					if( isset( $_GET['bulk_edit'] ) and !empty( $_GET['bulk_edit'] ) ) {
						$this->skipFilter = true;
					}

					// Viewing trash
					if( isset( $_GET['post_status'] ) and $_GET['post_status'] == 'trash' ) {
						$this->skipFilter = true;
					}

				}

			}

		}

		/**
		 * Add our filter types dropdown
		 *
		 * @filter restrict_manage_posts
		 */
		public function eventsFilter() {
			global $typenow;

			if( !$this->skipFilter and class_exists('Tribe__Events__Main') and $typenow == Tribe__Events__Main::POSTTYPE ) {

				$name = 'rhp-tribe-filter';
				$selected = isset( $_GET[$name] ) ? $_GET[$name] : 'upcoming';
				$upcoming_selected = ($selected == 'upcoming') ? 'selected="selected"' : '';
				$past_selected = ($selected == 'past') ? 'selected="selected"' : '';
				$all_selected = ($selected == 'all') ? 'selected="selected"' : '';

	            echo <<<HTML
<select name="{$name}">
	<option {$upcoming_selected} value="upcoming">Upcoming Events</option>
	<option {$past_selected} value="past">Past Events</option>
	<option {$all_selected} value="all">All Events</option>
</select>
HTML;

			submit_button( 'Go', 'secondary', 'rhp-tribe-filter-submit', false );

			}
		}

		/**
		 * Clear out cluttered sortable columns
		 *
		 * @filter manage_edit-tribe_events_sortable_columns
		 */
		public function removeColumns( $cols ) {

			$removals = array(
					'comments',
					'ecp_cost',
					'events-cats',
					'recurring',
					'ecp_organizer_filter_key',
					'ecp_venue_filter_key'
				);

			foreach( $removals as $remove ) {
				if( isset($cols[$remove] ) ) {
					unset( $cols[$remove] );
				}
			}

			return $cols;

		}

		/**
		 * Set the orderby parameters on the query used by
		 * the Tribe__Events__Admin__List class before it
		 * receives this data
		 *
		 * @param $orderby string ORDER BY text
		 * @param $query WP_Query The main WP Query object
		 *
		 * @filter posts_orderby
		 */
		public function sortOrder( $orderby, $query ) {

			if( is_admin() and $_SERVER['SCRIPT_NAME'] == '/wp-admin/edit.php' and $query->is_main_query() and $query->get( 'post_type' ) == Tribe__Events__Main::POSTTYPE ) {

				// Filter by default Start Date, Upcoming, but ignore our changes if set to All (revert to default Tribe)
				if( $this->filterType !== 'all' ) {
					$query->set( 'orderby', 'start-date' );

					$rhp_orderby = $this->filterType == 'upcoming' ? 'ASC' : 'DESC';
					$query->set( 'order', $rhp_orderby );
				}

			}

			// We didn't change this directly, Tribe will do our bidding in filter priority 10
			return $orderby;

		}

		/**
		 * Apply some filtering logic to the WP Admin query
		 *
		 * @param $where string SQL Where clause
		 * @param $query WP_Query The main WP Query object
		 *
		 * @filter posts_where
		 */
		public function sortWhere( $where, $query ) {

			if( !$this->skipFilter and is_admin() and $_SERVER['SCRIPT_NAME'] == '/wp-admin/edit.php' and $query->is_main_query() and $query->get( 'post_type' ) == Tribe__Events__Main::POSTTYPE ) {

				if( $this->filterType !== 'all' ) {
					$now = current_time('mysql');
					$filter = $this->filterType == 'upcoming' ? '>' : '<';

					// Thanks to whomever at Tribe change a table name without putting in release notes
					$field = ( version_compare( Tribe__Events__Main::VERSION, '3.11', '>=' ) ) ? 'tribe_event_start_date.meta_value' : 'eventStart.meta_value';

					$where .= " AND STR_TO_DATE({$field},'%Y-%m-%d %H:%i:%s') {$filter} '{$now}' ";
				}

			}

			return $where;

		}

		/**
		 * Static Singleton Factory Method
		 * @return RockhouseTribeWPAdmin
		 */
		public static function instance() {
			if (!isset(self::$instance)) {
				$className = __CLASS__;
				self::$instance = new $className;
			}
			return self::$instance;
		}

	}

	// Fire it up, boss!
	RockhouseTribeWPAdmin::instance();
}
