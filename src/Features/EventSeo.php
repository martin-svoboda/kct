<?php

namespace Kct\Features;

use Kct\Repositories\EventRepository;
use Kct\Seo\CanonicalSites;
use Kct\Seo\EventSeoData;
use Kct\Seo\EventSeoOutput;
use Kct\Seo\RankMathOutput;
use Kct\Seo\StandaloneOutput;
use Kct\Settings;

/**
 * SEO detailů akcí.
 *
 * Virtuální stránky /akce-db/{id} vznikají rewrite pravidlem na index.php?db_id=…,
 * takže je WordPress vyhodnotí jako výpis blogu a Rank Math jim dá titulek
 * a kanonickou adresu stránky nastavené jako page_for_posts. Feature to přepíše
 * a doplní strukturovaná data.
 */
class EventSeo {

	/**
	 * Pole akce pro filter_event_breadcrumb() — nastaví ho setup(), protože
	 * Rank Math volá filtr `rank_math/frontend/breadcrumb/items` až při
	 * skládání výstupu, ne hned při registraci.
	 *
	 * @var array
	 */
	private array $breadcrumb_event = array();

	/**
	 * Kanonická adresa akce pro filter_event_breadcrumb() — poslední
	 * položka drobečku (aktuální stránka) v Rank Mathu nese svůj odkaz i
	 * jako "current" prvek (get_breadcrumb() ho jen nevykreslí jako <a>);
	 * schema builder (class-breadcrumbs.php v Rank Mathu) prázdný odkaz
	 * naopak bere jako důvod celou položku ze BreadcrumbList vynechat.
	 *
	 * @var string
	 */
	private string $breadcrumb_canonical = '';

	public function __construct(
		private Events $events,
		private EventSeoData $data,
		private CanonicalSites $sites,
		private EventRepository $event_repository
	) {
		add_action( 'wp', array( $this, 'handle_event_request' ), 1 );
		add_action( 'wp', array( $this, 'setup' ) );
		add_filter( 'rank_math/sitemap/providers', array( $this, 'sitemap_provider' ) );

		// Přesměrování 404 na NOBLOGREDIRECT (wp-config.php) je zrušené
		// celoplošně ve Frontend.php — nastavení stavového kódu 404 tady
		// (viz send_404()) tak samo o sobě stačí, aby se detail akce doručil
		// jako skutečná 404. Dřív tenhle konstruktor registroval i vlastní
		// filtr `blog_redirect_404`, který přesměrování potlačoval jen pro
		// 404 nastavenou tady — po celoplošné opravě by dělal totéž znovu,
		// jen úžeji, takže je pryč.

		// Mapa webů nesmí přežít změnu, která ji zneplatňuje.
		add_action( 'wp_initialize_site', array( $this, 'flush_sites' ) );
		add_action( 'wp_delete_site', array( $this, 'flush_sites' ) );
		add_action( 'update_option_' . Settings::KEY, array( $this, 'flush_sites' ) );

		// Mapa drží i párování akcí na příspěvky, takže ji zneplatní i uložení
		// nebo smazání akce — jinak by kanonická adresa až půl dne ukazovala
		// na příspěvek, který už neexistuje. Obě akce zúžené na typ 'akce':
		// deleted_post bez přípony by vypálilo flush i na smazání každé
		// revize a přílohy v celé síti (řádově tisíce), zbytečně zahazovalo
		// mapu i tam, kde se párování akcí vůbec netýká. Koš i odpublikování
		// jdou přes wp_update_post(), takže je pokrývá save_post_akce —
		// deleted_post_akce zachytí jen tvrdé smazání.
		add_action( 'save_post_' . $this->event_repository->post_type(), array( $this, 'flush_sites' ) );
		add_action( 'deleted_post_' . $this->event_repository->post_type(), array( $this, 'flush_sites' ) );
	}

	public function flush_sites(): void {
		$this->sites->flush();
	}

	/** Sitemapa akcí se registruje jen tam, kde je Rank Math. */
	public function sitemap_provider( $providers ) {
		if ( class_exists( 'RankMath' ) ) {
			$providers[] = kct_container()->get( \Kct\Seo\EventSitemapProvider::class );
		}

		return $providers;
	}

