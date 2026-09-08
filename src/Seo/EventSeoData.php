<?php

namespace Kct\Seo;

use DateTimeImmutable;

/**
 * Skládá SEO hodnoty z pole akce.
 *
 * Třída záměrně nezná WordPress hooky ani Rank Math — dostane pole, jaké vrací
 * Events::get_event(), a vrátí řetězce. Díky tomu se dá ověřit přes `wp eval`
 * bez renderování stránky.
 */
class EventSeoData {

	/** Záložní obrázek pro sdílení, relativně ke složce šablony. */
	const FALLBACK_IMAGE = 'images/kct_barva.png';

	/** Popisek se nesmí protáhnout přes délku, kterou vyhledávače zobrazí. */
	const DESCRIPTION_LIMIT = 155;

	/**
	 * Google u Event.description žádný limit nemá (na rozdíl od meta
	 * popisku) — strop je tu jen proti tomu, aby se text nafoukl bůhvíjak.
	 */
	const EVENT_DESCRIPTION_LIMIT = 500;

	public function __construct( private \Kct\Features\OgImages $og_images ) {
	}

	/**
	 * Titulek stránky bez názvu webu — ten doplní Rank Math ze šablony.
	 *
	 * Formát: "46. Krajem nezbedného bakaláře — Rakovník, 5. 9. 2026"
	 */
	public function title( array $event ): string {
		$title = $this->text( $event['title'] ?? '' );

		if ( '' === $title ) {
			return '';
		}

		$year = (int) ( $event['year'] ?? 0 );
		if ( $year > 0 ) {
			$title = $year . '. ' . $title;
		}

		// Krátký název obce, ne podrobné místo srazu — to je v start.place
		// a bývá dlouhé přes sto znaků.
		$parts = array_filter(
			array(
				$this->text( $event['place'] ?? '' ),
				$this->format_date( $event ),
			)
		);

		if ( $parts ) {
			$title .= ' — ' . implode( ', ', $parts );
		}

		return $title;
	}

	/**
	 * Popisek složený z dat akce.
	 *
	 * Volný text (`content`) má jen 97 z 318 akcí a použitelnou délku 44, takže
	 * popisek stojí na datech a text ho jen doplní, když po nich zbude místo.
	 *
	 * @param array $event Pole akce z Events::get_event().
	 * @param int   $limit Maximální délka výsledku. Meta popisek má jiný strop
	 *                      než Event.description ve schema.org — volající si
	 *                      předá ten svůj, výchozí je pro meta popisek.
	 */
	public function description( array $event, int $limit = self::DESCRIPTION_LIMIT ): string {
		$sentences = array_filter(
			array(
				$this->discipline_sentence( $event ),
				$this->when_where_sentence( $event ),
				$this->organiser_sentence( $event ),
			)
		);

		$description = implode( ' ', $sentences );

		$content = trim( wp_strip_all_tags( $this->text( $event['content'] ?? '' ) ) );
		if ( $content ) {
			$remaining = $limit - mb_strlen( $description ) - 1;

			// Kratší útržek než třicet znaků je v ukázce k ničemu a jen useká
			// větu uprostřed slova.
			if ( $remaining >= 30 ) {
				$description = trim( $description . ' ' . $this->shorten( $content, $remaining ) );
			}
		}

		return $this->shorten( $description, $limit );
	}

