<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments – jedna karta.
 */

$image = $args['image'] ?? 0;
$title = $args['title'] ?? '';
$text  = $args['text'] ?? '';
$link  = $args['link'] ?? array();
$color = $args['color'] ?? '';

$has_link = ! empty( $link['url'] );
$tag      = $has_link ? 'a' : 'div';

?>
<<?= $tag ?> class="cart shadow"<?php if ( $has_link ) : ?> href="<?= esc_url( $link['url'] ) ?>" target="<?= esc_attr( $link['target'] ?? '' ) ?>" title="<?= esc_attr( $link['label'] ?? '' ) ?>"<?php endif; ?><?= $color ? ' style="--cart-accent: var(' . esc_attr( $color ) . ')"' : '' ?>>
	<?php if ( ! empty( $image ) ) {
		echo wp_get_attachment_image( $image, 'medium' );
	} ?>
	<div class="content">
		<?php if ( ! empty( $title ) ) : ?>
			<h3><?php echo $title; ?></h3>
		<?php endif; ?>
		<?php if ( ! empty( $text ) ) : ?>
			<p><?php echo $text; ?></p>
		<?php endif; ?>
	</div>
</<?= $tag ?>>
