<?php

namespace Kct\Turinka;

use Kct\PostTypes\EventPostType;
use WP_Post;

/**
 * Panel „Trasy v Turince“ na obrazovce akce.
 *
 * Tahle třída zná jen hooky a to, kam panel patří; co se v něm píše, drží
 * EventRoutesPanel — stejně jako u Facebook\ShareMetabox a Turinka\RouteMetabox.
 * Vykreslení je společné s přehledem akcí, kde je týž panel pod řádkem.
 */
class EventRoutesMetabox {
	const ID = 'kct-turinka-event-routes';

	public function __construct(
		private EventRoutesPanel $panel,
		private TokenStore $tokens
	) {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
	}

	public function register(): void {
		if ( ! $this->tokens->has_token() ) {
			return;
		}

		add_meta_box(
			self::ID,
			__( 'Trasy v Turince', 'kct' ),
			array( $this, 'render' ),
			EventPostType::KEY,
			'side',
			'default'
		);
	}

	public function render( WP_Post $post ): void {
		$ref = array( 'post_id' => (int) $post->ID );

		echo $this->panel->wrap( $ref, $this->panel->html( $ref ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapuje se při skládání.
	}
}
