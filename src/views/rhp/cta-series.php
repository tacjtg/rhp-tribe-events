<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rhino Event CTA for Event Series Category
 *
 * This template is called in the following locations:
 * 		List View Single Event Series (/list/loop/taxonomy-tribe_events_cat-single.php)
 * 		List View All Event Series (/list/loop/taxonomy-tribe_events_cat.php)
 *
 * @since  	1.2.0
 * @package Rhino
 * @author 	Rockhouse
 */

$rhp_cta = RockhouseEvents::getEventSeriesCtaContent();

do_action( 'rhp_event_before_cta' );
?>

<?php
if( !empty($rhp_cta['label']) ) {
	$label =
		( empty($rhp_cta['href']) ) ?
			$rhp_cta['label'] :
			'<a class="secondary button large" target="_blank" rel="external" title="' .$rhp_cta['label'] . '" href="' . $rhp_cta['href'] . '">' . $rhp_cta['label'] . '</a>';

	$classes = implode(' ',$rhp_cta['classes']);
	echo '<span class="' . $classes . '">' . $label . '</span>';
}

?>

<?php
do_action( 'rhp_event_after_cta' );
