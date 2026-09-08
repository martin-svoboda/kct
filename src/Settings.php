<?php

namespace Kct;

use Kct\Facebook\Credentials;
use Kct\Features\FacebookShare;
use Kct\Help\DocsLinks;
use Kct\Repositories\SettingsRepository;
use Kct\Features\Turinka as TurinkaFeature;
use Kct\Turinka\Embed;
use Kct\Turinka\EventState;
use Kct\Turinka\TokenStore;
use KctDeps\Wpify\CustomFields\CustomFields;

/**
 * Class Settings
 *
 * @package Wpify\Settings
 */
class Settings {
	/**
	 * @var CustomFields
	 */
	public $wcf;

	/**
	 * @var array
	 */
	public $options = array();

	/**
	 * Option key, and option page slug
	 *
	 * @var string
	 */
	const KEY = 'kct_options';

	public function __construct(
		CustomFields $wcf,
		private Credentials $credentials,
		private DocsLinks $docs,
		private TokenStore $turinka
	) {
		$this->wcf = $wcf;

		$this->setup();

		add_action( 'admin_notices', array( $this, 'settings_notices' ) );
	}

	/**
	 * Method to set up the plugin options page.
	 *
	 * @return void
	 */
	public function setup() {
		$fields = array(
			array(
				'id'      => 'add_style',
				'type'    => 'html',
				'content' => '<style>.forminp input[type=number].small-text {width:200px}</style>',
			),
			array(
				'id'      => 'docs_link',
				'type'    => 'html',
				'content' => sprintf(
					'<p>%s %s</p>',
					esc_html__( 'Co která volba dělá, popisuje uživatelská příručka:', 'kct' ),
					$this->docs->inline_link( 'settings', __( 'První nastavení webu', 'kct' ) )
				),
			),
			array(
				'title' => __( 'Kód oblasti / odboru', 'kct' ),
				'desc'  => __( 'Zadejte kód vaší oblasti (3 číslice) nebo odboru (6 číslic).', 'kct' ),
				'id'    => 'id_code',
				'type'  => 'number',
			),
			array(
				'title' => __( 'Adresa exportu z Databáze akcí KČT', 'kct' ),
				'desc'  => __( 'Adresa složky s XML exporty centrální Databáze akcí. Není veřejná a v pluginu proto není předvyplněná — sdělí vám ji na vyžádání ústředí KČT. Dokud je pole prázdné, akce se neimportují.', 'kct' ),
				'id'    => 'db_export_url',
				'type'  => 'text',
			)
		);

		foreach ( $fields as $index => $field ) {
			$fields[ $index ]['tab'] = 'obecne';
		}

		$settings = array_merge( $fields, $this->facebook_fields() );
		$settings = array_merge( $settings, $this->turinka_fields() );

		$this->wcf->create_options_page( array(
			'parent_slug' => 'options-general.php',
			'page_title'  => __( 'Nastavení funkcí KČT', 'kct' ),
			'menu_title'  => __( 'KČT', 'kct' ),
			'menu_slug'   => self::KEY,
			'capability'  => 'manage_options',
			// Knihovna čte `option_name`; `option_key` nezná a tiše ho ignoruje.
			// Bez toho zůstane název option prázdný, hodnoty se hledají jako
			// samostatné options (`get_option('fb_page_id')`) a formulář se
			// otevře prázdný, i když v `kct_options` data jsou.
			'option_name' => self::KEY,
			'items'       => $settings,
			'tabs'        => array(
				'obecne'   => 'Obecné',
				'facebook' => 'Facebook',
				'turinka'  => 'Turinka',
			),
		) );
	}


