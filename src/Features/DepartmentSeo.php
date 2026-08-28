<?php

namespace Kct\Features;

use Kct\Repositories\DepartmentRepository;
use Kct\Seo\DepartmentSeoData;
use Kct\Seo\EventSeoData;

/**
 * SEO detailu odboru (CPT `odbory`).
 *
 * Na rozdíl od EventSeo tu není žádná virtuální stránka ani dvojí zdroj dat —
 * odbor je vždycky skutečný CPT příspěvek, takže titulek, popisek i kanonickou
 * adresu skládá Rank Math sám a správně. Chybí mu ale tři věci, které odbory
 * z importu nikdy nemají vyplněné: popisek (`%excerpt%` je prázdné u všech 41),
 * náhledový obrázek pro sdílení a jakékoli JSON-LD kromě BreadcrumbList —
 * `pt_odbory_default_rich_snippet = off` v Rank Mathu vypne i WebSite a
 * WebPage, viz `can_add_global_entities()`. Tahle feature všechny tři doplní.
 *
 * Zatím jen pro weby s Rank Mathem — web bez SEO pluginu (kctpodebrady) dostává
 * aspoň obecné OG tagy z featury OpenGraph (funguje pro libovolný singulární
 * příspěvek, odbor nevyjímaje) a specifický popisek/schema mu chybí stejně,
 * jako dnes chybí úplně všem. Až bude potřeba i tam, přibude druhá
 * implementace analogicky k EventSeoOutput — dnes by to byla abstrakce
 * navíc bez druhého uživatele.
 */
class DepartmentSeo {

	/** Pole odboru z DepartmentModel::to_array() — nastaví ho setup(). */
	private array $department = array();

	/** Kanonická adresa (permalink) odboru — nastaví ho setup(). */
	private string $canonical = '';

	public function __construct(
		private DepartmentRepository $department_repository,
		private DepartmentSeoData $data,
		private EventSeoData $image_data
	) {
		add_action( 'wp', array( $this, 'setup' ) );
	}

	/** Rozpozná kontext a zaregistruje filtry Rank Mathu. */
	public function setup(): void {
		if ( ! class_exists( 'RankMath' ) ) {
			return;
		}

		if ( ! is_singular( $this->department_repository->post_type() ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		$model   = $post_id ? $this->department_repository->get( $post_id ) : null;

		if ( ! $model ) {
			return;
		}

		$this->department = $model->to_array();
		$this->canonical  = (string) ( get_permalink( $post_id ) ?: '' );

		// Bez tohohle Rank Math na odboru nevypíše ani WebSite, ani WebPage —
		// nastavení "Vypnuto" u typu strukturovaných dat v Titles & Meta
		// (pt_odbory_default_rich_snippet) neprojde whitelistem
		// v can_add_global_entities(). Stejný mechanismus a stejná oprava jako
		// u RankMathOutput::render() pro CPT akce.
		add_filter( 'rank_math/schema/add_global_entities', '__return_true' );
		add_filter( 'rank_math/json_ld', array( $this, 'filter_json_ld' ), 20 );

		add_filter( 'rank_math/frontend/description', array( $this, 'filter_description' ) );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'filter_description' ) );
		add_filter( 'rank_math/opengraph/twitter/twitter_description', array( $this, 'filter_description' ) );

		// Stejný důvod jako u RankMathOutput pro akce: `image` je dřívější
		// filtr uvnitř Image::add_image(), který proběhne vždycky, i bez
		// nalezeného obrázku — pozdní og_image/twitter_image filtry by se bez
		// obrázku vůbec nezavolaly.
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'filter_image' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'filter_image' ) );
	}

	/**
	 * Doplní SportsOrganization odboru do grafu.
	 *
	 * Klíč `richSnippet` záměrně, stejně jako u Event schématu — Rank Math
	 * u něj v connect_properties() doplní `@id`, `isPartOf` a `publisher`
	 * (odkaz na organizaci webu), aniž by bylo nutné to psát ručně. Na `name`,
	 * `address`, `geo`, `telephone`, `email`, `sameAs` ani `parentOrganization`
	 * přitom nesahá — connect_properties() přepisuje jen `@id`, `isPartOf`,
	 * `publisher`, `image` a `inLanguage` (viz add_prop() v class-jsonld.php),
	 * takže tu na rozdíl od Event.organizer u akcí není potřeba druhý filtr na
	 * PHP_INT_MAX, který by hodnotu vracel zpátky.
	 */
	public function filter_json_ld( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$parent = ( ! empty( $data['publisher']['@id'] ) )
			? array( '@id' => $data['publisher']['@id'] )
			: array();

		$schema = $this->data->schema( $this->department, $this->canonical, $parent );

		if ( $schema ) {
			$data['richSnippet'] = $schema;
		}

		return $data;
	}

	/**
	 * Popisek složený z dat odboru, jinak necháme hodnotu z Rank Mathu.
	 *
	 * @param string $description Původní hodnota z Rank Mathu.
	 * @return string
	 */
	public function filter_description( $description ) {
		return $this->data->description( $this->department ) ?: $description;
	}

	/**
	 * Obrázek odboru, jinak logo webu — necháme hodnotu z Rank Mathu, jen když
	 * ani jedno z toho není k dispozici (typicky odbor s vlastní featured image).
	 *
	 * @param string $image Původní hodnota z Rank Mathu.
	 * @return string
	 */
	public function filter_image( $image ) {
		return $this->image_url() ?: $image;
	}

	/**
	 * Obrázek loga odboru, jinak logo webu — sdílené s akcemi přes
	 * EventSeoData::image(), ať se výběr obrázku (vlastní → fallback na
	 * custom_logo) nepíše potřetí na dalším místě zvlášť. Tvar pole odboru
	 * (`image.url` z DepartmentModel::to_array()) je stejný, jaký metoda čeká
	 * u akcí, takže funguje beze změny.
	 */
	private function image_url(): string {
		return $this->image_data->image( $this->department )['url'];
	}
}
