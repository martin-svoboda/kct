<?php

namespace Kct\Features;

use Kct\PostTypes\RoadPostType;
use Kct\Turinka\Client;
use Kct\Turinka\Config;
use Kct\Turinka\LinkState;
use Kct\Turinka\RouteEmbed;
use Kct\Turinka\RoutePayload;
use Kct\Turinka\TokenStore;
use KctDeps\Wpify\Asset\AssetFactory;
use KctDeps\Wpify\PluginUtils\PluginUtils;
use WP_Post;

/**
 * Propojení trasy s aplikací Turinka.
 *
 * Jediné místo, které zná hooky trasy. Data trasy drží Turinka a zadávají se
 * jejím formulářem vloženým do stránky příspěvku; web vlastní název, text
 * a odkaz na článek a posílá je plánovanou úlohou.
 *
 * Trasu plugin sám **nezakládá**: API u nové trasy vyžaduje GPX stopu a tu má
 * formulář, ne web. Z toho plyne i zbytek — zápis se zkouší jen u propojené
 * trasy a propojení vzniká jedině přes formulář.
 */
class Routes {
	/** Odeslání trasy běží mimo uložení příspěvku. */
	const CRON_HOOK = 'kct_turinka_route_push';

	/**
	 * Odstup, po kterém se trasa odešle. Stejný důvod jako u akcí: redakce
	 * uloží příspěvek několikrát za sebou a minuta z toho udělá jedno odeslání.
	 */
	const DELAY = 60;

	/** Nonce odkazu „Odebrat z Turinky“ v hlášce. */
	const REMOVE_NONCE = 'kct-turinka-route-remove';

	/** Předpona transientu s trasami, o kterých se má redakce rozhodnout. */
	const PENDING_PREFIX = 'kct_turinka_route_pending_';

	/**
	 * Jak dlouho nabídka čeká. Delší než jedno načtení stránky být musí, déle
	 * než pracovní chvíli ne — rozhodnutí patří k té chvíli, kdy redakce článek
	 * mazala.
	 */
	const PENDING_TTL = 10 * MINUTE_IN_SECONDS;

	public function __construct(
		private Config $config,
		private Client $client,
		private TokenStore $tokens,
		private LinkState $state,
		private RoutePayload $payload,
		private RouteEmbed $embed,
		private PluginUtils $utils,
		private AssetFactory $assets
	) {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

		add_action( 'transition_post_status', array( $this, 'schedule_push' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'push' ) );
		add_action( 'admin_notices', array( $this, 'error_notice' ) );

		add_action( 'trashed_post', array( $this, 'offer_removal' ) );
		add_action( 'before_delete_post', array( $this, 'remember_deleted' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'removal_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_removal' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_embed_host' ) );
	}

	/**
	 * Skript metaboxu a hostitelský skript formuláře Turinky, jen na obrazovce
	 * příspěvku trasy.
	 */
	public function enqueue_admin( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || RoadPostType::KEY !== $screen->post_type || ! $this->tokens->has_token() ) {
			return;
		}

		// Hostitelský skript formuláře je cizí soubor na doméně aplikace: nemá
		// manifest ani verzi, kterou bychom znali, takže ručně — stejně jako
		// skript widgetu (viz enqueue_embed_host).
		wp_enqueue_script(
			'turinka-form-host',
			$this->config->public_url() . '/integrace/formular.js',
			array(),
			null,
			true
		);

		// Vlastní bundle přes factory: závislosti a verzi si vezme z
		// build/turinka-route.asset.php, takže se ručně neopisují. Volá se
		// uvnitř admin_enqueue_scripts, kde factory registruje a zařadí hned.
		$this->assets->admin_wp_script(
			$this->utils->get_plugin_path( 'build/turinka-route.js' ),
			array(
				'dependencies' => array( 'turinka-form-host' ),
				'in_footer'    => true,
			)
		);
	}

	/**
	 * Adresa formuláře pro tuhle trasu.
	 *
	 * Režim (`create` / `edit`) určuje Turinka podle toho, jestli `source_id`
	 * zná — plugin jí ho neříká a nehádá ho.
	 *
	 * @return array{form_url?: string, mode?: string, error?: string}
	 */
	public function form_url( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array( 'error' => __( 'Příspěvek neexistuje.', 'kct' ) );
		}

		$body = array(
			'type'       => 'route',
			'source_id'  => (string) $post_id,
			'source_url' => (string) get_permalink( $post ),
		);

		$prefill = $this->payload->prefill( (string) $post->post_title );

		if ( $prefill ) {
			$body['prefill'] = $prefill;
		}

		$result = $this->client->form_ticket( $this->tokens->token(), $body );

		if ( empty( $result['ok'] ) ) {
			return array( 'error' => $this->failure( $result ) );
		}

		return array(
			'form_url' => $this->config->rebase( (string) ( $result['data']['form_url'] ?? '' ) ),
			'mode'     => (string) ( $result['data']['mode'] ?? '' ),
		);
	}