	/**
	 * Sekce Facebook — napojení na Facebook a automatické sdílení.
	 * 
	 * 	Konstanta ve wp-config.php má přednost — pole v nastavení se pak
	 *	zobrazí jen jako needitovatelná informace, kde hodnota vznikla.
	 *	Dřívější hodnota uložená v nastavení se zároveň z databáze smaže —
	 *	jinak by dál ležela v autoloadované option, byla v každé záloze a
	 *	knihovna by ji dál posílala do HTML administrace (read-only režim
	 *	jen skryje input, hodnotu neschová).
	 *
	 * @return array
	 */
	private function facebook_fields(): array {

		if ( $this->credentials->is_from_constant( 'fb_page_id' ) ) {
			$this->forget_option_if_overridden_by_constant( 'fb_page_id' );

			$fb_page_id_field = array(
				'id'      => 'fb_page_id_readonly',
				'type'    => 'html',
				'content' => sprintf(
					'<p><strong>%s</strong> — %s</p>',
					esc_html__( 'ID Facebook stránky', 'kct' ),
					esc_html__( 'hodnotu přebíjí konstanta KCT_FB_PAGE_ID ve wp-config.php.', 'kct' )
				),
			);
		} else {
			$fb_page_id_field = array(
				'title' => __( 'ID Facebook stránky', 'kct' ),
				'desc'  => __( 'Číselné ID stránky, na kterou se budou odesílat příspěvky.', 'kct' ),
				'id'    => 'fb_page_id',
				'type'  => 'text',
			);
		}

		if ( $this->credentials->is_from_constant( 'fb_page_token' ) ) {
			$this->forget_option_if_overridden_by_constant( 'fb_page_token' );

			$fb_page_token_field = array(
				'id'      => 'fb_page_token_readonly',
				'type'    => 'html',
				'content' => sprintf(
					'<p><strong>%s</strong> — %s</p>',
					esc_html__( 'Page access token', 'kct' ),
					esc_html__( 'hodnota je nastavena konstantou KCT_FB_PAGE_TOKEN ve wp-config.php. Případná dříve uložená hodnota byla z databáze odstraněna.', 'kct' )
				),
			);
		} else {
			$fb_page_token_field = array(
				'title' => __( 'Page access token', 'kct' ),
				'desc'  => __( 'Dlouhodobý token stránky z Meta aplikace. Pozor: token uložený zde leží v autoloadované databázové option, načítá se při každém requestu webu a je součástí každé zálohy databáze. Bezpečnější je definovat konstantu KCT_FB_PAGE_TOKEN ve wp-config.php.', 'kct' ),
				'id'    => 'fb_page_token',
				'type'  => 'password',
			);
		}

		$fields = array(
			array(
				'id'    => 'fb_section',
				'title' => __( 'Sdílení na Facebook', 'kct' ),
				'type'  => 'title',
			),
			$fb_page_id_field,
			$fb_page_token_field,
			array(
				'title' => __( 'Sdílet automaticky — aktuality', 'kct' ),
				'label' => __( 'Nové aktuality mají sdílení ve výchozím stavu zapnuté.', 'kct' ),
				'id'    => 'fb_share_default_post',
				'type'  => 'toggle',
			),
			array(
				'title' => __( 'Sdílet automaticky — akce', 'kct' ),
				'label' => __( 'Nové akce mají sdílení ve výchozím stavu zapnuté.', 'kct' ),
				'id'    => 'fb_share_default_akce',
				'type'  => 'toggle',
			),
			array(
				'title' => __( 'Kolik dní před akcí odeslat', 'kct' ),
				'desc'  => __( 'Pozvánka se odešle s tímto odstupem před začátkem akce. Výchozích 12 dní není náhodné číslo: sobotní akce tím vyjde na pondělí a nedělní na úterý, tedy na začátek týdne, kdy lidé plánují další víkend. Nula znamená odeslat v den akce.', 'kct' ),
				'id'    => 'fb_event_lead_days',
				'type'  => 'number',
				'min'   => 0,
				'max'   => 365,
			),
			array(
				'title' => __( 'V kolik hodin odeslat', 'kct' ),
				'desc'  => __( 'Hodina, ve které se pozvánka na akci odešle. Bez ní by odešla v tu denní dobu, kdy byla akce náhodou publikována.', 'kct' ),
				'id'    => 'fb_event_hour',
				'type'  => 'number',
				'min'   => 0,
				'max'   => 23,
			),
			array(
				'label' => __( 'Připojení k Facebooku', 'kct' ),
				'title' => __( 'Ověřit připojení', 'kct' ),
				'desc'  => __( 'Zkusí se připojit k Facebooku a vypíše název stránky, ke které token patří.', 'kct' ),
				'id'    => 'fb_verify',
				'type'  => 'button',
				'url'   => $this->fb_verify_url(),
			),
		);
		
		foreach ( $fields as $index => $field ) {
			$fields[ $index ]['tab'] = 'facebook';
		}

		return $fields;
	}

