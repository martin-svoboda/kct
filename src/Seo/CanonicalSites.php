<?php

namespace Kct\Seo;

use Kct\Repositories\DbEventRepository;
use Kct\Repositories\EventRepository;
use Kct\Settings;

/**
 * Určuje, který web v síti je kanonický pro danou akci.
 *
 * Detail akce renderují všechny weby v síti (DbEventRepository čte sdílenou
 * tabulku přes switch_to_blog), takže bez tohoto pravidla by po zindexování
 * vznikla stejná stránka na více doménách zároveň. Kanonický je:
 *
 * 1. příspěvek typu akce, pokud je s akcí spárovaný přes db_id kdekoli v síti,
 * 2. jinak web pořádajícího odboru, pokud nějaký v síti existuje,
 * 3. jinak oblastní web.
 */
class CanonicalSites {

	/**
	 * Ve verzi klíče je tvar mapy: v2 drží blog_id (ne URL) a klíč `posts`
	 * jako pár blog_id/post_id (ne hotový permalink). Při další změně tvaru
	 * verzi zvýšit — starý transient přežije nasazení o 12 hodin a
	 * nekompatibilní tvar by shodil render fatální chybou (TypeError
	 * v canonical_blog_id() při návratu string místo int).
	 */
	const TRANSIENT = 'kct_canonical_sites_v2';
	const TTL       = 12 * HOUR_IN_SECONDS;

	/**
	 * Mapa nahraná v rámci aktuálního requestu.
	 *
	 * Sitemapa volá url_for() i is_canonical_here() na stovkách akcí za sebou
	 * — bez téhle memoizace by každé volání sáhlo do síťové option přes
	 * get_site_transient().
	 */
	private ?array $map = null;

	public function __construct(
		private DbEventRepository $db_event_repository,
		private EventRepository $event_repository
	) {
	}

	/**
	 * Kanonická adresa detailu akce.
	 *
	 * @param array $event Pole akce z Events::get_event().
	 */
	public function url_for( array $event ): string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( $db_id <= 0 ) {
			return '';
		}

		$map = $this->map();

		// Existující příspěvek je editorsky obohacený, má vlastní slug a je
		// už v akce-sitemap.xml — kanonická adresa je jeho permalink, ne
		// virtuální /akce-db/ stránka.
		if ( isset( $map['posts'][ $db_id ] ) ) {
			list( $post_blog_id, $post_id ) = $map['posts'][ $db_id ];
			$permalink                      = $this->paired_post_permalink( $post_blog_id, $post_id );

			if ( '' !== $permalink ) {
				return $permalink;
			}

			// get_permalink() může vrátit false (např. smazaný příspěvek,
			// jehož invalidace mapy se ještě nestihla projevit) — spadnout na
			// pravidlo podle odboru je lepší než vrátit prázdný řetězec.
		}

