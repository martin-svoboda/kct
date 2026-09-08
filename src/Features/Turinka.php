<?php

namespace Kct\Features;

use Kct\PostTypes\EventPostType;
use Kct\Repositories\SettingsRepository;
use Kct\Settings;
use Kct\Turinka\Client;
use Kct\Turinka\Config;
use Kct\Turinka\EventPayload;
use Kct\Turinka\Embed;
use Kct\Turinka\EventState;
use Kct\Turinka\TokenStore;
use WP_Post;

/**
 * Propojení webu s aplikací Turinka — zatím jen získání a ověření přístupu.
 *
 * Jediné místo, které zná hooky; Client, Config ani TokenStore o WordPressu nic
 * nevědí. Stejné rozdělení jako u sdílení na Facebook.
 */
class Turinka {

	/** Nonce tlačítek v nastavení. */
	const REQUEST_NONCE = 'kct-turinka-request';
	const VERIFY_NONCE  = 'kct-turinka-verify';

	/** Předpona transientu s výsledkem tlačítka; per uživatel. */
	const RESULT_PREFIX = 'kct_turinka_result_';

	const RESULT_TTL = 60;

	/** Pole v nastavení, kterým jde token vložit ručně. */
	const MANUAL_TOKEN_FIELD = 'turinka_token';

	/** Odeslání akce běží mimo uložení příspěvku. */
	const CRON_HOOK = 'kct_turinka_push';

	/**
	 * Odstup, po kterém se akce odešle.
	 *
	 * Redakce často uloží akci několikrát za sebou; minuta odstupu z toho udělá
	 * jedno odeslání místo pěti. Zároveň je to čas, po který se dá překlep ještě
	 * opravit dřív, než se rozejde do světa.
	 */
	const DELAY = 60;

	public function __construct(
		private Config $config,
		private Client $client,
		private TokenStore $tokens,
		private SettingsRepository $settings,
		private EventState $state,
		private EventPayload $payload,
		private Events $events,
		private Embed $embed
	) {
		add_action( 'admin_init', array( $this, 'handle_request' ) );
		add_action( 'admin_init', array( $this, 'handle_verify' ) );
		add_action( 'admin_init', array( $this, 'take_manual_token' ) );
		add_action( 'admin_notices', array( $this, 'result_notice' ) );
		add_action( 'admin_notices', array( $this, 'event_error_notice' ) );

		add_action( 'transition_post_status', array( $this, 'schedule_push' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'push' ) );

		add_filter( 'is_protected_meta', array( $this, 'protect_meta' ), 10, 2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_embed_host' ) );
	}

	/**
	 * Skript, který dopočítává výšku vloženého rámu, do hlavičky stránky akce.
	 *
	 * Do hlavičky, ne vedle rámu ve snippetu, a to je celý smysl: rám hlásí svou
	 * výšku hned po načtení a **jen při změně** (pojistka proti nekonečné smyčce
	 * ResizeObserver → resize). Když posluchač v tu chvíli ještě neexistuje,
	 * zpráva se ztratí a druhá už nepřijde — rám pak navždy zůstane na výchozích
	 * 320 px a obsah v něm scrolluje. Skript vedle rámu s `async` je závod, který
	 * se prohrává tím častěji, čím rychleji se rám načte.
	 *
	 * Načítá se jen na stránce akce — jinde žádný rám není.
	 */
	public function enqueue_embed_host(): void {
		if ( ! $this->embed->is_enabled() ) {
			return;
		}

		if ( ! is_singular( EventPostType::KEY ) && ! get_query_var( 'db_id' ) ) {
			return;
		}

		wp_enqueue_script(
			'kct-turinka-embed-host',
			$this->embed->host_script_url(),
			array(),
			null,
			false
		);
	}

	/**
	 * Naplánuje odeslání akce.
	 *
	 * Na `transition_post_status`, protože ten se spustí při každém uložení
	 * včetně publikování, odpublikování i přesunu do koše — jedno místo pro
	 * všechny případy. Meta v tu chvíli ještě uložená není, ale to nevadí:
	 * plánuje se jen ID a stav se čte až v úloze.
	 */
	public function schedule_push( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || EventPostType::KEY !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( ! $this->tokens->has_token() ) {
			return;
		}

		wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK, array( (int) $post->ID ) );
	}

