<?php

namespace Kct\Seo;

/**
 * Skládá SEO hodnoty z pole odboru.
 *
 * Stejný princip jako EventSeoData: třída dostane pole, jaké vrací
 * DepartmentModel::to_array() (nebo DepartmentRepository::find_published_to_array()),
 * a vrátí řetězce/pole bez závislosti na WordPress hookách nebo Rank Mathu — dá
 * se tak ověřit přes `wp eval` bez renderování stránky. Zámerně samostatná
 * třída, ne rozšíření EventSeoData — odbor není akce a nemá virtuální
 * stránku/duální (DB vs. CPT) zdroj dat, který u akcí zdůvodňuje složitější
 * EventSeo/EventSeoOutput dělení; nucené sdílení by tam žádnou logiku
 * neušetřilo, jen by to propojilo dvě nesouvisející entity.
 */
class DepartmentSeoData {

	/** Popisek se nesmí protáhnout přes délku, kterou vyhledávače zobrazí. */
	const DESCRIPTION_LIMIT = 155;

	/** Pravidelná druhá věta popisku — platí bez ohledu na to, jestli má odbor zrovna vypsanou nějakou akci. */
	const ACTIVITY_SENTENCE = 'Pořádá turistické vycházky a pochody pro veřejnost.';

	/** Pravidelná třetí věta popisku — odkazuje na obsah stránky, ne na konkrétní data, funguje tedy i bez kontaktů. */
	const CONTACT_SENTENCE = 'Kontakt na vedení odboru a přehled jeho akcí najdete na webu.';

	/**
	 * Popisek složený z dat odboru.
	 *
	 * Žádný odbor nemá vlastní popisek (`%excerpt%` je u všech prázdné) — popisek
	 * proto vždycky stojí na metapolích, ne na doplnění chybějícího textu, jak to
	 * dělá EventSeoData::description() u akcí s `content`.
	 *
	 * @param array $department Pole odboru z DepartmentModel::to_array().
	 * @param int   $limit      Maximální délka výsledku.
	 */
	public function description( array $department, int $limit = self::DESCRIPTION_LIMIT ): string {
		$sentences = array_filter(
			array(
				$this->intro_sentence( $department ),
				self::ACTIVITY_SENTENCE,
				self::CONTACT_SENTENCE,
			)
		);

		return $this->shorten( implode( ' ', $sentences ), $limit );
	}

	/**
	 * Graf schema.org/SportsOrganization.
	 *
	 * SportsOrganization, ne "SportsClub" — ten schema.org vůbec nedefinuje
	 * (existuje jen SportsOrganization a jeho podtyp SportsTeam). Organization
	 * by taky prošla, ale SportsOrganization je specifičtější platný typ a
	 * turistický odbor pod něj sémanticky spadá.
	 *
	 * @param array  $department          Pole odboru z DepartmentModel::to_array().
	 * @param string $canonical          Kanonická adresa (permalink) stránky odboru.
	 * @param array  $parent_organization Odkaz na entitu oblasti, `[ '@id' => … ]`, nebo
	 *                                     prázdné pole, když není k dispozici (web bez
	 *                                     Rank Mathu nebo bez nastaveného Organization
	 *                                     typu v Titles & Meta).
	 */
	public function schema( array $department, string $canonical, array $parent_organization = array() ): array {
		$name = $this->text( $department['name'] ?? '' ) ?: $this->text( $department['title'] ?? '' );

		if ( '' === $name ) {
			return array();
		}

		$schema = array(
			'@type' => 'SportsOrganization',
			'name'  => $name,
		);

		if ( $canonical ) {
			$schema['url'] = $canonical;
		}

		$address = $this->address( $department );
		if ( $address ) {
			$schema['address'] = $address;
		}

		$geo = $this->geo( $department );
		if ( $geo ) {
			$schema['geo'] = $geo;
		}

		$phone = $this->first( $department['phones'] ?? array() );
		if ( $phone ) {
			$schema['telephone'] = $phone;
		}

		$email = $this->first( $department['emails'] ?? array() );
		if ( $email ) {
			$schema['email'] = $email;
		}

		$website = $this->website_url( $department );
		if ( $website ) {
			$schema['sameAs'] = array( $website );
		}

		if ( $parent_organization ) {
			$schema['parentOrganization'] = $parent_organization;
		}

		return $schema;
	}

	/** Úvodní věta popisku: "Turistický odbor KČT v obci Slaný." — bez obce jen obecná věta. */
	private function intro_sentence( array $department ): string {
		$town = $this->town( $department );

		return $town
			? sprintf( 'Turistický odbor KČT v obci %s.', $town )
			: 'Turistický odbor KČT.';
	}

