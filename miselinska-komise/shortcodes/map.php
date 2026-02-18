<?php
defined( 'ABSPATH' ) || exit;

add_shortcode( 'miselinska_mapa', 'mk_shortcode_map' );

function mk_shortcode_map( $atts ) {
	$atts = shortcode_atts( [ 'height' => '600' ], $atts, 'miselinska_mapa' );
	$height = absint( $atts['height'] ) ?: 600;

	// Enqueue Leaflet + MarkerCluster
	wp_enqueue_style(  'leaflet',        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',     [], '1.9.4' );
	wp_enqueue_style(  'leaflet-cluster','https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', [], '1.5.3' );
	wp_enqueue_script( 'leaflet',        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',      [], '1.9.4', true );
	wp_enqueue_script( 'leaflet-cluster','https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', [ 'leaflet' ], '1.5.3', true );
	wp_enqueue_script( 'mk-map',         MK_URL . 'assets/js/map-shortcode.js', [ 'leaflet', 'leaflet-cluster' ], '1.0.4', true );

	wp_localize_script( 'mk-map', 'mkMapData', [
		'restUrl' => rest_url( 'wp/v2/recenze' ),
		'perPage' => 100,
	] );

	$map_id = 'mk-map-' . wp_unique_id();
	return sprintf(
		'<div id="%s" class="mk-map" style="height:%dpx;width:100%%;"></div>',
		esc_attr( $map_id ),
		$height
	);
}
