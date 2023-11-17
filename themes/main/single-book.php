<?php

use Kct\Repositories\DbEventRepository;

$book_repository = kct_container()->get( DbEventRepository::class );
$book            = $book_repository->get( get_post() );

get_header();
?>
	<h1><?php echo sprintf( __( '<strong>%s</strong> <small>by %s</small>', 'kct' ), $book->title, $book->author_name ); ?></h1>
<?php
if ( has_post_thumbnail( $book->id ) ) {
	the_post_thumbnail( $book->id );
}
?>
	<dl>
		<?php if ( ! empty( $book->isbn ) ): ?>
			<dt>
				<?php _e( 'ISBN', 'kct' ); ?>
			</dt>
			<dd>
				<?= $book->isbn; ?>
			</dd>
		<?php endif; ?>
		<?php if ( ! empty( $book->author_name ) ): ?>
			<dt>
				<?php _e( 'Author', 'kct' ); ?>
			</dt>
			<dd>
				<?= $book->author_name; ?>
			</dd>
		<?php endif; ?>
		<?php if ( ! empty( $book->rating ) ): ?>
			<dt>
				<?php _e( 'Rating', 'kct' ); ?>
			</dt>
			<dd>
				<?= $book->rating; ?>
			</dd>
		<?php endif; ?>
		<?php if ( ! empty( $book->publisher ) ): ?>
			<dt>
				<?php _e( 'Publisher', 'kct' ); ?>
			</dt>
			<dd>
				<a href="<?= esc_attr( get_term_link( $book->publisher->id, $book->publisher->taxonomy_name ) ) ?>">
					<?= $book->publisher->name; ?>
				</a>
			</dd>
		<?php endif; ?>
	</dl>
	<div class="content">
		<h3><?php _e( 'Book description', 'kct' ); ?></h3>
		<?php echo $book->content; ?>
	</div>
<?php
get_footer();
