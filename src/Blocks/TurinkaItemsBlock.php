<?php

namespace Kct\Blocks;

use Kct\Turinka\PublicItems;
use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

/**
 * Bloky „Trasy z Turinky“ a „Místa z Turinky“.
 *
 * Jedna třída na oba: data i karta jsou u tras a míst z větší části shodné,
 * liší se jen pár údajů. Dva bloky v nabídce jsou přesto schválně — redaktor
 * vybírá „trasy“ nebo „místa“, ne blok s přepínačem.
 *
 * Bloky se registrují **jen na propojeném webu**. Bez přístupu k Turince by
 * v nabídce stál blok, který nemá co vypsat.
 */
class TurinkaItemsBlock {

	/** Složka bloku v `blocks/` → typ obsahu ve výpisu. */
	const BLOCKS = array(
		'turinka-routes' => 'routes',
		'turinka-places' => 'places',
	);

	public function __construct(
		private PluginUtils $utils,
		private WordPressTemplate $template,
		private PublicItems $items
	) {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		if ( ! $this->items->has_access() ) {
			return;
		}

		foreach ( array_keys( self::BLOCKS ) as $folder ) {
			register_block_type(
				$this->utils->get_plugin_path( 'blocks/' . $folder ),
				array( 'render_callback' => array( $this, 'render' ) )
			);
		}
	}

	/**
	 * @param array  $attributes Nastavení bloku.
	 * @param string $content    Vnitřní obsah; dynamický blok ho nemá.
	 * @param mixed  $block      Instance bloku — z ní se pozná, který to je.
	 */
	public function render( array $attributes, string $content = '', $block = null ): string {
		$name   = is_object( $block ) && isset( $block->name ) ? (string) $block->name : '';
		$folder = str_replace( 'kct/', '', $name );
		$type   = self::BLOCKS[ $folder ] ?? 'routes';

		$result = $this->items->listing( $type, array(
			'count'      => (int) ( $attributes['count'] ?? 6 ),
			'scope'      => (string) ( $attributes['scope'] ?? 'mine' ),
			'difficulty' => (string) ( $attributes['difficulty'] ?? '' ),
			'item_type'  => $type,
		) );

		return (string) $this->template->render( 'blocks/turinka-items', null, array(
			'type'    => $type,
			'items'   => $result['items'],
			'ok'      => $result['ok'],
			'heading' => (string) ( $attributes['heading'] ?? '' ),
		) );
	}
}
