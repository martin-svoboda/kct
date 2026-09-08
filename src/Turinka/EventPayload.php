<?php

namespace Kct\Turinka;

use Kct\Seo\EventSeoData;

/**
 * Skládá to, co se o akci posílá do Turinky.
 *
 * Zná jen tvar akce z Events::get_event() a pravidla API; neposílá nic, neukládá
 * nic a o WordPressu ví jen tolik, kolik je potřeba na text obsahu.
 */
class EventPayload {
	/**
	 * Turinka vymáhá u krátkého popisku rozsah 100–150 znaků.
	 *
	 * Generátor popisků skládá text z dat akce a u chudé akce může vyjít
	 * kratší. Posílat ho pak nemá smysl — zablokoval by celý zápis kvůli poli,
	 * které je volitelné.
	 */
	const SHORT_MIN = 100;
	const SHORT_MAX = 150;

	public function __construct( private EventSeoData $seo ) {
	}

	/**
	 * Obsah k akci, kterou Turinka zná z feedu.
	 *
	 * Organizační pole tu nejsou schválně — u akce s db_id je drží centrální
	 * databáze a zápisové API je nepřijímá.
	 *
	 * @param bool $with_source_url Odkaz na stránku akce. Vypíná se, když
	 *                              stránka na webu přestala existovat: akce se
	 *                              tím vrátí do indexu Turinky, protože není kam
	 *                              odkázat.
	 */
	public function content( array $event, bool $with_source_url = true ): array {
		$payload = array( 'description' => $this->description( $event ) );

		$short = $this->short_description( $event );
		if ( '' !== $short ) {
			$payload['short_description'] = $short;
		}

		if ( $with_source_url && ! empty( $event['permalink'] ) ) {
			$payload['source_url'] = (string) $event['permalink'];
		}

		return $payload;
	}

	/**
	 * Vlastní akce webu.
	 *
	 * Na rozdíl od obsahu k akci z feedu se tu posílají i organizační pole —
	 * tuhle akci nikdo jiný nemá, takže ji web vlastní celou.
	 *
	 * Zařazení do struktury KČT (oblast, odbor) se neposílá; Turinka si ho
	 * odvodí z vlastníka tokenu a pole pro to nemá.
	 *
	 * @param string $visibility  `public`, nebo `unlisted`.
	 * @param bool   $published   Nepublikovaná akce se ruší a odkaz na ni se bere zpět.
	 */
	public function own_event( array $event, int $post_id, string $visibility, bool $published ): array {
		$payload = array(
			'source_id'    => (string) $post_id,
			'title'        => (string) ( $event['title'] ?? '' ),
			'starts_at'    => $this->moment( $event['start'] ?? array(), (string) ( $event['date'] ?? '' ) ),
			'visibility'   => $visibility,
			// Vynechané pole by znamenalo „nech jak je“, což u akce, kterou web
			// právě odpublikoval, není pravda. Posílá se tedy vždycky.
			'is_cancelled' => ! $published,
		);

		$ends_at = $this->moment( $event['finish'] ?? array(), '' );
		if ( null !== $ends_at ) {
			$payload['ends_at'] = $ends_at;
		}

		foreach ( array( 'place', 'district' ) as $key ) {
			if ( ! empty( $event[ $key ] ) ) {
				$payload[ $key ] = (string) $event[ $key ];
			}
		}

		// Popis je u vlastní akce volitelný — akce bez textu je pořád akce,
		// kterou se lidé dozvědí, že se koná. Práh 200 znaků platí jen u obsahu
		// dodávaného k akci z feedu.
		$description = $this->description( $event );
		if ( '' !== trim( wp_strip_all_tags( $description ) ) ) {
			$payload['description'] = $description;
		}

		$short = $this->short_description( $event );
		if ( '' !== $short ) {
			$payload['short_description'] = $short;
		}

		if ( $published && ! empty( $event['permalink'] ) ) {
			$payload['source_url'] = (string) $event['permalink'];
		}

		return $payload;
	}

	/**
	 * Datum a čas v ISO 8601, nebo null bez data.
	 *
	 * Čas je v pluginu volný text („7:30–10:00“, „dopoledne“), takže se z něj
	 * bere jen úvodní H:i, když tam je. Domýšlet víc by znamenalo posílat
	 * vymyšlené údaje; bez čitelného času jde půlnoc v časové zóně webu.
	 */
	private function moment( $part, string $fallback_date ): ?string {
		$part = is_array( $part ) ? $part : array();
		$date = trim( (string) ( $part['date'] ?? '' ) ) ?: trim( $fallback_date );

		if ( '' === $date ) {
			return null;
		}

		$time = '00:00';
		if ( preg_match( '/^\s*(\d{1,2}):(\d{2})/', (string) ( $part['time'] ?? '' ), $m ) ) {
			$time = sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
		}

		try {
			$moment = new \DateTimeImmutable( $date . ' ' . $time, wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}

		return $moment->format( \DateTimeInterface::ATOM );
	}

	/**
	 * Text akce jako HTML.
	 *
	 * Přes `the_content`, protože obsah je v blocích — bez toho by se posílaly
	 * komentáře bloků místo textu. Šablona akce vypisuje obsah stejně.
	 */
	public function description( array $event ): string {
		return (string) apply_filters( 'the_content', (string) ( $event['content'] ?? '' ) );
	}

	/**
	 * Délka textu popisu ve znacích — podle ní se pozná, že není co poslat.
	 */
	public function description_length( array $event ): int {
		return mb_strlen( trim( wp_strip_all_tags( $this->description( $event ) ) ) );
	}

	/**
	 * Krátký popisek, nebo prázdný řetězec, když se do rozsahu nevejde.
	 */
	private function short_description( array $event ): string {
		$short = trim( $this->seo->description( $event, self::SHORT_MAX ) );

		return mb_strlen( $short ) >= self::SHORT_MIN ? $short : '';
	}
}
