<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Extend the Tribe Events List widget to support more events than 10
 *
 * @since  	1.0.4
 * @package RockhouseEvents
 * @author 	Rockhouse
 */

if( class_exists( 'Tribe__Events__List_Widget' ) ):

class RHPTribeEventsListWidget extends Tribe__Events__List_Widget {

	/**
	 * Get rid of the Tribe widget and replace with our upgraded
	 * version
	 */
	public static function swap_tribe_widget() {
		unregister_widget( 'Tribe__Events__List_Widget' );
		unregister_widget( 'Tribe__Events__Pro__Advanced_List_Widget' );
		register_widget( 'RHPTribeEventsListWidget' );
	}

	public function __construct( $id_base = '', $name = '', $widget_options = array(), $control_options = array() ) {
		$widget_options = array_merge(
			array(
				'classname'   => 'tribe-events-list-widget',
				'description' => __( 'An enhanced widget for upcoming Events with thumbnails, categories, templates, and more.', 'tribe-events-calendar' )
			),
			$widget_options
		);

		$control_options = array_merge( array( 'id_base' => 'tribe-events-list-widget' ), $control_options );

		$id_base = empty( $id_base ) ? 'tribe-events-list-widget' : $id_base;
		$name    = empty( $name ) ? __( 'Events List', 'tribe-events-calendar' ) : $name;

		parent::__construct( $id_base, $name, $widget_options, $control_options );

		// Filter args for certain scenarios
		add_filter( 'tribe_events_list_widget_query_args', array( $this, 'widget_query_args' ), 10, 1);

		// Do not enqueue if the widget is inactive
		if ( is_active_widget( false, false, $this->id_base, true ) ) {
			add_action( 'init', array( $this, 'enqueue_stylesheet' ), 100 );
		}
	}

	/**
	 * If the widget is active then enqueue our stylesheet.
	 */
	public function enqueue_stylesheet() {
		// Load the calendar widget CSS (the list widget inherits much of the same)
		if( class_exists( 'Tribe__Events__Pro__Widgets' ) ) {
			Tribe__Events__Pro__Widgets::enqueue_calendar_widget_styles();
		}
	}

	/**
	 * Filter the query parameters for our instances
	 *
	 * @param array $args WP_Query args to be fed to our widget output
	 * @filter tribe_events_list_widget_query_args
	 */
	public function widget_query_args( $args ) {

		if ( empty( $this->instance ) ) {
			return $args;
		}

		// Advanced Tax args from Tribe Pro Widget
		$filters   = isset( $this->instance['raw_filters'] ) ? $this->instance['raw_filters'] : json_decode( $this->instance['filters'] );
		$tax_query = Tribe__Events__Pro__Widgets::form_tax_query( $filters, $this->instance['operand'] );

		if ( isset( $args['tax_query'] ) ) {
			$args['tax_query'] = array_merge( $args['tax_query'], $tax_query );
		} else {
			$args['tax_query'] = $tax_query;
		}

		// We only want to group with a Taxonomy set if it is an event-series
		if( !empty( $this->instance['group_series'] ) ) {
			$args['rhptribe_group_widget_list'] = true;
			$args['widget_num_posts'] = $args['posts_per_page'];
			$args['posts_per_page'] = -1;
		}

		return $args;

	}


	/**
	 * Output our event thumb when called using a tribe
	 * before filter on widget output
	 *
	 * @filter tribe_events_list_widget_before_the_event_title
	 */
	public function add_event_thumbnail() {
		global $post;
		if( isset( $post->is_series ) and $post->is_series ) {
			global $rhptribe_series;
			$img = $rhptribe_series[$post->ID]['rhp_series_page_image'];
			if( $img ) {
				echo <<<HTML
				<div class="tribe-events-event-image">
					<a href="{$rhptribe_series[$post->ID]['series_link']}">
						<img src="{$img}" class="attachment-thumbnail wp-post-image" alt="{$rhptribe_series[$post->ID]['term']->name}">
					</a>
				</div>
HTML;
			}

		} else {
			echo tribe_event_featured_image( $post->ID, 'thumbnail', true );
		}
	}


	public function widget( $args, $instance ) {

		$ecp            = Tribe__Events__Pro__Main::instance();
		$tooltip_status = $ecp->recurring_info_tooltip_status();
		$ecp->disable_recurring_info_tooltip();
		$this->instance_defaults( $instance );

		if ( $tooltip_status ) {
			$ecp->enable_recurring_info_tooltip();
		}

		// Select our template
		$template = 'widgets/list-widget'; // Default
		if( $instance['template'] == 'list-full' ) {
			$template = 'rhp/widgets/rhp-list-widget';
		}

		// Add images using a filter if our option is set
		if( !empty( $instance['show_thumbs'] ) ) {
			add_action( 'tribe_events_list_widget_before_the_event_title', array( $this, 'add_event_thumbnail' ) );
		}

		parent::widget_output( $args, $instance, $template );
	}

