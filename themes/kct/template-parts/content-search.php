<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */


// Štítek typu obsahu: výsledky míchají příspěvky, stránky, akce i odbory a
// dosud se od sebe nijak nelišily — z nadpisu „Rozhledna Máminka" nešlo poznat,
// jestli vede na článek, nebo na akci.
$kct_type_label = kct_post_type_label();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php if ( $kct_type_label ) : ?>
			<p class="entry-kind"><?php echo esc_html( $kct_type_label ); ?></p>
		<?php endif; ?>

		<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

		<?php if ( 'post' === get_post_type() ) : ?>
		<div class="entry-meta">
			<?php
			// Jen datum. Jméno autora tu bylo z výchozí šablony a na webu odboru
			// nic neříká — příspěvky píše jedna redakce a odkaz vedl na výpis
			// autora, který web nikde nenabízí.
			kct_posted_on();
			?>
		</div><!-- .entry-meta -->
		<?php endif; ?>
	</header><!-- .entry-header -->

	<?php kct_post_thumbnail(); ?>

	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div><!-- .entry-summary -->

	<footer class="entry-footer">
		<?php kct_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-<?php the_ID(); ?> -->
