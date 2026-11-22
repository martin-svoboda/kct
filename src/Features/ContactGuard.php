<?php

namespace Kct\Features;

/**
 * Ochrana zveřejněných kontaktů před automatickým sběrem.
 *
 * E-mail ani telefon nejde do HTML čitelně. Místo odkazu se vypíše prvek se
 * zakódovanou hodnotou a skript v prohlížeči z něj po načtení stránky udělá
 * skutečný `mailto:`/`tel:` odkaz s plným textem. Kódování umí
 * {@see ContactScanner}, které zároveň hledá kontakty v cizím textu.
 *
 * Ochrana **nemá nastavení**. Je to bezpečnostní opatření, ne volba vzhledu;
 * správce webu nemá co rozhodovat o tom, jestli se adresy vydají robotům.
 *
 * Projde stránkovou cache, protože výstup je hotová část uloženého HTML a nic
 * se nedoptává serveru. Žádné nonce ani per-request token — na tom se už
 * jednou rozbilo volání REST API pod cache (viz `assets/apps/events-archive.js`).
 *
 * Čeho to nedosáhne: sběrače, který stránku vyhodnotí jako prohlížeč, tím
 * nezastavíme — ten dostane dešifrovaný odkaz stejně jako člověk. A čitelná
 * záloha v `<noscript>` je ve zdroji pro každého, byť rozdělená tak, aby na ni
 * obecný výraz nesedl. Ochrana zdražuje sběr, neznemožňuje ho.
 */
final class ContactGuard {
	public function __construct() {
		// Pozdní priorita: obsah už je po `wpautop` a shortcodech, takže se
		// prochází text, který se opravdu vypíše.
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_content' ), 20 );

		// Blokové widgety (patička bývá na každé stránce) i jednotlivé bloky
		// v obsahu. Co projde dvakrát, se podruhé přeskočí — chráněný prvek
		// scanner pozná.
		add_filter( 'render_block', array( $this, 'filter_block' ), 20, 2 );

		// Klasické textové widgety na webech, které bloky nepoužívají.
		add_filter( 'widget_text', array( $this, 'filter_content' ), 20 );
	}

	/**
	 * Chráněný zápis e-mailu.
	 *
	 * @param string $email Adresa.
	 * @param array  $args  `class` přidá třídy prvku, `label` nahradí text odkazu.
	 */
	public function email( string $email, array $args = array() ): string {
		$email = trim( $email );

		if ( '' === $email || ! str_contains( $email, '@' ) ) {
			return '';
		}

		if ( $this->is_plain_text_output() ) {
			return $this->plain( ContactScanner::split_email( $email ) );
		}

		return $this->markup( 'data-kct-email', $email, ContactScanner::split_email( $email ), $args );
	}

	/**
	 * Chráněný zápis telefonu.
	 *
	 * @param string $phone Číslo v podobě, v jaké se má zobrazit.
	 * @param array  $args  `class` přidá třídy prvku, `label` nahradí text odkazu.
	 */
	public function phone( string $phone, array $args = array() ): string {
		$phone = trim( $phone );

		if ( '' === $phone ) {
			return '';
		}

		if ( $this->is_plain_text_output() ) {
			return $this->plain( ContactScanner::split_phone( $phone ) );
		}

		return $this->markup( 'data-kct-tel', $phone, ContactScanner::split_phone( $phone ), $args );
	}

	/**
	 * Projde text a kontakty v něm vymění za chráněný zápis.
	 *
	 * @param mixed $content Obsah z filtru; cokoli jiného než text se vrací beze změny.
	 */
	public function filter_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( is_admin() ) {
			return $content;
		}

		return ContactScanner::protect(
			$content,
			fn( string $email ): string => $this->email( $email ),
			fn( string $phone ): string => $this->phone( $phone )
		);
	}

	/**
	 * Totéž pro jeden vykreslený blok.
	 *
	 * @param mixed $content Vykreslený blok.
	 * @param mixed $block   Rozebraný blok.
	 */
	public function filter_block( $content, $block = null ) {
		return $this->filter_content( $content );
	}

	/**
	 * Sestaví prvek se zakódovanou hodnotou a čitelnou zálohou pro prohlížeč
	 * bez skriptů.
	 *
	 * Záloha je uvnitř `<noscript>` a slepená prázdnými komentáři: člověk
	 * uvidí správný údaj, ale v surovém zdroji nestojí `@` vedle domény ani
	 * devět číslic za sebou, takže obecný výraz nic nenajde.
	 *
	 * @param string   $attribute Atribut s hodnotou (`data-kct-email`, `data-kct-tel`).
	 * @param string   $value     Plná hodnota, která se zakóduje.
	 * @param string[] $parts     Části pro čitelnou zálohu.
	 * @param array    $args      `class`, `label`.
	 */
	private function markup( string $attribute, string $value, array $parts, array $args ): string {
		$classes = trim( 'kct-contact ' . (string) ( $args['class'] ?? '' ) );
		$label   = isset( $args['label'] ) ? (string) $args['label'] : '';

		return sprintf(
			'<span class="%s" %s="%s"%s><noscript>%s</noscript></span>',
			esc_attr( $classes ),
			esc_attr( $attribute ),
			esc_attr( ContactScanner::encode( $value ) ),
			'' !== $label ? ' data-kct-label="' . esc_attr( $label ) . '"' : '',
			$this->readable( $parts )
		);
	}

	/**
	 * Čitelná záloha: části slepené prázdným komentářem, zavináč entitou.
	 *
	 * @param string[] $parts
	 */
	private function readable( array $parts ): string {
		$pieces = array_map(
			static function ( string $part ): string {
				if ( '@' === $part ) {
					return '&#64;';
				}

				if ( ' ' === $part ) {
					return '&nbsp;';
				}

				return esc_html( $part );
			},
			$parts
		);

		return implode( '<!---->', $pieces );
	}

	/**
	 * Údaj pro výstup, do kterého se skript nedostane (RSS).
	 *
	 * Tam chráněný prvek nemá kdo rozebrat, takže by čtenář dostal prázdno.
	 * Zbývá čitelná podoba — ta kanálu neublíží a kontakt v něm zůstane.
	 *
	 * @param string[] $parts
	 */
	private function plain( array $parts ): string {
		$pieces = array_map(
			static fn( string $part ): string => '@' === $part ? ' (zavinac) ' : $part,
			$parts
		);

		return esc_html( trim( implode( '', $pieces ) ) );
	}

	/** Jde výstup někam, kde skript nepoběží? */
	private function is_plain_text_output(): bool {
		return is_feed();
	}
}