	public function update( $new_instance, $old_instance ) {
		$instance = parent::update( $new_instance, $old_instance );

		$instance['operand']   = strip_tags( $new_instance['operand'] );
		$instance['filters']   = maybe_unserialize( $new_instance['filters'] );

		// @todo remove after 3.7 (added for continuity when users transition from 3.5.x or earlier to this release)
		if ( isset( $old_instance['category'] ) ) {
			$this->include_cat_id( $instance['filters'], $old_instance['category'] );
			unset( $instance['category'] );
		}

		$instance['template'] = $new_instance['template'];

		$instance['show_thumbs'] = $new_instance['show_thumbs'];

		$instance['group_series'] = $new_instance['group_series'];

		return $instance;
	}

	protected function instance_defaults( $instance ) {
		$this->instance = wp_parse_args( (array) $instance, array(
			'title'              => __( 'Upcoming Events', 'tribe-events-calendar-pro' ),
			'limit'              => 5,
			'no_upcoming_events' => false,
			'operand'            => 'OR',
			'filters'            => '',
			'template'           => 'default',
			'show_thumbs'        => false,
			'group_series'       => false,
			'instance'           => &$this->instance
		) );
	}

	/**
	 * Adds the provided category ID to the list of filters.
	 *
	 * In 3.6 taxonomy filters were added to this widget (as already existed for the calendar
	 * widget): this helper exists to provide some continuity for users upgrading from a 3.5.x
	 * release or earlier, transitioning any existing category setting to the new filters
	 * list.
	 *
	 * @todo remove after 3.7
	 *
	 * @param mixed &$filters
	 * @param int   $id
	 */
	protected function include_cat_id( &$filters, $id ) {
		$id  = (string) absint( $id ); // An absint for sanity but a string for comparison purposes
		$tax = Tribe__Events__Main::TAXONOMY;
		if ( '0' === $id || ! is_string( $filters ) ) {
			return;
		}

		$filters = (array) json_decode( $filters, true );

		if ( isset( $filters[ $tax ] ) && ! in_array( $id, $filters[ $tax ] ) ) {
			$filters[ $tax ][] = $id;
		} elseif ( ! isset( $filters[ $tax ] ) ) {
			$filters[ $tax ] = array( $id );
		}

		$filters = json_encode( $filters );
	}

	public function form( $instance ) {
		$this->instance_defaults( $instance );
		$instance = $this->instance;
		$tribe_ecp = Tribe__Events__Main::instance();

		if ( isset( $this->instance['category'] ) ) {
			$this->include_cat_id( $this->instance['filters'], $this->instance['category'] ); // @todo remove after 3.7
		}

		$taxonomies = get_object_taxonomies( Tribe__Events__Main::POSTTYPE, 'objects' );
		$taxonomies = array_reverse( $taxonomies );

?>

<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'tribe-events-calendar' ); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
</p>

<p>
	<label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Number of Events to display:', 'tribe-events-calendar' ); ?></label>
	<select id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" class="widefat">
		<?php
			$nevents = array(1,2,3,4,5,10,15,25,50);
			foreach ( $nevents as $i ) {
			?>
			<option <?php if ( $i == $instance['limit'] ) {
				echo 'selected="selected"';
			} ?> > <?php echo $i; ?> </option>
		<?php } ?>
	</select>
</p>

<?php
/**
 * Filters
 */

$class = "";
if ( empty( $instance['filters'] ) ) {
	$class = "display:none;";
}
?>

<div class="calendar-widget-filters-container" style="<?php echo esc_attr( $class ); ?>">

	<h3 class="calendar-widget-filters-title"><?php esc_html_e( 'Filters', 'tribe-events-calendar-pro' ); ?>:</h3>

	<input type="hidden" name="<?php echo esc_attr( $this->get_field_name( 'filters' ) ); ?>"
	       id="<?php echo esc_attr( $this->get_field_id( 'filters' ) ); ?>" class="calendar-widget-added-filters"
	       value='<?php echo esc_attr( maybe_serialize( $instance['filters'] ) ); ?>' />
	<style>
		.customizer-select2 {
			z-index: 500001
		}
	</style>
	<div class="calendar-widget-filter-list">
		<?php
		if ( ! empty( $instance['filters'] ) ) {

			echo '<ul>';

			foreach ( json_decode( $instance['filters'] ) as $tax => $terms ) {
				$tax_obj = get_taxonomy( $tax );

				foreach ( $terms as $term ) {
					if ( empty( $term ) ) {
						continue;
					}
					$term_obj = get_term( $term, $tax );
					if ( empty( $term_obj ) || is_wp_error( $term_obj ) ) {
						continue;
					}
					echo sprintf( "<li><p>%s: %s&nbsp;&nbsp;<span><a href='#' class='calendar-widget-remove-filter' data-tax='%s' data-term='%s'>(" . __( 'remove', 'tribe-events-calendar-pro' ) . ')</a></span></p></li>', $tax_obj->labels->name, $term_obj->name, $tax, $term_obj->term_id );
				}
			}

			echo '</ul>';
		}
		?>