	/**
	 * Odešle akci do Turinky.
	 *
	 * Zatím jen obsah k akci, kterou Turinka zná z feedu — tedy k akci
	 * s vyplněným `db_id`. Vlastní akce přibudou samostatně.
	 */
	public function push( int $post_id ): void {
		if ( ! $this->tokens->has_token() ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || EventPostType::KEY !== $post->post_type ) {
			return;
		}

		if ( EventState::STATE_OFF === $this->state->desired( $post_id ) ) {
			return;
		}

		$kct_id = trim( (string) get_post_meta( $post_id, 'db_id', true ) );

		if ( '' === $kct_id || '0' === $kct_id ) {
			$this->push_own_event( $post );

			return;
		}

		$this->push_content( $post, $kct_id );
	}

	/**
	 * Vlastní akce webu — ta, kterou centrální databáze KČT nezná.
	 *
	 * Odpublikovaná akce se ruší, ne maže: lidé ji můžou mít v plánu a v deníku
	 * a Turinka jim zrušení ohlásí. Proto se u ní posílá `is_cancelled` místo
	 * toho, aby se prostě přestala posílat.
	 */
	private function push_own_event( WP_Post $post ): void {
		$published = 'publish' === $post->post_status;

		if ( ! $published && ! $this->state->is_sent( $post->ID ) ) {
			return;
		}

		$event   = $this->events->get_event( $post->ID, '' );
		$state   = $this->state->desired( $post->ID );
		$payload = $this->payload->own_event( $event, (int) $post->ID, $state, $published );

		// Akce bez data by v Turince nešla zařadit do kalendáře, kvůli kterému
		// vznikla — a odpovědí by bylo 422. Chybějící datum je ale běžný stav
		// rozepsané akce, ne chyba, kterou má redakce řešit hláškou.
		if ( null === $payload['starts_at'] ) {
			return;
		}

		$hash = $this->signature( $payload, $post );

		if ( $hash === $this->state->hash( $post->ID ) ) {
			return;
		}

		$result = $this->client->write_event( $this->tokens->token(), $payload );

		if ( ! empty( $result['ok'] ) ) {
			$this->state->mark_sent( $post->ID, $hash, $result['data'] );

			return;
		}

		$this->state->mark_error( $post->ID, $this->write_failure( $result ) );
	}

	/**
	 * Chyba zápisu vlastní akce.
	 *
	 * Odpověď 409 není odmítnutí — Turinka jen poznala, že tahle akce už
	 * v centrální databázi je, a poslala její číslo. Je to totéž číslo, na které
	 * má plugin pole, takže se z toho udělá pokyn pro redakci. Vyplnit ho
	 * automaticky by znamenalo přepsat podle cizího odhadu pole, kterým se řídí
	 * i zobrazení akce na webu.
	 */
	private function write_failure( array $result ): string {
		if ( 409 !== (int) $result['status'] ) {
			return $this->failure( $result );
		}

		$kct_id = (string) ( $result['body']['kct_id'] ?? '' );

		if ( '' === $kct_id ) {
			return $this->failure( $result );
		}

		return sprintf(
			/* translators: %s: číslo akce v centrální databázi KČT. */
			__( 'Turinka tuhle akci zná z centrální databáze KČT pod číslem %s. Doplňte ho do pole „ID akce z kct-db“ — obsah pak půjde k té existující akci a nevznikne duplicita.', 'kct' ),
			$kct_id
		);
	}

	/**
	 * Obsah k akci z feedu.
	 *
	 * Odpublikovaná akce si odkaz na sebe bere zpátky: stránka na webu zmizela,
	 * takže není kam vyhledávač poslat a akce se má vrátit do indexu Turinky.
	 * Obsah v Turince zůstává — ten web dodal a nezmizel s ním.
	 */
	private function push_content( WP_Post $post, string $kct_id ): void {
		$published = 'publish' === $post->post_status;

		// Akce, která nikdy neodešla a teď není publikovaná, nemá co řešit.
		if ( ! $published && ! $this->state->is_sent( $post->ID ) ) {
			return;
		}

		$event = $this->events->get_event( $post->ID, $kct_id );

		// Bez vlastního textu není co dodávat. Turinka by odpověděla 422 „popis
		// je povinný" a redakce by dostala chybu za akci, u které nic nechtěla —
		// a takových je většina.
		if ( 0 === $this->payload->description_length( $event ) ) {
			return;
		}

		$payload = $this->payload->content( $event, $published );
		$hash    = $this->signature( $payload, $post, $kct_id );

		if ( $hash === $this->state->hash( $post->ID ) ) {
			return;
		}

		$result = $this->client->write_content( $this->tokens->token(), $kct_id, $payload );

		if ( empty( $result['ok'] ) ) {
			$this->state->mark_error( $post->ID, $this->failure( $result ) );

			return;
		}

		$this->state->mark_sent( $post->ID, $hash, $result['data'] );
	}

