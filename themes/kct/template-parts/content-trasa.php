<?php
/**
 * Detail trasy.
 *
 * Stavba je stejná jako u detailu akce a odboru: hlavička přes celou šířku
 * s náhledovým obrázkem na pozadí, pod ní dva sloupce — obsah a postranní
 * oddíl — a pod článkem navigace a další trasy.
 *
 * Widget Turinky je rozdělený na tři rámy: nad textem mapa se stopou, v
 * postranním oddílu údaje o trase (tam, kde má akce svoje údaje) a pod textem
 * výškový profil s tlačítky aplikace. Mezi nimi stojí to, co vlastní web:
 * text článku.
 *
 * Nepropojená trasa vypíše jen článek. Je to normální stav rozepsané trasy,
 * ne chyba, kterou by měl návštěvník vidět.
 *
 * @package kct
 */

$kct_post_id = (int) get_the_ID();
$kct_title   = (string) get_the_title();
$kct_embed   = kct_container()->get( \Kct\Turinka\RouteEmbed::class );
$kct_map     = $kct_embed->map( $kct_post_id, $kct_title );
$kct_facts   = $kct_embed->facts( $kct_post_id, $kct_title );
$kct_details = $kct_embed->details( $kct_post_id, $kct_title );
$kct_image   = has_post_thumbnail() ? (string) get_the_post_thumbnail_url( null, 'full' ) : '';
?>

<article id="post-<?php echo esc_attr( (string) $kct_post_id ); ?>" class="route-post">
	<header class="entry-header full-width <?php echo $kct_image ? 'large' : ''; ?>"
		<?php if ( $kct_image ) : ?>style="background-image: url('<?php echo esc_url( $kct_image ); ?>')"<?php endif; ?>>
		<div class="container">
			<?php
			// Tmavá varianta jen s fotkou (.large má vlastní color:#fff) —
			// bez fotky je pozadí var(--line), tam musí být muted (viz
			// components/breadcrumbs.scss pro výpočet kontrastu).
			get_template_part( 'template-parts/breadcrumbs', null, array( 'muted' => ! $kct_image ) );
			?>
			<h1 class="entry-title"><?php the_title(); ?></h1>
		</div>
	</header><!-- .entry-header -->

	<div class="route-content-wrap">
		<div class="entry-content">
			<?php
			// Mapa nad textem: kvůli ní návštěvník na trasu přišel.
			echo $kct_map; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapováno v RouteEmbed.

			the_content();

			// Pod textem výškový profil a tlačítka aplikace (oblíbit, sdílet,
			// stáhnout GPX) a odkaz na trasu v Turince.
			echo $kct_details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapováno v RouteEmbed.

			get_template_part( 'template-parts/post-share' );
			?>
		</div><!-- .entry-content -->

		<div class="route-sidebar">
			<?php
			// Údaje o trase — délka, převýšení, odhad času, obtížnost. Drží je
			// aplikace, proto je to rám, a ne výpis z polí příspěvku.
			echo $kct_facts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapováno v RouteEmbed.

			get_template_part( 'template-parts/route-others' );
			?>
		</div>
	</div>
</article><!-- #post-<?php echo esc_attr( (string) $kct_post_id ); ?> -->

<?php
get_template_part( 'template-parts/route-nav' );
get_template_part( 'template-parts/route-related' );
