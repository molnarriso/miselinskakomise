<?php
defined( 'ABSPATH' ) || exit;

add_shortcode( 'miselinska_feed', 'mk_shortcode_feed' );

function mk_shortcode_feed( $atts ) {
	$atts = shortcode_atts( [
		'count'   => 12,
		'hashtag' => '',
		'orderby' => 'date',
	], $atts, 'miselinska_feed' );

	$args = [
		'post_type'      => 'recenze',
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, intval( $atts['count'] ) ),
		'orderby'        => in_array( $atts['orderby'], [ 'date', 'meta_value_num' ], true ) ? $atts['orderby'] : 'date',
		'order'          => 'DESC',
	];

	if ( $args['orderby'] === 'meta_value_num' ) {
		$args['meta_key'] = '_mk_rating';
	}

	if ( ! empty( $atts['hashtag'] ) ) {
		$args['tax_query'] = [ [
			'taxonomy' => 'hashtag',
			'field'    => 'slug',
			'terms'    => sanitize_title( $atts['hashtag'] ),
		] ];
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return '<p class="mk-no-reviews">Zatím žádné recenze.</p>';
	}

	$html = '<div class="mk-feed">';
	while ( $query->have_posts() ) {
		$query->the_post();
		$post_id  = get_the_ID();
		$rating   = (float) get_post_meta( $post_id, '_mk_rating', true );
		$rest_name = get_post_meta( $post_id, '_mk_restaurant_name', true ) ?: get_the_title();
		$hashtags = get_the_terms( $post_id, 'hashtag' );
		$author   = get_the_author();
		$date     = get_the_date( 'j. n. Y' );
		$excerpt  = get_the_excerpt();
		$permalink = get_permalink();
		$thumb    = get_the_post_thumbnail( $post_id, 'medium', [ 'class' => 'mk-card-thumb' ] );

		$rating_class = $rating >= 8 ? 'mk-rating-green' : ( $rating >= 5 ? 'mk-rating-yellow' : 'mk-rating-red' );

		$tag_html = '';
		if ( $hashtags && ! is_wp_error( $hashtags ) ) {
			foreach ( $hashtags as $tag ) {
				$tag_html .= '<a class="mk-tag" href="' . esc_url( get_term_link( $tag ) ) . '">#' . esc_html( $tag->name ) . '</a> ';
			}
		}

		$html .= '<article class="mk-card">';
		if ( $thumb ) {
			$html .= '<a href="' . esc_url( $permalink ) . '" class="mk-card-thumb-link">' . $thumb . '</a>';
		}
		$html .= '<div class="mk-card-body">';
		$html .= '<div class="mk-card-header">';
		$html .= '<h2 class="mk-card-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $rest_name ) . '</a></h2>';
		$html .= '<span class="mk-rating-badge ' . esc_attr( $rating_class ) . '">' . number_format( $rating, 1 ) . '</span>';
		$html .= '</div>';
		$html .= '<p class="mk-card-meta"><span class="mk-author">' . esc_html( $author ) . '</span> &middot; <span class="mk-date">' . esc_html( $date ) . '</span></p>';
		if ( $tag_html ) $html .= '<p class="mk-tags">' . $tag_html . '</p>';
		if ( $excerpt ) $html .= '<p class="mk-excerpt">' . esc_html( $excerpt ) . '</p>';
		$html .= '<a class="mk-card-link" href="' . esc_url( $permalink ) . '">Číst recenzi →</a>';
		$html .= '</div></article>';
	}
	$html .= '</div>';

	wp_reset_postdata();
	return $html;
}
