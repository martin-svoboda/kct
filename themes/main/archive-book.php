<?php
get_header();
?>
	<h1><?php _e( 'Here are all the books we have', 'kct' ); ?></h1>
<?php
while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content', get_post_type() );
}

get_footer();
