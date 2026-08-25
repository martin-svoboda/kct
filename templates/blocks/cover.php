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
<div class="kct-block block-cover full-width" <?php if ( $background ) { ?> style="background-image: url('<?= wp_get_attachment_url( $background ) ?>')" <?php } ?>>
	<div class="container">
		<div class="content block-cover__content">
			<span class="kct-tricolor block-cover__eyebrow" aria-hidden="true"><span></span><span></span><span></span></span>
			<?php if ( ! empty( $title ) ): ?>
				<h2 class="block-cover__title"><?php echo $title; ?></h2>
			<?php endif; ?>
			<?php if ( ! empty( $text ) ): ?>
				<p class="block-cover__lead"><?php echo $text; ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $link ) && ! empty( $link['url'] ) ): ?>
				<div class="block-cover__actions">
					<a class="btn btn--on-dark" href="<?= $link['url'] ?>" target="<?= $link['target'] ?>"
					   title="<?= $link['label'] ?>"><?= $link['label'] ?></a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
