<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mk_register_cpt' );

function mk_register_cpt() {
	$labels = [
		'name'               => 'Recenze',
		'singular_name'      => 'Recenze',
		'add_new'            => 'Přidat recenzi',
		'add_new_item'       => 'Přidat novou recenzi',
		'edit_item'          => 'Upravit recenzi',
		'new_item'           => 'Nová recenze',
		'view_item'          => 'Zobrazit recenzi',
		'search_items'       => 'Hledat recenze',
		'not_found'          => 'Žádné recenze nenalezeny',
		'not_found_in_trash' => 'Žádné recenze v koši',
		'all_items'          => 'Všechny recenze',
		'menu_name'          => 'Recenze',
	];

	register_post_type( 'recenze', [
		'labels'        => $labels,
		'public'        => true,
		'show_in_rest'  => true,
		'supports'      => [ 'title', 'editor', 'author', 'thumbnail' ],
		'has_archive'   => true,
		'rewrite'       => [ 'slug' => 'recenze' ],
		'menu_icon'     => 'dashicons-star-filled',
		'menu_position' => 5,
	] );
}