	/**
	 * Dočte trasu, kterou redakce právě uložila ve formuláři.
	 *
	 * Identita se bere z odpovědi serveru, ne z rámu: rám hlásí, co si myslí,
	 * kdežto tohle je stav, který Turinka opravdu má. Text se pak naplánuje
	 * k odeslání — formulář ho sice ukazuje, ale vlastní ho web.
	 *
	 * @return array{linked?: bool, error?: string}
	 */
	public function link( int $post_id ): array {
		$result = $this->client->route_by_source( $this->tokens->token(), (string) $post_id );

		if ( empty( $result['ok'] ) ) {
			return array( 'error' => $this->failure( $result ) );
		}

		$this->state->remember( $post_id, $result['data'] );
		$this->state->clear_error( $post_id );
		$this->schedule( $post_id );

		return array( 'linked' => true );
	}

	/**
	 * Odebere trasu z Turinky.
	 *
	 * Odpověď má tři podoby a každá znamená pro redakci něco jiného, takže se
	 * převádí na větu, ne na „hotovo“.
	 *
	 * @param string $title Název trasy; u smazaného příspěvku ho zná jen
	 *                      nabídka, protože příspěvek už neexistuje.
	 *
	 * @return array{message?: string, error?: string}
	 */
	public function remove( int $post_id, string $title = '' ): array {
		if ( ! $this->state->is_linked( $post_id ) && ! isset( $this->pending()[ $post_id ] ) ) {
			return array( 'message' => __( 'Trasa v Turince propojená není, odebírat není co.', 'kct' ) );
		}

		$result = $this->client->delete_route( $this->tokens->token(), (string) $post_id );

		// Trasa, kterou Turinka nezná, je pro web totéž jako odebraná — někdo ji
		// smazal tam. Propojení tu nemá co držet dál.
		if ( empty( $result['ok'] ) && 404 !== (int) $result['status'] ) {
			return array( 'error' => $this->failure( $result ) );
		}

		$outcome = (string) ( $result['data']['result'] ?? '' );
		$this->state->forget( $post_id );

		return array( 'message' => $this->removal_message( $outcome, $title ) );
	}

	/**
	 * Naplánuje odeslání trasy.
	 *
	 * Na `transition_post_status`, protože ten se spustí při každém uložení
	 * včetně publikování, odpublikování i přesunu do koše — jedno místo pro
	 * všechny případy. Meta v tu chvíli ještě uložená není, ale to nevadí:
	 * plánuje se jen ID a stav se čte až v úloze.
	 */
	public function schedule_push( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || RoadPostType::KEY !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$this->schedule( (int) $post->ID );
	}

