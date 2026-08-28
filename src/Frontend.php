<?php

namespace Kct;

use KctDeps\Wpify\Asset\AssetFactory;
use KctDeps\Wpify\PluginUtils\PluginUtils;

class Frontend {
	/** @var PluginUtils */
	private $utils;

	/** @var AssetFactory */
	private $asset_factory;

	public function __construct(
		PluginUtils $utils,
		AssetFactory $asset_factory
	) {
		$this->utils         = $utils;
		$this->asset_factory = $asset_factory;

		$this->setup();
		$this->setup_theme();

		// wp-config.php definuje NOBLOGREDIRECT (multisite konstanta pro adresy
		// neexistujících podwebů) na /error/404.php. maybe_redirect_404()
		// (wp-includes/ms-functions.php) ji ale používá i pro každou 404 na
		// hlavním webu — a ta stránka na produkci vrací HTTP 200. Vyhledávač
		// tak dostane 302 na obsah, který odpoví 200: učebnicová měkká 404,
		// mrtvá adresa zůstane v indexu navždy a časem se zaindexuje i ta
		// cílová stránka samotná. Prázdný řetězec přesměrování zruší, takže
		// WordPress doručí skutečnou 404 se šablonou (content-404.php).
		// Je to obecné chování frontendu celého webu, ne věc konkrétní
		// funkce — proto tady, ne ve Features/EventSeo. Filtr se reálně
		// uplatní jen na hlavním webu sítě (maybe_redirect_404() volá
		// apply_filters() jen pod is_main_site()), takže na odborových webech
		// se chování nemění.
		add_filter( 'blog_redirect_404', '__return_empty_string' );

		// Ikona webu, když ji web nemá nastavenou. Jádro v takovém případě
		// nevypíše nic (wp_site_icon() se ukončí hned na has_site_icon(), takže
		// ani filtr site_icon_meta_tags neproběhne) a prohlížeč ukáže prázdnou
		// ikonu, v administraci logo WordPressu. Odborové weby ikonu často
		// nastavenou nemají, tak jim podstrčíme obecné logo KČT ze šablony.
		add_action( 'wp_head', array( $this, 'fallback_site_icon' ) );
		add_action( 'admin_head', array( $this, 'fallback_site_icon' ) );
		add_action( 'login_head', array( $this, 'fallback_site_icon' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'setup_assets' ) );
		add_filter( 'excerpt_length', function () {
			return 20;
		} );
		add_filter( 'upload_mimes', array( $this, 'allow_gpx_upload' ) );
		//$this->setup_assets();

		add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			if (strtolower($ext) === 'gpx') {
				$data['ext'] = 'gpx';
				$data['type'] = 'application/gpx+xml';
				$data['proper_filename'] = $filename;
			}
			return $data;
		}, 10, 4);
	}

	public function setup() {
	}

	public function setup_theme() {
		register_theme_directory( $this->utils->get_plugin_path( 'themes' ) );
	}

	public function setup_assets() {
		$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/plugin.js' ), array(
			'in_footer' => true,
		) );

		// Archiv akcí i odborů: vanilla Leaflet mapa (markery ze serveru přes window.kctMarkers).
		// U akcí je mapa volitelná (Customizer kct_events_map); odbory ji mají vždy.
		$needs_map = is_post_type_archive( 'odbory' )
			|| ( is_post_type_archive( 'akce' ) && get_theme_mod( 'kct_events_map', true ) );
		if ( $needs_map ) {
			$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/map.js' ), array(
				'variables' => array(
					'site_rl'     => site_url(),
					'assets_url'  => $this->utils->get_plugin_url( 'assets' ),
					'kct_api_url' => rest_url( 'kct/v1' ),
				),
				'in_footer' => true,
			) );
		}

		// Archiv akcí: progresivní AJAX filtr nad PHP výpisem.
		if ( is_post_type_archive( 'akce' ) ) {
			$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/events-archive.js' ), array(
				'variables' => array(
					'kct_api_url' => rest_url( 'kct/v1' ),
				),
				'in_footer' => true,
			) );
		}
	}

	/**
	 * Záložní ikona webu, když ji web nemá nastavenou.
	 *
	 * Používá obecné logo KČT ze šablony ve dvou velikostech: malý soubor na
	 * ikonu v panelu prohlížeče, velký na dlaždice a přidání na plochu. Jádro
	 * si tyhle velikosti u nastavené ikony ořezává samo, tady jsou jako hotové
	 * soubory — proto se pro každý rozměr vybírá ten bližší.
	 *
	 * Vypisuje se jen když ikona chybí, aby weby s vlastní ikonou zůstaly beze
	 * změny; jádro svou verzi vypisuje na týchž hácích.
	 */
	public function fallback_site_icon(): void {
		if ( has_site_icon() ) {
			return;
		}

		$small = get_theme_file_uri( 'images/logo_kct.png' );
		$large = get_theme_file_uri( 'images/kct_barva.png' );

		printf( '<link rel="icon" href="%s" sizes="32x32" />' . "\n", esc_url( $small ) );
		printf( '<link rel="icon" href="%s" sizes="192x192" />' . "\n", esc_url( $large ) );
		printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( $large ) );
		printf( '<meta name="msapplication-TileImage" content="%s" />' . "\n", esc_url( $large ) );
	}

	public function allow_gpx_upload( $mimes ) {
		$mimes['gpx'] = 'application/gpx+xml';

		return $mimes;
	}
}
