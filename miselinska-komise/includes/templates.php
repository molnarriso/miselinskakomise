<?php
defined( 'ABSPATH' ) || exit;

/**
 * Load plugin templates unless the theme provides its own
 * single-recenze.php / archive-recenze.php.
 */
add_filter( 'template_include', 'mk_template_include' );

function mk_template_include( $template ) {
	if ( is_singular( 'recenze' ) ) {
		$theme_tpl = locate_template( 'single-recenze.php' );
		if ( ! $theme_tpl ) {
			return MK_DIR . 'templates/single-recenze.php';
		}
	}

	if ( is_post_type_archive( 'recenze' ) || is_tax( 'hashtag' ) ) {
		$theme_tpl = locate_template( 'archive-recenze.php' );
		if ( ! $theme_tpl ) {
			return MK_DIR . 'templates/archive-recenze.php';
		}
	}

	return $template;
}
