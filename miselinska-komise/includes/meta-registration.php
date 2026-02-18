<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mk_register_meta' );

function mk_register_meta() {
	$fields = [
		'_mk_rating'          => [ 'type' => 'number',  'description' => 'Hodnocení (0–10)' ],
		'_mk_restaurant_name' => [ 'type' => 'string',  'description' => 'Název restaurace' ],
		'_mk_google_maps_url' => [ 'type' => 'string',  'description' => 'Google Maps odkaz' ],
		'_mk_latitude'        => [ 'type' => 'number',  'description' => 'Zeměpisná šířka' ],
		'_mk_longitude'       => [ 'type' => 'number',  'description' => 'Zeměpisná délka' ],
		'_mk_address'         => [ 'type' => 'string',  'description' => 'Adresa' ],
		'_mk_visit_date'      => [ 'type' => 'string',  'description' => 'Datum návštěvy' ],
		'_mk_gallery'         => [ 'type' => 'string',  'description' => 'Galerie fotek (IDs)' ],
	];

	foreach ( $fields as $key => $args ) {
		register_post_meta( 'recenze', $key, [
			'type'          => $args['type'],
			'description'   => $args['description'],
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		] );
	}
}