	</div>

	<p class="calendar-widget-filters-operand">
		<label for="<?php echo esc_attr( $this->get_field_name( 'operand' ) ); ?>">
			<input <?php checked( $instance['operand'], 'AND' ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'operand' ) ); ?>" value="AND">
			<?php esc_html_e( 'Match all', 'tribe-events-calendar-pro' ); ?></label><br />
		<label for="<?php echo esc_attr( $this->get_field_name( 'operand' ) ); ?>">
			<input <?php checked( $instance['operand'], 'OR' ); ?> type="radio" name="<?php echo esc_attr( $this->get_field_name( 'operand' ) ); ?>" value="OR">
			<?php esc_html_e( 'Match any', 'tribe-events-calendar-pro' ); ?></label>
	</p>
</div>
<p>
	<label><?php esc_html_e( 'Add a filter', 'tribe-events-calendar-pro' ); ?>:
		<select class="widefat calendar-widget-add-filter" id="<?php echo esc_attr( $this->get_field_id( 'selector' ) ); ?>" data-storage="<?php echo esc_attr( $this->get_field_id( 'filters' ) ); ?>">
			<?php
			echo "<option value='0'>" . esc_html__( 'Select one...', 'tribe-events-calendar-pro' ) . '</option>';
			foreach ( $taxonomies as $tax ) {
				echo sprintf( "<optgroup id='%s' label='%s'>", esc_attr( $tax->name ), esc_attr( $tax->labels->name ) );
				$terms = get_terms( $tax->name, array( 'hide_empty' => false ) );
				foreach ( $terms as $term ) {
					echo sprintf( "<option value='%d'>%s</option>", esc_attr( $term->term_id ), esc_html( $term->name ) );
				}
				echo '</optgroup>';
			}
			?>
		</select>
	</label>
</p>

<script type="text/javascript">

	jQuery(document).ready(function ($) {
		if ($('div.widgets-sortables').find('select.calendar-widget-add-filter:not(#widget-tribe-mini-calendar-__i__-selector)').length && !$('#customize-controls').length) {

			$(".select2-container.calendar-widget-add-filter").remove();
			setTimeout(function () {
				$("select.calendar-widget-add-filter:not(#widget-tribe-mini-calendar-__i__-selector)").select2();
				calendar_toggle_all();
			}, 600);
		}
	});
</script>

<p>
	<label for="<?php echo $this->get_field_id( 'template' ); ?>"><?php _e( 'Template:', 'tribe-events-calendar' ); ?></label>
	<select id="<?php echo $this->get_field_id( 'template' ); ?>" name="<?php echo $this->get_field_name( 'template' ); ?>" class="widefat">
		<?php
			$tpls = array('default' => 'Default (for Sidebars)','list-full' => 'Full Width Events List');
			foreach ( $tpls as $i => $l ) {
			?>
			<option value="<?php echo $i; ?>" <?php if ( $i == $instance['template'] ) {
				echo 'selected="selected"';
			} ?> > <?php echo $l; ?> </option>
		<?php } ?>
	</select>
</p>

<p>
	<label for="<?php echo $this->get_field_id( 'no_upcoming_events' ); ?>"><?php _e( 'Show widget only if there are upcoming events:', 'tribe-events-calendar' ); ?></label>
	<input id="<?php echo $this->get_field_id( 'no_upcoming_events' ); ?>" name="<?php echo $this->get_field_name( 'no_upcoming_events' ); ?>" type="checkbox" <?php checked( $instance['no_upcoming_events'], 1 ); ?> value="1" />
</p>

<p>
	<label for="<?php echo $this->get_field_id( 'show_thumbs' ); ?>"><?php _e( 'Display Event Thumbnails:', 'tribe-events-calendar' ); ?></label>
	<input id="<?php echo $this->get_field_id( 'show_thumbs' ); ?>" name="<?php echo $this->get_field_name( 'show_thumbs' ); ?>" type="checkbox" <?php checked( $instance['show_thumbs'], 1 ); ?> value="1" />
</p>

<?php
		// Special behavior for grouping series
		if( RockhouseEvents::getOption( 'etixGroupSeries' ) ) {
?>
<p>
	<label for="<?php echo $this->get_field_id( 'group_series' ); ?>"><?php _e( 'Group Event Series:', 'tribe-events-calendar' ); ?></label>
	<input id="<?php echo $this->get_field_id( 'group_series' ); ?>" name="<?php echo $this->get_field_name( 'group_series' ); ?>" type="checkbox" <?php checked( $instance['group_series'], 1 ); ?> value="1" />
</p>
<?php 	} else { ?>
	<input style="display:none" id="<?php echo $this->get_field_id( 'group_series' ); ?>" name="<?php echo $this->get_field_name( 'group_series' ); ?>" type="checkbox" value="1" />
<?php
		}
	}

}

endif;
