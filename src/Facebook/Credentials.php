<?php

namespace Kct\Facebook;

use Kct\Repositories\SettingsRepository;

/**
 * Čte konfiguraci sdílení na Facebook.
 *
 * Konstanty ve wp-config.php mají přednost před hodnotami v nastavení, aby šel
 * token držet mimo databázi a mimo zálohy.
 */
class Credentials {
	public function __construct( private SettingsRepository $settings ) {
	}

	/**
	 * ID Facebook stránky, na kterou se odesílají příspěvky.
	 */
	public function page_id(): string {
		if ( defined( 'KCT_FB_PAGE_ID' ) ) {
			return (string) KCT_FB_PAGE_ID;
		}

		return (string) $this->scalar_option( 'fb_page_id' );
	}

	/**
	 * Page access token pro Graph API.
	 *
	 * Vrací tajemství — nikdy ho nelogovat ani nikam nevypisovat (chybové
	 * hlášky, debug výstupy, WP-CLI apod.).
	 */
	public function token(): string {
		if ( defined( 'KCT_FB_PAGE_TOKEN' ) ) {
			return (string) KCT_FB_PAGE_TOKEN;
		}

		return (string) $this->scalar_option( 'fb_page_token' );
	}

	/**
	 * Jsou vyplněné obě hodnoty potřebné k odeslání na Facebook?
	 */
	public function is_configured(): bool {
		return '' !== $this->page_id() && '' !== $this->token();
	}

	/**
	 * Výchozí stav přepínače "Sdílet na Facebook" u nového příspěvku.
	 */
	public function share_by_default(): bool {
		return (bool) $this->settings->get_option( 'fb_share_default' );
	}

	/**
	 * ID přílohy s výchozím OG obrázkem.
	 */
	public function default_image_id(): int {
		return (int) $this->scalar_option( 'fb_default_image' );
	}

	/**
	 * Je hodnota daná konstantou ve wp-config.php?
	 *
	 * Testuje se jen defined() — prázdná konstanta je legitimní způsob, jak
	 * sdílení vypnout, a nemá tiše spadnout zpátky na hodnotu z nastavení.
	 */
	public function is_from_constant( string $field ): bool {
		return match ( $field ) {
			'fb_page_id'    => defined( 'KCT_FB_PAGE_ID' ),
			'fb_page_token' => defined( 'KCT_FB_PAGE_TOKEN' ),
			default         => false,
		};
	}

	/**
	 * Hodnota nastavení, jen pokud je skalární.
	 *
	 * Sanitizace v knihovně wpify/custom-fields je řízená seznamem `items` —
	 * klíč, který v `items` chybí (přesně případ pole přebitého konstantou),
	 * se uloží syrový z POSTu, klidně jako pole. `(string) $array` by pak
	 * vyhodilo "Array to string conversion".
	 */
	private function scalar_option( string $key ): string|int|float|bool|null {
		$value = $this->settings->get_option( $key );

		return is_scalar( $value ) ? $value : null;
	}
}