	/**
	 * Obsluha požadavku na virtuální stránku akce.
	 *
	 * Řeší dva stavy, které by jinak skončily špatně: akce, která v tabulce
	 * není (dřív HTTP 500), a akce, která má vlastní CPT příspěvek, takže by
	 * tentýž obsah žil na dvou adresách.
	 *
	 * Visí na 'wp' s prioritou 1, ne na 'template_redirect'. Rank Math skládá
	 * celou hlavičku (titulek, popisek, canonical) na akci 'wp' — pozdější
	 * set_404() na template_redirect by sice stihl přepsat status hlavičku,
	 * ale Rank Math by v tu chvíli měl Paper už hotový podle kontextu výpisu
	 * blogu: stránka by vrátila 404, ale s titulkem a kanonickou adresou
	 * stránky page_for_posts. To je horší než původní stav (500) — vyhledávač
	 * by dostal 404 s kanonikálem na existující, cizí stránku. Priorita 1
	 * zajistí, že se stihne dřív než integrace Rank Mathu i než setup().
	 * Nepřesouvat zpátky na template_redirect bez vyřešení tohohle pořadí.
	 */
	public function handle_event_request(): void {
		$db_id = $this->db_id_from_query();

		// null = query var vůbec není nastavený, běžná stránka mimo /akce-db/.
		if ( null === $db_id ) {
			return;
		}

		// 0 = query var je nastavený, ale neplatný — pole (?db_id[]=…), 'abc',
		// '00' nebo '23954x'. Takové adresy nesmí skončit dvoustovkou
		// s prázdným detailem — je to neexistující akce stejně jako vymyšlené
		// číslo, a bez tohohle by šlo vyrobit libovolně mnoho indexovatelných
		// adres s obsahem výpisu blogu (a content-akce.php by k tomu ještě
		// hlásil PHP warningy o chybějících klíčích v prázdném poli akce).
		if ( 0 === $db_id ) {
			$this->send_404();

			return;
		}

		// Převedená akce má vlastní příspěvek. Trvalé přesměrování, protože ten
		// stav je konečný — jednou převedená akce se zpátky nevrací.
		//
		// Hledá se přes find_by_db_id() na aktuálním webu, ne přes síťovou
		// mapu z CanonicalSites — a to záměrně, ne kvůli výkonu (request se
		// stejně ukončí exitem dřív, než by mapu někdo potřeboval). Je to
		// sémantický rozdíl: přesměrování smí rozhodovat jen web, kde
		// spárovaný příspěvek skutečně leží. Proto kctricany.test/akce-db/24065/
		// správně vrací 200, ne 301, i když je akce spárovaná na oblastním
		// webu — cizí web má nechat kanonikál na CanonicalSites, ne
		// přesměrovávat pryč (a mezi doménami v síti by to wp_safe_redirect()
		// stejně odmítl).
		$post_id   = $this->event_repository->find_by_db_id( $db_id );
		$permalink = $post_id ? (string) ( get_permalink( $post_id ) ?: '' ) : '';

		// wp_validate_redirect() dělá přesně tu kontrolu, kterou by jinak
		// dělal wp_safe_redirect() těsně předtím, než cíl použije (nebo tiše
		// nahradí admin_url()) — vlastní porovnání hostname by ji jen
		// nepřesně duplikovalo (např. by neprošlo přes wp_sanitize_redirect()
		// a nectilo filtr allowed_redirect_hosts). Prázdný $target pokrývá
		// obojí: nespárovaný permalink i cíl, který validaci neprošel.
		$target = $permalink ? wp_validate_redirect( $permalink, '' ) : '';

		if ( $target ) {
			// phpcs:ignore WordPress.Security.SafeRedirect -- cíl prošel wp_validate_redirect() výš; wp_safe_redirect() by ho jen tiše nahradil admin_url().
			wp_redirect( $target, 301, 'kct' );
			exit;
		}

		// Sem se dojde i bez spárovaného příspěvku, i s permalinkem, který
		// selhal na kontrole výš (prázdný, protože get_permalink() vrátilo
		// false, např. u příspěvku smazaného těsně před tímhle requestem,
		// nebo neprošel wp_validate_redirect()) — pokračovat normálním
		// renderem je pořád lepší než tichá bílá stránka s exitem po
		// neodeslaném přesměrování.

		// Akce neexistuje — buď je db_id z URL vymyšlené, nebo ji import smazal,
		// protože ji feed označil jako zrušenou. Vyhledávač musí dostat 404,
		// jinak si mrtvou adresu podrží v indexu. Platí i pro nedoručený
		// redirect výš: prázdná data v databázové tabulce znamenají 404 bez
		// ohledu na to, jestli má akce spárovaný příspěvek.
		if ( ! $this->events->get_event( 0, $db_id ) ) {
			$this->send_404();
		}
	}

