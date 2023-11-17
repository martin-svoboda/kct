<?php

use Kct\Models\PublisherModel;
use Kct\Repositories\PublisherRepository;

/**
 * @var PublisherRepository $publisher_repository
 * @var PublisherModel $publisher
 */

$publisher_repository = kct_container()->get( PublisherRepository::class );
$publisher            = $publisher_repository->get( get_queried_object()->term_id );

get_header();
?>
	<h1><?= $publisher->name ?></h1>
<?php
echo wp_get_attachment_image( $publisher->logo, 'full' );
?>
	<p>
		<?= $publisher->description ?>
	</p>
	<h2><?= sprintf( __( 'Books by %s', 'kct' ), $publisher->name ); ?></h2>
<?php
while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content', get_post_type() );
}

get_footer();
