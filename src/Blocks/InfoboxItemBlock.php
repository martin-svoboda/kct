<?php

namespace Kct\Blocks;

use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

class InfoboxItemBlock {
	private $utils;
	private $template;

	public function __construct( PluginUtils $utils, WordPressTemplate $template ) {
		$this->utils    = $utils;
		$this->template = $template;

		if ( ! kct_theme_is_active() ) {
			return;
		}

		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'register_pattern' ) );
	}

	public function register() {
		register_block_type(
			$this->utils->get_plugin_path( 'blocks/infobox-item' ),
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Pattern „Info karty" – core sloupce se třemi kartami na jedno kliknutí.
	 */
	public function register_pattern() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category( 'kct', array( 'label' => __( 'KČT', 'kct' ) ) );
		}

		$card    = '<!-- wp:kct/infobox-item /-->';
		$column  = '<!-- wp:column --><div class="wp-block-column">' . $card . '</div><!-- /wp:column -->';
		$content = '<!-- wp:columns {"className":"is-style-kct-cards"} -->'
			. '<div class="wp-block-columns is-style-kct-cards">' . str_repeat( $column, 3 ) . '</div>'
			. '<!-- /wp:columns -->';

		register_block_pattern( 'kct/info-cards', array(
			'title'       => __( 'Info karty', 'kct' ),
			'description' => __( 'Sloupce se třemi info kartami.', 'kct' ),
			'categories'  => array( 'kct' ),
			'content'     => $content,
		) );
	}

	/**
	 * Barva proužku nad kartou: klíč z bloku → token šablony.
	 *
	 * Klíč z obsahu se do CSS nikdy nedostane, do stylu jde jen hodnota
	 * odsud — jinak by stačilo uložit přes REST API libovolný řetězec
	 * a blok by ho vypsal do atributu `style`.
	 *
	 * Barvy značek jsou tu schválně celá čtveřice: proužek nad kartou je
	 * tentýž motiv jako středové pruhy turistických značek, takže karta
	 * umí patřit ke konkrétní barvě značení. Prázdná volba znamená
	 * primární barvu webu, aby řada karet držela jeden tón, dokud někdo
	 * neřekne jinak.
	 *
	 * Klíče `--*-color` jsou dědictví po starším zápisu, kde atribut nesl
	 * přímo jméno proměnné. Ty proměnné ale v šabloně nikdy nevznikly, takže
	 * se vybraná barva neprojevila; seznam je drží, aby starší obsah barvu
	 * dostal, až se stránka znovu vykreslí, a nemusel se nikde přepisovat.
	 */
	private const ACCENTS = array(
		'primary'          => 'var(--primary)',
		'blue'             => 'var(--kct-blue)',
		'red'              => 'var(--kct-red)',
		'green'            => 'var(--kct-green)',
		'yellow'           => 'var(--kct-yellow)',
		'dark'             => 'var(--text)',
		'--primary-color'  => 'var(--primary)',
		'--secondary-color'=> 'var(--text)',
		'--blue-color'     => 'var(--kct-blue)',
		'--red-color'      => 'var(--kct-red)',
		'--green-color'    => 'var(--kct-green)',
		'--yellow-color'   => 'var(--kct-yellow)',
	);

	public function render( array $attributes, string $content = '' ) {
		$key = (string) ( $attributes['color'] ?? '' );

		// Neznámý klíč se tiše chová jako výchozí — obsah uložený starší nebo
		// novější verzí bloku nemá kartu rozbít.
		$attributes['accent'] = self::ACCENTS[ $key ] ?? '';

		return $this->template->render( 'blocks/infobox-item', null, $attributes );
	}
}