	/**
	 * Normalizuje syrovou hodnotu query varu `db_id` na kladné celé číslo.
	 *
	 * Sjednocuje handle_event_request() a context() na jedno místo, aby se
	 * nemohly rozejít v tom, co považují za platné db_id. `db_id` je veřejný
	 * query var, takže WordPress u něj v WP::parse_request() zachovává i pole
	 * poslané zvenku (?db_id[]=23954) — musí se odchytit ještě před `(int)`,
	 * na kterém by z neprázdného pole tiše vypadla 1 (bez varování). Na
	 * pořadí kontrol záleží: `'' === $raw` běží první, ale nad polem je to
	 * striktní porovnání dvou různých typů, takže vrátí `false` a pole
	 * korektně propadne až na `is_scalar()` níž.
	 *
	 * @return int|null Kladné db_id; `0` pro nastavenou, ale neplatnou hodnotu
	 *                   — pole i cokoli jiného neplatného (patří do
	 *                   handle_event_request() jako 404); `null` pro query
	 *                   var, který vůbec není nastavený (normální stránka
	 *                   mimo /akce-db/).
	 */
	private function db_id_from_query(): ?int {
		$raw = get_query_var( 'db_id' );

		// Nenastavený query var — běžná stránka mimo /akce-db/.
		if ( '' === $raw ) {
			return null;
		}

		// Pole (?db_id[]=…) je stejně neplatná hodnota jako 'abc'. Musí se
		// odchytit před (int), ze kterého by z neprázdného pole tiše vypadla 1.
		if ( ! is_scalar( $raw ) ) {
			return 0;
		}

		$db_id = (int) $raw;

		return ( $db_id > 0 && (string) $db_id === (string) $raw ) ? $db_id : 0;
	}

	/**
	 * Nastaví 404 pro virtuální stránku akce.
	 *
	 * Zapisuje db_id jen do $wp_query (přes set_query_var()) — šablona
	 * index.php čte výhradně přes get_query_var(), takže to stačí;
	 * $GLOBALS['wp']->query_vars['db_id'] si původní hodnotu podrží, ale nic
	 * ho po tomhle bodu requestu nečte.
	 */
	private function send_404(): void {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		// Šablona index.php větví podle db_id dřív než podle is_404(),
		// takže by i na 404 vykreslila prázdný detail akce.
		set_query_var( 'db_id', '' );
	}

	/** Rozpozná kontext a předá data výstupu. */
	public function setup(): void {
		$context = $this->context();

		if ( ! $context ) {
			return;
		}

		list( $event, $canonical, $is_single ) = $context;

		// Jen na virtuální stránce (index.php?db_id=…), ne na CPT příspěvku
		// (/akce/{slug}/) — tam Rank Math trail skládá sám a správně, díky
		// has_archive u EventPostType. class_exists(): filtr je specifický
		// pro Rank Math, na webu bez něj (StandaloneOutput) by se nikdy
		// nezavolal, ale netřeba ho tam vůbec registrovat.
		if ( ! $is_single && class_exists( 'RankMath' ) ) {
			$this->breadcrumb_event     = $event;
			$this->breadcrumb_canonical = $canonical;
			add_filter( 'rank_math/frontend/breadcrumb/items', array( $this, 'filter_event_breadcrumb' ) );
		}

		$output = $this->output();

		if ( $output ) {
			$output->render( $event, $canonical, $is_single );
		}
	}

