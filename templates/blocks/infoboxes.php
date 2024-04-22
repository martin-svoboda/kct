<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments
 */

$boxes = $args['boxes'];


?>
<div class="kct-block infoboxes">
	<?php foreach ( $boxes

	as $box ) :
	$image = $box['image'];
	$title = $box['title'];
	$text  = $box['text'];
	$link  = $box['link'];
	$color = $box['color'] ?? '';
	?>
	<<?= $link['url'] ? 'a href="' . $link['url'] . '" target="' . $link['target'] . '"
					   title="' . $link['label'] . '"' : 'div' ?> class="cart
	shadow" <?= $color ? 'style="background-color: var( ' . $color . ')"' : '' ?>>
	<?php if ( ! empty( $image ) ) {
		echo wp_get_attachment_image( $image, 'medium' );
	} ?>
	<div class="content">
		<?php if ( ! empty( $title ) ): ?>
			<h3><?php echo $title; ?></h3>
		<?php endif; ?>
		<?php if ( ! empty( $text ) ): ?>
			<p><?php echo $text; ?></p>
		<?php endif; ?>
	</div>
</<?= $link['url'] ? 'a' : 'div' ?>>
<?php endforeach; ?>
</div>
