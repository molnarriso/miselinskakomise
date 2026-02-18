<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mk_register_taxonomy' );

function mk_register_taxonomy() {
	$labels = [
		'name'              => 'Hashtagy',
		'singular_name'     => 'Hashtag',
		'search_items'      => 'Hledat hashtagy',
		'all_items'         => 'Všechny hashtagy',
		'edit_item'         => 'Upravit hashtag',
		'update_item'       => 'Aktualizovat hashtag',
		'add_new_item'      => 'Přidat nový hashtag',
		'new_item_name'     => 'Nový hashtag',
		'menu_name'         => 'Hashtagy',
	];

	register_taxonomy( 'hashtag', 'recenze', [
		'labels'            => $labels,
		'hierarchical'      => false,
		'public'            => true,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'hashtag' ],
		'show_admin_column' => true,
	] );
}
