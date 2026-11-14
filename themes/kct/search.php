<?php
/**
 * The template for displaying search results pages
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package kct
 */

get_header();
?>
	<div class="container">
		<main id="primary" class="site-main">

			<?php if ( have_posts() ) : ?>

				<header class="page-header">
					<?php get_template_part( 'template-parts/breadcrumbs', null, array( 'muted' => true ) ); ?>
					<h1 class="page-title">
						<?php
						/* translators: %s: search query. */
						printf( esc_html__( 'Výsledky hledání: %s', 'kct' ), '<span>' . get_search_query() . '</span>' );
						?>
					</h1>
					<?php
					// Počet výsledků i formulář patří NAD výpis. Dosud tu nebyl ani
					// jeden: kdo hledal špatné slovo, musel se vracet zpátky, a
					// nikde nestálo, jestli je výsledek jeden nebo sto.
					?>
					<p class="search-count"><?php echo esc_html( kct_search_found_label( (int) $wp_query->found_posts ) ); ?></p>
					<div class="search-again"><?php get_search_form(); ?></div>
				</header><!-- .page-header -->

				<?php
				/* Start the Loop */
				while ( have_posts() ) :
					the_post();

					/**
					 * Run the loop for the search to output the results.
					 * If you want to overload this in a child theme then include a file
					 * called content-search.php and that will be used instead.
					 */
					get_template_part( 'template-parts/content', 'search' );

				endwhile;

				the_posts_pagination( array(
					'mid_size'           => 2,
					'prev_text'          => '<span class="nav-arrow" aria-hidden="true">&lsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Předchozí', 'kct' ) . '</span>',
					'next_text'          => '<span class="nav-arrow" aria-hidden="true">&rsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Další', 'kct' ) . '</span>',
					'screen_reader_text' => esc_html__( 'Navigace v příspěvcích', 'kct' ),
				) );

			else :

				get_template_part( 'template-parts/content', 'none' );

			endif;
			?>

		</main><!-- #main -->

		<?php get_sidebar(); ?>
	</div>
<?php
get_footer();
