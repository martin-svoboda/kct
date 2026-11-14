<?php

namespace Kct\Turinka;

use Kct\Repositories\SettingsRepository;

/**
 * Propojení akce s Turinkou.
 *
 * Nad společným stavem (LinkState) má akce jednu věc navíc: volbu redakce,
 * jestli a jak se má posílat. Je to běžné pole akce, proto bez předpony —
 * stejně jako db_id, year nebo place ve stejném metaboxu.
 */
class EventState extends LinkState {
	/** Volba redakce: posílat, a jak. */
	const META_STATE = 'turinka_state';

	const STATE_OFF      = 'off';
	const STATE_PUBLIC   = 'public';
	const STATE_UNLISTED = 'unlisted';

	public function __construct( private SettingsRepository $settings ) {
	}

	/**
	 * Přípustné volby a jejich popis pro administraci.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		return array(
			self::STATE_OFF      => __( 'Neposílat', 'kct' ),
			self::STATE_PUBLIC   => __( 'Veřejně', 'kct' ),
			self::STATE_UNLISTED => __( 'Jen na odkaz (mimo výpisy)', 'kct' ),
		);
	}

	/**
	 * Co si redakce u téhle akce přeje.
	 *
	 * Bez uložené hodnoty platí výchozí stav z nastavení webu — akce založené
	 * dřív, než se web propojil, se tak řídí tím, co správce nastavil, a nemusí
	 * se procházet jedna po druhé.
	 */
	public function desired( int $post_id ): string {
		$value = (string) get_post_meta( $post_id, self::META_STATE, true );

		if ( ! isset( self::choices()[ $value ] ) ) {
			$value = (string) $this->settings->get_option( 'turinka_default_state' );
		}

		return isset( self::choices()[ $value ] ) ? $value : self::STATE_OFF;
	}
}