	/**
	 * Nahradí drobečky na virtuální stránce akce.
	 *
	 * Rewrite pravidlo na index.php?db_id=… nemá vlastní typ požadavku —
	 * WordPress ho vyhodnotí jako výpis blogu, takže by Rank Math sestavil
	 * „Úvod / Aktuality a zprávy“. Pro detail akce to nedává smysl; trail má
	 * být „Úvod / Akce / {název akce}“.
	 *
	 * @param array $crumbs Trail, jak ho sestavil Rank Math — pole trojic
	 *                       [ název, odkaz, 'hide_in_schema' => bool ].
	 *
	 * @return array
	 */
	public function filter_event_breadcrumb( array $crumbs ): array {
		$title = trim( (string) ( $this->breadcrumb_event['title'] ?? '' ) );

		// Bez názvu akce (data v tabulce chybí) je bezpečnější ponechat, co
		// spočítal Rank Math, než vrátit trail s prázdnou poslední položkou.
		if ( '' === $title ) {
			return $crumbs;
		}

		// První prvek, pokud existuje a vede na homepage, přebíráme beze
		// změny — respektuje uživatelův label i odkaz z nastavení Rank
		// Mathu (Úvod). Poznává se podle odkazu, ne podle nastavení
		// breadcrumbs_home — ať sedí i kdyby ho Rank Math někdy vyhodnotil
		// jinak, než čekáme.
		$home_link = untrailingslashit( home_url( '/' ) );
		$home      = ( $crumbs && untrailingslashit( (string) ( $crumbs[0][1] ?? '' ) ) === $home_link )
			? array( $crumbs[0] )
			: array();

		$post_type    = $this->event_repository->post_type();
		$type_object  = get_post_type_object( $post_type );
		$archive_link = (string) get_post_type_archive_link( $post_type );

		// Archiv jen s odkazem — bez něj by ho Rank Math vykreslil stylem
		// aktuální stránky (get_breadcrumb() bere prázdný odkaz jako důvod
		// nedělat z položky <a>, ne jen u opravdu poslední položky) a schema
		// builder by ji z BreadcrumbList rovnou zahodil (stejné pravidlo,
		// které jinde v týhle metodě využíváme pro aktuální stránku).
		// get_post_type_archive_link() vrací false, když typ nemá archiv —
		// u EventPostType (has_archive) dnes nenastane, ale radši bez
		// vizuálně zavádějící položky navíc, než na to spoléhat.
		$items = array();

		if ( '' !== $archive_link ) {
			$items[] = array(
				$type_object->labels->name,
				$archive_link,
				'hide_in_schema' => false,
			);
		}

		$items[] = array(
			$title,
			$this->breadcrumb_canonical,
			'hide_in_schema' => false,
		);

		return array_merge( $home, $items );
	}

	/**
	 * @return array|null [pole akce, kanonická adresa, je to CPT příspěvek?]
	 */
	private function context(): ?array {
		// null (nenastavený) i 0 (neplatný, viz db_id_from_query()) mají spadnout
		// dál na kontrolu is_singular() — 0 se sem dostane, jen když
		// handle_event_request() výš (priorita 1) request už poslal do 404,
		// takže is_singular() na CPT akce stejně bude false.
		$db_id = $this->db_id_from_query();

		if ( $db_id ) {
			$event = $this->events->get_event( 0, $db_id );

			return $event ? array( $event, $this->sites->url_for( $event ), false ) : null;
		}

		if ( is_singular( $this->event_repository->post_type() ) ) {
			$post_id = get_queried_object_id();
			$event   = $this->events->get_event( $post_id, '' );

			// U příspěvku je kanonická adresa jeho permalink — příspěvek žije
			// na tomhle webu a Rank Math ho určuje správně. get_permalink()
			// může vrátit false (smazaný příspěvek); bez explicitního (string)
			// by se to bez strict_types tiše přetypovalo na '', a další úkol
			// do téhle větve přidá JSON-LD, kde by to nebylo vidět.
			return $event ? array( $event, (string) ( get_permalink( $post_id ) ?: '' ), true ) : null;
		}

		return null;
	}

	/**
	 * Implementace výstupu podle toho, jaký SEO plugin má web aktivní.
	 *
	 * StandaloneOutput vznikne až v dalším úkolu — do té doby web bez Rank
	 * Mathu (kctpodebrady) dostane null a setup() render() vůbec nezavolá.
	 * Bez téhle pojistky by kct_container()->get() na neexistující třídu
	 * skončil fatální chybou a rozbil i stránky, které dnes fungují.
	 */
	private function output(): ?EventSeoOutput {
		if ( class_exists( 'RankMath' ) ) {
			return kct_container()->get( RankMathOutput::class );
		}

		return class_exists( StandaloneOutput::class )
			? kct_container()->get( StandaloneOutput::class )
			: null;
	}
}