	/**
	 * Název obce v čitelném tvaru — data z importu KČT ho mají verzálkami
	 * ("SLANÝ", "KUTNÁ HORA"), do věty patří normální tvar ("Slaný", "Kutná Hora").
	 */
	private function town( array $department ): string {
		$town = $this->text( $department['town'] ?? '' );

		if ( '' === $town ) {
			return '';
		}

		return mb_convert_case( mb_strtolower( $town, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
	}

	/** Adresa odboru jako PostalAddress, nebo prázdné pole bez jediné složky adresy. */
	private function address( array $department ): array {
		$street = $this->text( $department['street'] ?? '' );
		$zip    = $this->text( $department['zip'] ?? '' );
		$town   = $this->text( $department['town'] ?? '' );

		if ( '' === $street && '' === $zip && '' === $town ) {
			return array();
		}

		$address = array( '@type' => 'PostalAddress' );

		if ( $street ) {
			$address['streetAddress'] = $street;
		}

		if ( $zip ) {
			$address['postalCode'] = $zip;
		}

		if ( $town ) {
			$address['addressLocality'] = $town;
		}

		// Pole `state` má v datech z importu vždycky "Česká republika" —
		// schema.org/Google čeká u addressCountry ISO kód, stejně jako
		// EventSeoData::location() u adresy místa konání akce.
		$address['addressCountry'] = 'CZ';

		return $address;
	}

	/**
	 * Souřadnice odboru jako GeoCoordinates, jen když jsou obě nenulové.
	 *
	 * `lat`/`lng` rovné nule jsou u odborů bez zjištěné polohy (12 ze 41), ne
	 * skutečný bod na rovníku a nultém poledníku — GeoCoordinates s takovou
	 * hodnotou by ukazovaly do Atlantiku, a proto se v tom případě nevypisují.
	 */
	private function geo( array $department ): array {
		$lat = (float) ( $department['lat'] ?? 0 );
		$lng = (float) ( $department['lng'] ?? 0 );

		if ( 0.0 === $lat || 0.0 === $lng ) {
			return array();
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}

	/** První neprázdná hodnota ze serializovaného pole (telefony, e-maily). */
	private function first( $values ): string {
		if ( ! is_array( $values ) ) {
			return $this->text( $values );
		}

		foreach ( $values as $value ) {
			$value = $this->text( $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Web odboru jako platná absolutní adresa pro `sameAs`, nebo prázdný řetězec.
	 *
	 * Pole `web` bývá bez schématu ("www.kct-kolin.kvalitne.cz") — https:// se
	 * doplní, jen když tam schéma chybí. Samotné filter_var(FILTER_VALIDATE_URL)
	 * ale propustí i poškozená data typu "wwwkct/bosa-turistika" (host bez tečky
	 * je syntakticky platný, viz RFC 3986) — proto se navíc vyžaduje tečka
	 * v hostiteli, ať se do `sameAs` nedostane odkaz, který zjevně není doménou.
	 */
	private function website_url( array $department ): string {
		$web = $this->text( $department['web'] ?? '' );

		if ( '' === $web ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $web ) ) {
			$web = 'https://' . $web;
		}

		if ( ! filter_var( $web, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$host = wp_parse_url( $web, PHP_URL_HOST );

		return ( $host && false !== strpos( $host, '.' ) ) ? $web : '';
	}

	/**
	 * Bezpečně převede hodnotu z importu na řetězec — stejný důvod jako
	 * EventSeoData::text(): některá pole bývají místo prázdného řetězce prázdné
	 * pole a přímé (string) přetypování by na nich vyhodilo varování.
	 */
	private function text( $value ): string {
		return is_array( $value ) ? '' : trim( (string) $value );
	}

	/**
	 * Zkrátí na hranici slova a doplní výpustku — kopie
	 * EventSeoData::shorten(). Vytáhnout ji do sdíleného traitu by znamenalo
	 * sahat do schválené, provozní třídy kvůli deseti řádkům obecného
	 * ořezávání textu; riziko takové úpravy je vůči přínosu nepřiměřené, proto
	 * zůstává duplikovaná tady.
	 */
	private function shorten( string $text, int $limit ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );

		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $limit - 1 );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > $limit / 2 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		$cut = preg_replace( '/[\s,.;:–—-]+$/u', '', $cut ) ?? '';

		return $cut . '…';
	}
}
