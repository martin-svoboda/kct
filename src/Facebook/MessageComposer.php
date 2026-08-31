<?php

namespace Kct\Facebook;

use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PostPostType;
use WP_Post;

/**
 * Skládá text a odkaz příspěvku na Facebook.
 *
 * Čistá třída — žádné HTTP, žádný zápis do databáze.
 */
class MessageComposer {
	/**
	 * Max. délka perexu běžné aktuality a akce ve znacích.
	 *
	 * Redakční limit, ne technický strop — Facebook zobrazí v náhledu jen
	 * první pár řádků, delší text čtenář stejně nedočte bez rozkliknutí.
	 */
	const MAX_EXCERPT = 300;

	/**
	 * Krátká aktualita se nekrátí vůbec — nemá odkaz na detail (viz link()),
	 * takže useknutý konec by byl pro čtenáře nedostupný. Tahle konstanta
	 * proto není redakční limit, ale tvrdá pojistka proti stropu délky
	 * příspěvku na zeď stránky v Graph API (řádově desítky tisíc znaků).
	 */
	const MAX_SHORT_NEWS = 60000;

	/**
	 * Text příspěvku. Vlastní text redaktora má vždy přednost.
	 *
	 * Odkaz se připojuje na konec, protože se sdílí fotkou — u fotopříspěvku
	 * není klikací náhledová karta a adresa v textu je jediné místo, odkud se
	 * dá na web dostat. Krátké aktuality odkaz nemají (nemají detail, na který
	 * by vedl), takže u nich text zůstává, jak byl.
	 */
	public function compose( WP_Post $post ): string {
		return $this->body( $post );
	}

	/**
	 * Text i s odkazem na konci — pro sdílení fotkou.
	 *
	 * U fotopříspěvku není klikací náhledová karta, takže adresa v textu je
	 * jediné místo, odkud se dá na web dostat.
	 *
	 * Musí to být samostatná metoda, ne chování compose(): záložní cesta
	 * posílá odkaz zvlášť v poli `link` a Facebook z něj skládá náhledovou
	 * kartu. Kdyby ho compose() přidávala i tam, byl by na příspěvku dvakrát —
	 * jednou jako text, jednou jako karta.
	 */
	public function compose_with_link( WP_Post $post ): string {
		$body = $this->body( $post );
		$link = $this->link( $post );

		return null === $link ? $body : $body . "\n\n" . $link;
	}

	/** Samotný text bez odkazu. */
	private function body( WP_Post $post ): string {
		$custom = get_post_meta( $post->ID, ShareState::META_MESSAGE, true );

		if ( ! empty( $custom ) ) {
			return trim( (string) $custom );
		}

		if ( EventPostType::KEY === $post->post_type ) {
			return $this->event_message( $post );
		}

		$length = $this->is_short_news( $post ) ? self::MAX_SHORT_NEWS : self::MAX_EXCERPT;

		return trim( $post->post_title . "\n\n" . $this->excerpt( $post, $length ) );
	}

	/**
	 * Odkaz, ze kterého Facebook složí náhledovou kartu.
	 *
	 * Krátké aktuality nemají proklik na detail — obsah se zobrazuje celý ve
	 * výpise a web na detail nikde neodkazuje. Posílají se proto bez odkazu.
	 */
	public function link( WP_Post $post ): ?string {
		if ( $this->is_short_news( $post ) ) {
			return null;
		}

		$permalink = get_permalink( $post );

		return $permalink ? $permalink : null;
	}

	private function is_short_news( WP_Post $post ): bool {
		return PostPostType::KEY === $post->post_type && (bool) get_post_meta( $post->ID, 'short_news', true );
	}

