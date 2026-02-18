<?php
defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes',        'mk_add_meta_boxes' );
add_action( 'save_post_recenze',     'mk_save_meta_boxes', 10, 2 );
add_action( 'admin_enqueue_scripts', 'mk_admin_enqueue_scripts_recenze' );

function mk_admin_enqueue_scripts_recenze( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'recenze' ) return;
	// Pass resolve nonce to inline admin JS
	wp_add_inline_script( 'jquery', 'var mkAdminAjax=' . wp_json_encode( [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'resolveNonce' => wp_create_nonce( 'mk_resolve_gmaps' ),
	] ) . ';', 'before' );
}

function mk_add_meta_boxes() {
	add_meta_box( 'mk_review_details', 'Detaily recenze', 'mk_render_meta_box', 'recenze', 'normal', 'high' );
}

function mk_render_meta_box( $post ) {
	wp_nonce_field( 'mk_save_meta', 'mk_meta_nonce' );

	$rating      = get_post_meta( $post->ID, '_mk_rating',          true );
	$visit_date  = get_post_meta( $post->ID, '_mk_visit_date',      true );
	$rest_name   = get_post_meta( $post->ID, '_mk_restaurant_name', true );
	$maps_url    = get_post_meta( $post->ID, '_mk_google_maps_url', true );
	$lat         = get_post_meta( $post->ID, '_mk_latitude',        true );
	$lng         = get_post_meta( $post->ID, '_mk_longitude',       true );
	$address     = get_post_meta( $post->ID, '_mk_address',         true );
	$gallery_raw = get_post_meta( $post->ID, '_mk_gallery',         true );
	$gallery_ids = $gallery_raw ? array_filter( explode( ',', $gallery_raw ) ) : [];
	?>
	<style>
		.mk-meta-table { width:100%; border-collapse:collapse; }
		.mk-meta-table td { padding:6px 4px; vertical-align:top; }
		.mk-meta-table label { font-weight:600; }
		.mk-meta-table input[type=text],
		.mk-meta-table input[type=number],
		.mk-meta-table input[type=url],
		.mk-meta-table input[type=date] { width:100%; }
		#mk_gallery_preview { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
		#mk_gallery_preview img { width:80px; height:80px; object-fit:cover; border-radius:4px; }
		.mk-gps-hint { font-size:12px; color:#666; margin-top:3px; }
		.mk-gps-ok  { color:#1a7a1a; }
		.mk-gps-err { color:#c0392b; }
	</style>

	<table class="mk-meta-table">
		<tr>
			<td style="width:180px"><label for="mk_rating">Hodnocení (0–10) *</label></td>
			<td><input type="number" id="mk_rating" name="mk_rating"
				value="<?php echo esc_attr( $rating ); ?>" min="0" max="10" step="0.1"></td>
		</tr>
		<tr>
			<td><label for="mk_visit_date">Datum návštěvy</label></td>
			<td><input type="date" id="mk_visit_date" name="mk_visit_date"
				value="<?php echo esc_attr( $visit_date ); ?>"></td>
		</tr>
		<tr>
			<td><label for="mk_restaurant_name">Název restaurace *</label></td>
			<td><input type="text" id="mk_restaurant_name" name="mk_restaurant_name"
				value="<?php echo esc_attr( $rest_name ); ?>"></td>
		</tr>
		<tr>
			<td><label for="mk_google_maps_url">Google Maps odkaz</label></td>
			<td>
				<input type="url" id="mk_google_maps_url" name="mk_google_maps_url"
					value="<?php echo esc_attr( $maps_url ); ?>"
					placeholder="https://maps.app.goo.gl/... nebo plná URL">
				<div id="mk_gps_status" class="mk-gps-hint"></div>
			</td>
		</tr>
		<tr>
			<td><label for="mk_latitude">Zeměpisná šířka</label></td>
			<td><input type="number" id="mk_latitude" name="mk_latitude"
				value="<?php echo esc_attr( $lat ); ?>" step="0.000001" placeholder="50.075538"></td>
		</tr>
		<tr>
			<td><label for="mk_longitude">Zeměpisná délka</label></td>
			<td><input type="number" id="mk_longitude" name="mk_longitude"
				value="<?php echo esc_attr( $lng ); ?>" step="0.000001" placeholder="14.437800"></td>
		</tr>
		<tr>
			<td><label for="mk_address">Adresa</label></td>
			<td><input type="text" id="mk_address" name="mk_address"
				value="<?php echo esc_attr( $address ); ?>" placeholder="Václavské náměstí 1, Praha"></td>
		</tr>
		<tr>
			<td><label>Galerie fotek</label></td>
			<td>
				<input type="hidden" id="mk_gallery" name="mk_gallery"
					value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
				<button type="button" class="button" id="mk_gallery_btn">Přidat fotky</button>
				<div id="mk_gallery_preview">
					<?php foreach ( $gallery_ids as $gid ) :
						$src = wp_get_attachment_image_url( (int) $gid, 'thumbnail' );
						if ( $src ) : ?>
							<img src="<?php echo esc_url( $src ); ?>" alt="">
						<?php endif; endforeach; ?>
				</div>
			</td>
		</tr>
	</table>

	<script>
	jQuery(function($){
		function parseCoordsFromUrl(url) {
			var m = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
			if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]), accurate: true };
			m = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
			if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]), accurate: false };
			return null;
		}
		function isShortUrl(url) {
			return /maps\.app\.goo\.gl|goo\.gl\/maps/i.test(url);
		}
		function fillCoords(coords) {
			$('#mk_latitude').val(coords.lat.toFixed(6));
			$('#mk_longitude').val(coords.lng.toFixed(6));
			var label = 'GPS: ' + coords.lat.toFixed(5) + ', ' + coords.lng.toFixed(5);
			if (!coords.accurate) label += ' (přibližně)';
			$('#mk_gps_status').text(label).removeClass('mk-gps-err').addClass('mk-gps-ok');
		}

		$('#mk_google_maps_url').on('change blur', function(){
			var url = $(this).val().trim();
			var $s  = $('#mk_gps_status');
			if (!url) { $s.text(''); return; }

			var coords = parseCoordsFromUrl(url);
			if (coords) { fillCoords(coords); return; }

			if (isShortUrl(url)) {
				$s.text('Zjišťuji souřadnice…').removeClass('mk-gps-ok mk-gps-err');
				$.post(mkAdminAjax.ajaxUrl, {
					action: 'mk_resolve_gmaps_url',
					nonce:  mkAdminAjax.resolveNonce,
					url:    url
				}, function(res){
					if (res.success) {
						fillCoords(res.data);
					} else {
						$s.text('Nelze zjistit souřadnice.').removeClass('mk-gps-ok').addClass('mk-gps-err');
					}
				});
				return;
			}
			$s.text('Souřadnice nenalezeny v URL.').removeClass('mk-gps-ok').addClass('mk-gps-err');
		});

		// Gallery media uploader
		var frame;
		$('#mk_gallery_btn').on('click', function(e){
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: 'Vyberte fotky pro galerii', button: { text: 'Přidat do galerie' }, multiple: true });
			frame.on('select', function(){
				var ids = [];
				frame.state().get('selection').each(function(a){
					ids.push(a.id);
					$('#mk_gallery_preview').append('<img src="'+(a.attributes.sizes&&a.attributes.sizes.thumbnail?a.attributes.sizes.thumbnail.url:a.attributes.url)+'">');
				});
				var existing = $('#mk_gallery').val();
				$('#mk_gallery').val(existing ? existing+','+ids.join(',') : ids.join(','));
			});
			frame.open();
		});
	});
	</script>
	<?php
}