	private function schedule( int $post_id ): void {
		if ( ! $this->tokens->has_token() ) {
			return;
		}

		wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Odešle název a text trasy.
	 *
	 * Jen u propojené trasy: novou trasu plugin založit nemůže, protože API
	 * u ní vyžaduje GPX stopu. Nepropojený příspěvek tedy úloha tiše opustí —
	 * není to chyba, je to normální stav trasy, kterou redakce zatím jen
	 * rozepsala.
	 */
	public function push( int $post_id ): void {
		if ( ! $this->tokens->has_token() || ! $this->state->is_linked( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || RoadPostType::KEY !== $post->post_type ) {
			return;
		}

		$published = 'publish' === $post->post_status;

		// Název jen napoprvé — dál ho vlastní formulář Turinky. `is_sent()` je
		// pravda od prvního úspěšného odeslání, takže tahle podmínka přesně
		// odpovídá „poprvé“.
		$first     = ! $this->state->is_sent( $post_id );
		$payload   = $this->payload->route(
			$post_id,
			(string) $post->post_title,
			(string) apply_filters( 'the_content', (string) $post->post_content ),
			(string) get_permalink( $post ),
			$published,
			$first
		);

		$hash = $this->signature( $payload, $post );

		if ( $hash === $this->state->hash( $post_id ) ) {
			return;
		}

		$result = $this->client->write_route( $this->tokens->token(), $payload );

		if ( ! empty( $result['ok'] ) ) {
			$this->state->mark_sent( $post_id, $hash, $result['data'] );

			return;
		}

		// Trasu, kterou Turinka nezná, někdo smazal tam. Opakovat zápis nemá co
		// a držet propojení na neexistující trasu by redakci jen lhalo.
		//
		// Pozor na dvě různé odpovědi na tutéž situaci. Čtení neznámé trasy
		// vrátí 404, ale ZÁPIS na neznámé `source_id` je pro API pokus o nové
		// založení, a na to odpoví 422 s `gpx_file_id: Pole je u nové trasy
		// povinné.` — stopu plugin poslat neumí a nemá. Kdyby se tu hlídala jen
		// 404, redakce by se po smazání trasy v Turince dozvěděla jedině to, že
		// „pole je povinné“, a neměla by tušení které ani proč.
		if ( $this->is_missing_in_turinka( $result ) ) {
			$this->state->forget( $post_id );
			$this->state->mark_error( $post_id, __( 'Trasa v Turince už není. Propojení bylo zrušeno; novou trasu lze založit tlačítkem.', 'kct' ) );

			return;
		}

		$this->state->mark_error( $post_id, $this->failure( $result ) );
	}

	/**
	 * Pozná odpověď „tuhle trasu v Turince nemáme“.
	 *
	 * Buď 404 u čtení, nebo 422 u zápisu s chybou na `gpx_file_id` — API totiž
	 * zápis na neznámé `source_id` chápe jako zakládání nové trasy a tam stopu
	 * vyžaduje. Jiná 422 (třeba prázdný název) je skutečná vada obsahu a tou se
	 * propojení rušit nesmí.
	 */
	private function is_missing_in_turinka( array $result ): bool {
		$status = (int) ( $result['status'] ?? 0 );

		if ( 404 === $status ) {
			return true;
		}

		return 422 === $status && array_key_exists( 'gpx_file_id', (array) ( $result['errors'] ?? array() ) );
	}

	/**
	 * Otisk, podle kterého se pozná, že se trasa od posledního odeslání
	 * změnila.
	 *
	 * Text se do otisku bere **syrový z příspěvku**, ne vykreslený. Vykreslení
	 * není deterministické: blok obrázku dostane od jádra při každém průchodu
	 * nové `data-wp-key` a `imageId`, takže by se trasa s obrázkem odesílala
	 * při každém uložení znovu, i když se nic nezměnilo.
	 */
	private function signature( array $payload, WP_Post $post ): string {
		$payload['description'] = $post->post_content;

		return md5( (string) wp_json_encode( $payload ) );
	}

	// Skrytí meta `kct_turinka_*` před editorem vlastních polí se tady
	// nepřidává: filtr `is_protected_meta` ve Features\Turinka se neptá na typ
	// příspěvku, takže platí i pro trasy. Druhý stejný filtr by jen zdvojil
	// práci a rozešel se při první změně jednoho z nich.

	/**
	 * Chyba odeslání u právě otevřené trasy.
	 *
	 * Metabox ji ukazuje taky, ale ten je na kraji obrazovky; hláška nahoře je
	 * to, co redakce po uložení opravdu vidí.
	 */
	public function error_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || RoadPostType::KEY !== $screen->id || 'post' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen čtení id z adresy obrazovky.
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$error   = $post_id ? $this->state->error( $post_id ) : '';

		if ( '' === $error ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s %s</p></div>',
			esc_html__( 'Do Turinky se tuhle trasu nepodařilo odeslat:', 'kct' ),
			esc_html( $error )
		);
	}

	/**
	 * Po přesunu do koše nabídne odebrání z Turinky.
	 *
	 * Nabídne, ne provede: v Turince je trasa dál a je to dobře — redakce může
	 * chtít stáhnout článek a trasu nechat. Totéž říká API: když požadavek
	 * nepřijde, položka v Turince prostě žije dál.
	 */
	public function offer_removal( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || RoadPostType::KEY !== $post->post_type ) {
			return;
		}

		if ( ! $this->state->is_linked( $post_id ) ) {
			return;
		}

		$this->add_pending( $post_id, (string) $post->post_title );
	}

