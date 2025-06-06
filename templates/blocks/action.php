<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments
 */

$background     = $args['background'];
$image          = $args['image'];
$image_position = $args['image_position'] ?: 'right';
$title          = $args['title'];
$text           = $args['text'];
$link           = $args['link'];
$gradient       = $args['gradient'] ?? true;

?>
<div
	class="kct-block block-action full-width <?= $gradient ? 'gradient' : '' ?>" <?php if ( $background ) { ?> style="background-image: url('<?= wp_get_attachment_url( $background ) ?>')" <?php } ?>>
	<div class="container <?= $image_position ?>">
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
		<?php if ( ! empty( $image ) ) {
			echo wp_get_attachment_image( $image, 'large' );
		} ?>
	</div>
</div>