		return $this->virtual_url( $db_id, $this->canonical_blog_id( $event, $map ) );
	}

	/**
	 * Je aktuální web kanonický pro tuto akci? Podle toho se plní sitemapa.
	 *
	 * Pro nespárovanou akci je to porovnání s url_for(): kanonická adresa je
	 * adresa aktuálního webu, právě když se virtuální adresa podle
	 * canonical_blog_id() shoduje s virtuální adresou tady — canonical_blog_id()
	 * je totéž pravidlo, které používá url_for() pro sestavení výsledné adresy,
	 * takže se obě metody nemůžou v tomhle případě rozejít.
	 *
	 * U spárované akce vrací false vždycky, i na webu, kde příspěvek leží —
	 * url_for() tam vrátí permalink příspěvku, ne virtuální adresu, takže by
	 * se stejně nikdy nerovnaly. To je pro sitemapu správně: spárovaná akce
	 * patří do akce-sitemap.xml podle příspěvku, ne sem podle virtuální
	 * stránky. Rozhoduje se ale rovnou přes isset(), bez volání url_for() —
	 * ta by u spárované akce zbytečně počítala permalink (viz
	 * paired_post_permalink()), a sitemapa touhle metodou prochází stovky
	 * akcí za sebou.
	 */
	public function is_canonical_here( array $event ): bool {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( $db_id <= 0 ) {
			return false;
		}

		$map = $this->map();

		if ( isset( $map['posts'][ $db_id ] ) ) {
			return false;
		}

		return $this->virtual_url( $db_id, $this->canonical_blog_id( $event, $map ) )
			=== $this->virtual_url( $db_id, get_current_blog_id() );
	}

	/** Adresa virtuální stránky /akce-db/{id} na daném webu. */
	private function virtual_url( int $db_id, int $blog_id ): string {
		return trailingslashit( get_home_url( $blog_id ) ) . 'akce-db/' . $db_id . '/';
	}

	/** ID webu, který je pro akci kanonický podle pravidla odbor → oblast. */
	private function canonical_blog_id( array $event, array $map ): int {
		$department = $this->department_of( $event );

		if ( $department && isset( $map['departments'][ $department ] ) ) {
			return $map['departments'][ $department ];
		}

		// Mapa bez vyplněného oblastního webu se necachuje (viz map()), ale
		// v rámci jednoho requestu se přesto může vrátit — bez cizího webu,
		// na který ukázat, je nejmíň špatná volba aktuální web.
		return $map['region'] ? (int) $map['region'] : get_current_blog_id();
	}

	/**
	 * Kód pořádajícího odboru.
	 *
	 * U CPT akcí klíč `department` v poli chybí — Events::get_event() prochází
	 * jen klíče příspěvku a EventModel tuhle vlastnost zakomentovanou nemá.
	 * Podmínka musí rozlišovat chybějící klíč od hodnoty 0 — akcí, kde je
	 * department v datech KČT skutečně 0.0 (bez odboru), je 31 a mají klíč
	 * přítomný; dotahovat k nim znovu z DB by bylo zbytečné a stejně by to
	 * vrátilo tutéž nulu.
	 *
	 * Veřejná i pro DepartmentLink::for_event() — ten potřebuje kód pořádajícího
	 * odboru pro odkaz na jeho stránku a bez týhle metody by musel tutéž
	 * nekonzistenci (DB akce vs. CPT akce) řešit znovu, s rizikem, že se časem
	 * rozejde s tím, jak podle pořadatele určuje kanonický web tahle třída.
	 */
	public function department_of( array $event ): string {
		if ( array_key_exists( 'department', $event ) ) {
			return $this->code( $event['department'] );
		}

		if ( empty( $event['db_id'] ) ) {
			return '';
		}

		try {
			$db_event = $this->db_event_repository->get_by_db_id( (int) $event['db_id'] );
		} catch ( \Throwable $e ) {
			// Volá se i z wp_head() při vykreslování stránky — chybějící
			// kanonikál je nesrovnatelně menší škoda než shozený render celé
			// stránky kvůli výjimce z repozitáře.
			return '';
		}

		return $db_event ? $this->code( $db_event->department ) : '';
	}

	/**
	 * Sjednotí kód odboru/oblasti na řetězec bez ohledu na to, jak je uložený —
	 * `id_code` v kct_options bývá podle webu int, float i string, `department`
	 * v wp_db_events je vždy float.
	 *
	 * Převod přes sprintf( '%d', ... ) nepřežije vedoucí nulu, ale kódy KČT
	 * (třímístné oblasti i šestimístné odbory) vedoucí nulou nezačínají, takže
	 * to tu nevadí.
	 */
	private function code( $value ): string {
		return is_numeric( $value ) ? sprintf( '%d', $value ) : trim( (string) $value );
	}

	/**
	 * Permalink spárovaného příspěvku, případně prázdný řetězec.
	 *
	 * Ukládá se blog_id + post_id, ne hotový permalink — jinak by se do mapy
	 * vrátil tentýž problém jako u home_url (viz map()), jen spouštěný slugem
	 * místo schématu: po změně slugu by kanonikál ze všech webů mířil na
	 * starou adresu, kterou wp_old_slug_redirect() přesměruje (zpátky
	 * "kanonikál ukazuje na přesměrování"), a po odpublikování příspěvku by
	 * kanonikál mířil na 404 a akce by ze sítě zmizela úplně, protože
	 * is_canonical_here() by kdekoli vrátila false. Adresa se skládá až tady,
	 * z aktuálního stavu příspěvku.
	 */
	private function paired_post_permalink( int $blog_id, int $post_id ): string {
		if ( get_current_blog_id() === $blog_id ) {
			return (string) ( get_permalink( $post_id ) ?: '' );
		}

		try {
			switch_to_blog( $blog_id );
			$permalink = get_permalink( $post_id );
		} finally {
			restore_current_blog();
		}

		return (string) ( $permalink ?: '' );
	}

	/**
	 * Mapa `department → blog_id`, `region → blog_id` a `db_id → [ blog_id,
	 * post_id ]` spárovaného příspěvku.
	 *
	 * Neprochází weby přes switch_to_blog() — get_blog_option() a
	 * get_home_url( $blog_id ) umí číst cizí web bez přepnutí, což je rychlejší
	 * a nehrozí u toho, že by výjimka uprostřed cyklu nechala zbytek requestu
	 * vykreslit se pod cizím webem.
	 *
	 * Ukládá se blog_id, ne hotová URL — get_home_url() bez blog_id vynucuje
	 * https, jen když je aktuální request is_ssl(); mapa se ale staví jednou
	 * (klidně z WP-CLI nebo cronu, kde is_ssl() je vždy false) a porovnává se
	 * pak v HTTPS requestech. Blog_id na tohle citlivé není, schéma se dořeší
	 * až při čtení v aktuálním requestu.
	 */
	public function map(): array {
		if ( null !== $this->map ) {
			return $this->map;
		}

		$cached = get_site_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['departments'], $cached['region'], $cached['posts'] ) ) {
			$this->map = $cached;

			return $cached;
		}

		try {
			$map = $this->build_map();
		} catch ( \Throwable $e ) {
			// Stejný důvod jako u department_of(): build_map() prochází sedm
			// switch_to_blog() a get_permalink(), který si o cizí web může
			// zavolat i filtry třetích stran (post_type_link apod.) — výjimka
			// odtud nesmí shodit render, který mapu potřebuje jen k sestavení
			// jednoho odkazu. Prázdná mapa se díky podmínce níž nezacachuje,
			// takže to příští request zkusí znovu.
			$map = $this->empty_map();
		}

		// Necachovat mapu bez oblastního webu: canonical_blog_id() by se pak
		// pro každou akci bez odboru v síti vrátil na aktuální web (viz jeho
		// komentář) — každý web v síti by se tak na 12 hodin prohlásil
		// kanonickým sám pro sebe, pro všechny akce. Bez cache to při dalším
		// requestu aspoň zkusí spravit znovu.
		if ( $map['region'] ) {
			set_site_transient( self::TRANSIENT, $map, self::TTL );
		}

		$this->map = $map;

		return $map;
	}

	/** Prázdná mapa — výchozí tvar pro build_map() i degradaci při výjimce. */
	private function empty_map(): array {
		return array(
			'departments' => array(),
			'region'      => 0,
			'posts'       => array(),
		);
	}

	/** Zahodí mapu — volá se při změně nastavení, složení sítě nebo slugu spárovaného příspěvku. */
	public function flush(): void {
		delete_site_transient( self::TRANSIENT );
		$this->map = null;
	}

	/**
	 * Projde weby v síti a sestaví mapu jedním dotazem na web (ne na akci).
	 *
	 * `public => 1` vyřadí i weby jako šablona nebo staging (public = 0) rovnou
	 * podle jejich stavu, ne až podle toho, jestli mají vyplněný id_code —
	 * `get_sites()` bez těchhle filtrů vrací i archivované, smazané a spam
	 * weby (WP_Site_Query je bez explicitního zadání do WHERE nezahrne).
	 */
	private function build_map(): array {
		$map = $this->empty_map();

		$blog_ids = get_sites(
			array(
				'number'   => 0,
				'public'   => 1,
				'archived' => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'fields'   => 'ids',
			)
		);

		foreach ( $blog_ids as $blog_id ) {
			$blog_id = (int) $blog_id;
			$options = get_blog_option( $blog_id, Settings::KEY, array() );
			$code    = isset( $options['id_code'] ) ? $this->code( $options['id_code'] ) : '';

			if ( 6 === strlen( $code ) && ! isset( $map['departments'][ $code ] ) ) {
				$map['departments'][ $code ] = $blog_id;
			} elseif ( 3 === strlen( $code ) && ! $map['region'] ) {
				$map['region'] = $blog_id;
			}

			// Kolize (týž db_id spárovaný na dvou webech) rozhodne první web
			// v pořadí, ve kterém je tenhle cyklus prochází — first-wins, jen
			// implicitně přes +=, protože klíče db_id, které tu už jsou,
			// zůstanou. V síti dnes nejsou disjunktní páry žádné dvě, ale
			// kdyby byly, vyhraje web s nižším pořadím v get_sites(), ne
			// nutně nižší blog_id.
			$map['posts'] += $this->paired_posts_on_blog( $blog_id );
		}

		return $map;
	}

	/**
	 * Spárované příspěvky na jednom webu jako `db_id → [ blog_id, post_id ]`.
	 *
	 * Jeden dotaz na web místo EventRepository::find_by_db_id() volaného pro
	 * každou akci zvlášť — v sitemapě s 318 akcemi by to zdvojnásobilo počet
	 * dotazů. Dotaz běží uvnitř switch_to_blog(), takže $wpdb->postmeta a
	 * $wpdb->posts míří samy na tabulky správného webu — switch_to_blog() je
	 * tu nutný tak jako tak kvůli permalink strukturám (viz níže), takže není
	 * důvod ho pro dotaz obcházet přes get_blog_prefix().
	 *
	 * Tahle metoda ukládá jen blog_id a post_id, ne permalink — ten se skládá
	 * až v paired_post_permalink() v okamžiku čtení, přes vlastní
	 * switch_to_blog(). I tak platí stejný předpoklad jako by platil tady:
	 * správnost permalinku cizího webu stojí na tom, že CPT `akce` má v síti
	 * všude stejný permastruct a všechny weby mají hezké permalinky —
	 * switch_to_blog() totiž $wp_rewrite neresetuje, takže by jinak cizí web
	 * mohl vracet adresu poskládanou podle pravidel webu, odkud request přišel.
	 * Dnes to platí (ověřeno: url_for() pro tutéž spárovanou akci vyšel stejně
	 * volaný ze tří webů s různým permalink_structure), ale je to nezapsaný
	 * předpoklad, na kterém paired_post_permalink() stojí.
	 *
	 * try/finally zajistí restore_current_blog() i při výjimce z dotazu nebo
	 * z filtrů volaných uvnitř switch_to_blog().
	 */
	private function paired_posts_on_blog( int $blog_id ): array {
		global $wpdb;

		$posts = array();

		try {
			switch_to_blog( $blog_id );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_value AS db_id
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = 'db_id'
					AND pm.meta_value NOT IN ( '', '0' )
					AND p.post_type = %s
					AND p.post_status = 'publish'",
					$this->event_repository->post_type()
				)
			);

			foreach ( $rows as $row ) {
				$db_id = (int) $row->db_id;

				if ( $db_id > 0 ) {
					$posts[ $db_id ] = array( $blog_id, (int) $row->post_id );
				}
			}
		} finally {
			restore_current_blog();
		}

		return $posts;
	}
}
