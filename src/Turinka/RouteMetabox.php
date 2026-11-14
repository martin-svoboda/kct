<?php

namespace Kct\Turinka;

use Kct\PostTypes\RoadPostType;
use WP_Post;

/**
 * Panel Turinky na obrazovce trasy — stav propojení a tlačítka.
 *
 * Staví se ručně, ne z polí redaktora: nic z něj se neukládá odesláním
 * formuláře příspěvku, je to jen stav a tlačítka. Co se po jejich stisku
 * stane, řeší skript a REST endpoint, ne tenhle soubor.
 *
 * Oddělený od Features\Routes ze stejného důvodu jako ShareMetabox od
 * FacebookShare: vykreslení je samo o sobě celá obrazovka textu a hooky
 * odesílání s ním nemají nic společného.
 */
class RouteMetabox {
	const ID = 'kct-turinka-route';

	public function __construct(
		private Config $config,
		private LinkState $state,
		private TokenStore $tokens
	) {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
	}

	public function register(): void {
		add_meta_box(
			self::ID,
			__( 'Turinka', 'kct' ),
			array( $this, 'render' ),
			RoadPostType::KEY,
			'side',
			'high'
		);
	}

	public function render( WP_Post $post ): void {
		echo $this->html( (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hodnoty se escapují při skládání.
	}

	/**
	 * HTML panelu.
	 *
	 * Vrací se jako řetězec, protože ho po uložení formuláře posílá REST
	 * endpoint k překreslení — administrace se tím neobnovuje.
	 */
	public function html( int $post_id ): string {
		if ( ! $this->tokens->has_token() ) {
			return sprintf(
				'<p>%s</p>',
				esc_html__( 'Web ještě není propojený s Turinkou. Propojení se zřizuje v KČT → Nastavení → Turinka.', 'kct' )
			);
		}

		$out   = '';
		$error = $this->state->error( $post_id );

		if ( '' !== $error ) {
			$out .= sprintf(
				'<p class="notice notice-warning" style="padding:6px 10px"><strong>%s</strong><br>%s</p>',
				esc_html__( 'Poslední odeslání se nepovedlo:', 'kct' ),
				esc_html( $error )
			);
		}

		if ( ! $this->state->is_linked( $post_id ) ) {
			$saved = ! in_array( get_post_status( $post_id ), array( 'auto-draft', false ), true );

			$out .= sprintf( '<p>%s</p>', esc_html__( 'Trasa v Turince ještě není. Stopa, obtížnost a další údaje se zadávají v jejím formuláři.', 'kct' ) );
			$out .= sprintf(
				'<p><button type="button" class="button button-primary" data-kct-turinka-route="create" data-post="%d"%s>%s</button></p>',
				$post_id,
				$saved ? '' : ' disabled',
				esc_html__( 'Založit trasu v Turince', 'kct' )
			);

			if ( ! $saved ) {
				$out .= sprintf( '<p class="description">%s</p>', esc_html__( 'Nejdřív příspěvek ulož — formulář potřebuje jeho adresu.', 'kct' ) );
			}

			return $out;
		}

		$sent = $this->state->sent_at( $post_id );
		$url  = $this->config->rebase( $this->state->url( $post_id ) );

		if ( '' !== $url ) {
			$out .= sprintf(
				'<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'Zobrazit trasu v Turince', 'kct' )
			);
		}

		$out .= sprintf(
			'<p class="description">%s</p>',
			$sent
				? esc_html( sprintf(
					/* translators: %s: datum a čas posledního odeslání. */
					__( 'Název a text odeslány %s.', 'kct' ),
					wp_date( 'j. n. Y H:i', $sent )
				) )
				: esc_html__( 'Název a text se odešlou do minuty po uložení příspěvku.', 'kct' )
		);

		$out .= sprintf(
			'<p><button type="button" class="button" data-kct-turinka-route="edit" data-post="%d">%s</button></p>',
			$post_id,
			esc_html__( 'Upravit v Turince', 'kct' )
		);

		$out .= sprintf(
			'<p><button type="button" class="button-link delete" data-kct-turinka-route="remove" data-post="%d">%s</button></p>',
			$post_id,
			esc_html__( 'Odebrat z Turinky', 'kct' )
		);

		return $out;
	}
}