	/**
	 * Graf schema.org/Event.
	 *
	 * @param array  $event     Pole akce z Events::get_event().
	 * @param string $canonical Kanonická adresa akce.
	 * @param string $image     Absolutní URL obrázku, může být prázdné.
	 */
	public function event_schema( array $event, string $canonical, string $image ): array {
		$title = $this->text( $event['title'] ?? '' );
		$start = $this->iso_datetime( $event['start']['date'] ?? ( $event['date'] ?? '' ), $event['start']['time'] ?? '' );

		// Bez názvu a data by schema stejně neprošlo validací — radši nic
		// než neúplná entita.
		if ( '' === $title || '' === $start ) {
			return array();
		}

		$schema = array(
			'@type'       => 'Event',
			'name'        => $title,
			'startDate'   => $start,
			'description' => $this->description( $event, self::EVENT_DESCRIPTION_LIMIT ),
		);

		// Poslední čas z rozsahu cíle (kdy se uzavírá), ne první (kdy se
		// otevírá) — proto `true`. Porovnání jako okamžiky, ne jako řetězce:
		// prázdné finish.time vrátí jen holé datum bez času, které se od
		// startDate s časem textově vždycky liší, i když jde o půlnoc
		// téhož dne před začátkem akce.
		$end = $this->iso_datetime( $event['finish']['date'] ?? '', $event['finish']['time'] ?? '', true );
		if ( $end && strtotime( $end ) > strtotime( $start ) ) {
			$schema['endDate'] = $end;
		}

		// schema.org definuje EventScheduled doslova jako „the event is
		// taking place or has taken place on the startDate as scheduled" —
		// platí tedy i pro proběhlou akci, ne jen budoucí. EventStatusType
		// žádnou hodnotu pro „proběhlo" nemá, alternativy (Cancelled,
		// Postponed, Rescheduled, MovedOnline) navíc nemáme odkud zjistit.
		$schema['eventStatus'] = 'https://schema.org/EventScheduled';

		$location = $this->location( $event );
		if ( $location ) {
			$schema['location'] = $location;
		}

		$organizer = $this->organizer( $event );
		if ( $organizer ) {
			$schema['organizer'] = $organizer;
		}

		if ( $canonical ) {
			$schema['url'] = $canonical;
		}

		if ( $image ) {
			// Ne holý řetězec — Rank Math ve vlastním connect_properties()
			// bere `image` jako prázdný ImageObject, kdykoli to není pole
			// ("empty( $schema['image']['url'] ) && ! is_array(...)"), a
			// takový záznam sám tiše smaže dřív, než JSON-LD vypíše.
			$schema['image'] = array(
				'@type' => 'ImageObject',
				'url'   => $image,
			);
		}

		return $schema;
	}

	/**
	 * Organizer property Event schématu: skutečný pořadatel akce z dat.
	 *
	 * Vytažené z event_schema() do vlastní veřejné metody, protože ji potřebuje
	 * i RankMathOutput — ten musí Rank Mathu vracet správného pořadatele zpátky
	 * poté, co ho Frontend::connect_schema_entities() bezpodmínečně přepíše
	 * odkazem na organizaci provozovatele webu. Logika skládání pořadatele tak
	 * zůstává na jednom místě.
	 *
	 * @param array $event Pole akce z Events::get_event().
	 *
	 * @return array{'@type': string, name: string}|array{}
	 */
	public function organizer( array $event ): array {
		$name = $this->text( $event['organiser']['name'] ?? '' );

		if ( '' === $name ) {
			return array();
		}

		return array(
			'@type' => 'Organization',
			'name'  => $name,
		);
	}