	private function event_message( WP_Post $post ): string {
		$start = get_post_meta( $post->ID, 'start', true );

		// Knihovna wpify/custom-fields umí skupinu polí uložit i jako objekt
		// (stdClass) — Events::update_start_date() ošetřuje stejný případ ze
		// stejného důvodu. Bez toho by is_array() selhalo, $start by zůstalo
		// prázdné pole a fallback níž by vzal místo ze samostatné mety misto
		// hodnoty, kterou redaktor skutečně vyplnil ve skupině 'start'.
		if ( is_object( $start ) ) {
			$start = (array) $start;
		} elseif ( ! is_array( $start ) ) {
			$start = array();
		}

		// U akcí importovaných z centrální databáze KČT je 'start.date' uložené
		// jako prázdný řetězec a skutečné datum leží v samostatné metě 'date' —
		// klíč v poli tedy existuje, jen je prázdný, takže by ho `??` nechalo
		// být a na samostatnou metu vůbec nespadlo. Proto se testuje empty(),
		// ne isset().
		$date  = ! empty( $start['date'] ) ? $start['date'] : get_post_meta( $post->ID, 'date', true );
		$time  = ! empty( $start['time'] ) ? $start['time'] : '';
		$place = ! empty( $start['place'] ) ? $start['place'] : get_post_meta( $post->ID, 'place', true );

		$lines = array( $post->post_title );

		if ( ! empty( $date ) ) {
			$lines[] = sprintf(
				/* translators: %s: naformátované datum (a případně čas) začátku akce. */
				__( 'Kdy: %s', 'kct' ),
				$this->format_event_date( (string) $date, (string) $time )
			);
		}

		if ( ! empty( $place ) ) {
			$lines[] = sprintf(
				/* translators: %s: místo konání akce. */
				__( 'Kde: %s', 'kct' ),
				$place
			);
		}

		$excerpt = $this->excerpt( $post, self::MAX_EXCERPT );

		if ( '' !== $excerpt ) {
			$lines[] = '';
			$lines[] = $excerpt;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Naformátuje datum akce v časovém pásmu webu.
	 *
	 * strtotime() by 'Y-m-d' přečetl jako půlnoc UTC a datum ve formátu jako
	 * '01/02/2026' navíc americky (2. ledna místo 1. února) — proto se
	 * parsuje explicitně podle očekávaného formátu a v časovém pásmu webu.
	 * Nepodaří-li se datum takhle přečíst, vypíše se syrová hodnota z pole,
	 * ať redaktor vidí, že je potřeba ji opravit, místo aby zmizela úplně.
	 *
	 * createFromFormat() navíc přetečené datum (např. '2026-02-30' nebo
	 * '0000-00-00' z importu KČT) tiše přepočítá na jiný, validní den —
	 * vrátí objekt, ale zpřístupní varování přes getLastErrors(). Bez téhle
	 * kontroly by na Facebook odešlo sebevědomě špatné datum. Od PHP 8.2
	 * vrací getLastErrors() `false` (ne prázdné pole), když je vše v pořádku.
	 */
	private function format_event_date( string $date, string $time ): string {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$errors = \DateTimeImmutable::getLastErrors();

		$invalid = is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) );

		if ( ! $parsed || $invalid ) {
			return '' !== $time ? $date . ', ' . $time : $date;
		}

		// Dny v týdnu se uprostřed české věty píší malým písmenem ("Kdy:
		// sobota …", ne "Kdy: Sobota …").
		$formatted = mb_strtolower( wp_date( 'l j. n. Y', $parsed->getTimestamp() ), 'UTF-8' );

		if ( '' !== $time ) {
			$formatted .= ', ' . $time;
		}

		return $formatted;
	}

	/**
	 * Perex bez HTML, zkrácený na daný počet znaků.
	 */
	private function excerpt( WP_Post $post, int $length ): string {
		$text = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;

		// Bloky serializované na jednom řádku (tabulka, nadpis + odstavec z
		// klasického editoru) by se bez zalomení slepily do jednoho slova
		// ("Trasa15 kmCenazdarma") — zalomení se vkládá ještě před stripem
		// tagů, dokud jsou uzavírací tagy k dispozici.
		$text = preg_replace( '#</(p|div|li|h[1-6]|tr|td|th|figcaption|blockquote)>#i', '$0' . "\n", $text ) ?? $text;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );

		// Entity se dekódují až po odstranění tagů — jinak by '&lt;script&gt;'
		// ožilo jako skutečný tag. Bez dekódování by navíc zůstávalo '&nbsp;'
		// za každou jednopísmennou předložkou, kterou tam sype Gutenberg.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$text = preg_replace( '/[ \t\x{00a0}]+/u', ' ', $text ) ?? '';
		$text = preg_replace( '/\n{3,}/u', "\n\n", $text ) ?? '';
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return $this->truncate( $text, $length );
	}

	/**
	 * Zkrátí text na hranici slova a odstraní případnou useknutou entitu na
	 * konci — bez toho by řez uprostřed slova nebo uprostřed entity vypadal
	 * nedbale ("Trasa vede kolem přehra…").
	 */
	private function truncate( string $text, int $length ): string {
		$cut = mb_substr( $text, 0, $length );
		$cut = preg_replace( '/&[^;\s]{0,8}$/u', '', $cut ) ?? $cut;

		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > (int) ( $length * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		// rtrim() pracuje po bajtech — vícebajtové znaky jako pomlčka U+2013
		// by se v seznamu znaků k odstranění rozpadly na jednotlivé bajty a
		// klidně odřízly poslední bajt platného vícebajtového znaku (třeba
		// 'Ó') těsně před nimi. preg_replace() s '/u' pracuje po znacích.
		$cut = preg_replace( '/[\s,.;:–—-]+$/u', '', $cut ) ?? $cut;

		return $cut . '…';
	}
}
