<?php
/**
 * Plugin Name: Mišelinská Komise
 * Description: Restaurační recenze pro miselinskakomise.cz
 * Version: 1.0.0
 * Author: Mišelinská Komise
 * Text Domain: miselinska-komise
 */

defined( 'ABSPATH' ) || exit;

define( 'MK_DIR', plugin_dir_path( __FILE__ ) );
define( 'MK_URL', plugin_dir_url( __FILE__ ) );

require_once MK_DIR . 'includes/cpt.php';
require_once MK_DIR . 'includes/taxonomy.php';
require_once MK_DIR . 'includes/meta-registration.php';
require_once MK_DIR . 'includes/meta-boxes.php';
require_once MK_DIR . 'includes/ajax.php';
require_once MK_DIR . 'includes/templates.php';
require_once MK_DIR . 'shortcodes/map.php';
require_once MK_DIR . 'shortcodes/feed.php';
require_once MK_DIR . 'shortcodes/form.php';

// Enqueue global plugin styles
add_action( 'wp_enqueue_scripts', 'mk_enqueue_styles' );
function mk_enqueue_styles() {
	wp_enqueue_style( 'miselinska-komise', MK_URL . 'assets/css/miselinska.css', [], '1.1.0' );
}

// Inject map-relevant meta directly into REST response
add_filter( 'rest_prepare_recenze', 'mk_rest_inject_meta', 10, 2 );
function mk_rest_inject_meta( $response, $post ) {
	$data = $response->get_data();
	$id   = $post->ID;
	$data['mk_rating']          = (float)  get_post_meta( $id, '_mk_rating',          true );
	$data['mk_restaurant_name'] = (string) get_post_meta( $id, '_mk_restaurant_name', true );
	$data['mk_visit_date']      = (string) get_post_meta( $id, '_mk_visit_date',      true );
	$data['mk_latitude']        = (float)  get_post_meta( $id, '_mk_latitude',        true );
	$data['mk_longitude']       = (float)  get_post_meta( $id, '_mk_longitude',       true );
	$data['mk_google_maps_url'] = (string) get_post_meta( $id, '_mk_google_maps_url', true );
	$response->set_data( $data );
	return $response;
}

// Remove GeneratePress "Built with GeneratePress" footer credit
add_filter( 'generate_copyright', 'mk_footer_copyright' );
function mk_footer_copyright() {
	return '&copy; ' . date( 'Y' ) . ' Mišelinská Komise';
}

// Disable sidebar globally (no sidebar, no search widget)
add_filter( 'generate_sidebar_layout', function() { return 'no-sidebar'; } );
add_filter( 'generate_get_sidebar_layout', function() { return 'no-sidebar'; } );

// Hide page title on front page (removes "Domů" heading from body)
add_filter( 'generate_show_title', function( $show ) {
	if ( is_front_page() ) {
		return false;
	}
	return $show;
} );

