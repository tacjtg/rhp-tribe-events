<?php
/**
 * Rockhouse AddOn Events template Tags
 *
 * Display functions for use in WordPress templates.
 */

// Don't load directly
if ( !defined('ABSPATH') ) { die('-1'); }

if( class_exists( 'RockhouseEvents' ) ) {

	if ( !function_exists( '__rhp_event_post_id' ) ) {
		/**
		 * Utility function to do everything possible to marshall the
		 * given input to a post_id
		 */
		function __rhp_event_post_id( $post_id = false ) {

			if( is_object($post_id) )
				$post_id = (int) $post_id->ID;

			if( empty( $post_id ) )
				$post_id = (int) get_the_ID();

			if( is_numeric($post_id) )
				$post_id = (int) $post_id;

			if( get_post_type($post_id) != Tribe__Events__Main::POSTTYPE )
				return false;

			return $post_id;

		}

	}

	if ( !function_exists( 'rhp_event_status' ) ) {

		/**
	 	 * Return a token for the Event Status
		 *
		 *   Will be one of the following:
		 * 		comingsoon
		 * 		onsale
		 * 		offsale
		 * 		past
		 *
		 * 	@var (mixed) $post Post Object or Post ID (optional, defaults to current)
		 */
		function rhp_event_status( $post_id = false ) {
			$post_id = __rhp_event_post_id( $post_id );

			if( $post_id == false ) {
				return false;
			}

			$now = current_time('timestamp');
			$start_ts = (int) get_field('rhp_event_start_date',$post_id,false);
			$onsale_ts = (int) get_field('rhp_event_on_sale_date',$post_id,false);
			$offsale_ts = (int) get_field('rhp_event_off_sale_date',$post_id,false);
			$end_ts = (int) strtotime( tribe_get_end_date($post_id,true,'Y-m-d H:i:s') );
			$cta_url = get_field('rhp_event_cta_url',$post_id);

			if( $now > $end_ts )
				return 'past';
			elseif( $onsale_ts and $onsale_ts > $now )
				return 'comingsoon';
			elseif( ( $offsale_ts and $offsale_ts < $now ) or ( empty( $cta_url ) ) )
				return 'offsale';
			else
				return 'onsale';
		}
	}


	if ( !function_exists( 'rhp_event_status_is' ) ) {

		/**
		 * Check the current or given Event status
		 *
		 * @var (string) $status Status to check for (onsale, offsale, comingsoon, past)
		 * @var (mixed) $post Post object or Post ID (optional, defaults to current)
		 */
		function rhp_event_status_is( $status = '', $post_id = false ) {

			$event_status = rhp_event_status($post_id);
			return ( $event_status == $status ) ? true : false;

		}

	}


	if ( !function_exists( 'rhp_event_in_series' ) ) {
		/**
		 * Check if this Event is part of one of our Event Series
		 *
		 * @var (mixed) $post Post object or Post ID (optional, defaults to current)
		 */
		function rhp_event_in_series( $post_id = false ) {

			$series = rhp_event_get_series( $post_id );
			return ( $series == false ) ? false : true;

		}
	}


	if ( !function_exists( 'rhp_event_get_series' ) ) {

		/**
		 * Check if this Event is part of one of our Event Series
		 *
		 * @var (mixed) $post Post object or Post ID (optional, defaults to current)
		 */
		function rhp_event_get_series( $post_id = false ) {

			$series_term = term_exists('series', Tribe__Events__Main::TAXONOMY );
			if( !is_array( $series_term ) ) {
				return false;
			}

			$series_term_id = (int) $series_term['term_id'];
			$post_id = __rhp_event_post_id( $post_id );

			if( $post_id == false ) {
				return false;
			}

			$event_terms = get_the_terms( $post_id, Tribe__Events__Main::TAXONOMY );
			if( is_array( $event_terms ) ) {
				foreach( $event_terms as $cat ) {
					if( $cat->parent == $series_term_id ) {
						return $cat;
					}
				}
			} else {
				return false;
			}

		}
	}

	if( !function_exists( 'rhp_series_get_events' ) ) {

		/**
		 * Get the Events that are part of a given Event Series
		 *
		 * Uses tribe_get_events and returns an array of events (not a WP_Query object)
		 *
		 * @var (mixed) $series Event Series slug or term_id
		 * @var boolean $full Return a simple array or full WP_Query object
		 * @return array
		 */
		function rhp_series_get_events( $term, $full = false ) {

			$wp_series = array(
				'posts_per_page' => -1,
				'tax_query' => array(
					array(
						'taxonomy' => Tribe__Events__Main::TAXONOMY,
						'field' => is_numeric( $term ) ? 'id' : 'slug',
						'terms' => $term
					)
				)
			);

			return tribe_get_events( $wp_series, $full );

		}
	}


	if( !function_exists( 'rhp_series_get_event_siblings' ) ) {

		/**
		 * Get the other Events that are part of the Event Series
		 * a given Event is in.
		 *
		 * Uses tribe_get_events and returns an array of events (not a WP_Query object)
		 *
		 * @var (mixed) $post Post object or Post ID (optional, defaults to current)
		 * @var boolean $full Return a simple array or full WP_Query object
		 * @return array
		 */
		function rhp_series_get_event_siblings( $post_id = false, $full = false ) {

			$series = rhp_event_get_series( $post_id );
			$post_id = __rhp_event_post_id( $post_id );

			if( $post_id and $series != false ) {
				return rhp_series_get_events( $series->term_id, $full );
			} else {
				return $full ? array() : new WP_Query();
			}

		}
	}

}
