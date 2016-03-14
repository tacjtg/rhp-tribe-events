<?php
// Add Venue select to Events

if(function_exists("register_field_group"))
{
	register_field_group(array (
		'id' => 'acf_event-location',
		'title' => 'Event Location',
		'fields' => array (
			array (
				'key' => 'field_54e4f8a08659f',
				'label' => 'Venue',
				'name' => 'rhp_event_venue',
				'type' => 'post_object',
				'post_type' => array (
					0 => 'tribe_venue',
				),
				'taxonomy' => array (
					0 => 'all',
				),
				'allow_null' => 1,
				'multiple' => 0,
			),
		),
		'location' => array (
			array (
				array (
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'tribe_events',
					'order_no' => 0,
					'group_no' => 0,
				),
			),
		),
		'options' => array (
			'position' => 'side',
			'layout' => 'default',
			'hide_on_screen' => array (
			),
		),
		'menu_order' => 0,
	));
}

