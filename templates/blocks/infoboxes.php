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
	<div class="infoboxes__inner">
	<?php foreach ( $boxes as $box ) :
		$image = $box['image'] ?? 0;
		$title = $box['title'] ?? '';
		$text  = $box['text'] ?? '';
		$link  = $box['link'] ?? array();
		?>
		<<?= ! empty( $link['url'] ) ? 'a href="' . esc_url( $link['url'] ) . '" target="' . esc_attr( $link['target'] ?? '' ) . '"' : 'div' ?> class="cart shadow">
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
		</<?= ! empty( $link['url'] ) ? 'a' : 'div' ?>>
	<?php endforeach; ?>
	</div>
</div>
