<?php

use Kct\Repositories\DbEventRepository;

$book_repository = kct_container()->get( DbEventRepository::class );
$book            = $book_repository->get( get_post() );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<h3>
		<a href="<?= esc_attr( get_permalink() ) ?>">
			<?= $book->title ?>
		</a>
	</h3>
	<dl>
		<dt><?= __( 'Author:', 'kct' ) ?></dt>
		<dd><?= $book->author_name ?></dd>
	</dl>
</article>
