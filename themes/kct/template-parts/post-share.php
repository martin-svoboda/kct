<?php
/**
 * Patička článku — tagy (vlevo) + sdílení (vpravo). Bez autora.
 *
 * @package kct
 */

$kct_url   = get_permalink();
$kct_title = get_the_title();
$kct_tags  = get_the_tags();

$kct_fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $kct_url );
$kct_x  = 'https://twitter.com/intent/tweet?url=' . rawurlencode( $kct_url ) . '&text=' . rawurlencode( $kct_title );
?>
<div class="post-share">
	<div class="post-share__tags">
		<?php if ( $kct_tags ) : ?>
			<?php foreach ( $kct_tags as $kct_tag ) : ?>
				<a class="post-share__tag" href="<?php echo esc_url( get_tag_link( $kct_tag ) ); ?>"><?php echo esc_html( $kct_tag->name ); ?></a>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="post-share__actions">
		<span class="post-share__label"><?php esc_html_e( 'Sdílet', 'kct' ); ?></span>

		<a class="post-share__btn" href="<?php echo esc_url( $kct_fb ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
			<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><path d="M13.5 21v-8H16l.4-3h-2.9V8.2c0-.9.3-1.5 1.6-1.5h1.4V4.1C16.2 4 15.3 4 14.3 4c-2.1 0-3.6 1.3-3.6 3.7V10H8v3h2.7v8h2.8z"/></svg>
		</a>

		<a class="post-share__btn" href="<?php echo esc_url( $kct_x ); ?>" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)">
			<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true"><path d="M17.5 3h3l-6.6 7.5L21.7 21h-5.9l-4.6-6-5.3 6H3l7-8L2.6 3h6l4.1 5.5L17.5 3zm-1 16h1.6L8 4.6H6.3L16.5 19z"/></svg>
		</a>

		<button type="button" class="post-share__btn js-copy-link" data-url="<?php echo esc_url( $kct_url ); ?>" aria-label="<?php esc_attr_e( 'Kopírovat odkaz', 'kct' ); ?>">
			<svg class="post-share__ico-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
			<svg class="post-share__ico-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
		</button>
	</div>
</div>
<?php
edit_post_link(
	esc_html__( 'Upravit článek', 'kct' ),
	'<div class="post-edit-link">',
	'</div>'
);
