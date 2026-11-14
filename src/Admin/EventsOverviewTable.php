<?php

namespace Kct\Admin;

use Kct\Turinka\EventState;
use Kct\Turinka\Field;
use WP_List_Table;

/**
 * Tabulka přehledu akcí — jeden řádek na akci, tři sloupce zdrojů.
 *
 * Táž akce bývá ve všech třech zdrojích naráz: jako příspěvek na webu, jako
 * záznam v databázi KČT a jako položka v Turince. Tři oddělené tabulky by z ní
 * udělaly tři řádky a provázanost by nebyla vidět nikde — a právě ta je to, co
 * se dohledává nejhůř. Řádek je proto jedna akce a sloupce říkají, kde všude
 * je a kde chybí.
 *
 * Data už spojená dává Events::get_events() (příspěvek + databáze KČT); stav
 * Turinky je v metadatech příspěvku. Tabulka sama nic nestahuje ani neukládá.
 */
class EventsOverviewTable extends WP_List_Table {

	/** Kolik akcí se vejde na stránku. */
	const PER_PAGE = 50;

	/** @var array<int,array> Akce ze spojeného výpisu, doplněné o Turinku. */
	private array $events;

	/**
	 * Odpověděla Turinka?
	 *
	 * Bez téhle informace by sloupec u každého řádku tvrdil „Turinka ji nezná“,
	 * i když se jí web nemohl zeptat — a to není totéž. Nevím, že ji nezná;
	 * nevím nic.
	 */
	private bool $answered = false;

	/**
	 * @param array $events    Spojený výpis z Events::get_events().
	 * @param array $turinka   Indexy z MineItems::all(); prázdné, když web propojený není.
	 * @param bool  $connected Je web propojený s Turinkou? Rozhoduje o tom, jestli
	 *                         sloupec a filtry Turinky vůbec existují.
	 */
	public function __construct(
		private EventState $state,
		array $events,
		private array $turinka = array(),
		private bool $connected = false
	) {
		$this->answered = ! empty( $this->turinka['ok'] );
		$this->events   = $this->merge( $events );

		parent::__construct( array(
			'singular' => 'akce',
			'plural'   => 'akce',
			'ajax'     => false,
		) );
	}

	/**
	 * Doplní ke každé akci položku z Turinky a přidá řádky za akce, které jsou
	 * jen tam.
	 *
	 * Páruje se dvěma klíči: `source_id` je id příspěvku (akci dodal tenhle
	 * web) a `kct_id` je číslo akce v databázi KČT (tutéž akci zná Turinka
	 * z feedu). Akce bez obojího je v Turince samostatně — třeba ji odbor
	 * založil rovnou v aplikaci.
	 */
	private function merge( array $events ): array {
		$by_source = (array) ( $this->turinka['by_source_id'] ?? array() );
		$by_kct    = (array) ( $this->turinka['by_kct_id'] ?? array() );
		$used      = array();

		foreach ( $events as $index => $event ) {
			$post_id = (string) ( $event['post_id'] ?? '' );
			$db_id   = (string) ( $event['db_id'] ?? '' );

			$item = ( '' !== $post_id && isset( $by_source[ $post_id ] ) )
				? $by_source[ $post_id ]
				: ( ( '' !== $db_id && isset( $by_kct[ $db_id ] ) ) ? $by_kct[ $db_id ] : null );

			if ( $item ) {
				$events[ $index ]['turinka'] = $item;
				$used[ (string) $item['id'] ] = true;
			}
		}

		foreach ( (array) ( $this->turinka['items'] ?? array() ) as $item ) {
			if ( isset( $used[ (string) $item['id'] ] ) ) {
				continue;
			}

			// `db_id` se vyplní, i když web tu akci z feedu nemá — je to pořád
			// číslo té akce a redakci pomůže ji dohledat. Že je řádek jen
			// z Turinky, drží vlastní příznak, ne prázdné číslo.
			$events[] = array(
				'id'           => 0,
				'post_id'      => 0,
				'db_id'        => (string) ( $item['kct_id'] ?? '' ),
				'title'        => (string) ( $item['title'] ?? '' ),
				'date'         => substr( (string) ( $item['starts_at'] ?? '' ), 0, 10 ),
				'turinka'      => $item,
				'only_turinka' => true,
			);
		}

		return $this->mark_duplicates( $events );
	}

