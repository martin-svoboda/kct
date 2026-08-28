<?php

namespace Kct\Seo;

/**
 * Výstup pro weby bez SEO pluginu.
 *
 * Feature OpenGraph se na těchto webech stará o běžné stránky, ale detail akce
 * nezná — je to virtuální stránka bez WP_Post, takže by pro ni složila tagy
 * z výpisu blogu. Tahle třída ji na detailu akce zastoupí.
 */
class StandaloneOutput implements EventSeoOutput {

	private array $event = array();
	private string $canonical = '';

	public function __construct( private EventSeoData $data ) {
	}

	public function render( array $event, string $canonical, bool $is_single ): void {
		// U CPT příspěvku odvede práci feature OpenGraph — je to skutečný
		// WP_Post a tagy z něj složí správně.
		if ( $is_single ) {
			return;
		}

		$this->event     = $event;
		$this->canonical = $canonical;

		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_action( 'wp_head', array( $this, 'head' ), 4 );

		// Feature OpenGraph vypisuje na wp_head a pro tenhle kontext by
		// složila tagy z výpisu blogu. Tady je nahrazujeme.
		//
		// Priorita se zjišťuje přes has_action(), ne opisuje natvrdo — je
		// zadrátovaná i v OpenGraph::__construct() a kdyby se tam někdy
		// změnila, remove_action() s cizí prioritou by tiše vrátilo false
		// a stránka by dostala OG tagy dvakrát, beze stopy v logu.
		$open_graph = kct_container()->get( \Kct\Features\OpenGraph::class );
		$priority   = has_action( 'wp_head', array( $open_graph, 'render' ) );

		if ( false !== $priority ) {
			remove_action( 'wp_head', array( $open_graph, 'render' ), $priority );
		}
	}

	public function document_title( $title ) {
		$own = $this->data->title( $this->event );

		if ( '' === $own ) {
			return $title;
		}

		// Stejný filtr jako jádro ve wp_get_document_title() — natvrdo psaná
		// pomlčka by ho obcházela, i když by dnes vyšla stejně (výchozí
		// hodnota jádra je taky '-').
		$separator = apply_filters( 'document_title_separator', '-' );

		// esc_html(): <title> je RCDATA a "</title>" v datech z importu KČT
		// by z něj vyskočilo. pre_get_document_title zkracuje
		// wp_get_document_title(), které výsledek dál neescapuje.
		return esc_html( $own . ' ' . $separator . ' ' . get_bloginfo( 'name' ) );
	}

	public function head(): void {
		$title       = $this->data->title( $this->event );
		$description = $this->data->description( $this->event );
		// Sdílené s RankMathOutput přes EventSeoData::image() — vlastní
		// obrázek akce, jinak logo z tématu, aby se výběr nepsal na dvou
		// místech zvlášť a event_schema() dostal totéž, co jde do OG tagů.
		$image = $this->data->image( $this->event );

		$tags = array(
			array( 'name', 'description', $description ),
			array( 'property', 'og:type', 'article' ),
			array( 'property', 'og:locale', get_locale() ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:url', $this->canonical ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'property', 'og:image', $image['url'] ),
			// Bez obrázku (web nemá ani fotku akce, ani custom_logo) je
			// summary_large_image nesmysl — stejně jako to řeší OpenGraph::render().
			array( 'name', 'twitter:card', $image['url'] ? 'summary_large_image' : 'summary' ),
			array( 'name', 'twitter:title', $title ),
			array( 'name', 'twitter:description', $description ),
			array( 'name', 'twitter:image', $image['url'] ),
		);

		if ( $this->canonical ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $this->canonical ) );
		}

		foreach ( $tags as $tag ) {
			list( $attr, $name, $content ) = $tag;

			if ( '' === $content ) {
				continue;
			}

			printf(
				'<meta %1$s="%2$s" content="%3$s" />' . "\n",
				esc_attr( $attr ),
				esc_attr( $name ),
				esc_attr( $content )
			);
		}

		// Rozměry zná EventSeoData::image() jen u loga (lokální příloha) —
		// u vlastní fotky akce je to vzdálená URL z importu a width/height
		// jsou 0. Bez rozměrů Facebook náhled nevykreslí, dokud si obrázek
		// sám nestáhne.
		if ( $image['width'] && $image['height'] ) {
			printf( '<meta property="og:image:width" content="%d" />' . "\n", intval( $image['width'] ) );
			printf( '<meta property="og:image:height" content="%d" />' . "\n", intval( $image['height'] ) );
		}

		$schema = $this->data->event_schema( $this->event, $this->canonical, $image['url'] );

		if ( $schema ) {
			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode(
					array(
						'@context' => 'https://schema.org',
						'@graph'   => array( $schema ),
					),
					// JSON_HEX_TAG převede znaky '<' a '>' na jejich unicode escape
					// (\u003C, \u003E). JSON_UNESCAPED_SLASHES samo o sobě žádnou
					// ochranu nedává — bez HEX_TAG by řetězec "</script>" v datech
					// z importu (např. v názvu akce) mohl předčasně ukončit tenhle
					// blok a vpašovat vlastní <script> do stránky. NEODEBÍRAT.
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
				)
			);
		}
	}
}
