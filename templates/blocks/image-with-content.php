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
$content        = $args['content'];

?>
<div
	class="kct-block block-image-with-content full-width" <?php if ( $background ) { ?> style="background-image: url('<?= wp_get_attachment_url( $background ) ?>')" <?php } ?>>
	<div class="container <?= $image_position ?>">
		<div class="content">
			<?php echo $content ?>
		</div>
		<?php if ( ! empty( $image ) ) {
			echo wp_get_attachment_image( $image, 'large' );
		} ?>
	</div>
</div>