	/**
	 * Obrázek pro OG/Twitter tagy: vlastní obrázek akce, jinak logo z tématu.
	 *
	 * Sdílí ho StandaloneOutput, RankMathOutput i DepartmentSeo, ať se sémantika
	 * (vlastní obrázek → záloha → prázdno) nepíše na třech místech zvlášť
	 * a nerozejde se. Použité funkce jsou z jádra, ne hooky ani závislost na
	 * Rank Mathu — nejde proti omezení třídy z hlavičky souboru.
	 *
	 * Rozměry (width/height) umí vrátit jen u zálohy — je to lokální soubor
	 * šablony. U vlastní fotky akce je to vzdálená URL z importu KČT, rozměry
	 * neznáme, proto 0/0.
	 *
	 * @param array $event Pole akce z Events::get_event().
	 *
	 * @return array{url: string, width: int, height: int}
	 */
	public function image( array $event ): array {
		// Vlastní sdílecí obrázek má přednost před fotkou z importu i před
		// logem — nese titulek, datum a data akce, takže odkaz na sociální
		// síti vypadá jako akce, ne jako obrázek.
		//
		// Navíc u něj známe rozměry. Fotka z importu je vzdálená URL a
		// width/height jsou nula, takže StandaloneOutput og:image:width vůbec
		// nevypíše a Facebook náhled při prvním sdílení nevykreslí, dokud si
		// obrázek sám nestáhne.
		$own = $this->og_images->for_event( $event );

		if ( $own ) {
			return $own;
		}

		$url = $this->text( $event['image']['url'] ?? '' );

		if ( $url ) {
			return array(
				'url'    => $url,
				'width'  => 0,
				'height' => 0,
			);
		}

		return $this->fallback_image();
	}

	/**
	 * Záložní obrázek pro sdílení — obecné logo KČT ze šablony.
	 *
	 * Záměrně ne `custom_logo` daného webu: na oblastním webu se vypisují akce
	 * a odbory z celé oblasti, takže logo oblasti je u cizího odboru nemístné.
	 * Obecné logo KČT sedí na obojí a je stejné napříč celou sítí.
	 *
	 * Soubor je součástí šablony, ne příloha, takže rozměry se čtou ze souboru.
	 * Sdílené sítě je vyžadují: bez nich Facebook náhled při prvním sdílení
	 * nevykreslí, dokud si obrázek sám nestáhne. Výsledek se drží ve statické
	 * proměnné, ať se soubor nečte několikrát za request.
	 */
	private function fallback_image(): array {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$empty = array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
		);

		$path = get_theme_file_path( self::FALLBACK_IMAGE );

		if ( ! is_readable( $path ) ) {
			$cached = $empty;

			return $cached;
		}

		$size = wp_getimagesize( $path );

		$cached = $size
			? array(
				'url'    => get_theme_file_uri( self::FALLBACK_IMAGE ),
				'width'  => (int) $size[0],
				'height' => (int) $size[1],
			)
			: $empty;

