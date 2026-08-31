<?php

namespace Kct;

use Kct\Facebook\ShareState;
use Kct\Features\FacebookShare;
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
		if (defined('WP_CLI') && WP_CLI) {
			kct_container()->get(CLI::class);
		}
	}

	/**
	 * @param bool $network_wide
	 */
	public function activate( bool $network_wide ) {
	}

	/**
	 * Deaktivace pluginu — po sobě nezůstanou naplánované události ani provozní
	 * data, která bez běžícího pluginu nemají význam.
	 *
	 * Nastavení ani stav odeslání příspěvků se nemažou: deaktivace je často jen
	 * dočasná a po znovuzapnutí má web pokračovat tam, kde skončil. Od toho je
	 * uninstall().
	 *
	 * @param bool $network_wide Deaktivuje se plugin pro celou síť?
	 */
	public function deactivate( bool $network_wide ) {
		$this->for_each_site( $network_wide, array( $this, 'clear_runtime_data' ) );
	}

	/**
	 * Odinstalace pluginu — smaže se i to, co deaktivace nechává být.
	 *
	 * Na multisite je odinstalace vždy síťová akce (jednotlivý web plugin
	 * odinstalovat nemůže), takže se úklid pouští nad všemi weby sítě.
	 */
	/**
	 * Odinstalace pluginu.
	 *
	 * ÚMYSLNĚ NEMAŽE ŽÁDNÁ DATA. Uklidí se jen běhové věci, které bez pluginu
	 * nedávají smysl — naplánované cron události, krátkodobé transienty
	 * a zámky odesílání. Nastavení ani stav sdílení u příspěvků zůstávají.
	 *
	 * Je to vědomá odchylka od konvence WordPressu, že odinstalace po sobě
	 * uklidí i data. Důvod: běžný způsob ruční aktualizace je plugin smazat
	 * a nahrát znovu. WordPress u smazání nijak nevaruje, že tím přijdete
	 * o nastavení — ID stránky a token by se ztratily nenávratně a s nimi
	 * i evidence odeslaných příspěvků. A protože se podle té evidence pozná,
	 * co už na Facebooku je, znamenala by její ztráta, že se dřív sdílené
	 * příspěvky odešlou na veřejnou stránku znovu.
	 *
	 * Zbytky v databázi jsou proti tomu zanedbatelná cena.
	 */
	public function uninstall() {
		$this->for_each_site( true, array( $this, 'clear_runtime_data' ) );
	}

	/**
	 * Zruší naplánované události a smaže provozní data aktuálního webu.
	 *
	 * Zámky odesílání i transienty s výsledky tlačítek mají krátkou životnost,
	 * ale bez běžícího pluginu je nemá kdo uklidit — po deaktivaci uprostřed
	 * odesílání by řádek se zámkem zůstal v options navždy.
	 */
	private function clear_runtime_data(): void {
		wp_unschedule_hook( 'kct_update_events' );
		wp_unschedule_hook( FacebookShare::CRON_HOOK );

		delete_option( FacebookShare::TOKEN_ERROR_OPTION );

		$this->delete_transients_by_prefix( FacebookShare::VERIFY_RESULT_PREFIX );
		$this->delete_transients_by_prefix( FacebookShare::RETRY_RESULT_PREFIX );
		$this->delete_options_by_prefix( ShareState::LOCK_PREFIX );
	}


	/**
	 * Smaže transienty začínající danou předponou.
	 *
	 * Transienty jsou vázané na uživatele, takže jejich přesné názvy dopředu
	 * neznáme. Maže se přes delete_transient(), ne přímým DELETE — jinak by
	 * hodnota zůstala v object cache.
	 *
	 * @param string $prefix Předpona názvu transientu (bez `_transient_`).
	 */
	private function delete_transients_by_prefix( string $prefix ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- názvy transientů nejsou dopředu známé, viz docblock.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `option_name` FROM `$wpdb->options` WHERE `option_name` LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%'
			)
		);

		foreach ( $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}

	/**
	 * Smaže options začínající danou předponou.
	 *
	 * Používá se na zbylé zámky odesílání — ty vznikají a mizí mimo API
	 * options, ale mazat je přes delete_option() je i tak správně: postará se
	 * o object cache.
	 *
	 * @param string $prefix Předpona názvu option.
	 */
	private function delete_options_by_prefix( string $prefix ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- názvy zámků obsahují ID příspěvku, viz docblock.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `option_name` FROM `$wpdb->options` WHERE `option_name` LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		foreach ( $names as $name ) {
			delete_option( (string) $name );
		}
	}

	/**
	 * Pustí úklid nad jedním webem, nebo nad všemi weby sítě.
	 *
	 * Options i post meta jsou v multisite per-web, takže síťová deaktivace
	 * nebo odinstalace musí projít weby jeden po druhém — jinak by se uklidil
	 * jen ten, na kterém akce zrovna běží. Zbytek pluginu přepíná weby stejně
	 * (switch_to_blog / restore_current_blog, viz Events a DbEventRepository).
	 *
	 * @param bool     $network_wide Týká se akce celé sítě?
	 * @param callable $callback     Úklid, který se má nad webem provést.
	 */
	private function for_each_site( bool $network_wide, callable $callback ): void {
		if ( ! $network_wide || ! is_multisite() ) {
			$callback();

			return;
		}

		$site_ids = get_sites( array(
			'fields' => 'ids',
			'number' => 0,
		) );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			$callback();

			restore_current_blog();
		}
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