	/**
	 * Smazání natrvalo: identitu trasy je potřeba odložit **dřív**, než
	 * s příspěvkem zmizí jeho meta. Po `deleted_post` už není z čeho hlášku
	 * složit.
	 */
	public function remember_deleted( int $post_id, $post = null ): void {
		$post = $post instanceof WP_Post ? $post : get_post( $post_id );

		if ( ! $post instanceof WP_Post || RoadPostType::KEY !== $post->post_type ) {
			return;
		}

		if ( ! $this->state->is_linked( $post_id ) ) {
			return;
		}

		$this->add_pending( $post_id, (string) $post->post_title );
	}

	/**
	 * Hláška s rozhodnutím.
	 *
	 * Dialog navěšený na „Přesunout do koše“ se vědomě nedělá: to tlačítko je
	 * komponenta editoru, kterou jádro nijak nefiltruje, a zásah do ní se
	 * rozbije při aktualizaci WordPressu. Hláška se tomu vyhne a zeptá se ve
	 * stejnou chvíli.
	 *
	 * Výsledek odebrání se vypisuje **před** podmínkou na prázdnou nabídku: po
	 * úspěšném odebrání je nabídka prázdná a za podmínkou by se zpráva nikdy
	 * neukázala.
	 */
	public function removal_notice(): void {
		$done_key = self::PENDING_PREFIX . 'done_' . get_current_user_id();
		$done     = get_transient( $done_key );

		if ( $done ) {
			delete_transient( $done_key );
			printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( (string) $done ) );
		}

		$pending = $this->pending();

		if ( ! $pending ) {
			return;
		}

