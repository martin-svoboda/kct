<?php

namespace Kct;

use Kct\Managers\ApiManager;
use Kct\Managers\BlocksManager;
use Kct\Managers\FeaturesManager;
use Kct\Managers\PostTypesManager;
use Kct\Managers\RepositoryManager;
use Kct\Managers\SnippetsManager;

final class Plugin {
	public function __construct(
		RepositoryManager $repository_manager,
		ApiManager $api_manager,
		FeaturesManager $features_manager,
		BlocksManager $blocks_manager,
		PostTypesManager $post_types_manager,
		SnippetsManager $snippets_manager,
		Frontend $frontend,
		Settings $settings
	) {
	}

	/**
	 * @param bool $network_wide
	 */
	public function activate( bool $network_wide ) {
	}

	/**
	 * @param bool $network_wide
	 */
	public function deactivate( bool $network_wide ) {
	}

	/**
	 *
	 */
	public function uninstall() {
	}

	/**
	 * Checks if the KCT theme is currently active.
	 *
	 * @return bool Returns true if the KCT theme is active, false otherwise.
	 */
	public function kct_theme_is_active() {
		$theme = wp_get_theme();
		return 'kct' === $theme->name || 'kct' === $theme->parent_theme;
	}
}
