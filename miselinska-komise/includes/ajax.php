<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_mk_submit_review', 'mk_ajax_submit_review' );

function mk_ajax_submit_review() {
	check_ajax_referer( 'mk_submit_review', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [ 'message' => 'Musíte být přihlášeni.' ], 403 );
	}

	$restaurant = sanitize_text_field( $_POST['restaurant_name'] ?? '' );
	$rating_raw = $_POST['rating'] ?? '';

	if ( empty( $restaurant ) ) {
		wp_send_json_error( [ 'message' => 'Název restaurace je povinný.' ] );
	}
	if ( $rating_raw === '' ) {
		wp_send_json_error( [ 'message' => 'Hodnocení je povinné.' ] );
	}

	$rating         = floatval( $rating_raw );
	$visit_date_raw = sanitize_text_field( $_POST['visit_date'] ?? '' );
	$visit_date     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $visit_date_raw ) ? $visit_date_raw : '';
	$maps_url       = esc_url_raw( $_POST['google_maps_url'] ?? '' );
	$lat            = floatval( $_POST['latitude']  ?? 0 );
	$lng            = floatval( $_POST['longitude'] ?? 0 );
	$address        = sanitize_text_field( $_POST['address'] ?? '' );
	$body           = wp_kses_post( $_POST['review_body'] ?? '' );
	$hashtags       = sanitize_text_field( $_POST['hashtags'] ?? '' );
	$post_id_edit   = absint( $_POST['post_id'] ?? 0 );

	// ── Update existing post ─────────────────────────────────────────
	if ( $post_id_edit > 0 ) {
		if ( ! current_user_can( 'edit_post', $post_id_edit ) ) {
			wp_send_json_error( [ 'message' => 'Nemáte oprávnění upravit tuto recenzi.' ], 403 );
		}
		$existing = get_post( $post_id_edit );
		if ( ! $existing || $existing->post_type !== 'recenze' ) {
			wp_send_json_error( [ 'message' => 'Recenze nenalezena.' ] );
		}
		$post_id = $post_id_edit;
		wp_update_post( [ 'ID' => $post_id, 'post_title' => $restaurant, 'post_content' => $body ] );

	// ── Create new post ──────────────────────────────────────────────
	} else {
		$post_id = wp_insert_post( [
			'post_type'    => 'recenze',
			'post_title'   => $restaurant,
			'post_content' => $body,
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Chyba při ukládání recenze.' ] );
		}
	}

	update_post_meta( $post_id, '_mk_rating',          $rating );
	update_post_meta( $post_id, '_mk_visit_date',      $visit_date );
	update_post_meta( $post_id, '_mk_restaurant_name', $restaurant );
	update_post_meta( $post_id, '_mk_google_maps_url', $maps_url );
	update_post_meta( $post_id, '_mk_latitude',        $lat );
	update_post_meta( $post_id, '_mk_longitude',       $lng );
	update_post_meta( $post_id, '_mk_address',         $address );

	// Handle photo uploads (new photos appended to existing gallery)
	if ( ! empty( $_FILES['photos'] ) && ! empty( $_FILES['photos']['name'][0] ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$existing_gallery = get_post_meta( $post_id, '_mk_gallery', true );
		$gallery_ids      = $existing_gallery
			? array_filter( array_map( 'absint', explode( ',', $existing_gallery ) ) )
			: [];

		$files = $_FILES['photos'];
		$count = count( $files['name'] );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( $files['error'][ $i ] !== UPLOAD_ERR_OK ) continue;
			$_FILES['photo_single'] = [
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			];
			$attachment_id = media_handle_upload( 'photo_single', $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				$gallery_ids[] = $attachment_id;
			}
		}

		if ( ! empty( $gallery_ids ) ) {
			if ( ! has_post_thumbnail( $post_id ) ) {
				set_post_thumbnail( $post_id, $gallery_ids[0] );
			}
			update_post_meta( $post_id, '_mk_gallery', implode( ',', $gallery_ids ) );
		}
	}

	if ( $hashtags !== '' ) {
		$tags = array_filter( array_map( 'sanitize_text_field', explode( ',', $hashtags ) ) );
		$tags = array_map( fn( $t ) => ltrim( trim( $t ), '#' ), $tags );
		wp_set_object_terms( $post_id, $tags, 'hashtag' );
	}

	wp_send_json_success( [
		'message' => $post_id_edit > 0 ? 'Recenze byla aktualizována.' : 'Recenze byla uložena.',
		'url'     => get_permalink( $post_id ),
	] );
}

// ── Google Maps URL resolver ─────────────────────────────────────────
add_action( 'wp_ajax_mk_resolve_gmaps_url', 'mk_ajax_resolve_gmaps_url' );

function mk_ajax_resolve_gmaps_url() {
	check_ajax_referer( 'mk_resolve_gmaps', 'nonce' );

	$url = sanitize_text_field( $_POST['url'] ?? '' );
	if ( ! $url ) {
		wp_send_json_error( [ 'message' => 'Chybí URL.' ] );
	}

	$final_url = mk_follow_redirects( $url );

	// Try accurate place coordinates: !3d{lat}!4d{lng}
	if ( preg_match( '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $final_url, $m ) ) {
		wp_send_json_success( [ 'lat' => (float) $m[1], 'lng' => (float) $m[2], 'accurate' => true ] );
	}

	// Fallback: viewport center @lat,lng
	if ( preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $final_url, $m ) ) {
		wp_send_json_success( [ 'lat' => (float) $m[1], 'lng' => (float) $m[2], 'accurate' => false ] );
	}

	wp_send_json_error( [ 'message' => 'Souřadnice nenalezeny v URL.' ] );
}

function mk_follow_redirects( $url, $max = 5 ) {
	// Use curl if available — gives us CURLINFO_EFFECTIVE_URL after all redirects
	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init();
		curl_setopt_array( $ch, [
			CURLOPT_URL            => $url,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => $max,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_NOBODY         => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WordPress)',
			CURLOPT_SSL_VERIFYPEER => true,
		] );
		curl_exec( $ch );
		$final = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
		curl_close( $ch );
		return $final ?: $url;
	}

	// Fallback: manual WP HTTP redirect chain
	for ( $i = 0; $i < $max; $i++ ) {
		$r = wp_remote_head( $url, [ 'redirection' => 0, 'timeout' => 8 ] );
		if ( is_wp_error( $r ) ) break;
		$code = wp_remote_retrieve_response_code( $r );
		if ( $code < 300 || $code >= 400 ) break;
		$loc = wp_remote_retrieve_header( $r, 'location' );
		if ( ! $loc ) break;
		if ( strpos( $loc, 'http' ) !== 0 ) {
			$p   = parse_url( $url );
			$loc = $p['scheme'] . '://' . $p['host'] . $loc;
		}
		$url = $loc;
	}
	return $url;
}

// ── Hashtag autocomplete ─────────────────────────────────────────────
add_action( 'wp_ajax_mk_get_hashtags',        'mk_ajax_get_hashtags' );
add_action( 'wp_ajax_nopriv_mk_get_hashtags', 'mk_ajax_get_hashtags' );

function mk_ajax_get_hashtags() {
	$terms = get_terms( [ 'taxonomy' => 'hashtag', 'hide_empty' => false, 'fields' => 'names' ] );
	wp_send_json_success( is_wp_error( $terms ) ? [] : $terms );
}
