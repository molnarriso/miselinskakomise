<?php
/**
 * Template: Archive Recenze
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="mk-archive">
	<header class="mk-archive-header">
		<h1 class="mk-archive-title">
			<?php
			if ( is_tax( 'hashtag' ) ) {
				echo '#' . esc_html( single_term_title( '', false ) );
			} else {
				echo 'Všechny recenze';
			}
			?>
		</h1>
	</header>

	<?php if ( have_posts() ) : ?>
	<div class="mk-feed">
		<?php while ( have_posts() ) : the_post();
			$post_id   = get_the_ID();
			$rating    = (float) get_post_meta( $post_id, '_mk_rating', true );
			$rest_name = get_post_meta( $post_id, '_mk_restaurant_name', true ) ?: get_the_title();
			$hashtags  = get_the_terms( $post_id, 'hashtag' );
			$rating_class = $rating >= 8 ? 'mk-rating-green' : ( $rating >= 5 ? 'mk-rating-yellow' : 'mk-rating-red' );
			$permalink = get_permalink();
			$thumb     = get_the_post_thumbnail( $post_id, 'medium', [ 'class' => 'mk-card-thumb' ] );

			$tag_html = '';
			if ( $hashtags && ! is_wp_error( $hashtags ) ) {
				foreach ( $hashtags as $tag ) {
					$tag_html .= '<a class="mk-tag" href="' . esc_url( get_term_link( $tag ) ) . '">#' . esc_html( $tag->name ) . '</a> ';
				}
			}
		?>
		<article <?php post_class( 'mk-card' ); ?>>
			<a href="<?php echo esc_url( $permalink ); ?>" class="mk-card-thumb-link">
				<?php if ( $thumb ) : ?>
					<?php echo $thumb; ?>
				<?php else : ?>
					<div class="mk-card-thumb mk-card-thumb-placeholder"></div>
				<?php endif; ?>
				<span class="mk-rating-badge <?php echo esc_attr( $rating_class ); ?>"><?php echo number_format( $rating, 1 ); ?></span>
			</a>
			<div class="mk-card-body">
				<div class="mk-card-header">
					<h2 class="mk-card-title">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $rest_name ); ?></a>
					</h2>
				</div>
				<p class="mk-card-meta">
					<span class="mk-author"><?php echo esc_html( get_the_author() ); ?></span>
					&middot;
					<span class="mk-date"><?php echo get_the_date( 'j. n. Y' ); ?></span>
				</p>
				<?php if ( $tag_html ) : ?>
				<p class="mk-tags"><?php echo $tag_html; ?></p>
				<?php endif; ?>
				<p class="mk-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<a class="mk-card-link" href="<?php echo esc_url( $permalink ); ?>">Detail</a>
			</div>
		</article>
		<?php endwhile; ?>
	</div>

	<div class="mk-pagination">
		<?php the_posts_pagination( [
			'mid_size'  => 2,
			'prev_text' => '← Novější',
			'next_text' => 'Starší →',
		] ); ?>
	</div>

	<?php else : ?>
	<p class="mk-no-reviews">Zatím žádné recenze.</p>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
