<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments
 */

$background = $args['background'];
$title      = $args['title'];
$text       = $args['text'];
$link       = $args['link'];

?>
<div class="block-cover full-width" <?php if ( $background ) { ?> style="background-image: url('<?= wp_get_attachment_url( $background ) ?>')" <?php } ?>>
	<div class="container">
		<div class="content">
			<?php if ( ! empty( $title ) ): ?>
				<h2><?php echo $title; ?></h2>
			<?php endif; ?>
			<?php if ( ! empty( $text ) ): ?>
				<p><?php echo $text; ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $link ) ): ?>
				<a class="button" href="<?= $link['url'] ?>" target="<?= $link['target'] ?>"
				   title="<?= $link['label'] ?>"><?= $link['label'] ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