		return $cached;
	}

	/** Místo konání s GPS, když je k dispozici. */
	private function location( array $event ): array {
		// Do schematu jde podrobné místo srazu, ne krátký název obce — tady
		// na délce nezáleží a podrobnost pomáhá.
		$name = $this->text( $event['start']['place'] ?? '' )
			?: $this->text( $event['place'] ?? '' );

		if ( '' === $name ) {
			return array();
		}

		$location = array(
			'@type' => 'Place',
			'name'  => $name,
		);

		// gps_n/gps_e jsou u 79 z 318 akcí prázdné pole, ne řetězec — proto
		// text(), ne přímé přetypování. Bez toho by na těch stránkách padalo
		// "Array to string conversion".
		$lat = $this->text( $event['start']['gps_n'] ?? '' );
		$lng = $this->text( $event['start']['gps_e'] ?? '' );

		if ( '' === $lat || '' === $lng ) {
			// Events::get_event() slučuje data z modelů (EventModel,
			// DbEventModel), jejichž to_array() do top-level lat/lng už
			// počítá vlastní fallback na finish.gps_* — stejná souřadnice,
			// jakou jinde v pluginu berou mapové markery. Recyklovat ji je
			// konzistentnější než počítat fallback znovu a jinak.
			$lat = $this->text( $event['lat'] ?? '' );
			$lng = $this->text( $event['lng'] ?? '' );
		}

		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			$location['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}

		// Bez adresy Google Event nezobrazí. Obec (place) je nejlepší
		// dostupná adresa; když je prázdná (akce má vyplněné jen podrobné
		// místo srazu), radši $name než žádná adresa vůbec — $name je tu
		// vždycky, jinak by se metoda vrátila dřív.
		$location['address'] = array(
			'@type'           => 'PostalAddress',
			'addressLocality' => $this->text( $event['place'] ?? '' ) ?: $name,
			'addressCountry'  => 'CZ',
		);

		return $location;
	}

	/**
	 * ISO 8601 datum a čas z data ve tvaru Y-m-d a lidsky psaného rozsahu času.
	 *
	 * `time` bývá rozsah ("6:00–12:00", "do 18:00"), ne strojový čas. U startu
	 * je relevantní první nalezená dvojice H:MM (kdy akce začíná), u cíle
	 * naopak poslední (kdy se uzavírá) — proto $last. Bez rozpoznatelného
	 * času se vrátí jen datum (schema.org to připouští), bez platného data
	 * prázdný řetězec.
	 *
	 * @param mixed $date Datum ve tvaru 'Y-m-d', případně prázdné pole z importu.
	 * @param mixed $time Lidsky psaný čas/rozsah, případně prázdné pole z importu.
	 * @param bool  $last Vzít poslední nalezený čas z rozsahu místo prvního.
	 */
	private function iso_datetime( $date, $time, bool $last = false ): string {
		$parsed = $this->parse_date( $this->text( $date ) );

		if ( ! $parsed ) {
			return '';
		}

		if ( preg_match_all( '/(\d{1,2}):(\d{2})/', $this->text( $time ), $matches, PREG_SET_ORDER ) ) {
			$match  = $last ? end( $matches ) : $matches[0];
			$hour   = (int) $match[1];
			$minute = (int) $match[2];

			if ( $hour <= 23 && $minute <= 59 ) {
				return $parsed->setTime( $hour, $minute )->format( 'c' );
			}
		}

		return $parsed->format( 'Y-m-d' );
	}

	/**
	 * Proběhla už akce? Rozhoduje datum konce, u jednodenní akce datum konání.
	 */
	public function is_past( array $event ): bool {
		$end = $this->parse_date( $this->text( $event['finish']['date'] ?? '' ) ) ?? $this->event_date( $event );

		if ( ! $end ) {
			return false;
		}

		$today = new DateTimeImmutable( 'today', wp_timezone() );

		return $end < $today;
	}

	/**
	 * Disciplína a délky tras: "Pěší turistika 10–40 km."
	 *
	 * `details` míchá disciplíny s bonusy (sleva, turistické známky). Řadí je
	 * `weight` — 30 pěší, 50 cyklo, 136 IVV, 180 sleva KČT — takže disciplína
	 * je položka s nejnižší vahou a bonusy do popisku nepatří.
	 */
	private function discipline_sentence( array $event ): string {
		$details = $this->details( $event );

		if ( ! $details ) {
			return '';
		}

		usort(
			$details,
			static function ( $a, $b ) {
				return (int) ( $a['weight'] ?? 0 ) <=> (int) ( $b['weight'] ?? 0 );
			}
		);

		$name = $this->text( $details[0]['name'] ?? '' );
		if ( '' === $name ) {
			return '';
		}

		$km = '';
		foreach ( $details as $detail ) {
			// `km` je v datech buď řetězec ("10–40 km"), nebo prázdné pole.
			$candidate = $this->text( $detail['km'] ?? null );
			if ( '' !== $candidate ) {
				$km = $candidate;
				break;
			}
		}

		return rtrim( $name . ( $km ? ' ' . $km : '' ), '.' ) . '.';
	}

	/** Termín a místo: "5. 9. 2026, Rakovník, start 6:00–12:00." */
	private function when_where_sentence( array $event ): string {
		$parts = array_filter(
			array(
				$this->format_date( $event ),
				$this->text( $event['place'] ?? '' ),
			)
		);

		if ( ! $parts ) {
			return '';
		}

		// `start.time` je v datech buď řetězec, nebo prázdné pole — stejně jako `km`.
		$start_time = $this->text( $event['start']['time'] ?? '' );
		if ( '' !== $start_time ) {
			$parts[] = 'start ' . $start_time;
		}

		return implode( ', ', $parts ) . '.';
	}

	/** Pořadatel: "Pořádá KČT, odbor Rakovník." */
	private function organiser_sentence( array $event ): string {
		$name = $this->text( $event['organiser']['name'] ?? '' );

		return $name ? 'Pořádá ' . rtrim( $name, '.' ) . '.' : '';
	}

	/** Normalizuje `details` na seznam polí — v datech bývá i jediný záznam bez obalu. */
	private function details( array $event ): array {
		$details = $event['details'] ?? array();

		if ( ! is_array( $details ) || ! $details ) {
			return array();
		}

		if ( isset( $details['detailid'] ) ) {
			return array( $details );
		}

		return array_values( array_filter( $details, 'is_array' ) );
	}

	/**
	 * "2026-09-05" → "5. 9. 2026"; bez čitelného data vrátí prázdný řetězec.
	 *
	 * Veřejná i pro šablonu (content-akce.php) — kvůli poznámce o proběhlé
	 * akci. Ať parsování data (parse_date()) zůstane jen na jednom místě.
	 */
	public function format_date( array $event ): string {
		$date = $this->event_date( $event );

		return $date ? wp_date( 'j. n. Y', $date->getTimestamp() ) : '';
	}

	/**
	 * Datum akce jako DateTimeImmutable v časovém pásmu webu, nebo null.
	 *
	 * Funkce strtotime() by 'Y-m-d' přečetla jako půlnoc UTC a přetečené datum
	 * (např. '2026-02-30' nebo '0000-00-00' z importu KČT) by tiše přepočítala
	 * na jiný, validní den. createFromFormat() na stejném vstupu vrátí objekt,
	 * ale zpřístupní chybu přes getLastErrors() — bez téhle kontroly by SEO
	 * hodnoty (a později event_schema()/is_past()) klidně pracovaly se
	 * sebevědomě špatným datem. Konvence viz
	 * Kct\Facebook\MessageComposer::format_event_date().
	 */
	private function event_date( array $event ): ?DateTimeImmutable {
		return $this->parse_date( $this->text( $event['date'] ?? '' ) );
	}

	/**
	 * Obecný rozparsovač data 'Y-m-d' na DateTimeImmutable v časovém pásmu
	 * webu, nebo null. Sdílí ho event_date(), iso_datetime() i is_past(),
	 * ať se nekontroluje platnost data na třech místech třikrát jinak.
	 */
	private function parse_date( string $date ): ?DateTimeImmutable {
		if ( '' === $date ) {
			return null;
		}

		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();

		$invalid = is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) );

		return ( $parsed && ! $invalid ) ? $parsed : null;
	}

	/**
	 * Bezpečně převede hodnotu z importu na řetězec.
	 *
	 * `start.time`, `details[].km`, `start.gps_n` a `start.gps_e` mají v datech
	 * z importu KČT nekonzistentně buď řetězec, nebo prázdné pole — přímý
	 * `(string) $value` by na poli vyhodil varování. Sjednocený helper na
	 * tuhle ochranu nedovolí nikde v třídě zapomenout.
	 */
	private function text( $value ): string {
		return is_array( $value ) ? '' : trim( (string) $value );
	}

	/** Zkrátí na hranici slova a doplní výpustku. */
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

		// Regulární výraz místo rtrim() — rtrim() ořezává po jednotlivých bajtech,
		// takže by u vícebajtové pomlčky (–, U+2013) mohl uříznout jen její
		// část a vyrobit nevalidní UTF-8. /u navíc ořízne i em dash (—), který
		// třída sama používá jako oddělovač v title().
		$cut = preg_replace( '/[\s,.;:–—-]+$/u', '', $cut ) ?? '';

		return $cut . '…';
	}
}
