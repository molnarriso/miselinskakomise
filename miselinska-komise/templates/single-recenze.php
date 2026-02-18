<?php
/**
 * Template: Single Recenze
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="mk-single-review">
<?php while ( have_posts() ) : the_post();
	$post_id    = get_the_ID();
	$rating     = (float) get_post_meta( $post_id, '_mk_rating', true );
	$visit_date = get_post_meta( $post_id, '_mk_visit_date', true );
	$rest_name  = get_post_meta( $post_id, '_mk_restaurant_name', true ) ?: get_the_title();
	$maps_url   = get_post_meta( $post_id, '_mk_google_maps_url', true );
	$lat        = (float) get_post_meta( $post_id, '_mk_latitude', true );
	$lng        = (float) get_post_meta( $post_id, '_mk_longitude', true );
	$address    = get_post_meta( $post_id, '_mk_address', true );
	$gallery_raw = get_post_meta( $post_id, '_mk_gallery', true );
	$gallery_ids = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : [];
	$hashtags   = get_the_terms( $post_id, 'hashtag' );
	$rating_class = $rating >= 8 ? 'mk-rating-green' : ( $rating >= 5 ? 'mk-rating-yellow' : 'mk-rating-red' );

	// Format visit date for display
	$visit_date_display = '';
	if ( $visit_date ) {
		$ts = strtotime( $visit_date );
		if ( $ts ) {
			$visit_date_display = date_i18n( 'j. n. Y', $ts );
		}
	}

	// Edit link → frontend form with ?edit=ID
	$edit_url = '';
	if ( current_user_can( 'edit_post', $post_id ) ) {
		$form_page  = get_page_by_path( 'nova-recenze' );
		$form_base  = $form_page ? get_permalink( $form_page->ID ) : home_url( '/nova-recenze/' );
		$edit_url   = add_query_arg( 'edit', $post_id, $form_base );
	}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'mk-review' ); ?>>

	<header class="mk-review-header">
		<h1 class="mk-review-title"><?php echo esc_html( $rest_name ); ?></h1>
		<div class="mk-review-meta-row">
			<span class="mk-rating-badge mk-rating-large <?php echo esc_attr( $rating_class ); ?>">
				<?php echo number_format( $rating, 1 ); ?> / 10
			</span>
			<span class="mk-author-date">
				<?php echo esc_html( get_the_author() ); ?> &middot; <?php echo get_the_date( 'j. n. Y' ); ?>
				<?php if ( $visit_date_display ) : ?>
					&middot; <span class="mk-visit-date">návštěva <?php echo esc_html( $visit_date_display ); ?></span>
				<?php endif; ?>
			</span>
		</div>

		<?php if ( $hashtags && ! is_wp_error( $hashtags ) ) : ?>
		<p class="mk-tags">
			<?php foreach ( $hashtags as $tag ) : ?>
				<a class="mk-tag" href="<?php echo esc_url( get_term_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
			<?php endforeach; ?>
		</p>
		<?php endif; ?>

		<?php if ( $address ) : ?>
		<p class="mk-address">📍 <?php echo esc_html( $address ); ?></p>
		<?php endif; ?>

		<?php if ( $maps_url ) : ?>
		<p class="mk-maps-link">
			<a href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener">Otevřít v Google Maps →</a>
		</p>
		<?php endif; ?>

		<?php if ( $edit_url ) : ?>
		<p class="mk-edit-link"><a href="<?php echo esc_url( $edit_url ); ?>">✏️ Upravit recenzi</a></p>
		<?php endif; ?>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
	<div class="mk-review-hero">
		<?php the_post_thumbnail( 'large', [ 'class' => 'mk-hero-img' ] ); ?>
	</div>
	<?php endif; ?>

	<div class="mk-review-content">
		<?php the_content(); ?>
	</div>

	<?php if ( ! empty( $gallery_ids ) ) : ?>
	<div class="mk-gallery">
		<h3>Fotogalerie</h3>
		<div class="mk-gallery-grid">
			<?php foreach ( $gallery_ids as $img_id ) :
				$full = wp_get_attachment_image_url( $img_id, 'large' );
				$thumb = wp_get_attachment_image( $img_id, 'medium', false, [ 'class' => 'mk-gallery-img' ] );
				if ( $thumb ) : ?>
				<a href="<?php echo esc_url( $full ); ?>" class="mk-gallery-item" target="_blank">
					<?php echo $thumb; ?>
				</a>
				<?php endif;
			endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $lat && $lng ) : ?>
	<div class="mk-review-map-section">
		<h3>Poloha</h3>
		<div id="mk-single-map" class="mk-map" style="height:300px;width:100%;"></div>
	</div>
	<?php
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );
		$inline = sprintf(
			'document.addEventListener("DOMContentLoaded",function(){
				var m=L.map("mk-single-map").setView([%f,%f],15);
				L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"&copy; OpenStreetMap contributors"}).addTo(m);
				L.marker([%f,%f]).addTo(m).bindPopup(%s).openPopup();
			});',
			$lat, $lng, $lat, $lng,
			wp_json_encode( '<strong>' . esc_html( $rest_name ) . '</strong>' )
		);
		wp_add_inline_script( 'leaflet', $inline );
	?>
	<?php endif; ?>

</article>

<?php endwhile; ?>
</main>
<?php get_footer(); ?>