		foreach ( $pending as $post_id => $title ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'kct-action' => 'turinka_route_remove',
						'post'       => (int) $post_id,
					),
					admin_url( 'edit.php?post_type=' . RoadPostType::KEY )
				),
				self::REMOVE_NONCE
			);

			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a class="button button-small" href="%s">%s</a></p></div>',
				esc_html( sprintf(
					/* translators: %s: název trasy. */
					__( 'Článek je pryč z webu. Trasa „%s“ v Turince zůstala viditelná.', 'kct' ),
					$title
				) ),
				esc_url( $url ),
				esc_html__( 'Odebrat z Turinky', 'kct' )
			);
		}
	}

	/**
	 * Obsluha odkazu z hlášky.
	 *
	 * Název se bere z nabídky, ne z příspěvku — ten už nemusí existovat.
	 */
	public function handle_removal(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání akce, nonce se ověřuje níž.
		if ( ! isset( $_REQUEST['kct-action'] ) || 'turinka_route_remove' !== sanitize_key( wp_unslash( $_REQUEST['kct-action'] ) ) ) {
			return;
		}

		$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0;

		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), self::REMOVE_NONCE )
			|| ! current_user_can( 'delete_posts' )
		) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ), '', array( 'response' => 403 ) );
		}

		$pending = $this->pending();
		$title   = (string) ( $pending[ $post_id ] ?? '' );
		$result  = $this->remove( $post_id, $title );

		unset( $pending[ $post_id ] );
		set_transient( $this->pending_key(), $pending, self::PENDING_TTL );

		set_transient(
			self::PENDING_PREFIX . 'done_' . get_current_user_id(),
			$result['message'] ?? ( $result['error'] ?? '' ),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . RoadPostType::KEY ) );
		exit;
	}

	/**
	 * Skript, který dopočítává výšku vloženého rámu, do hlavičky stránky trasy.
	 *
	 * Do hlavičky, ne vedle rámu: rám hlásí svou výšku hned po načtení a jen
	 * při změně. Když posluchač v tu chvíli ještě neexistuje, zpráva se ztratí
	 * a druhá už nepřijde — rám pak navždy zůstane na výchozích 320 px.
	 */
	public function enqueue_embed_host(): void {
		if ( ! $this->embed->is_enabled() || ! is_singular( RoadPostType::KEY ) ) {
			return;
		}

		wp_enqueue_script( 'kct-turinka-embed-host', $this->embed->host_script_url(), array(), null, false );
	}

	/**
	 * Zapíše trasu do nabídky. Klíč je id příspěvku, takže opakované smazání
	 * téhož nic nezdvojí.
	 */
	private function add_pending( int $post_id, string $title ): void {
		$pending             = $this->pending();
		$pending[ $post_id ] = $title;

		set_transient( $this->pending_key(), $pending, self::PENDING_TTL );
	}

	/**
	 * @return array<int, string> id příspěvku → název trasy
	 */
	private function pending(): array {
		$pending = get_transient( $this->pending_key() );

		return is_array( $pending ) ? $pending : array();
	}

	private function pending_key(): string {
		return self::PENDING_PREFIX . get_current_user_id();
	}

	/**
	 * Co se odebráním stalo, slovy pro redakci.
	 */
	private function removal_message( string $outcome, string $title ): string {
		$name = '' !== $title ? sprintf( '„%s“ ', $title ) : '';

		return match ( $outcome ) {
			'archived' => sprintf(
				/* translators: %s: název trasy v uvozovkách, nebo nic. */
				__( 'Trasa %sje v Turince schovaná a lidé ji už nenajdou. Další uložení příspěvku ji vrátí.', 'kct' ),
				$name
			),
			'unlinked' => sprintf(
				/* translators: %s: název trasy v uvozovkách, nebo nic. */
				__( 'Web se od trasy %sodpojil. Trasa v Turince zůstává — patří někomu jinému a web ji jen doplňoval.', 'kct' ),
				$name
			),
			default    => sprintf(
				/* translators: %s: název trasy v uvozovkách, nebo nic. */
				__( 'Trasa %sv Turince už není propojená s tímhle webem.', 'kct' ),
				$name
			),
		};
	}

	/**
	 * Chybová hláška z odpovědi. Nedostupnou Turinku je potřeba odlišit od
	 * odpovědi, kterou Turinka poslala — pro redakci je to jiný problém.
	 */
	private function failure( array $result ): string {
		if ( 0 === (int) $result['status'] ) {
			return sprintf(
				/* translators: %s: popis chyby spojení. */
				__( 'Turinku se nepodařilo zastihnout: %s', 'kct' ),
				(string) $result['message']
			);
		}

		if ( 403 === (int) $result['status'] ) {
			return __( 'Trasu v Turince stáhl její správce. Zápis se neprovede, dokud ji neobnoví.', 'kct' );
		}

		$fields = array_filter( (array) $result['errors'], 'is_scalar' );

		return $fields ? implode( ' ', $fields ) : (string) $result['message'];
	}
}