	/**
	 * Otisk, podle kterého se pozná, že se akce od posledního odeslání změnila.
	 *
	 * Popis se do otisku bere **syrový z příspěvku**, ne vykreslený. Vykreslení
	 * není deterministické: blok obrázku dostane od jádra při každém průchodu
	 * nové `data-wp-key` a `imageId` (interaktivní API), takže by se akce
	 * s obrázkem odesílala při každém uložení znovu, i když se nic nezměnilo.
	 * Zdrojem pravdy o změně je příspěvek, ne jeho výstup.
	 */
	private function signature( array $payload, WP_Post $post, string $kct_id = '' ): string {
		$payload['description'] = $post->post_content;

		return md5( (string) wp_json_encode( $payload ) . '|' . $kct_id );
	}

	/**
	 * Chyba odeslání u právě otevřené akce.
	 */
	public function event_error_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || EventPostType::KEY !== $screen->id || 'post' !== $screen->base ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$error   = $post_id ? $this->state->error( $post_id ) : '';

		if ( '' === $error ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s %s</p></div>',
			esc_html__( 'Do Turinky se tuhle akci nepodařilo odeslat:', 'kct' ),
			esc_html( $error )
		);
	}

	/**
	 * Provozní stav propojení do editoru vlastních polí nepatří — mění ho
	 * plugin, ne člověk. Volba redakce (turinka_state) skrytá není.
	 */
	public function protect_meta( bool $protected, string $meta_key ): bool {
		return $protected || str_starts_with( $meta_key, 'kct_turinka_' );
	}

	/**
	 * Odešle žádost o přístup.
	 *
	 * Vlastníka pošle podle kódu webu — bez něj se tlačítko ani nenabízí,
	 * protože žádost bez vlastníka v Turince uvázne neschválená.
	 */
	public function handle_request(): void {
		if ( ! $this->is_action( 'turinka_request' ) ) {
			return;
		}

		$this->guard( self::REQUEST_NONCE, __( 'K žádosti o přístup k Turince nemáte oprávnění.', 'kct' ) );

		$field = $this->owner_field();
		$code  = (string) $this->settings->get_option( 'id_code' );

		if ( '' === $field ) {
			$this->finish( __( 'Nejdřív vyplňte kód oblasti nebo odboru — bez něj nemá Turinka žádost k čemu přiřadit.', 'kct' ) );
		}

		$domain = $this->config->site_domain();
		$result = $this->client->request_access( $domain, $field, $code );

		if ( empty( $result['ok'] ) ) {
			$this->finish( $this->failure( $result ) );
		}

		$this->tokens->mark_pending( $domain );

		$this->finish( sprintf(
			/* translators: %s: doména webu. */
			__( 'Žádost o přístup pro doménu %s odešla. Až ji správce Turinky schválí, token dorazí sám; když se to nepovede, vloží se ručně níž.', 'kct' ),
			$domain
		) );
	}

	/**
	 * Ověří uložený token a obnoví podle Turinky vlastníka i oprávnění.
	 */
	public function handle_verify(): void {
		if ( ! $this->is_action( 'turinka_verify' ) ) {
			return;
		}

		$this->guard( self::VERIFY_NONCE, __( 'K ověření připojení k Turince nemáte oprávnění.', 'kct' ) );

		if ( ! $this->tokens->has_token() ) {
			$this->finish( __( 'Web zatím nemá token k Turince.', 'kct' ) );
		}

		$result = $this->client->me( $this->tokens->token() );

		if ( empty( $result['ok'] ) ) {
			$this->finish( $this->failure( $result ) );
		}

		$this->tokens->refresh( $result['data'] );

		$this->finish( $this->identity_message( $result['data'] ) );
	}

	/**
	 * Převezme token vložený ručně do nastavení.
	 *
	 * Doručení je jednorázové a bez opakování, takže web nedostupný zvenčí by se
	 * bez téhle cesty nepropojil vůbec — správce Turinky token vidí na obrazovce
	 * a přenese ho sem.
	 *
	 * Z nastavení se pole vyprázdní vždycky, i když ověření selže. Jinak by se
	 * neplatný token pokoušel ověřit při každém načtení administrace a zůstal by
	 * v autoloadované option ležet v plaintextu.
	 *
	 * Na konci se přesměrovává, protože nastavení se skládá už na
	 * `plugins_loaded` — bez toho by stránka dopsala starý stav („čeká na
	 * schválení“) a pole na token by zůstalo viset i po úspěšném připojení.
	 */
	public function take_manual_token(): void {
		$options = $this->settings->get_options();
		$token   = trim( (string) ( $options[ self::MANUAL_TOKEN_FIELD ] ?? '' ) );

		if ( '' === $token ) {
			return;
		}

		unset( $options[ self::MANUAL_TOKEN_FIELD ] );
		update_option( Settings::KEY, $options );
		$this->settings->reset();

		$result = $this->client->me( $token );

		if ( empty( $result['ok'] ) ) {
			$this->finish( sprintf(
				/* translators: %s: důvod, proč se token nepodařilo ověřit. */
				__( 'Vložený token se nepodařilo ověřit a nebyl uložen. %s', 'kct' ),
				$this->failure( $result )
			) );
		}

		$this->tokens->activate( $token, $result['data'] );

		$this->finish( $this->identity_message( $result['data'] ) );
	}

	public function result_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_transient( $this->result_key() );

		if ( ! $message ) {
			return;
		}

		delete_transient( $this->result_key() );

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html( (string) $message )
		);
	}

	/**
	 * Pole vlastníka podle kódu webu, nebo prázdný řetězec bez kódu.
	 *
	 * Web patří jedné organizaci — odboru, nebo oblasti. Obojí naráz Turinka
	 * odmítá, takže se posílá právě jedno.
	 */
	private function owner_field(): string {
		return match ( $this->settings->code_type() ) {
			'department' => 'department_number',
			'region'     => 'region_number',
			default      => '',
		};
	}

	/**
	 * Věta o tom, komu přístup patří a co smí.
	 */
	private function identity_message( array $identity ): string {
		$owner  = (array) ( $identity['owner'] ?? array() );
		$scopes = (array) ( $identity['scopes'] ?? array() );

		return sprintf(
			/* translators: 1: doména, 2: jméno vlastníka, 3: seznam oprávnění. */
			__( 'Připojeno k Turince. Doména %1$s, vlastník %2$s, oprávnění: %3$s.', 'kct' ),
			(string) ( $identity['domain'] ?? '' ),
			(string) ( $owner['name'] ?? __( 'nepřiřazen', 'kct' ) ),
			$scopes ? implode( ', ', $scopes ) : __( 'žádná', 'kct' )
		);
	}

	/**
	 * Chybová hláška z odpovědi. Nedostupnou Turinku je potřeba odlišit od
	 * odpovědi, kterou Turinka poslala — pro správce webu je to jiný problém.
	 */
	private function failure( array $result ): string {
		if ( 0 === (int) $result['status'] ) {
			return sprintf(
				/* translators: %s: popis chyby spojení. */
				__( 'Turinku se nepodařilo zastihnout: %s', 'kct' ),
				(string) $result['message']
			);
		}

		// U validačních chyb je `detail` jen obecné „Neplatná data“; co je
		// doopravdy špatně, stojí v `errors` po polích.
		$fields = array_filter( (array) $result['errors'], 'is_scalar' );

		return $fields ? implode( ' ', $fields ) : (string) $result['message'];
	}

	private function is_action( string $action ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání akce, nonce se ověřuje v guard().
		if ( ! isset( $_REQUEST['kct-action'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viz výš.
		return $action === sanitize_key( wp_unslash( $_REQUEST['kct-action'] ) );
	}

	private function guard( string $nonce, string $denied ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( $denied ), '', array( 'response' => 403 ) );
		}

		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), $nonce )
		) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Uloží výsledek a vrátí správce na nastavení.
	 */
	private function finish( string $message ): void {
		$this->remember( $message );

		wp_safe_redirect( Settings::get_settings_url() );
		exit;
	}

	private function remember( string $message ): void {
		set_transient( $this->result_key(), $message, self::RESULT_TTL );
	}

	private function result_key(): string {
		return self::RESULT_PREFIX . get_current_user_id();
	}
}
