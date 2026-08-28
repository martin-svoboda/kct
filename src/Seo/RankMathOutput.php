<?php

namespace Kct\Seo;

/**
 * Přepíše hodnoty, které Rank Math na detailu akce skládá špatně.
 *
 * Filtry se registrují až uvnitř kontextu akce (volá se z EventSeo::setup()
 * na hooku `wp`), takže na ostatních stránkách webu vůbec nevzniknou.
 */
class RankMathOutput implements EventSeoOutput {

	private array $event      = array();
	private string $canonical = '';
	private bool $is_single   = false;

	public function __construct( private EventSeoData $data ) {
	}

	public function render( array $event, string $canonical, bool $is_single ): void {
		$this->event     = $event;
		$this->canonical = $canonical;
		$this->is_single = $is_single;

		add_filter( 'rank_math/json_ld', array( $this, 'filter_json_ld' ), 20 );

		// Frontend::connect_schema_entities() (priorita 99 na týž filtr)
		// v connect_properties() u Event entity bezpodmínečně přepíše
		// `organizer` na `[ '@id' => $data['publisher']['@id'] ]` — tedy
		// odkazem na organizaci provozovatele webu, viz add_prop_publisher()
		// v class-jsonld.php. Na oblastním webu, kde se vypisují akce cizích
		// odborů, je to nepravdivé tvrzení (skutečný pořadatel je odbor, ne
		// oblast). Musí běžet AŽ PO Rank Mathu, proto priorita 100 — a nesmí
		// nahradit filter_json_ld() na 20 výš, protože RM na devadesátce
		// devítce dělá i užitečnou práci (@id, isPartOf, mainEntityOfPage),
		// o kterou nechceme přijít. NERUŠIT / NEPŘESOUVAT bez ověření pořadí.
		//
		// PHP_INT_MAX místo 100: kdyby Rank Math svou prioritu někdy zvedl,
		// oprava by se tiše vrátila do původního stavu a nikdo by si toho
		// nevšiml, protože ve schematu by prostě zase stálo jméno oblasti.
		add_filter( 'rank_math/json_ld', array( $this, 'filter_event_organizer' ), PHP_INT_MAX );

		// U CPT příspěvku skládá titulek, popisek i kanonickou adresu Rank Math
		// správně (a redakce je může ručně přepsat) — přepisovat je by editorovi
		// sebralo kontrolu. Registruje se proto jen JSON-LD (výše). Ten ale i
		// tak přepíše $data['richSnippet'], které si Rank Mathova vlastní
		// Singular třída dřív v témž filtru (priorita 10, my jedeme na 20) už
		// naplnila podle volby v Titles & Meta — je to vědomý kompromis, ne
		// přehlédnutí. Riziko je malé: vlastní schéma z postranního panelu
		// editoru jde pod klíč `schema-*`, kterého se nedotýkáme.
		if ( $is_single ) {
			// Bez tohohle Rank Math na CPT akci nevypíše vůbec nic — ani
			// WebSite, ani WebPage, ani BreadcrumbList. Nastavení "Event" v
			// Titles & Meta totiž neprojde whitelistem v
			// get_default_schema_type(). Na virtuální stránce (kde
			// is_singular() je false) je tenhle filtr no-op —
			// can_add_global_entities() tam vrací true už sama od sebe —
			// proto se registruje jen tady.
			add_filter( 'rank_math/schema/add_global_entities', '__return_true' );
			return;
		}

		add_filter( 'rank_math/frontend/title', array( $this, 'filter_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_description' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_canonical' ) );

		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'filter_title' ) );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'filter_description' ) );
		add_filter( 'rank_math/opengraph/facebook/og_url', array( $this, 'filter_canonical' ) );
		add_filter( 'rank_math/opengraph/facebook/og_type', array( $this, 'filter_og_type' ) );

		add_filter( 'rank_math/opengraph/twitter/twitter_title', array( $this, 'filter_title' ) );
		add_filter( 'rank_math/opengraph/twitter/twitter_description', array( $this, 'filter_description' ) );

		// og_image/twitter_image filtrují až finální hodnotu tagu — ten se ale
		// vypíše, jen když Image třída už nějaký obrázek našla. Bez vlastního
		// obrázku, výchozího OG obrázku v nastavení Rank Mathu i featured image
		// (virtuální stránka žádný post nemá) žádný neexistuje a tag se
		// nevypíše vůbec, takže by se pozdní filtr nikdy nezavolal. `image` je
		// dřívější filtr uvnitř Image::add_image() — ten proběhne vždycky,
		// i bez nalezeného obrázku (viz komentář „This allows … filter to be
		// used even if no image is set“ v Rank Mathu), takže je to jediné
		// spolehlivé místo pro doplnění obrázku.
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'filter_image' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'filter_image' ) );
	}

	/**
	 * Doplní Event a opraví entitu stránky.
	 *
	 * Na virtuální stránce je `is_home()` pravdivé, takže Rank Math WebPage
	 * otypuje jako CollectionPage a dá jí název a adresu výpisu blogu. Detail
	 * akce ale rozcestník není.
	 */
	public function filter_json_ld( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$schema = $this->data->event_schema( $this->event, $this->canonical, $this->image_url() );

		if ( $schema ) {
			$data['richSnippet'] = $schema;
		}

		if ( ! $this->is_single && isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
			$data['WebPage']['@type'] = 'WebPage';
			$data['WebPage']['name']  = $this->data->title( $this->event );

			if ( $this->canonical ) {
				$data['WebPage']['url'] = $this->canonical;
				$data['WebPage']['@id'] = $this->canonical . '#webpage';
			}
		}

		return $data;
	}

	/**
	 * Vrátí Rank Mathem přepsaného pořadatele Event entity zpátky na
	 * skutečného pořadatele akce.
	 *
	 * Běží na prioritě 100 — viz komentář u registrace v render(). Prochází
	 * všechny entity grafu (ne jen `richSnippet`), protože klíč u CPT
	 * příspěvku může být i `schema-*` podle nastavení v Titles & Meta.
	 *
	 * @param mixed $data Pole JSON-LD entit z Rank Mathu.
	 * @return mixed
	 */
	public function filter_event_organizer( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$organizer = $this->data->organizer( $this->event );

		foreach ( $data as $key => $schema ) {
			if ( ! is_array( $schema ) || empty( $schema['@type'] ) ) {
				continue;
			}

			$types    = array_map( 'strtolower', (array) $schema['@type'] );
			$is_event = false;

			// Shoda podřetězcem, ne přesná — Rank Math klíč přepisuje přes
			// Str::contains( 'event', $type ), takže sáhne i na SportsEvent
			// nebo EventSeries. Kdybychom hledali jen přesné 'event', zůstala
			// by u takové entity jeho nepravdivá hodnota.
			foreach ( $types as $type ) {
				if ( false !== strpos( $type, 'event' ) ) {
					$is_event = true;
					break;
				}
			}

			if ( ! $is_event ) {
				continue;
			}

			if ( $organizer ) {
				$data[ $key ]['organizer'] = $organizer;
				continue;
			}

			// Bez pořadatele v datech klíč zahodíme, ne že ho necháme být:
			// Rank Math ho na prioritě 99 už nastavil na odkaz na organizaci
			// provozovatele webu, takže předčasný návrat by v grafu nechal
			// přesně tu lež, kvůli které tenhle filtr vznikl.
			unset( $data[ $key ]['organizer'] );
		}

		return $data;
	}

	/**
	 * Titulek se sufixem názvu webu.
	 *
	 * Rank Math sufix doplňuje ze šablony `%title% %sep% %sitename%`, ale ta se
	 * na virtuální stránce vyhodnotí proti stránce page_for_posts. Skládá se
	 * proto tady, ze stejného oddělovače, jaký má web nastavený.
	 *
	 * Visí zároveň na filtru frontend titulku i na OG/Twitter titulku (ten druhý
	 * dostane jako $title hodnotu, kterou už jednou prošla tahle metoda). Musí
	 * proto být idempotentní — vždycky vrací nově sestavenou hodnotu bez ohledu
	 * na $title, ne že by k němu sufix připojovala. Kdyby to dělala, druhé
	 * zavolání by sufix zdvojilo.
	 *
	 * @param string $title Původní hodnota z Rank Mathu (nepoužije se, viz výše).
	 * @return string
	 */
	public function filter_title( $title ) {
		$own = $this->data->title( $this->event );

		if ( '' === $own ) {
			return $title;
		}

		$separator = html_entity_decode( \RankMath\Helper::get_settings( 'titles.title_separator' ) ?: '-', ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return $own . ' ' . $separator . ' ' . get_bloginfo( 'name' );
	}

	/**
	 * Popisek složený z dat akce, jinak necháme hodnotu z Rank Mathu.
	 *
	 * @param string $description Původní hodnota z Rank Mathu.
	 * @return string
	 */
	public function filter_description( $description ) {
		return $this->data->description( $this->event ) ?: $description;
	}

	/**
	 * Kanonická adresa akce spočtená v EventSeo::context(), jinak necháme
	 * hodnotu z Rank Mathu.
	 *
	 * @param string $canonical Původní hodnota z Rank Mathu.
	 * @return string
	 */
	public function filter_canonical( $canonical ) {
		return $this->canonical ?: $canonical;
	}

	/**
	 * Obrázek akce, jinak logo oblasti — viz image_url(). Necháme hodnotu
	 * z Rank Mathu, jen když ani jedno z toho není k dispozici.
	 *
	 * @param string $image Původní hodnota z Rank Mathu (typicky prázdná, viz
	 *                       komentář u registrace filtru v render()).
	 * @return string
	 */
	public function filter_image( $image ) {
		return $this->image_url() ?: $image;
	}

	/**
	 * Akce je událost, ne rozcestník — `website` by tu byl nesmysl.
	 *
	 * @param string $type Původní typ z Rank Mathu.
	 * @return string
	 */
	public function filter_og_type( $type ) {
		return 'article';
	}

	/**
	 * Obrázek akce, jinak logo oblasti.
	 *
	 * Vlastní obrázek má 6 z 318 akcí, takže záloha je běžný stav. Bez ní by
	 * sdílený odkaz na Facebooku vypadal jako holý text. Výběr obrázku (a
	 * fallback na custom_logo) je sdílený se StandaloneOutput přes
	 * EventSeoData::image(), ať se sémantika nepíše na dvou místech zvlášť.
	 */
	private function image_url(): string {
		return $this->data->image( $this->event )['url'];
	}
}