	/**
	 * Sekce „Turinka“ — stav přístupu a cesty, jak ho získat.
	 *
	 * Pole se mění podle stavu, protože každý stav má jinou jedinou rozumnou
	 * další akci: bez přístupu požádat, po odeslání počkat, s přístupem ověřit.
	 * Nabízet všechno naráz by znamenalo nabízet většinou nefunkční tlačítka.
	 */
	private function turinka_fields(): array {
		$state  = $this->turinka->state();
		$fields = array(
			array(
				'id'    => 'turinka_section',
				'title' => __( 'Turinka', 'kct' ),
				'type'  => 'title',
			),
			array(
				'id'      => 'turinka_state',
				'type'    => 'html',
				'content' => sprintf( '<p>%s</p>', esc_html( $this->turinka_state_text( $state ) ) ),
			),
		);

		// Widget stojí mimo obě větve schválně: čtecí API Turinky je veřejné,
		// takže funguje i webu, který o přístup nepožádal.
		$fields[] = array(
			'title' => __( 'Widget Turinky u akcí', 'kct' ),
			'label' => __( 'Na stránce akce zobrazit rámeček z Turinky s tlačítky Plánovat, Oblíbit a Sdílet.', 'kct' ),
			'desc'  => __( 'Funguje i bez propojení — zobrazí se u každé akce, kterou Turinka zná. Vypnutím zmizí i odchozí odkaz na Turinku.', 'kct' ),
			'id'      => Embed::OPTION,
			'type'    => 'toggle',
			'default' => true,
		);

		if ( TokenStore::STATE_ACTIVE !== $state ) {
			$fields[] = array(
				'label' => __( 'Přístup k Turince', 'kct' ),
				'title' => __( 'Požádat o přístup', 'kct' ),
				'desc'  => __( 'Odešle žádost s doménou tohoto webu a kódem oblasti nebo odboru. Schvaluje ji člověk, takže to není hned.', 'kct' ),
				'id'    => 'turinka_request',
				'type'  => 'button',
				'url'   => $this->turinka_action_url( 'turinka_request', TurinkaFeature::REQUEST_NONCE ),
			);

			$fields[] = array(
				'title' => __( 'Token vložený ručně', 'kct' ),
				'desc'  => __( 'Používá se, jen když se token nepodařilo doručit automaticky — pak ho správce Turinky vidí na obrazovce a předá ho sem. Po uložení se ověří a z tohoto pole zmizí.', 'kct' ),
				'id'    => TurinkaFeature::MANUAL_TOKEN_FIELD,
				'type'  => 'password',
			);
		} else {
			$fields[] = array(
				'label' => __( 'Připojení k Turince', 'kct' ),
				'title' => __( 'Ověřit připojení', 'kct' ),
				'desc'  => __( 'Zkusí se připojit k Turince a vypíše, komu přístup patří a co smí.', 'kct' ),
				'id'    => 'turinka_verify',
				'type'  => 'button',
				'url'   => $this->turinka_action_url( 'turinka_verify', TurinkaFeature::VERIFY_NONCE ),
			);

			$options = array();
			foreach ( EventState::choices() as $value => $label ) {
				$options[] = array( 'label' => $label, 'value' => $value );
			}

			$fields[] = array(
				'title'   => __( 'Výchozí stav u akcí', 'kct' ),
				'desc'    => __( 'Platí pro akce, u kterých volbu nikdo nenastavil — tedy i pro ty založené dřív, než se web propojil. U jednotlivé akce se dá kdykoli přebít.', 'kct' ),
				'id'      => 'turinka_default_state',
				'type'    => 'select',
				'options' => $options,
			);
		}

		foreach ( $fields as $index => $field ) {
			$fields[ $index ]['tab'] = 'turinka';
		}

		return $fields;
	}