	/**
	 * Označí dvojice, které jsou nejspíš táž akce, ale web je nemá spárované.
	 *
	 * Příspěvek se s akcí z databáze KČT páruje číslem (`db_id`), a to vznikne
	 * jedině převodem akce na vlastní. Příspěvek, který redakce napsala sama
	 * a akce pak přibyla do databáze, tohle číslo nemá — a výpis akcí pak
	 * tutéž akci ukáže dvakrát, na webu i tady.
	 *
	 * Tabulka je **nespojuje**: shoda názvu a data je domněnka, ne fakt, a
	 * slepit podle ní dva záznamy by znamenalo tiše schovat chybu v datech.
	 * Místo toho obě strany pojmenuje a pošle redakci tam, kde se to spraví —
	 * do pole „ID akce z kct-db“ u příspěvku. Víc není potřeba: jakmile tam
	 * číslo je, páruje se akce všude stejně jako převedená.
	 */
	private function mark_duplicates( array $events ): array {
		$keys = array();

		foreach ( $events as $index => $event ) {
			$title = trim( (string) preg_replace( '/\s+/u', ' ', (string) ( $event['title'] ?? '' ) ) );
			$date  = (string) ( $event['date'] ?? '' );

			if ( '' === $title || '' === $date ) {
				continue;
			}

			$keys[ mb_strtolower( $title ) . '|' . $date ][] = $index;
		}

		foreach ( $keys as $indexes ) {
			if ( count( $indexes ) < 2 ) {
				continue;
			}

			$posts = array();
			$db    = array();

			foreach ( $indexes as $index ) {
				$has_post = (int) ( $events[ $index ]['post_id'] ?? 0 ) > 0;
				$has_db   = '' !== (string) ( $events[ $index ]['db_id'] ?? '' );

				if ( $has_post && ! $has_db ) {
					$posts[] = $index;
				} elseif ( $has_db && ! $has_post ) {
					$db[] = $index;
				}
			}

			if ( ! $posts || ! $db ) {
				continue;
			}

			$db_id   = (string) ( $events[ reset( $db ) ]['db_id'] ?? '' );
			$post_id = (int) ( $events[ reset( $posts ) ]['post_id'] ?? 0 );

			foreach ( $posts as $index ) {
				$events[ $index ]['duplicate']    = $db_id;
				$events[ $index ]['duplicate_of'] = 0;
			}

			foreach ( $db as $index ) {
				$events[ $index ]['duplicate']    = 'post';
				$events[ $index ]['duplicate_of'] = $post_id;
			}
		}

		return $events;
	}

	/**
	 * Sloupec Turinky má jen propojený web.
	 *
	 * Propojení je dobrovolné. Webu, který ho nemá, nemá co připomínat službu,
	 * kterou nepoužívá — ani prázdným sloupcem, ani hláškou.
	 */
	public function get_columns(): array {
		$columns = array(
			'title' => __( 'Akce', 'kct' ),
			'date'  => __( 'Termín', 'kct' ),
			'post'  => __( 'Příspěvek', 'kct' ),
			'db'    => __( 'Databáze KČT', 'kct' ),
		);

		if ( $this->connected ) {
			$columns['turinka'] = __( 'Turinka', 'kct' );
		}

		return $columns;
	}

