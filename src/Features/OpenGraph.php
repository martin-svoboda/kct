<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

/**
 * Open Graph tagy pro slušný náhled odkazu na sociálních sítích.
 *
 * Web nemá SEO plugin; pokud ho někdy dostane, tato feature se vypne, aby tagy
 * nebyly v HTML dvakrát.
 */
class OpenGraph {
	/**
	 * @param Credentials $credentials Konfigurace sdílení, odsud se čte výchozí obrázek.
	 * @param OgImages    $og_images   Vlastní sdílecí obrázky.
	 */
	public function __construct(
		private Credentials $credentials,
		private OgImages $og_images
	) {
		add_action( 'wp_head', array( $this, 'render' ), 5 );
	}

	/**
	 * Vypíše Open Graph a Twitter Card tagy do hlavičky stránky.
	 *
	 * Priorita 5 na wp_head — má se vykreslit dřív než ostatní výstup v hlavičce.
	 */
	public function render(): void {
		if ( $this->has_seo_plugin() || is_404() ) {
			return;
		}

		// Archiv, který se navenek tváří jako platná stránka (WordPress ho
		// nepošle na 404), ale nemá k sobě žádný skutečný objekt — typicky
		// autor s neexistujícím slugem. Bez téhle pojistky by og:url spadl na
		// homepage a rozbitý odkaz by na Facebooku vypadal jako plnohodnotný.
		if ( ! is_singular() && $this->is_broken_archive() ) {
			return;
		}

		$post = is_singular() ? get_post() : null;

		$title       = $post instanceof WP_Post ? get_the_title( $post ) : $this->context_title();
		$description = $post instanceof WP_Post ? $this->description( $post ) : $this->context_description();
		$url         = $post instanceof WP_Post ? get_permalink( $post ) : $this->context_url();
		$image       = $this->image_url( $post );

		// Statická titulní stránka je pořád "webem", ne článkem, i když je
		// technicky singulární WP_Post.
		$type = ( $post instanceof WP_Post && ! is_front_page() ) ? 'article' : 'website';

		$tags = array(
			array( 'og:type', $type, 'attr' ),
			array( 'og:title', $title, 'attr' ),
			array( 'og:description', $description, 'attr' ),
			array( 'og:url', $url, 'url' ),
			array( 'og:site_name', get_bloginfo( 'name' ), 'attr' ),
			array( 'og:locale', get_locale(), 'attr' ),
		);

		foreach ( $tags as $tag ) {
			list( $property, $content, $escaper ) = $tag;

			// false se objeví typicky u get_permalink() nad neplatným
			// příspěvkem — bez toho testu by esc_url()/esc_attr() z něj
			// udělaly prázdný řetězec a tag by se vypsal prázdný místo toho,
			// aby se přeskočil.
			if ( '' === $content || false === $content ) {
				continue;
			}

			printf(
				'<meta property="%s" content="%s" />' . "\n",
				esc_attr( $property ),
				'url' === $escaper ? esc_url( $content ) : esc_attr( $content )
			);
		}

		if ( $image ) {
			printf(
				'<meta property="og:image" content="%s" />' . "\n",
				esc_url( $image[0] )
			);
			printf(
				'<meta property="og:image:width" content="%d" />' . "\n",
				intval( $image[1] )
			);
			printf(
				'<meta property="og:image:height" content="%d" />' . "\n",
				intval( $image[2] )
			);
		}

		printf(
			'<meta name="twitter:card" content="%s" />' . "\n",
			$image ? 'summary_large_image' : 'summary'
		);
	}

	/**
	 * Běží na webu aktivní SEO plugin, který si OG tagy řeší sám?
	 */
	private function has_seo_plugin(): bool {
		return class_exists( 'WPSEO_Options' ) || class_exists( 'RankMath' );
	}

	/**
	 * Archiv, u kterého get_queried_object() nevrací očekávaný objekt.
	 *
	 * WordPress u některých neplatných archivů (typicky autor s neexistujícím
	 * slugem) nepřesměruje na 404 a is_author() zůstane pravdivé, i když
	 * queried object chybí — get_the_archive_title() pak vrátí jen holý
	 * prefix ("Autor:") a v context_url() by nezbylo nic jiného než spadnout
	 * na homepage. Radši se pro takovou stránku nevypíše nic.
	 */
	private function is_broken_archive(): bool {
		if ( is_author() ) {
			return ! ( get_queried_object() instanceof WP_User );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return ! ( get_queried_object() instanceof WP_Term );
		}

		if ( is_post_type_archive() ) {
			return ! ( get_queried_object() instanceof WP_Post_Type );
		}

		return false;
	}

	/**
	 * Titulek pro stránky mimo singulární obsah — homepage, archivy, vyhledávání.
	 *
	 * get_the_archive_title() vrací text s prefixem typu "Kategorie: …", někdy
	 * i obalený HTML — proto se prohání přes wp_strip_all_tags().
	 */
	private function context_title(): string {
		if ( is_front_page() ) {
			return get_bloginfo( 'name' );
		}

		// Výpis příspěvků na vlastní adrese (nastavení "Zobrazovat na úvodní
		// stránce" → statická stránka + stránka příspěvků). Bez tohohle by
		// spadl na název webu jako kterákoli jiná nerozpoznaná stránka.
		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );

