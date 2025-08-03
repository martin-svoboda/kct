<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

get_header();
?>
	<div class="container pt-0">
		<main id="primary" class="site-main">

			<?php
			if ( get_query_var( 'db_id' ) ) {
				get_template_part( 'template-parts/content', 'akce' );
			} elseif ( is_404() ) {
				get_template_part( 'template-parts/content', '404' );
			} else {

				if ( have_posts() ) :

					if ( ( is_home() && ! is_front_page() ) || is_archive() ) :
						$current_slug = '';
						$current_cat = null;
						$parent_cat = null;

						if ( is_category() ) {
							$current_cat  = get_queried_object();
							$current_slug = $current_cat->slug;

							// Pokud je aktuální kategorie podřazená, najdeme její rodiče
							if ( $current_cat->parent > 0 ) {
								$parent_cat = get_category( $current_cat->parent );
							}
						}

						$title = get_the_title( get_option( 'page_for_posts', true ) ) ?? 'Všechny články';
						if ( $parent_cat ) {
							$title = $parent_cat->name . ' / ' . $current_cat->name;
						} elseif ( $current_cat ) {
							$title = $current_cat->name;
						}
						?>
						<header>
							<h1 class="entry-title"><?php echo $title; ?></h1>

							<?php
							$top_categories = get_categories( array(
								'parent'     => 0,
								'hide_empty' => false
							) );

							if ( $top_categories && count( $top_categories ) > 0 ) {
								/** @var \WP_Query $wp_query */
								global $wp_query;


								// Určíme, která top kategorie je aktivní
								$active_top_cat = null;
								if ( $parent_cat ) {
									$active_top_cat = $parent_cat;
								} elseif ( $current_cat && $current_cat->parent == 0 ) {
									$active_top_cat = $current_cat;
								}
								?>
								<div class="category-menu">
									<a href="<?php echo get_permalink( get_option( 'page_for_posts', true ) ) ?>"
									   class="category-menu__item shadow<?php echo ! $current_slug ? ' active' : '' ?>"
									>Vše</a>
									<?php
									/** @var \WP_Term $category */
									foreach ( $top_categories as $category ) {
										$is_active = ( $active_top_cat && $active_top_cat->term_id == $category->term_id );
										?>
										<a href="<?php echo get_term_link( $category ) ?>"
										   class="category-menu__item shadow<?php echo $is_active ? ' active' : '' ?>"
										><?php echo $category->name ?></a>
										<?php
									}
									?>
								</div>

								<?php
								// Zobrazit podkategorie pouze pokud je nějaká top kategorie aktivní
								if ( $active_top_cat ) {
									$child_categories = get_categories( array(
										'parent'     => $active_top_cat->term_id,
										'hide_empty' => false
									) );

									if ( $child_categories && count( $child_categories ) > 0 ) {
										?>
										<div class="category-menu category-menu--sub">
											<?php
											foreach ( $child_categories as $child_category ) {
												$is_active = ( $current_slug === $child_category->slug );
												?>
												<a href="<?php echo get_term_link( $child_category ) ?>"
												   class="category-menu__item shadow<?php echo $is_active ? ' active' : '' ?>"
												><?php echo $child_category->name ?></a>
												<?php
											}
											?>
										</div>
										<?php
									}
								}
								?>
							<?php } ?>
						</header>
					<?php
					endif;
					?>
					<div class="articles_grid"> <?php
					/* Start the Loop */
					while ( have_posts() ) :
						the_post();

						/*
						 * Include the Post-Type-specific template for the content.
						 * If you want to override this in a child theme, then include a file
						 * called content-___.php (where ___ is the Post Type name) and that will be used instead.
						 */
						get_template_part( 'template-parts/content-boxed', get_post_type() );

					endwhile;
					?>
					</div><?php
					the_posts_navigation();

				else :

					get_template_part( 'template-parts/content', 'none' );

				endif;
			}
			?>

		</main><!-- #main -->
	</div>
<?php
get_footer();