	private function turinka_state_text( string $state ): string {
		if ( TokenStore::STATE_ACTIVE === $state ) {
			$owner  = $this->turinka->owner();
			$scopes = $this->turinka->scopes();

			return sprintf(
				/* translators: 1: doména, 2: jméno vlastníka, 3: seznam oprávnění. */
				__( 'Propojeno. Doména %1$s, vlastník %2$s, oprávnění: %3$s.', 'kct' ),
				$this->turinka->domain(),
				(string) ( $owner['name'] ?? __( 'nepřiřazen', 'kct' ) ),
				$scopes ? implode( ', ', $scopes ) : __( 'žádná', 'kct' )
			);
		}

		if ( TokenStore::STATE_PENDING === $state ) {
			return __( 'Žádost odeslána, čeká na schválení správcem Turinky.', 'kct' );
		}

		return __( 'Nepropojeno. Bez přístupu se do Turinky nic neodesílá.', 'kct' );
	}

	/**
	 * Adresa tlačítka sekce Turinka i s jednorázovým tokenem.
	 *
	 * Nonce vzniká jen na vlastní obrazovce a URL se skládá add_query_arg() —
	 * důvody obojího jsou popsané u fb_verify_url() a platí i tady.
	 */
	private function turinka_action_url( string $action, string $nonce ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání obrazovky, nic se nemění.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! is_admin() || self::KEY !== $page ) {
			return '';
		}

		return add_query_arg(
			array(
				'kct-action' => $action,
				'_wpnonce'   => wp_create_nonce( $nonce ),
			),
			admin_url( 'index.php' )
		);
	}

	/**
	 * Adresa tlačítka „Ověřit připojení“ i s jednorázovým tokenem.
	 *
	 * Nonce vzniká, jen když se zobrazuje samotná stránka nastavení: setup()
	 * běží v `plugins_loaded` při každém requestu včetně frontendu a
	 * wp_create_nonce() by tam předčasně vynutilo určení přihlášeného
	 * uživatele. Na ostatních obrazovkách se tlačítko stejně nevykresluje.
	 *
	 * Adresa se skládá funkcí add_query_arg(), ne wp_nonce_url() — ta výsledek
	 * prožene esc_html(), takže by se do URL dostalo `&amp;`. V HTML odkazu to
	 * je správně, ale tohle pole vykresluje React a atribut by nastavil doslova,
	 * takže by z nonce vznikl parametr `amp;_wpnonce`.
	 */
	private function fb_verify_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání obrazovky, nic se nemění.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! is_admin() || self::KEY !== $page ) {
			return '';
		}

		return add_query_arg(
			array(
				'kct-action' => 'fb_verify',
				'_wpnonce'   => wp_create_nonce( FacebookShare::VERIFY_NONCE ),
			),
			admin_url( 'index.php' )
		);
	}

	/**
	 * Smaže hodnotu daného klíče z `kct_options`, je-li tam ještě uložená.
	 *
	 * Používá se pro pole, jejichž hodnotu přebíjí konstanta ve wp-config.php —
	 * jinak by dřívější hodnota dál ležela v autoloadované option, v každé
	 * záloze databáze a knihovna by ji dál posílala do HTML administrace, i
	 * když je pole zobrazené jako needitovatelné. Do databáze se zapisuje jen
	 * když klíč skutečně existuje, ne při každém requestu.
	 */
	private function forget_option_if_overridden_by_constant( string $key ): void {
		$options = get_option( self::KEY, array() );

		if ( empty( $options[ $key ] ) ) {
			return;
		}

		unset( $options[ $key ] );
		update_option( self::KEY, $options );
	}

	/**
	 * Method to display success updated events notice on the plugin options page.
	 *
	 * @return void
	 */
	public function settings_notices(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] != $this::KEY ) {
			return;
		}

		if ( isset( $_GET['events_loaded'] ) && $_GET['events_loaded'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Načtení akcí z centrální Databáze akcí KČT byla úspěšná.', 'kct' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['eventtypes_loaded'] ) && $_GET['eventtypes_loaded'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Načtení typů akcí z centrální Databáze akcí KČT byla úspěšná.', 'kct' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Method to get the URL of the plugin settings page.
	 *
	 * Statická proto, že adresu potřebují i třídy, které se nastavením jinak
	 * nezabývají (FacebookShare v upozorněních administrace) — instanci Settings
	 * by kvůli složení jedné URL injektovat nemusely.
	 *
	 * @return string The URL of the plugin settings page.
	 */
	public static function get_settings_url(): string {
		return add_query_arg( array( 'page' => self::KEY ), admin_url( 'options-general.php' ) );
	}
}