function mk_save_meta_boxes( $post_id, $post ) {
	if ( ! isset( $_POST['mk_meta_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['mk_meta_nonce'], 'mk_save_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$fields = [
		'mk_rating'          => '_mk_rating',
		'mk_visit_date'      => '_mk_visit_date',
		'mk_restaurant_name' => '_mk_restaurant_name',
		'mk_google_maps_url' => '_mk_google_maps_url',
		'mk_latitude'        => '_mk_latitude',
		'mk_longitude'       => '_mk_longitude',
		'mk_address'         => '_mk_address',
		'mk_gallery'         => '_mk_gallery',
	];

	foreach ( $fields as $field => $meta_key ) {
		if ( ! isset( $_POST[ $field ] ) ) continue;
		$value = $_POST[ $field ];
		switch ( $meta_key ) {
			case '_mk_rating':
			case '_mk_latitude':
			case '_mk_longitude':
				$value = (float) $value; break;
			case '_mk_google_maps_url':
				$value = esc_url_raw( $value ); break;
			case '_mk_visit_date':
				$value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : ''; break;
			case '_mk_gallery':
				$ids = array_filter( array_map( 'absint', explode( ',', $value ) ) );
				$value = implode( ',', $ids ); break;
			default:
				$value = sanitize_text_field( $value );
		}
		update_post_meta( $post_id, $meta_key, $value );
	}
}
