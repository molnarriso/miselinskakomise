<?php
defined( 'ABSPATH' ) || exit;

add_shortcode( 'miselinska_formular', 'mk_shortcode_form' );

function mk_shortcode_form() {
	if ( ! is_user_logged_in() ) {
		return '<p class="mk-login-notice">Pro přidání recenze se musíte <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">přihlásit</a>.</p>';
	}

	// ── Edit mode ────────────────────────────────────────────────────
	$edit_id   = 0;
	$edit_post = null;
	$prefill   = [];

	if ( ! empty( $_GET['edit'] ) ) {
		$eid = absint( $_GET['edit'] );
		$ep  = get_post( $eid );
		if ( $ep && $ep->post_type === 'recenze' && current_user_can( 'edit_post', $eid ) ) {
			$edit_id   = $eid;
			$edit_post = $ep;

			$hashtag_terms = get_the_terms( $eid, 'hashtag' );
			$hashtags_str  = '';
			if ( $hashtag_terms && ! is_wp_error( $hashtag_terms ) ) {
				$hashtags_str = implode( ', ', array_map( fn( $t ) => '#' . $t->name, $hashtag_terms ) );
			}

			$prefill = [
				'restaurant' => get_post_meta( $eid, '_mk_restaurant_name', true ) ?: $ep->post_title,
				'rating'     => get_post_meta( $eid, '_mk_rating',          true ),
				'visit_date' => get_post_meta( $eid, '_mk_visit_date',      true ),
				'maps_url'   => get_post_meta( $eid, '_mk_google_maps_url', true ),
				'lat'        => get_post_meta( $eid, '_mk_latitude',        true ),
				'lng'        => get_post_meta( $eid, '_mk_longitude',       true ),
				'address'    => get_post_meta( $eid, '_mk_address',         true ),
				'hashtags'   => $hashtags_str,
				'body'       => $ep->post_content,
			];
		}
	}

	$is_edit   = $edit_id > 0;
	$btn_label = $is_edit ? 'Uložit změny' : 'Odeslat recenzi';

	wp_enqueue_script( 'mk-form', MK_URL . 'assets/js/form.js', [ 'jquery' ], '1.0.4', true );
	wp_localize_script( 'mk-form', 'mkFormData', [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'mk_submit_review' ),
		'resolveNonce' => wp_create_nonce( 'mk_resolve_gmaps' ),
		'hashtagsUrl'  => admin_url( 'admin-ajax.php' ) . '?action=mk_get_hashtags',
		'editId'       => $edit_id,
		'submitText'   => $btn_label,
	] );

	$v = fn( $key ) => $is_edit ? ' value="' . esc_attr( $prefill[ $key ] ?? '' ) . '"' : '';

	ob_start();
	?>
	<form id="mk-review-form" class="mk-form" enctype="multipart/form-data" novalidate>

		<?php if ( $is_edit ) : ?>
		<input type="hidden" name="post_id" value="<?php echo $edit_id; ?>">
		<?php endif; ?>

		<div class="mk-form-group">
			<label for="mk_f_restaurant">Název restaurace *</label>
			<input type="text" id="mk_f_restaurant" name="restaurant_name" required placeholder="Název restaurace"<?php echo $v( 'restaurant' ); ?>>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_rating">Hodnocení (0–10) *</label>
			<input type="number" id="mk_f_rating" name="rating" min="0" max="10" step="0.1" required placeholder="7.5"<?php echo $v( 'rating' ); ?>>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_visit_date">Datum návštěvy</label>
			<input type="date" id="mk_f_visit_date" name="visit_date"<?php echo $v( 'visit_date' ); ?>>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_maps">Google Maps odkaz</label>
			<input type="url" id="mk_f_maps" name="google_maps_url" placeholder="https://maps.app.goo.gl/... nebo dlouhá URL z Google Maps"<?php echo $v( 'maps_url' ); ?>>
			<small>Vložte odkaz z Google Maps — GPS souřadnice se vyplní automaticky.</small>
			<div id="mk_f_gps_status" class="mk-gps-status"></div>
		</div>

		<div class="mk-form-group mk-form-row">
			<div>
				<label for="mk_f_lat">Zeměpisná šířka</label>
				<input type="number" id="mk_f_lat" name="latitude" step="0.000001" placeholder="50.075538" readonly<?php echo $v( 'lat' ); ?>>
			</div>
			<div>
				<label for="mk_f_lng">Zeměpisná délka</label>
				<input type="number" id="mk_f_lng" name="longitude" step="0.000001" placeholder="14.437800" readonly<?php echo $v( 'lng' ); ?>>
			</div>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_address">Adresa</label>
			<input type="text" id="mk_f_address" name="address" placeholder="Václavské náměstí 1, Praha"<?php echo $v( 'address' ); ?>>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_hashtags">Hashtagy</label>
			<input type="text" id="mk_f_hashtags" name="hashtags" placeholder="#pizza, #brno, #vegan"<?php echo $v( 'hashtags' ); ?>>
			<small>Oddělujte čárkami. Znak # je nepovinný.</small>
			<div id="mk_hashtag_suggestions" class="mk-autocomplete"></div>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_photos">Fotky</label>
			<input type="file" id="mk_f_photos" name="photos[]" multiple accept="image/*">
			<small><?php echo $is_edit ? 'Nové fotky budou přidány ke stávajícím.' : 'První fotka bude použita jako náhled. Formáty: JPG, PNG, WEBP.'; ?></small>
		</div>

		<div class="mk-form-group">
			<label for="mk_f_body">Text recenze</label>
			<textarea id="mk_f_body" name="review_body" rows="8" placeholder="Popište vaši zkušenost..."><?php echo $is_edit ? esc_textarea( $prefill['body'] ?? '' ) : ''; ?></textarea>
		</div>

		<div id="mk_form_message" class="mk-form-message" style="display:none;"></div>

		<button type="submit" class="mk-btn mk-btn-primary" id="mk_submit_btn"><?php echo esc_html( $btn_label ); ?></button>
	</form>
	<?php
	return ob_get_clean();
}
