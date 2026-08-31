<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use Kct\Facebook\GraphClient;
use Kct\Facebook\MessageComposer;
use Kct\Facebook\Publisher;
use Kct\Facebook\ShareMetabox;
use Kct\Facebook\ShareSchedule;
use Kct\Facebook\ShareState;
use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PostPostType;
use Kct\Settings;
use KctDeps\Wpify\CustomFields\CustomFields;
use WP_Post;

/**
 * Automatické sdílení publikovaných aktualit a akcí na Facebook stránku.
 */
class FacebookShare {
	const CRON_HOOK = 'kct_facebook_share';

	/** Zpoždění odeslání po publikaci, aby se stihla uložit metadata. */
	const DELAY = 60;

	/** Odstupy opakovaných pokusů v sekundách: 5 min, 30 min, 2 h. */
	const RETRY_DELAYS = array( 300, 1800, 7200 );

	/**
	 * O kolik sekund musí být vypočtený čas odeslání v budoucnu, aby se
	 * přeplánovalo.
	 *
	 * Bez tolerance by se běh, kterému vyjde cíl o pár sekund dopředu, ještě
	 * jednou přeplánoval a odeslání by se zbytečně odložilo o další minutu.
	 */
	const SCHEDULE_TOLERANCE = 120;

	/** Option, do které se ukládá chyba tokenu pro upozornění v administraci. */
	const TOKEN_ERROR_OPTION = 'kct_fb_token_error';

	/** Akce nonce u tlačítka „Ověřit připojení“ v nastavení. */
	const VERIFY_NONCE = 'kct-fb-verify';

	/** Předpona transientu s výsledkem ověření připojení. */
	const VERIFY_RESULT_PREFIX = 'kct_fb_verify_result_';

	/** Jak dlouho (v sekundách) výsledek ověření čeká na zobrazení. */
	const VERIFY_RESULT_TTL = 60;

	/** Předpona transientu s výsledkem ručního odeslání („Zkusit znovu“). */
	const RETRY_RESULT_PREFIX = 'kct_fb_retry_result_';

	/** Jak dlouho (v sekundách) výsledek ručního odeslání čeká na zobrazení. */
	const RETRY_RESULT_TTL = 60;

	/** Výsledek pokusu: příspěvek se právě odeslal. */
	const RESULT_SHARED = 'shared';

	/** Výsledek pokusu: odeslání selhalo, Facebook vrátil chybu. */
	const RESULT_FAILED = 'failed';

	/** Výsledek pokusu: neodeslalo se, protože nebyla splněná některá podmínka. */
	const RESULT_SKIPPED = 'skipped';

	/**
	 * Metabox se stavem odeslání.
	 *
	 * Drží se ve vlastnosti, ne jen v uzávěře háčku — instance jinak nemá
	 * jiného vlastníka než add_action() uvnitř svého konstruktoru a její
	 * životnost by závisela na tom, co se s háčkem stane.
	 *
	 * Zůstane null, když sdílení není nastavené — metabox se pak vůbec
	 * netvoří, viz konstruktor.
	 *
	 * @var ShareMetabox|null
	 */
	private ?ShareMetabox $metabox = null;