			return $page_id ? get_the_title( $page_id ) : get_bloginfo( 'name' );
		}

		if ( is_search() ) {
			return sprintf(
				/* translators: %s: hledaný výraz. */
				__( 'Výsledky hledání: %s', 'kct' ),
				get_search_query()
			);
		}

		if ( is_archive() ) {
			$title = trim( wp_strip_all_tags( get_the_archive_title() ) );

			if ( '' !== $title ) {
				return $title;
			}
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Popis pro stránky mimo singulární obsah — u archivů popis rubriky nebo
	 * jiné taxonomie, jinak popis webu.
	 */
	private function context_description(): string {
		if ( is_archive() ) {
			$description = trim( wp_strip_all_tags( get_the_archive_description() ) );

			if ( '' !== $description ) {
				return $description;
			}
		}

		return get_bloginfo( 'description' );
	}

	/**
	 * Adresa aktuální stránky mimo singulární obsah.
	 *
	 * Facebook bere og:url jako kanonickou adresu sdíleného odkazu — když by
	 * tag na archivu nebo ve výpisu pořád ukazoval na homepage, sdílení by se
	 * přiřadilo k homepage a náhled by ukázal něco jiného, než na co člověk
	 * klikl.
	 */
	private function context_url(): string {
		if ( is_front_page() ) {
			return $this->paginate( home_url( '/' ) );
		}

		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			$url     = $page_id ? get_permalink( $page_id ) : false;

			return $this->paginate( $url ? $url : home_url( '/' ) );
		}

		if ( is_search() ) {
			return $this->paginate( get_search_link() );
		}

		// Datový archiv nemá k sobě žádný objekt — get_queried_object() je
		// pro něj vždy null, protože žádný konkrétní objekt "rok/měsíc/den"
		// neexistuje. Adresa se proto skládá přímo z query vars.
		if ( is_date() ) {
			return $this->paginate( $this->date_archive_link() );
		}

		$queried = get_queried_object();

		if ( $queried instanceof WP_Term ) {
			$link = get_term_link( $queried );

			return $this->paginate( is_string( $link ) ? $link : home_url( '/' ) );
		}

		if ( $queried instanceof WP_Post_Type ) {
			$link = get_post_type_archive_link( $queried->name );

			return $this->paginate( $link ? $link : home_url( '/' ) );
		}

		if ( $queried instanceof WP_User ) {
			return $this->paginate( get_author_posts_url( $queried->ID ) );
		}

		return home_url( '/' );
	}

	/**
	 * Adresa datumového archivu (rok, měsíc, nebo den).
	 */
	private function date_archive_link(): string {
		if ( is_day() ) {
			return get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
		}

		if ( is_month() ) {
			return get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
		}

		if ( is_year() ) {
			return get_year_link( get_query_var( 'year' ) );
		}

		return home_url( '/' );
	}

	/**
	 * Adresa druhé a další stránky výpisu, je-li aktuální request stránkovaný.
	 *
	 * get_pagenum_link() si adresu skládá sám z aktuálního requestu, ne
	 * z předané $url — ta se použije jen na první, nestránkované stránce.
	 *
	 * @param string $url Adresa první stránky výpisu.
	 */
	private function paginate( string $url ): string {
		return is_paged() ? get_pagenum_link( get_query_var( 'paged' ) ) : $url;
	}

	/**
	 * Popis pro og:description — perex, jinak začátek obsahu, ořezaný na 200 znaků.
	 *
	 * Heslem chráněný příspěvek nesmí popis nabídnout: titulek WordPress
	 * zamaskuje ("Chráněno: …"), ale obsah by se jinak vypsal do veřejného
	 * HTML a Facebook scraper by si ho nacacheoval.
	 *
	 * @param WP_Post $post Příspěvek, ze kterého se popis skládá.
	 */
	private function description( WP_Post $post ): string {
		if ( '' !== $post->post_password ) {
			return get_bloginfo( 'description' );
		}

		$text = $post->post_excerpt ?: $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		return mb_strlen( $text ) > 200 ? rtrim( mb_substr( $text, 0, 200 ) ) . '…' : $text;
	}

	/**
	 * Náhledový obrázek: vlastní sdílecí karta, jinak featured image příspěvku.
	 *
	 * Velikost 'full': registrovaná velikost by WordPress prohnal přes
	 * image_constrain_size_for_editor() a omezil ji na $content_width tématu
	 * (640 px) — pod doporučenými 1200×630 pro Facebook, který si kartu
	 * skládá z deklarovaných rozměrů ještě před stažením obrázku.
	 *
	 * @param WP_Post|null $post Zobrazený příspěvek, nebo null mimo singulární stránku.
	 *
	 * @return array{0: string, 1: int, 2: int}|null
	 */
	private function image_url( ?WP_Post $post ): ?array {
		// Vlastní sdílecí obrázek má přednost — nese titulek, kategorii a datum
		// (u akce start, cíl a pořadatele), na rozdíl od holého náhledu.
		//
		// Akce se řeší tady proto, že Seo\StandaloneOutput se na CPT stránce
		// úmyslně nezapojuje a nechává tagy na téhle feature.
		if ( $post instanceof WP_Post ) {
			$own = 'post' === $post->post_type
				? $this->og_images->for_post( (int) $post->ID )
				: $this->og_images->for_event_post( (int) $post->ID );

			if ( $own ) {
				return array( $own['url'], $own['width'], $own['height'] );
			}
		}

		$attachment_id = $post instanceof WP_Post ? get_post_thumbnail_id( $post ) : 0;

		if ( ! $attachment_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );

		return $image ? array( $image[0], $image[1], $image[2] ) : null;
	}
}
