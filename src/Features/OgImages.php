<?php

namespace Kct\Features;

use Kct\Og\IconCache;
use Kct\Og\OgImageService;
use Kct\Repositories\EventRepository;
use WP_Post;

/**
 * Sdílecí obrázky příspěvků a akcí.
 *
 * Skládá data pro karty z WordPressu a z pole akce, věší se na uložení
 * příspěvku a na filtry Rank Mathu. Samotné kreslení a ukládání je v Kct\Og.
 */
class OgImages {

	/**
	 * Zapamatované výsledky pro tenhle request, klíčované ID příspěvku.
	 *
	 * Hodnotou může být i null (obrázek se nevyrobil) — proto se existence
	 * ověřuje přes array_key_exists(), ne isset().
	 *
	 * @var array<int, array{url: string, width: int, height: int}|null>
	 */
	private array $post_cache = array();

	public function __construct(
		private OgImageService $service,
		private IconCache $icons,
		private Events $events,
		private EventRepository $event_repository
	) {
		add_action( 'save_post', array( $this, 'on_save' ), 20, 2 );

		// Filtry Rank Mathu jen pro příspěvky. `image`, ne `og_image`:
		// og_image filtruje až finální hodnotu tagu a ten se vypíše, jen když
		// Rank Math nějaký obrázek už našel — bez nalezeného obrázku by se
		// pozdní filtr nezavolal vůbec. `image` je dřívější filtr uvnitř
		// Image::add_image() a proběhne vždycky. Totéž zjištění má u sebe
		// Seo\RankMathOutput pro akce.
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'filter_rank_math_image' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'filter_rank_math_image' ) );

		// Rozměry. Filtr `image` bere jen URL, takže Rank Math u dosazeného
		// obrázku nezná šířku ani výšku a og:image:width/height nevypíše —
		// Facebook pak náhled při prvním sdílení nevykreslí, dokud si obrázek
		// sám nestáhne. `image_array` běží hned za ním nad celým polem a
		// Image::image_meta() z něj width a height vypíše (class-image.php).
		add_filter( 'rank_math/opengraph/facebook/image_array', array( $this, 'filter_rank_math_image_array' ) );
		add_filter( 'rank_math/opengraph/twitter/image_array', array( $this, 'filter_rank_math_image_array' ) );
	}

	/**
	 * Vyrobí obrázek hned při uložení, ať na něj nečeká první návštěvník.
	 *
	 * @param int     $post_id ID příspěvku.
	 * @param WP_Post $post    Příspěvek.
	 */
	public function on_save( $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'post' === $post->post_type ) {
			$this->for_post( (int) $post_id );
			$this->social_for_post( (int) $post_id );

			return;
		}

		if ( $post->post_type === $this->event_repository->post_type() ) {
			$this->social_for_event_post( (int) $post_id );
		}
	}

	public function filter_rank_math_image( $image ) {
		if ( ! is_singular( 'post' ) ) {
			return $image;
		}

		$own = $this->for_post( (int) get_the_ID() );

		return $own ? $own['url'] : $image;
	}

	/**
	 * Doplní k dosazenému obrázku jeho rozměry.
	 *
	 * Sahá jen na pole, jehož URL je náš vygenerovaný obrázek — u cizího
	 * obrázku by rozměry 1200×630 byly lež.
	 *
	 * @param array $attachment Pole obrázku od Rank Mathu.
	 *
	 * @return array
	 */
	public function filter_rank_math_image_array( $attachment ) {
		if ( ! is_array( $attachment ) || ! is_singular( 'post' ) ) {
			return $attachment;
		}

		$own = $this->for_post( (int) get_the_ID() );

		if ( ! $own || ( $attachment['url'] ?? '' ) !== $own['url'] ) {
			return $attachment;
		}

		$attachment['width']  = $own['width'];
		$attachment['height'] = $own['height'];

		return $attachment;
	}

	/**
	 * Sdílecí obrázek příspěvku.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function for_post( int $post_id ): ?array {
		// Rank Math se ptá čtyřikrát za vykreslení stránky (facebook i twitter,
		// image i image_array) a OpenGraph jednou. Bez zapamatování by se
		// pokaždé znovu počítala doba čtení přes celý obsah příspěvku.
		if ( array_key_exists( $post_id, $this->post_cache ) ) {
			return $this->post_cache[ $post_id ];
		}

		$this->post_cache[ $post_id ] = $this->build_post( $post_id );

		return $this->post_cache[ $post_id ];
	}

	private function build_post( int $post_id ): ?array {
		$data = $this->post_data( $post_id );

		return $data ? $this->service->post( $data, 'post-' . $post_id ) : null;
	}

	/**
	 * Data karty příspěvku, nebo null když příspěvek není publikovaný.
	 *
	 * Skládá se na jednom místě, protože je potřebují obě karty — 1200×630
	 * i 4:5.
	 */
	private function post_data( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		$categories = get_the_category( $post_id );
		$category   = ! empty( $categories ) ? $categories[0]->name : '';

		// Doba čtení stejně jako v template-parts/post-hero.php, ať karta
		// a hero hlavička neukazují každá jiné číslo.
		$words   = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$minutes = max( 1, (int) round( $words / 200 ) );

		return array(
			'title'    => get_the_title( $post ),
			'category' => $category,
			'meta'     => sprintf(
				/* translators: 1: datum vydání, 2: doba čtení v minutách. */
				__( '%1$s   •   %2$d min čtení', 'kct' ),
				get_the_date( 'j. n. Y', $post ),
				$minutes
			),
			'photo'    => $this->thumbnail_path( $post_id ),
			'logo'     => $this->logo_path(),
			'month'    => (int) get_the_date( 'n', $post ),
		);
	}

	/**
	 * Sdílecí obrázek akce.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function for_event( array $event ): ?array {
		$data = $this->event_data( $event );

		return $data ? $this->service->event( $data, $this->event_prefix( $event ) ) : null;
	}

	/**
	 * Data karty akce, nebo null když akce nemá titulek nebo datum.
	 *
	 * Nese klíče pro obě karty: `columns` čte karta 1200×630, `rows`
	 * a `routes` karta 4:5. Zdvojení je záměrné — na šířku se údaje vejdou
	 * jen do tří úzkých sloupců, na výšku se píšou pod sebe a s délkami tras.
	 */
	private function event_data( array $event ): ?array {
		$title = $this->text( $event['title'] ?? '' );

		if ( '' === $title ) {
			return null;
		}

		$formatted = $event['formated_date'] ?? null;

		if ( ! is_array( $formatted ) || '' === $this->text( $formatted['day'] ?? '' ) ) {
			// Bez data by karta neměla o čem být — datumová kartička je její
			// nosný prvek. Volající spadne na dnešní obrázek.
			return null;
		}

		$range   = ! empty( $formatted['is_range'] );
		$columns = $this->event_columns( $event );

		return array(
			'title'   => $title,
			'eyebrow' => $this->year_label( $event ),
			'date'    => array(
				'is_range'  => $range,
				// Velikost písmen dělá na webu CSS (.event-date__head má
				// text-transform: uppercase, .event-date__mon lowercase),
				// Imagick nic takového nemá — tak se to udělá tady, ať karta
				// a výpis akcí ukazují totéž. date_i18n('M') vrací „Led“.
				'head'      => mb_strtoupper( $this->text( $range ? ( $formatted['days_label'] ?? '' ) : ( $formatted['day_abbr'] ?? '' ) ), 'UTF-8' ),
				'day'       => $this->text( $formatted['day'] ?? '' ),
				'month'     => mb_strtolower( $this->text( $formatted['month'] ?? '' ), 'UTF-8' ),
				'end_day'   => $this->text( $formatted['end_day'] ?? '' ),
				'end_month' => mb_strtolower( $this->text( $formatted['end_month'] ?? '' ), 'UTF-8' ),
			),
			'columns' => $columns,
			'points'  => $this->event_points( $event ),
			'organiser_line' => $this->event_organiser_line( $event ),
			'map'     => $this->event_map_path( $event ),
			'rows'    => $columns,
			'routes'  => $this->event_routes( $event ),
			'icons'   => $this->event_icons( $event ),
			'photo'   => $this->event_photo_path( $event ),
			'logo'    => $this->logo_path(),
			'month'   => $this->event_month( $event ),
		);
	}

	/**
	 * Sdílecí obrázek akce, která má vlastní CPT příspěvek.
	 *
	 * Používá ho feature OpenGraph na webech bez SEO pluginu. Tam se totiž
	 * Seo\StandaloneOutput na CPT stránce úmyslně nezapojuje a nechává tagy
	 * na OpenGraphu — ten ale sám o akcích nic neví.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function for_event_post( int $post_id ): ?array {
		if ( get_post_type( $post_id ) !== $this->event_repository->post_type() ) {
			return null;
		}

		$event = $this->events->get_event( $post_id, '' );

		return $event ? $this->for_event( $event ) : null;
	}

	/**
	 * Předpona názvu souboru akce.
	 *
	 * Akce z centrální databáze se klíčuje jejím db_id, i když k ní existuje
	 * příspěvek — je to jedna akce a má mít jeden obrázek. Bez db_id (ručně
	 * založená akce) se klíčuje ID příspěvku.
	 */
	private function event_prefix( array $event ): string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		return $db_id ? 'akce-db-' . $db_id : 'akce-' . (int) ( $event['id'] ?? 0 );
	}

	private function year_label( array $event ): string {
		$year = $event['year'] ?? '';

		if ( '' === $year || null === $year || 0 === (int) $year ) {
			return '';
		}

		/* translators: %d: pořadové číslo ročníku akce. */
		return sprintf( __( '%d. ročník', 'kct' ), (int) $year );
	}

	/**
	 * Start, cíl a pořadatel — totéž, co na detailu nesou infoboxy pod
	 * hlavičkou (template-parts/content-akce.php).
	 *
	 * @return array<int, array{label: string, value: string, note: string}>
	 */
	private function event_columns( array $event ): array {
		return array(
			$this->point_column( __( 'START', 'kct' ), $event['start'] ?? array() ),
			$this->point_column( __( 'CÍL', 'kct' ), $event['finish'] ?? array() ),
			array(
				'label' => __( 'POŘADATEL', 'kct' ),
				'value' => $this->text( $event['organiser']['name'] ?? '' ),
				'note'  => $this->place_note( $event ),
			),
		);
	}

	/**
	 * @param array $point Pole `start` nebo `finish` z akce.
	 *
	 * @return array{label: string, value: string, note: string}
	 */
	private function point_column( string $label, $point ): array {
		if ( ! is_array( $point ) ) {
			$point = array();
		}

		$date = $this->text( $point['date'] ?? '' );
		$time = $this->text( $point['time'] ?? '' );

		$value = '';

		if ( '' !== $date ) {
			$timestamp = strtotime( $date );
			$value     = $timestamp ? date_i18n( 'j. n.', $timestamp ) : '';

			if ( '' !== $value && '' !== $time ) {
				$value .= ' ' . $time;
			}
		}

		return array(
			'label' => $label,
			'value' => $value,
			'note'  => $this->text( $point['place'] ?? '' ),
		);
	}

	private function place_note( array $event ): string {
		$place    = $this->text( $event['place'] ?? '' );
		$district = $this->text( $event['district'] ?? '' );

		if ( '' !== $place && '' !== $district ) {
			/* translators: 1: místo konání, 2: okres. */
			return sprintf( __( '%1$s, okr. %2$s', 'kct' ), $place, $district );
		}

		if ( '' !== $district ) {
			/* translators: %s: okres. */
			return sprintf( __( 'okr. %s', 'kct' ), $district );
		}

		return $place;
	}

	/** Cesta k souboru náhledového obrázku příspěvku, nebo prázdný řetězec. */
	private function thumbnail_path( int $post_id ): string {
		$attachment_id = get_post_thumbnail_id( $post_id );

		if ( ! $attachment_id ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );

		return ( $path && is_readable( $path ) ) ? $path : '';
	}

	/**
	 * Cesta k fotce akce, nebo prázdný řetězec.
	 *
	 * Bere se jen soubor, který leží na disku téhle instalace. Obrázky
	 * z importu KČT jsou vzdálené URL a stahovat je kvůli textuře, která je
	 * stejně ztmavená na 18 % viditelnosti, nestojí za složitost — týká se to
	 * 6 akcí z 318 a bez fotky karta vypadá dobře.
	 */
	private function event_photo_path( array $event ): string {
		$url = $this->text( $event['image']['url'] ?? '' );

		if ( '' === $url ) {
			return '';
		}

		$attachment_id = attachment_url_to_postid( $url );

		if ( ! $attachment_id ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );

		return ( $path && is_readable( $path ) ) ? $path : '';
	}

	/**
	 * Cesta k logu — logo webu, jinak obecné logo KČT ze šablony.
	 *
	 * Platí pro obě karty stejně. U akcí to byla chvíli výjimka (vždy obecné
	 * logo, protože oblastní web vypisuje i akce cizích odborů a logo oblasti
	 * by u nich tvrdilo něco, co není pravda — tak to zdůvodňuje
	 * Seo\EventSeoData::fallback_image()). Martin rozhodl jinak: sdílí se
	 * odkaz na konkrétní web, takže na obrázku má být logo toho webu.
	 */
	private function logo_path(): string {
		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$path = get_attached_file( $logo_id );

			// SVG se přeskočí — Imagick na produkci SVG delegáta nemá,
			// takže by se logo vykreslilo lokálně a na produkci ne.
			if ( $path && is_readable( $path ) && ! preg_match( '/\.svgz?$/i', $path ) ) {
				return $path;
			}
		}

		$fallback = get_theme_file_path( 'images/kct_barva.png' );

		return is_readable( $fallback ) ? $fallback : '';
	}

	/**
	 * Cesty k ikonám typů akce.
	 *
	 * Pole `details` nese typy sloučené s nastavením z options
	 * (Events::merge_event_details_data()), včetně adresy piktogramu na serveru
	 * centrální databáze. Stažení a místní kopii řeší IconCache; když se ikona
	 * nedá získat, prostě na kartě nebude.
	 *
	 * @return string[]
	 */
	private function event_icons( array $event ): array {
		$details = $event['details'] ?? array();

		if ( ! is_array( $details ) ) {
			return array();
		}

		$paths = array();

		foreach ( $details as $detail ) {
			$url = is_array( $detail ) ? $this->text( $detail['icon'] ?? '' ) : '';

			if ( '' === $url ) {
				continue;
			}

			$path = $this->icons->path( $url );

			if ( $path ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	private function text( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Start a cíl pro kartu 4:5 — čas a místo, bez data.
	 *
	 * Datum se na plakátu neopakuje, je v datumové kartičce v hlavičce.
	 * Hodnotou je proto samotný čas („6:00–12:00", „do 20:00"); když ho akce
	 * nemá, spadne se na datum, ať sloupec není prázdný.
	 *
	 * @return array<int, array{label: string, value: string, note: string}>
	 */
	private function event_points( array $event ): array {
		$point = function ( string $label, $data ): array {
			if ( ! is_array( $data ) ) {
				$data = array();
			}

			$time = $this->text( $data['time'] ?? '' );
			$date = $this->text( $data['date'] ?? '' );

			if ( '' === $time && '' !== $date ) {
				$timestamp = strtotime( $date );
				$time      = $timestamp ? date_i18n( 'j. n.', $timestamp ) : '';
			}

			return array(
				'label' => $label,
				'value' => $time,
				'note'  => $this->text( $data['place'] ?? '' ),
			);
		};

		return array(
			$point( __( 'START', 'kct' ), $event['start'] ?? array() ),
			$point( __( 'CÍL', 'kct' ), $event['finish'] ?? array() ),
		);
	}

	/**
	 * Pořadatel a místo jedním řádkem: „KČT, odbor Benešov | Benešov, okr. Benešov".
	 *
	 * Části se spojují svislítkem, ne čárkou — v obou už čárky jsou.
	 */
	private function event_organiser_line( array $event ): string {
		$parts = array_filter( array(
			$this->text( $event['organiser']['name'] ?? '' ),
			$this->place_note( $event ),
		) );

		return implode( ' | ', $parts );
	}

	/**
	 * Cesta k uložené mapě akce, nebo prázdný řetězec.
	 *
	 * Mapy vznikají z mapy.cz při zobrazení detailu akce a leží
	 * v uploads/maps pod názvem složeným z ID a souřadnic — viz
	 * themes/kct/template-parts/content-akce.php, kde se generují. Název se
	 * tady skládá stejně, aby se trefil do už existujícího souboru.
	 *
	 * Nic se nestahuje. Souřadnice má 279 akcí z 319 a všechny svoji mapu na
	 * disku mají, protože ji vyrobí první zobrazení detailu. Kdyby tam nebyla,
	 * karta prostě bude bez mapy — stahovat ji uprostřed kreslení obrázku by
	 * znamenalo závislost na cizím serveru v nejhorší možnou chvíli.
	 */
	private function event_map_path( array $event ): string {
		$lng = $event['lng'] ?? '';
		$lat = $event['lat'] ?? '';

		if ( empty( $lng ) || empty( $lat ) ) {
			return '';
		}

		$id = ! empty( $event['db_id'] ) ? $event['db_id'] : ( $event['id'] ?? 0 );

		$file = sprintf(
			'map_%s_%s-%s.jpg',
			$id,
			str_replace( '.', '', (string) round( (float) $lng, 5 ) ),
			str_replace( '.', '', (string) round( (float) $lat, 5 ) )
		);

		$path = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'maps/' . $file;

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Měsíc konání akce (1–12), nebo 0 když se nedá zjistit.
	 *
	 * Slouží k odvození barvy turistické značky na kartě 4:5.
	 */
	private function event_month( array $event ): int {
		$date = $this->text( $event['start']['date'] ?? '' );

		if ( '' === $date ) {
			$date = $this->text( $event['date'] ?? '' );
		}

		$timestamp = '' !== $date ? strtotime( $date ) : false;

		return $timestamp ? (int) date( 'n', $timestamp ) : 0;
	}

	/**
	 * Sdílecí obrázek 4:5 příspěvku pro sdílení fotkou.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_for_post( int $post_id ): ?array {
		$data = $this->post_data( $post_id );

		return $data ? $this->service->social_post( $data, 'post-' . $post_id ) : null;
	}

	/**
	 * Sdílecí obrázek 4:5 akce.
	 *
	 * Bere pole akce, ne ID příspěvku — sdílení sice obsluhuje jen akce
	 * s příspěvkem, ale vyrenderovat kartu jde pro kteroukoli akci a bez toho
	 * by se okrajové případy (tři trasy, dlouhé km, vícedenní) nedaly ověřit:
	 * z 318 akcí má vlastní příspěvek 12.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_for_event( array $event ): ?array {
		$data = $this->event_data( $event );

		return $data ? $this->service->social_event( $data, $this->event_prefix( $event ) ) : null;
	}

	/**
	 * Sdílecí obrázek 4:5 akce, která má vlastní CPT příspěvek.
	 *
	 * Tuhle cestu používá sdílení na Facebook — obsluhuje jen skutečné
	 * příspěvky.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_for_event_post( int $post_id ): ?array {
		if ( get_post_type( $post_id ) !== $this->event_repository->post_type() ) {
			return null;
		}

		$event = $this->events->get_event( $post_id, '' );

		return $event ? $this->social_for_event( $event ) : null;
	}

	/**
	 * Délky tras — jeden řádek na typ akce, který má vyplněné km.
	 *
	 * Hodnota `km` je v datech volný, už naformátovaný text (`12 km`,
	 * `14, 17 km`, `9–35 km`, ale i `individuální trasy` nebo `dle propozic`),
	 * takže se nic nepřepočítává ani nedoplňuje — jen se spojí s názvem typu.
	 *
	 * Typy bez vyplněného km řádek nedostanou; zůstanou jen jako ikona nahoře.
	 *
	 * @return array<int, array{icon: string, text: string}>
	 */
	private function event_routes( array $event ): array {
		$details = $event['details'] ?? array();

		if ( ! is_array( $details ) ) {
			return array();
		}

		$routes = array();

		foreach ( $details as $detail ) {
			if ( ! is_array( $detail ) ) {
				continue;
			}

			$km   = $this->text( $detail['km'] ?? '' );
			$name = $this->text( $detail['name'] ?? '' );

			if ( '' === $km || '' === $name ) {
				continue;
			}

			$icon = $this->text( $detail['icon'] ?? '' );

			$routes[] = array(
				'icon' => '' !== $icon ? (string) ( $this->icons->path( $icon ) ?? '' ) : '',
				/* translators: 1: název typu akce, 2: délky tras. */
				'text' => sprintf( __( '%1$s: %2$s', 'kct' ), $name, $km ),
			);
		}

		return $routes;
	}
}
