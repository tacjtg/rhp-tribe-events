<?php

// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if ( !class_exists( 'RockhouseTribeEventsbar' ) ) {

/**
 * Tribe Events Calendar - Events Bar Overrides
 *
 * @since  	1.1.1
 * @package rhp
 * @author 	Rockhouse
 */

	class RockhouseTribeEventsbar {

		/**
		 * @var $instance Static singleton for this class
		 */
		protected static $instance;

		/**
		 * Constructor to get this moving
		 */
		public function __construct( ) {

			// Set up our filters real late after other plugins have been loaded
			add_action( 'plugins_loaded', array( $this, 'pluginsLoaded'), 100 );

			// Add our JS under the Event Navbar
			add_action( 'tribe_events_bar_after_template', array( $this, 'jsChanges' ), 100 );

		}

		/**
		 * Adjust the Event Filter Bar using Tribe hooks
		 *
		 * @action plugins_loaded
		 */
		public function pluginsLoaded() {

			if( class_exists( 'Tribe__Events__Main' ) and defined( 'RHPTRIBE_ACTIVE' )  ) {
				add_filter('tribe-events-bar-filters', array($this,'barFilters'), 1000, 1 );
				add_action('pre_get_posts', array($this,'filterListQuery'), 25, 1);
			}

		}


		/**
		 * Add/Rm Filters for the Events Bar
		 *
		 * @param $filters Tribe filter array
		 * @filter tribe-events-bar-filters
		 */

		public function barFilters( $filters ) {

			// Drop out Geolocation, always
			if ( isset( $filters['tribe-bar-geoloc'] ) )
				unset( $filters['tribe-bar-geoloc'] );

			// Drop out 'Events From'
			if ( isset( $filters['tribe-bar-date'] ) )
				unset( $filters['tribe-bar-date'] );

			// Setup our View All option
			$view_all = tribe_get_events_link();
			if( tribe_is_month() ) {
				$view_all = tribe_get_gridview_link();
			} // Photo view??

			// Add Venue Filter
			if( RockhouseEvents::getOption('multipleVenues') ) {

				//$venues = tribe_get_venues();  // This is actually filtered by the current WP_Query
				//  There isn't a tribe method to get all venues... curious
				$venues = new WP_Query( 'post_type=' . Tribe__Events__Main::VENUE_POST_TYPE . '&nopaging=1&suppress_filters=true' );

				if( $venues->post_count ) {
					$html = '<select name="tribe_bar_rhp_venue" id="tribe_bar_rhp_venue"><option value="0" data-url="' . esc_url( $view_all ) . '">All Venues</option>';

					$base_url = $_SERVER['REQUEST_URI'];
					$url_query = strpos( $_SERVER['REQUEST_URI'], '?' );
					$base_url .= empty( $url_query ) ? '?' : '&' ;
					$base_url .= 'tribe_bar_rhp_venue=';


					foreach($venues->posts as $venue) {
						$selected = (isset($_REQUEST['tribe_bar_rhp_venue']) and $_REQUEST['tribe_bar_rhp_venue'] == $venue->ID ) ? ' selected="selected"' : '';
						$html .= '<option value="' . $venue->ID . '"' . $selected . ' data-url="' . $base_url . $venue->ID . '">' . $venue->post_title . '</option>';
					}
					$html .= '</select>';

					$filters['tribe-bar-rhp-venue'] = array(
						'name' => 'tribe-bar-rhp-venue',
						'caption' => 'Venue',
						'html' => $html
					);
				}

			}

			// Add Category Filter (which is really a list of links)

			$tax_terms = get_terms( Tribe__Events__Main::TAXONOMY, array( 'hide_empty' => true ) );

			if( !empty($tax_terms) ) {

				$html = '<select name="tribe_bar_rhp_cat" id="tribe_bar_rhp_cat"><option value="0" data-url="' . esc_url( $view_all ) . '">Event Categories</option>';

				// These don't have a selected since they are a single tax view
				foreach($tax_terms as $tax_term) {
					$html .= '<option value="' . $tax_term->slug . '" data-url="/events/category/' . $tax_term->slug . '/">' . $tax_term->name . '</option>';
				}

				$html .= '</select>';

				$filters['tribe-bar-rhp-cat'] = array(
					'name' => 'tribe-bar-rhp-cat',
					'caption' => 'Category',
					'html' => $html
				);
			}

			return $filters;

		}

		/**
		 * Output a snippet of javascript to handle our filters
		 *
		 * @action tribe_events_bar_after_template
		 */
		function jsChanges() {
			echo <<<JS
<script> jQuery(document).ready(function($) {
	$('#tribe_bar_rhp_cat, #tribe_bar_rhp_venue').on('change',function(){  window.location.href = $('option:selected', this).data('url'); });
}); </script>
JS;

		}

		/**
		 * Add our Venue filter to the Query when set on the Events Bar
		 *
		 * We're preemting the tribe_events_pre_get_post since they'll take
		 * care of the venue meta if we add 'venue' to the query
		 *
		 * @param $query WP_Query
		 * @filter pre_get_posts
		 */

		function filterListQuery( $query ) {

			if( !empty( $query->query['suppress_filters'] ) ) {
				return $query;
			}

			if ( !empty( $_REQUEST['tribe_bar_rhp_venue'] ) ) {
				// see lib/tribe-event-query.class.php:298
				$query->query_vars['venue'] = $_REQUEST['tribe_bar_rhp_venue'];
			}

			if ( !empty( $_REQUEST['tribe_bar_rhp_cat'] ) ) {

				$tax_query = array(
								array(
									'taxonomy'	=> 'tribe_events_cat',
									'field'		=> 'term_id',
									'terms'		=> $_REQUEST['tribe_bar_rhp_cat'],
									'operator'=> 'IN'
								),
							);

				$query->set( 'tax_query', $tax_query );

			}

			return $query;

		}

		/**
		 * Static Singleton Factory Method
		 * @return RockhouseTribeEventsbar
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
	RockhouseTribeEventsbar::instance();
}
