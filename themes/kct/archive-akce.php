<?php
/**
 * The template for displaying archive pages
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

get_header();
?>
<div data-app="events"></div>
	<iframe style="border:none" src="https://frame.mapy.cz/s/johavobogo" width="100%" height="500"
			frameborder="0"></iframe>
	<div class="container">
		<main id="primary" class="site-main">
			<?php /*
			$block_attributes['count'] = 0;
			$block_attributes['time_period'] = '';
			kct_container()->get( KctDeps\Wpify\Template\WordPressTemplate::class )->print( 'blocks/events', null, $block_attributes );
			*/ ?>
		</main><!-- #main -->

		<?php // get_sidebar(); ?>
	</div>
<?php
get_footer();
