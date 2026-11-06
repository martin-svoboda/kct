<?php

namespace Kct\Updates;

use KctDeps\Wpify\PluginUtils\PluginUtils;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Aktualizace pluginu standardní cestou WordPressu.
 *
 * Plugin není v adresáři WordPress.org, takže by se v přehledu aktualizací
 * sám od sebe nikdy neobjevil a správce cizího webu by musel nahrávat ZIP
 * ručně. Metadata dodává vlastní endpoint na updates.sokct.cz, instalační
 * ZIP se stahuje přímo z přílohy vydání na GitHubu.
 *
 * Proč knihovna a ne vlastní háčky: `pre_set_site_transient_update_plugins`
 * a `plugins_api` jsou jen začátek. Dál je potřeba přejmenovat rozbalenou
 * složku z `kct-2.1.0` na `kct`, obsloužit multisite, naplnit modál „Zobrazit
 * detaily“ a snést souběh s cizími cachemi aktualizací. Knihovna to má
 * odladěné; psát to znovu by znamenalo nacházet tytéž chyby na cizích webech.
 */
final class UpdateChecker {

	/**
	 * Adresa endpointu s metadaty.
	 *
	 * Musí se shodovat s hlavičkou `Update URI` v kct.php — podle ní WordPress
	 * od 5.8 pozná, že se na aktualizace tohoto pluginu nemá ptát
	 * WordPress.org.
	 */
	private const ENDPOINT = 'https://updates.sokct.cz/kct';

	public function __construct( private PluginUtils $utils ) {
		// Weby nasazované rsyncem z deploy.yml (sokct.cz, posazavskastezka.cz,
		// vývojová kopie) aktualizaci nepotřebují a v přehledu instalací by
		// jen šuměly.
		if ( defined( 'KCT_DISABLE_UPDATES' ) && KCT_DISABLE_UPDATES ) {
			return;
		}

		// Kdyby balíček přišel bez vendor/, je lepší web bez aktualizací než
		// web s fatální chybou.
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$checker = PucFactory::buildUpdateChecker(
			self::ENDPOINT,
			$this->utils->get_plugin_file(),
			$this->utils->get_plugin_slug()
		);

		$checker->addQueryArgFilter( array( $this, 'add_site_info' ) );
	}

	/**
	 * Doplní do dotazu údaje o webu.
	 *
	 * Knihovna sama posílá `installed_version`, `php` a `locale`. Filtr se
	 * věší na `request_info_query_args`, což pokrývá i pravidelnou kontrolu
	 * aktualizací — ta uvnitř knihovny volá tutéž cestu a přidává si
	 * `checking_for_updates=1`.
	 *
	 * Adresa webu přichází i v hlavičce User-Agent, kterou nastavuje WP HTTP
	 * API. Posílá se přesto i jako parametr, protože hlavičku může na webu
	 * přepsat jiný plugin.
	 *
	 * @param array $args Parametry dotazu.
	 *
	 * @return array
	 */
	public function add_site_info( array $args ): array {
		$args['wp']        = get_bloginfo( 'version' );
		$args['multisite'] = is_multisite() ? '1' : '0';

		// Na multisite je identitou instalace adresa sítě, ne jednotlivého
		// webu — plugin je nainstalovaný jednou. Počet webů jde zvlášť: síť
		// dvaceti odborů je jedna instalace, ale dvacet webů.
		$args['site']        = is_multisite() ? network_home_url( '/' ) : home_url( '/' );
		$args['sites_count'] = (string) ( is_multisite() ? get_blog_count() : 1 );

		return $args;
	}
}