	/**
	 * @param CustomFields    $wcf         Knihovna pro metabox s poli redaktora.
	 * @param Credentials     $credentials Konfigurace sdílení.
	 * @param GraphClient     $client      Klient Graph API.
	 * @param MessageComposer $composer    Skládání textu a odkazu.
	 * @param ShareState      $state       Stav odeslání v post meta.
	 */
	public function __construct(
		private CustomFields $wcf,
		private Credentials $credentials,
		private GraphClient $client,
		private MessageComposer $composer,
		private ShareState $state,
		private OgImages $og_images,
		private ShareSchedule $schedule,
		private Events $events,
		private Publisher $publisher
	) {
		add_action( 'transition_post_status', array( $this, 'maybe_schedule' ), 10, 3 );

		// Termín akce se může po publikaci posunout. Naplánované odeslání se
		// proto zruší a rozhodne se znovu — priorita 20, ať jsou metadata
		// s novým datem už uložená.
		add_action( 'save_post_' . EventPostType::KEY, array( $this, 'reschedule' ), 20, 2 );
		add_action( self::CRON_HOOK, array( $this, 'share' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'is_protected_meta', array( $this, 'protect_meta' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_retry' ) );
		add_action( 'admin_init', array( $this, 'handle_verify' ) );
		add_action( 'admin_notices', array( $this, 'token_notice' ) );
		add_action( 'admin_notices', array( $this, 'verify_notice' ) );
		add_action( 'admin_notices', array( $this, 'retry_notice' ) );

		// Oba metaboxy se registrují jen tam, kde je sdílení nastavené. Bez ID
		// stránky a tokenu se stejně nic neodešle, takže přepínač „Sdílet na
		// Facebook" i pole pro text a časování jen slibují něco, co se nestane
		// — a redakci to mate víc, než by jí pomohlo.
		//
		// Kontrola je tady, ne uvnitř jednotlivých metaboxů: je to jedna
		// podmínka na jednom místě a obojí má stejný důvod k existenci.
		if ( ! $this->credentials->is_configured() ) {
			return;
		}

		$this->register_metabox();

		// ShareMetabox se tvoří přímo, ne přes kontejner: potřebuje seznam
		// typů příspěvků, který je definovaný tady ve feature, takže by ho
		// kontejner stejně nedokázal sestavit bez další konfigurace.
		$this->metabox = new ShareMetabox(
			$this->state,
			$this->post_types(),
			fn( WP_Post $post ): string => $this->schedule_note( $post )
		);
	}

	/**
	 * Typy příspěvků, které se sdílejí na Facebook.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		return array( PostPostType::KEY, EventPostType::KEY );
	}

	/**
	 * Naplánuje odeslání při přechodu do stavu publikováno.
	 *
	 * Pozor: tento hook běží dřív, než se uloží metabox, takže hodnota přepínače
	 * tady ještě nemusí být aktuální. Definitivní kontrola je až v share().
	 *
	 * @param string $new_status Nový stav příspěvku.
	 * @param string $old_status Předchozí stav příspěvku.
	 * @param mixed  $post       Příspěvek, kterého se změna týká.
	 */
	public function maybe_schedule( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		// Heslem chráněný příspěvek se nesdílí — jeho obsah je záměrně
		// neveřejný, viz share().
		if ( '' !== $post->post_password ) {
			return;
		}

		if ( ! $this->credentials->is_configured() || $this->state->is_shared( $post->ID ) ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, array( $post->ID ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK, array( $post->ID ) );
	}

	/**
	 * Odešle příspěvek na Facebook.
	 *
	 * Podmínky se kontrolují znovu a celé — argument cron události pochází
	 * z pole naplánovaných úloh v options, ne z ověřeného requestu, a hook
	 * je veřejný název akce, který může spustit kdokoli. Příspěvek mezitím
	 * mohl změnit typ, stav i heslo.
	 *
	 * Odeslání chrání zámek (ShareState::claim()), aby dva souběžné běhy
	 * nezveřejnily tentýž příspěvek dvakrát.
	 *
	 * Pozor na požadavek, který na straně Facebooku uspěje, ale u nás skončí
	 * timeoutem: příspěvek na zdi vznikne, my ale dostaneme chybu a uložíme
	 * ji. Právě proto se chyba spojení (kód 0) automaticky neopakuje, viz
	 * handle_failure().
	 *
	 * Pozor také na switch_to_blog(): SettingsRepository drží nastavení
	 * v paměti po celý proces a kontejner je singleton, takže volání share()
	 * uvnitř switch_to_blog() by vzalo Page ID i token původního webu.
	 * Dnes to nenastane (nikde v pluginu se pod switch_to_blog() nepublikuje
	 * příspěvek typu post ani akce), ale až taková cesta vznikne, je tohle
	 * místo, které se rozbije.
	 *
	 * @param int $post_id ID příspěvku k odeslání.
	 */
	public function share( $post_id = 0 ): void {
		$post = get_post( intval( $post_id ) );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		// Heslem chráněný příspěvek se nesdílí: obsah je záměrně neveřejný,
		// ale stav zůstává 'publish', takže by prošel dál a MessageComposer
		// by z něj složil perex ze syrového post_content.
		if ( '' !== $post->post_password ) {
			return;
		}

		if ( ! $this->credentials->is_configured() || $this->state->is_shared( $post->ID ) ) {
			return;
		}

		if ( ! $this->state->should_share( $post->ID, $this->credentials->share_default_for( $post->post_type ) ) ) {
			return;
		}

		// Akce se neodesílají hned po publikaci, ale s odstupem před začátkem.
		//
		// Rozhoduje se až tady, ne v maybe_schedule(): transition_post_status
		// běží uvnitř wp_insert_post(), tedy dřív, než metaboxy uloží svoje
		// metadata — datum akce tam ještě nemusí být. Proto má tenhle běh
		// zpoždění DELAY, viz komentář u té konstanty.
		if ( EventPostType::KEY === $post->post_type ) {
			$target = $this->schedule->target_for_event(
				$this->events->get_event( $post->ID, '' ),
				$this->lead_override( (int) $post->ID )
			);

			if ( null === $target ) {
				// Proběhlá akce. Pozvánka na loňský pochod je horší než
				// žádný příspěvek.
				return;
			}

			if ( $target > time() + self::SCHEDULE_TOLERANCE ) {
				wp_schedule_single_event( $target, self::CRON_HOOK, array( (int) $post->ID ) );

				return;
			}
		}

		// Zámek se zabírá až po všech kontrolách — běh, který stejně nic
		// neodešle, nemá blokovat ten, který by odeslat mohl.
		if ( ! $this->state->claim( $post->ID ) ) {
			return;
		}

		try {
			// Kontrola se opakuje pod zámkem: mezi ní a zabráním zámku je pár
			// řádků, ve kterých mohl souběžný běh odeslání dokončit.
			if ( $this->state->is_shared( $post->ID ) ) {
				return;
			}

			$result = $this->publisher->send(
				$this->composer->compose( $post ),
				$this->composer->compose_with_link( $post ),
				$this->composer->link( $post ),
				$this->social_image( $post )
			);

			if ( ! empty( $result['ok'] ) ) {
				$this->state->mark_shared( $post->ID, (string) $result['id'] );

				// Odeslání prokázalo, že token funguje — upozornění z dřívějška
				// už neplatí a nemá dál strašit v administraci.
				delete_option( self::TOKEN_ERROR_OPTION );

				return;
			}

			$this->state->mark_error( $post->ID, (int) $result['code'], (string) $result['message'] );
			$this->handle_failure( $post->ID, (int) $result['code'], (string) $result['message'] );
		} finally {
			$this->state->release( $post->ID );
		}
	}

	/**
	 * Rozhodne, co dál po neúspěšném odeslání.
	 *
	 * Buď naplánuje další pokus s rostoucím odstupem podle RETRY_DELAYS, nebo
	 * pokusy zastaví — u chyb, se kterými opakování nic nesvede.
	 *
	 * Neplatný token (kód 190) se neopakuje: dokud ho někdo nevymění, dopadne
	 * každý další pokus stejně. Místo opakování se uloží option, ze které
	 * token_notice() vypíše upozornění do administrace — netýká se totiž
	 * jednoho příspěvku, ale sdílení na celém webu.
	 *
	 * Neopakuje se ani chyba spojení (kód 0), a to záměrně: požadavek, který
	 * u nás skončil timeoutem, mohl na straně Facebooku uspět, takže by
	 * opakování přidalo na zeď druhý stejný příspěvek. WP_Error nerozliší
	 * timeout od nedostupné sítě (obojí je `http_request_failed`), takže se
	 * s oběma zachází stejně opatrně: automaticky nic, rozhodnutí zůstává na
	 * člověku. Ten si může zeď stránky prohlédnout a použít tlačítko „Zkusit
	 * znovu“ v editoru. Metabox u kódu 0 na tuhle možnost upozorňuje.
	 *
	 * Metoda je veřejná záměrně: vynucené odeslání z WP-CLI (`wp kct fb_share
	 * <id> --force`) obchází share() — musí umět odeslat i příspěvek, který už
	 * odeslaný byl — ale po selhání má projít úplně stejnou obsluhou jako
	 * automatická cesta. Bez toho by ruční odeslání zvýšilo počítadlo pokusů,
	 * ale opakování by nenaplánovalo a chybu tokenu by nikde nenahlásilo.
	 *
	 * @param int    $post_id ID příspěvku.
	 * @param int    $code    Kód chyby vrácený GraphClient.
	 * @param string $message Text chyby vrácený GraphClient.
	 */
	public function handle_failure( int $post_id, int $code, string $message ): void {
		if ( GraphClient::ERROR_INVALID_TOKEN === $code ) {
			// Bez autoloadu — na frontendu tahle hodnota k ničemu není.
			update_option( self::TOKEN_ERROR_OPTION, $message, false );

			return;
		}

		if ( 0 === $code ) {
			return;
		}

		// mark_error() počítadlo právě zvýšilo, takže po prvním selhání je na
		// jedné a sahá se na index 0. Pokusů je tedy o jeden víc, než kolik má
		// RETRY_DELAYS položek: první odeslání plus tři opakování.
		$index = $this->state->attempts( $post_id ) - 1;

		if ( ! isset( self::RETRY_DELAYS[ $index ] ) ) {
			return;
		}

		wp_schedule_single_event(
			time() + self::RETRY_DELAYS[ $index ],
			self::CRON_HOOK,
			array( $post_id )
		);
	}

	/**
	 * Zruší čekající naplánované odeslání příspěvku.
	 *
	 * Volá se před ručním odesláním — ať už z tlačítka v editoru, nebo z WP-CLI.
	 * Čekající událost je po ručním odeslání k ničemu a nechat ji viset by navíc
	 * rozbilo odstupy opakování: WordPress tiše zahodí událost naplánovanou do
	 * deseti minut od už existující (wp-includes/cron.php), takže by se po
	 * neúspěchu další pokus vůbec nenaplánoval.
	 *
	 * @param int $post_id ID příspěvku.
	 */
	public function unschedule( int $post_id ): void {
		$scheduled = wp_next_scheduled( self::CRON_HOOK, array( $post_id ) );

		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Snímek stavu odeslání pořízený před pokusem o odeslání.
	 *
	 * Slouží jako vstup pro outcome() — share() nic nevrací (je to obsluha cron
	 * události) a jediná stopa po pokusu je změna stavu v post meta.
	 *
	 * @param int $post_id ID příspěvku.
	 *
	 * @return array{fb_post_id: string, shared_at: int, attempts: int}
	 */
	public function snapshot( int $post_id ): array {
		return array(
			'fb_post_id' => $this->state->fb_post_id( $post_id ),
			'shared_at'  => $this->state->shared_at( $post_id ),
			'attempts'   => $this->state->attempts( $post_id ),
		);
	}

	/**
	 * Vyhodnotí, co se stalo při pokusu o odeslání, a složí hlášku pro člověka.
	 *
	 * Rozlišuje tři případy: odesláno, selhalo (s důvodem od Facebooku)
	 * a neodesláno kvůli podmínce (vypnutý přepínač, koncept, heslo…). share()
	 * na podmínkách tiše končí — bez tohohle vyhodnocení by tlačítko „Zkusit
	 * znovu“ i WP-CLI příkaz mlčky neudělaly nic.
	 *
	 * @param int                                                  $post_id ID příspěvku.
	 * @param array{fb_post_id: string, shared_at: int, attempts: int} $before Snímek stavu před pokusem.
	 *
	 * @return array{status: string, message: string}
	 */
	public function outcome( int $post_id, array $before ): array {
		// Počítadlo pokusů zvyšuje jen mark_error(), takže jeho růst je
		// nejspolehlivější stopa po neúspěšném pokusu — a to i tehdy, když
		// Facebook vrátil úspěch bez použitelného ID příspěvku.
		if ( $this->state->attempts( $post_id ) > (int) $before['attempts'] ) {
			$error = $this->state->error( $post_id );

			return array(
				'status'  => self::RESULT_FAILED,
				'message' => $this->failure_message( (int) ( $error['code'] ?? 0 ), (string) ( $error['message'] ?? '' ) ),
			);
		}

		if ( $this->state->is_shared( $post_id ) ) {
			$fb_post_id = $this->state->fb_post_id( $post_id );

			if ( $fb_post_id !== $before['fb_post_id'] || $this->state->shared_at( $post_id ) !== (int) $before['shared_at'] ) {
				return array(
					'status'  => self::RESULT_SHARED,
					'message' => sprintf(
						/* translators: %s: ID příspěvku na Facebooku. */
						__( 'Příspěvek byl odeslán na Facebook (ID %s).', 'kct' ),
						$fb_post_id
					),
				);
			}

			return array(
				'status'  => self::RESULT_SKIPPED,
				'message' => __( 'Příspěvek na Facebooku už odeslaný je, znovu se neodesílá.', 'kct' ),
			);
		}

		return array(
			'status'  => self::RESULT_SKIPPED,
			'message' => $this->skip_reason( $post_id ),
		);
	}

	/**
	 * Věta popisující chybu odeslání.
	 *
	 * Kód 0 znamená, že se Facebook nepodařilo vůbec zastihnout — od chybové
	 * odpovědi API je to potřeba odlišit, protože se takové odeslání záměrně
	 * neopakuje (viz handle_failure()).
	 *
	 * @param int    $code    Kód chyby vrácený GraphClient.
	 * @param string $message Text chyby vrácený GraphClient.
	 */
	private function failure_message( int $code, string $message ): string {
		if ( 0 === $code ) {
			return sprintf(
				/* translators: %s: popis chyby spojení. */
				__( 'Odeslání selhalo, Facebook se nepodařilo zastihnout: %s', 'kct' ),
				$message
			);
		}

		return sprintf(
			/* translators: 1: číselný kód chyby, 2: text chyby vrácený Facebookem. */
			__( 'Odeslání selhalo, Facebook vrátil chybu %1$d: %2$s', 'kct' ),
			$code,
			$message
		);
	}

	/**
	 * Proč se příspěvek neodeslal.
	 *
	 * Podmínky se testují ve stejném pořadí jako v share(), aby hláška mluvila
	 * o tom, na čem odeslání skutečně skončilo.
	 *
	 * @param int $post_id ID příspěvku.
	 */
	private function skip_reason( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return __( 'Příspěvek neexistuje.', 'kct' );
		}

		if ( 'publish' !== $post->post_status ) {
			return __( 'Příspěvek není publikovaný — sdílí se jen publikovaný obsah.', 'kct' );
		}

		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return __( 'Tento typ obsahu se na Facebook nesdílí.', 'kct' );
		}

		if ( '' !== $post->post_password ) {
			return __( 'Příspěvek je chráněný heslem, proto se na Facebook nesdílí.', 'kct' );
		}

		if ( ! $this->credentials->is_configured() ) {
			return __( 'V nastavení chybí ID Facebook stránky nebo token.', 'kct' );
		}

		if ( ! $this->state->should_share( $post_id, $this->credentials->share_default_for( (string) get_post_type( $post_id ) ) ) ) {
			return __( 'Sdílení je u tohoto příspěvku vypnuté přepínačem v metaboxu Facebook.', 'kct' );
		}

		if ( $this->state->is_locked( $post_id ) ) {
			return __( 'Odeslání tohoto příspěvku právě probíhá jinde, zkuste to za chvíli.', 'kct' );
		}

		return __( 'Odeslání neproběhlo — podmínky se mezitím změnily.', 'kct' );
	}

	/**
	 * Obsluha tlačítka „Zkusit znovu“ z metaboxu v editoru.
	 *
	 * Odeslání se zkusí rovnou v tomto requestu, ne přes cron — redaktor, který
	 * na tlačítko klikl, má výsledek vidět hned po návratu do editoru.
	 */
	public function handle_retry(): void {
		if ( ! $this->is_action( 'fb_retry' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce se ověřuje hned pod tím, vůči tomuto ID.
		$post_id = isset( $_REQUEST['post'] ) ? intval( $_REQUEST['post'] ) : 0;

		if ( ! $post_id ) {
			return;
		}

		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'kct-fb-retry-' . $post_id )
		) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ), '', array( 'response' => 403 ) );
		}

		// Nonce sám o sobě nestačí: platí pro přihlášeného uživatele, ale
		// neříká nic o tom, jestli na tenhle příspěvek smí.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'K odeslání tohoto příspěvku na Facebook nemáte oprávnění.', 'kct' ), '', array( 'response' => 403 ) );
		}