	protected function get_sortable_columns(): array {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
		);
	}

	/**
	 * Výběr, stránkování a řazení. Všechno nad polem v paměti — spojený výpis
	 * akcí se stejně skládá celý, takže dotazovat zdroje znovu by nic neušetřilo.
	 */
	public function prepare_items(): void {
		$rows = $this->events;

		$search = isset( $_REQUEST['s'] ) ? trim( (string) wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( '' !== $search ) {
			$rows = array_filter( $rows, static function ( $e ) use ( $search ) {
				return false !== mb_stripos( (string) ( $e['title'] ?? '' ), $search );
			} );
		}

		$source = isset( $_REQUEST['zdroj'] ) ? sanitize_key( wp_unslash( $_REQUEST['zdroj'] ) ) : '';
		if ( '' !== $source ) {
			$rows = array_filter( $rows, function ( $e ) use ( $source ) {
				$post_id = (int) ( $e['post_id'] ?? 0 );

				switch ( $source ) {
					case 'prispevek':
						return $post_id > 0;
					case 'jen-databaze':
						return 0 === $post_id && ! empty( $e['db_id'] );
					case 'v-turince':
						return ! empty( $e['turinka'] );
					case 'jen-turinka':
						return ! empty( $e['only_turinka'] );
					case 'vlastni':
						return 'own' === ( $e['turinka']['access'] ?? '' );
					case 'spravovane':
						return 'managed' === ( $e['turinka']['access'] ?? '' );
					case 'mimo-turinku':
						return empty( $e['turinka'] );
					case 'duplicity':
						return ! empty( $e['duplicate'] );
					case 'chyba':
						return $post_id > 0 && '' !== $this->state->error( $post_id );
				}

				return true;
			} );
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'date';
		$desc    = 'asc' !== ( isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc' );

		usort( $rows, static function ( $a, $b ) use ( $orderby, $desc ) {
			$result = 'title' === $orderby
				? strnatcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) )
				: strcmp( (string) ( $a['date'] ?? '' ), (string) ( $b['date'] ?? '' ) );

			return $desc ? -$result : $result;
		} );

		$total   = count( $rows );
		$page    = max( 1, (int) $this->get_pagenum() );
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => self::PER_PAGE,
			'total_pages' => (int) ceil( $total / self::PER_PAGE ),
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
		$this->items           = array_slice( array_values( $rows ), ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );
	}

	/** Filtr zdrojů nad tabulkou. */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current = isset( $_REQUEST['zdroj'] ) ? sanitize_key( wp_unslash( $_REQUEST['zdroj'] ) ) : '';
		$choices = array(
			''             => __( 'Všechny akce', 'kct' ),
			'prispevek'    => __( 'S vlastním příspěvkem', 'kct' ),
			'jen-databaze' => __( 'Jen v databázi KČT', 'kct' ),
			'duplicity'    => __( 'Nespárované dvojice', 'kct' ),
		);

		if ( $this->connected ) {
			$choices += array(
				'v-turince'    => __( 'Zná je Turinka', 'kct' ),
				'jen-turinka'  => __( 'Jen v Turince', 'kct' ),
				'vlastni'      => __( 'Vlastní v Turince', 'kct' ),
				'spravovane'   => __( 'Spravované za odbor', 'kct' ),
				'mimo-turinku' => __( 'Turinka je nezná', 'kct' ),
				'chyba'        => __( 'S chybou odeslání', 'kct' ),
			);
		}
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="kct-zdroj"><?php esc_html_e( 'Filtr zdroje', 'kct' ); ?></label>
			<select name="zdroj" id="kct-zdroj">
				<?php foreach ( $choices as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filtrovat', 'kct' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function no_items(): void {
		esc_html_e( 'Žádné akce — web zatím nemá vlastní akce a z databáze KČT se nic nenaimportovalo.', 'kct' );
	}

	/**
	 * Název akce s odkazy na místa, kde se s ní dá něco udělat.
	 */
	public function column_title( $item ): string {
		$post_id = (int) ( $item['post_id'] ?? 0 );
		$db_id   = (string) ( $item['db_id'] ?? '' );
		$title   = (string) ( $item['title'] ?? __( '(bez názvu)', 'kct' ) );

		$name = $post_id
			? sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( (string) get_edit_post_link( $post_id ) ), esc_html( $title ) )
			: sprintf( '<strong>%s</strong>', esc_html( $title ) );

		$actions = array();

		if ( ! empty( $item['permalink'] ) ) {
			$actions['view'] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( (string) $item['permalink'] ), esc_html__( 'Zobrazit na webu', 'kct' ) );
		}

		// Akce z databáze bez příspěvku — převod je jediná cesta, jak k ní web
		// může dopsat vlastní obsah. Stejný odkaz je na stránce akce.
		if ( ! $post_id && '' !== $db_id ) {
			$actions['convert'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( array(
					'kct-action' => 'convert-action',
					'db_id'      => $db_id,
					'_wpnonce'   => wp_create_nonce( 'kct-convert-action' ),
				), admin_url( 'admin-post.php' ) ) ),
				esc_html__( 'Převést na vlastní akci', 'kct' )
			);
		}

		// Trasy jsou u akce, kterou web v Turince spravuje — u cizí akce vazby
		// spravuje její pořadatel a nabízet je tu by znamenalo slibovat něco,
		// co endpoint stejně odmítne.
		if ( $this->routes_id( $item ) ) {
			$actions['routes'] = sprintf(
				'<a href="#" data-kct-event-routes="toggle" data-row="%s" aria-expanded="false">%s</a>',
				esc_attr( $this->routes_row_id( $item ) ),
				esc_html__( 'Trasy v Turince', 'kct' )
			);
		}

		$duplicate = (string) ( $item['duplicate'] ?? '' );

		if ( '' !== $duplicate ) {
			if ( 'post' === $duplicate ) {
				// Druhá strana dvojice: odkaz vede rovnou do příspěvku, kde se
				// číslo akce vyplňuje.
				$other = (int) ( $item['duplicate_of'] ?? 0 );
				$note  = esc_html__( 'Tutéž akci má web i jako příspěvek bez čísla akce — nejsou spárované, výpis je ukáže dvakrát.', 'kct' );

				if ( $other ) {
					$note .= ' ' . sprintf(
						'<a href="%s">%s</a>',
						esc_url( (string) get_edit_post_link( $other ) ),
						esc_html__( 'Doplnit číslo u příspěvku', 'kct' )
					);
				}
			} else {
				$note = esc_html( sprintf(
					/* translators: %s: číslo akce v centrální databázi KČT. */
					__( 'Stejný název i datum má akce č. %s z databáze KČT. Spárujete je tak, že tohle číslo vyplníte u příspěvku do pole „ID akce z kct-db“; do té doby je výpis ukáže dvakrát.', 'kct' ),
					$duplicate
				) );
			}

			$name .= sprintf( '<br><span class="description">%s</span>', $note );
		}

		return $name . $this->row_actions( $actions );
	}

	/**
	 * Řádek akce a pod ním schovaný panel tras.
	 *
	 * Panel je prázdný a obsah si vyžádá skript až při otevření: stránka má
	 * padesát řádků a padesát dotazů do Turinky při každém načtení by z
	 * přehledu udělalo nejpomalejší obrazovku administrace.
	 *
	 * @param array $item
	 */
	public function single_row( $item ): void {
		parent::single_row( $item );

		$turinka_id = $this->routes_id( $item );

		if ( ! $turinka_id ) {
			return;
		}

		printf(
			'<tr class="kct-routes-row" id="%s" hidden><td colspan="%d">'
			. '<div data-kct-event-routes="panel" data-post="%d" data-turinka="%d"><p class="description">%s</p></div>'
			. '</td></tr>',
			esc_attr( $this->routes_row_id( $item ) ),
			count( $this->get_columns() ),
			(int) ( $item['post_id'] ?? 0 ),
			$turinka_id,
			esc_html__( 'Načítám trasy…', 'kct' )
		);
	}

	/**
	 * Id akce v Turince, pokud se u ní dají spravovat vazby na trasy.
	 *
	 * Vrací nulu, když web propojený není, Turinka akci nezná, nebo akce patří
	 * někomu jinému — v těch případech se o trasách nikde nepíše.
	 */
	private function routes_id( array $item ): int {
		if ( ! $this->connected || empty( $item['turinka'] ) ) {
			return 0;
		}

		if ( ! in_array( (string) ( $item['turinka']['access'] ?? '' ), array( 'own', 'managed' ), true ) ) {
			return 0;
		}

		return (int) ( $item['turinka']['id'] ?? 0 );
	}

	private function routes_row_id( array $item ): string {
		return 'kct-routes-' . $this->routes_id( $item );
	}

	public function column_date( $item ): string {
		$date = (string) ( $item['date'] ?? '' );

		if ( '' === $date ) {
			return '—';
		}

		return esc_html( wp_date( 'j. n. Y', (int) strtotime( $date ) ) ?: $date );
	}

	public function column_post( $item ): string {
		$post_id = (int) ( $item['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'bez příspěvku', 'kct' ) . '</span>';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( (string) get_edit_post_link( $post_id ) ),
			esc_html( get_post_status( $post_id ) === 'publish' ? __( 'Publikovaný', 'kct' ) : __( 'Koncept', 'kct' ) )
		);
	}

	public function column_db( $item ): string {
		$db_id = (string) ( $item['db_id'] ?? '' );

		// Akce, kterou web z feedu nemá, ale Turinka u ní číslo zná — číslo
		// se vypíše, ať se dá dohledat, a popiskem se řekne, že na webu není.
		if ( '' !== $db_id && ! empty( $item['only_turinka'] ) ) {
			return sprintf(
				'%s<br><span class="description">%s</span>',
				/* translators: %s: číslo akce v centrální databázi KČT. */
				esc_html( sprintf( __( 'č. %s', 'kct' ), $db_id ) ),
				esc_html__( 'web ji z databáze nemá', 'kct' )
			);
		}

		if ( '' === $db_id ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'není v databázi KČT', 'kct' ) . '</span>';
		}

		/* translators: %s: číslo akce v centrální databázi KČT. */
		return esc_html( sprintf( __( 'č. %s', 'kct' ), $db_id ) );
	}

	/**
	 * Co o akci ví Turinka.
	 *
	 * Dva různé údaje v jednom sloupci, a je to schválně: co Turinka o akci má
	 * (zná ji / nezná) a co s ní web udělal (odeslal obsah, nebo se mu to
	 * nepovedlo). Oddělit je do dvou sloupců by přehled rozšířilo o sloupec,
	 * který je u většiny řádků prázdný.
	 */
	public function column_turinka( $item ): string {
		$post_id = (int) ( $item['post_id'] ?? 0 );
		$turinka = $item['turinka'] ?? null;
		$lines   = array();

		if ( $turinka ) {
			$label = $this->turinka_state( $turinka );
			$url   = (string) ( $turinka['url'] ?? '' );

			$lines[] = '' === $url
				? esc_html( $label )
				: sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $label ) );

			$access = $this->turinka_access( $turinka, $post_id > 0 );

			if ( '' !== $access ) {
				$lines[] = sprintf( '<span class="description">%s</span>', esc_html( $access ) );
			}
		} elseif ( ! $this->answered ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'bez odpovědi Turinky', 'kct' ) . '</span>';
		} elseif ( ! $post_id ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Turinka ji nezná', 'kct' ) . '</span>';
		} else {
			$lines[] = esc_html__( 'Turinka ji nezná', 'kct' );
		}

		if ( $post_id ) {
			$error = $this->state->error( $post_id );
			$sent  = $this->state->sent_at( $post_id );

			if ( '' !== $error ) {
				$lines[] = sprintf(
					'<span class="description" style="color:#b32d2e">%s</span>',
					esc_html( sprintf( /* translators: %s: popis chyby. */ __( 'Chyba odeslání: %s', 'kct' ), $error ) )
				);
			} elseif ( $sent ) {
				$lines[] = sprintf(
					'<span class="description">%s</span>',
					esc_html( sprintf(
						/* translators: %s: datum a čas posledního odeslání. */
						__( 'Obsah odeslán %s', 'kct' ),
						wp_date( 'j. n. Y H:i', $sent )
					) )
				);
			}
		}

		return implode( '<br>', $lines );
	}

	/**
	 * Krátký stav položky v Turince — viditelnost a moderace dohromady, ať se
	 * dá sloupec přečíst jedním pohledem.
	 */
	private function turinka_state( array $item ): string {
		if ( ! empty( $item['is_cancelled'] ) ) {
			return __( 'Zrušená', 'kct' );
		}

		$visibility = Field::code( $item['visibility'] ?? null );

		// Popisky stavu skládá Turinka — tabulka je bere, jak přišly, aby
		// akce měla v administraci tentýž stav, jaký je vidět v aplikaci.
		$parts = array_filter( array(
			Field::label( $item['status'] ?? null ),
			'unlisted' === $visibility ? Field::label( $item['visibility'] ?? null ) : '',
		) );

		if ( ! $parts ) {
			$parts[] = 'unlisted' === $visibility
				? __( 'Jen přes odkaz', 'kct' )
				: __( 'Zveřejněná', 'kct' );
		}

		return implode( ', ', $parts );
	}

	/**
	 * Co s položkou web smí a jak je na ní napojený.
	 *
	 * Rozdíl mezi „vlastní“ a „spravovaná“ je pro redakci podstatný: do vlastní
	 * akce web zasahuje vždycky, kdežto obsah spravované akce drží jen do
	 * chvíle, než ho dodá web odboru, kterému akce patří.
	 *
	 * Druhá polovina věty je o napojení a skládá se ze tří různých zdrojů, ne
	 * z jednoho:
	 *
	 * - **příspěvek na webu** se bere z webu, ne z API. `source` v odpovědi
	 *   říká jen to, jestli o propojení ví Turinka — napsat u akce
	 *   s příspěvkem „bez příspěvku na webu“ by byla lež;
	 * - **`source`** znamená, že Turinka zná id příspěvku. Vzniká zápisem
	 *   vlastní akce, ne dodáním obsahu k akci z databáze;
	 * - **`has_web_content`** je dodaný obsah. U akce z databáze je to jediné,
	 *   podle čeho se pozná, že z webu něco přišlo.
	 *
	 * @param bool $has_post Má akce na webu vlastní příspěvek?
	 */
	private function turinka_access( array $item, bool $has_post ): string {
		$access  = (string) ( $item['access'] ?? '' );
		$linked  = ! empty( $item['source']['source_id'] );
		$content = ! empty( $item['has_web_content'] );

		if ( 'linked' === $access ) {
			return __( 'spárovaná, doplňujete prázdná pole', 'kct' );
		}

		if ( 'own' === $access ) {
			if ( ! $has_post ) {
				return __( 'vlastní, bez příspěvku na webu', 'kct' );
			}

			if ( $linked ) {
				return __( 'vlastní, napojená na příspěvek', 'kct' );
			}

			return $content
				? __( 'vlastní, obsah z webu dodaný', 'kct' )
				: __( 'vlastní, příspěvek zatím neodeslaný', 'kct' );
		}

		if ( 'managed' === $access ) {
			if ( ! $has_post ) {
				return __( 'spravujete za odbor', 'kct' );
			}

			if ( $linked ) {
				return __( 'spravujete za odbor, napojená na příspěvek', 'kct' );
			}

			return $content
				? __( 'spravujete za odbor, obsah z webu dodaný', 'kct' )
				: __( 'spravujete za odbor, příspěvek zatím neodeslaný', 'kct' );
		}

		return '';
	}

	/**
	 * @param array  $item
	 * @param string $column_name
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) && is_scalar( $item[ $column_name ] )
			? esc_html( (string) $item[ $column_name ] )
			: '';
	}
}