		// reset_attempts() maže jen počítadlo, ne uloženou chybu — share() níž
		// může na některé z podmínek tiše skončit a v editoru má zůstat vidět,
		// proč se předtím neodeslalo.
		$this->state->reset_attempts( $post_id );

		// Čekající událost je teď k ničemu: odeslání proběhne rovnou tady.
		$this->unschedule( $post_id );

		// share() nic nevrací a na kterékoli z podmínek tiše skončí — snímek
		// stavu před pokusem je jediný způsob, jak potom poznat, co se stalo,
		// a říct to redaktorovi místo němého přesměrování zpět do editoru.
		$before = $this->snapshot( $post_id );

		$this->share( $post_id );

		set_transient(
			$this->retry_result_key(),
			$this->outcome( $post_id, $before ),
			self::RETRY_RESULT_TTL
		);

		$redirect = get_edit_post_link( $post_id, 'raw' );

		wp_safe_redirect( $redirect ? $redirect : admin_url() );
		exit;
	}

	/**
	 * Obsluha tlačítka „Ověřit připojení“ v nastavení.
	 *
	 * Výsledek se ukládá do transientu vázaného na uživatele a vypíše ho
	 * verify_notice() po přesměrování zpět na stránku nastavení.
	 */
	public function handle_verify(): void {
		if ( ! $this->is_action( 'fb_verify' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'K ověření připojení k Facebooku nemáte oprávnění.', 'kct' ), '', array( 'response' => 403 ) );
		}

		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), self::VERIFY_NONCE )
		) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ), '', array( 'response' => 403 ) );
		}

		set_transient( $this->verify_result_key(), $this->verify_result(), self::VERIFY_RESULT_TTL );

		wp_safe_redirect( Settings::get_settings_url() );
		exit;
	}

	/**
	 * Ověří připojení a složí větu, která se vypíše správci.
	 *
	 * Token se do výsledku nikdy nedostane — vypisuje se jen název stránky
	 * a hlášení, které vrátil Facebook.
	 */
	private function verify_result(): string {
		if ( ! $this->credentials->is_configured() ) {
			return __( 'Chybí ID stránky nebo token.', 'kct' );
		}

		$result = $this->client->verify( $this->credentials->token() );

		if ( empty( $result['ok'] ) ) {
			// Kód 0 znamená, že se Facebook nepodařilo vůbec zastihnout — od
			// chybové odpovědi API je to potřeba odlišit.
			if ( 0 === (int) $result['code'] ) {
				return sprintf(
					/* translators: %s: popis chyby spojení. */
					__( 'Nepodařilo se spojit s Facebookem: %s', 'kct' ),
					(string) $result['message']
				);
			}

			return sprintf(
				/* translators: 1: číselný kód chyby, 2: text chyby vrácený Facebookem. */
				__( 'Facebook vrátil chybu %1$d: %2$s', 'kct' ),
				(int) $result['code'],
				(string) $result['message']
			);
		}

		// Token prokazatelně funguje, upozornění z dřívějška už neplatí.
		delete_option( self::TOKEN_ERROR_OPTION );

		$message = sprintf(
			/* translators: %s: název připojené Facebook stránky. */
			__( 'Připojeno ke stránce „%s“.', 'kct' ),
			(string) ( $result['name'] ?? '' )
		);

		$id = (string) ( $result['id'] ?? '' );

		// /me vrací identitu, ke které token patří — u uživatelského tokenu
		// nebo tokenu jiné stránky se liší od ID stránky v nastavení.
		if ( '' !== $id && $id !== $this->credentials->page_id() ) {
			$message .= ' ' . sprintf(
				/* translators: 1: ID vrácené Facebookem, 2: ID stránky uložené v nastavení. */
				__( 'Pozor: token patří k jinému účtu, než je nastavené ID stránky (Facebook vrátil %1$s, v nastavení je %2$s).', 'kct' ),
				$id,
				$this->credentials->page_id()
			);
		}

		return $message;
	}

	/**
	 * Upozornění na neplatný token.
	 *
	 * Neplatný token zastaví sdílení všech příspěvků, ne jen toho jednoho, u
	 * kterého se to zrovna projevilo — patří proto do administrace, ne jen do
	 * metaboxu v editoru.
	 */
	public function token_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_option( self::TOKEN_ERROR_OPTION );

		if ( ! $message ) {
			return;
		}

		$text = sprintf(
			/* translators: %s: text chyby vrácený Facebookem. */
			__( 'Sdílení na Facebook nefunguje — token je neplatný nebo vypršel. Facebook hlásí: %s', 'kct' ),
			(string) $message
		);

		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( $text ),
			esc_url( Settings::get_settings_url() ),
			esc_html__( 'Otevřít nastavení', 'kct' )
		);
	}

	/**
	 * Výsledek ověření připojení po návratu z tlačítka v nastavení.
	 */
	public function verify_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_transient( $this->verify_result_key() );

		if ( ! $message ) {
			return;
		}

		delete_transient( $this->verify_result_key() );

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html( (string) $message )
		);
	}

	/**
	 * Výsledek ručního odeslání po návratu z tlačítka „Zkusit znovu“.
	 *
	 * Stejný vzor jako verify_notice(): výsledek počká v transientu vázaném na
	 * uživatele, který na tlačítko klikl, a vypíše se po přesměrování zpět do
	 * editoru.
	 */
	public function retry_notice(): void {
		$result = get_transient( $this->retry_result_key() );

		if ( ! is_array( $result ) || empty( $result['message'] ) ) {
			return;
		}

		delete_transient( $this->retry_result_key() );

		$class = match ( $result['status'] ?? '' ) {
			self::RESULT_SHARED => 'notice-success',
			self::RESULT_FAILED => 'notice-error',
			default             => 'notice-warning',
		};

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( (string) $result['message'] )
		);
	}

	/**
	 * Jde o požadavek z daného tlačítka v administraci?
	 *
	 * `kct-action` je obyčejný parametr URL, který může poslat kdokoli — sám
	 * o sobě tedy k ničemu neopravňuje. Slouží jen k rozpoznání, o kterou akci
	 * jde; nonce i oprávnění se kontrolují až v obsluze.
	 *
	 * @param string $action Název očekávané akce.
	 */
	private function is_action( string $action ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání akce, nonce se ověřuje v obsluze.
		if ( ! isset( $_REQUEST['kct-action'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viz výš.
		return $action === sanitize_key( wp_unslash( $_REQUEST['kct-action'] ) );
	}

	/**
	 * Klíč transientu s výsledkem ověření.
	 *
	 * Vázaný na uživatele — výsledek patří tomu, kdo na tlačítko klikl, a nemá
	 * vyskočit jinému správci, který má zrovna otevřenou administraci.
	 */
	private function verify_result_key(): string {
		return self::VERIFY_RESULT_PREFIX . get_current_user_id();
	}

	/**
	 * Klíč transientu s výsledkem ručního odeslání.
	 *
	 * Vázaný na uživatele ze stejného důvodu jako verify_result_key(): výsledek
	 * patří tomu, kdo na tlačítko klikl.
	 */
	private function retry_result_key(): string {
		return self::RETRY_RESULT_PREFIX . get_current_user_id();
	}

	/**
	 * Metabox s poli pro redaktora.
	 *
	 * Přepínač musí mít `default` — knihovna wpify/custom-fields registruje
	 * meta i s výchozí hodnotou a zapisuje ji při každém uložení příspěvku.
	 * Bez `default` je registrovaný výchozí stav `false`, takže by přepínač
	 * u nového příspěvku byl vypnutý bez ohledu na globální nastavení a po
	 * prvním uložení by meta existovala s hodnotou 0 — ShareState::should_share()
	 * na globální nastavení spadne jen tehdy, když meta vůbec neexistuje.
	 * Sdílení by pak nikdy nic neodeslalo.
	 */
	private function register_metabox(): void {
		// Dva metaboxy místo jednoho: liší se výchozí hodnota přepínače
		// (jiné nastavení pro aktuality a pro akce) i skladba polí (počet dní
		// jen u akcí), a to jedním voláním create_metabox() nejde.
		foreach ( $this->post_types() as $post_type ) {
			$this->wcf->create_metabox( array(
				'id'         => 'kct_facebook_' . $post_type,
				'title'      => __( 'Facebook', 'kct' ),
				'post_types' => array( $post_type ),
				'context'    => 'side',
				'priority'   => 'default',
				'items'      => $this->metabox_items( $post_type ),
			) );
		}
	}

	/**
	 * Pole metaboxu pro daný typ obsahu.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function metabox_items( string $post_type ): array {
		$items = array(
			array(
				'type'    => 'toggle',
				'id'      => ShareState::META_SHARE,
				'label'   => __( 'Sdílet na Facebook', 'kct' ),
				'default' => $this->credentials->share_default_for( $post_type ),
			),
			array(
				'type'  => 'textarea',
				'id'    => ShareState::META_MESSAGE,
				'label' => __( 'Text příspěvku', 'kct' ),
				'desc'  => __( 'Necháte-li prázdné, použije se automaticky složený text.', 'kct' ),
			),
		);

		if ( EventPostType::KEY === $post_type ) {
			$items[] = array(
				'type'  => 'number',
				'id'    => ShareState::META_LEAD_DAYS,
				'label' => __( 'Kolik dní předem', 'kct' ),
				'desc'  => sprintf(
					/* translators: %d: výchozí počet dní z nastavení webu. */
					__( 'Necháte-li prázdné, použije se nastavení webu (%d dní).', 'kct' ),
					$this->schedule->lead_days()
				),
				'min'   => 0,
				'max'   => 365,
			);
		}

		return $items;
	}

	/**
	 * Meta se neposílají do REST API a needituje je kdokoli.
	 */
	public function register_meta(): void {
		foreach ( $this->post_types() as $post_type ) {
			foreach ( $this->state_meta_keys() as $key => $type ) {
				register_post_meta( $post_type, $key, array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					// Právo se ptá na konkrétní příspěvek, ne na obecné
					// edit_posts — to má i redaktor, který na tenhle
					// příspěvek nesmí.
					'auth_callback' => function ( $allowed, $meta_key, $object_id ) {
						return current_user_can( 'edit_post', $object_id );
					},
				) );
			}
		}
	}

	/**
	 * Označí stavové meta za chráněné.
	 *
	 * Typ „akce" má v supports 'custom-fields', takže bez tohoto filtru by
	 * klíče sdílení byly vidět v boxu Vlastní pole a redaktor by mohl
	 * kct_fb_post_id přepsat nebo smazat — a tím rozbít ochranu proti
	 * opakovanému odeslání. Týká se to i polí, která redaktor vyplňuje
	 * v metaboxu Facebook (přepínač a vlastní text): patří do metaboxu, ne do
	 * syrového seznamu vlastních polí, kde by šly nastavit podruhé a jinak.
	 * Filtr `is_protected_meta` mění jen výpis vlastních polí a REST — zápisy
	 * knihovny wpify/custom-fields jdou přes update_post_meta(), takže se
	 * metaboxu nedotkne.
	 *
	 * @param bool   $protected Je meta chráněná podle dosavadního vyhodnocení?
	 * @param string $meta_key  Klíč meta.
	 *
	 * @return bool
	 */
	public function protect_meta( $protected, $meta_key ) {
		if ( in_array( $meta_key, $this->protected_meta_keys(), true ) ) {
			return true;
		}

		return $protected;
	}

	/**
	 * Všechny meta klíče sdílení, které nemají být vidět mezi vlastními poli.
	 *
	 * Kromě stavových klíčů i pole redaktora z metaboxu — ta se ale
	 * neregistrují přes register_post_meta(), o to se stará knihovna
	 * wpify/custom-fields, proto nejsou ve state_meta_keys().
	 *
	 * @return string[]
	 */
	private function protected_meta_keys(): array {
		return array_merge(
			array_keys( $this->state_meta_keys() ),
			array( ShareState::META_SHARE, ShareState::META_MESSAGE )
		);
	}

	/**
	 * Stavové meta klíče a jejich datové typy.
	 *
	 * @return array<string, string>
	 */
	private function state_meta_keys(): array {
		return array(
			ShareState::META_POST_ID  => 'string',
			ShareState::META_TIME     => 'integer',
			ShareState::META_ERROR    => 'array',
			ShareState::META_ATTEMPTS => 'integer',
			ShareState::META_LEAD_DAYS => 'integer',
		);
	}

	/**
	 * Adresa sdílecího obrázku 4:5, nebo null.
	 *
	 * Null znamená „pošli to odkazem jako dřív" — sdílení se kvůli obrázku
	 * nesmí neuskutečnit.
	 */
	private function social_image( WP_Post $post ): ?string {
		$result = PostPostType::KEY === $post->post_type
			? $this->og_images->social_for_post( (int) $post->ID )
			: $this->og_images->social_for_event_post( (int) $post->ID );

		return $result['url'] ?? null;
	}

	/**
	 * Přeplánuje odeslání akce, které se posunul termín.
	 *
	 * Neplatí pro akci, která už odeslaná je — poslat pozvánku podruhé kvůli
	 * opravě překlepu v místě startu by bylo horší než tu opravu neudělat.
	 *
	 * @param int     $post_id ID příspěvku.
	 * @param WP_Post $post    Příspěvek.
	 */
	public function reschedule( $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->credentials->is_configured() || $this->state->is_shared( (int) $post_id ) ) {
			return;
		}

		wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $post_id ) );
		wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK, array( (int) $post_id ) );
	}

	/**
	 * Přepsání počtu dní u konkrétní akce, nebo null.
	 *
	 * Prázdné pole znamená „použij nastavení webu", ne „nula dní" — proto se
	 * prázdná hodnota rozlišuje od vyplněné nuly.
	 */
	private function lead_override( int $post_id ): ?int {
		$value = get_post_meta( $post_id, ShareState::META_LEAD_DAYS, true );

		return is_numeric( $value ) ? (int) $value : null;
	}

	/**
	 * Řádek do metaboxu: kdy se akce odešle.
	 *
	 * Prázdný řetězec u aktuality a u akce, která je už odeslaná — tam by to
	 * bylo jen šum.
	 */
	public function schedule_note( WP_Post $post ): string {
		if ( EventPostType::KEY !== $post->post_type || $this->state->is_shared( (int) $post->ID ) ) {
			return '';
		}

		$target = $this->schedule->target_for_event(
			$this->events->get_event( $post->ID, '' ),
			$this->lead_override( (int) $post->ID )
		);

		if ( null === $target ) {
			return __( 'Akce už proběhla, na Facebook se neodešle.', 'kct' );
		}

		if ( $target <= time() + self::SCHEDULE_TOLERANCE ) {
			return __( 'Odešle se během několika minut.', 'kct' );
		}

		/* translators: %s: datum a čas odeslání. */
		return sprintf( __( 'Odešle se %s.', 'kct' ), wp_date( 'j. n. Y \v H:i', $target ) );
	}
}
