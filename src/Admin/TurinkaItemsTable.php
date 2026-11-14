<?php

namespace Kct\Admin;

use Kct\Turinka\Field;
use WP_List_Table;

/**
 * Tabulka tras nebo míst organizace tak, jak je vede Turinka.
 *
 * Proti přehledu akcí je to jednodušší pohled: trasy ani místa web nedrží
 * jako příspěvky, takže není co párovat — řádek je položka z Turinky a nic
 * jiného. Sloupce se liší jen tím, co dává smysl u trasy (délka, převýšení,
 * obtížnost) a co u místa (kategorie, obec).
 *
 * Jedna třída pro oba typy schválně: kostra odpovědi API je u nich shodná
 * a rozdíl jsou dva sloupce. Dvě skoro stejné tabulky by se rozešly při první
 * opravě, která by se udělala jen v jedné.
 */
class TurinkaItemsTable extends WP_List_Table {

	const PER_PAGE = 50;

	/** @var array<int,array> Položky z výpisu. */
	private array $items_all;

	/**
	 * @param string $type  `routes` | `places`.
	 * @param array  $items Položky z MineItems::all().
	 */
	public function __construct( private string $type, array $items ) {
		$this->items_all = $items;

		parent::__construct( array(
			'singular' => 'polozka',
			'plural'   => 'polozky',
			'ajax'     => false,
		) );
	}

	private function is_route(): bool {
		return 'routes' === $this->type;
	}

	public function get_columns(): array {
		$columns = array(
			'title' => __( 'Název', 'kct' ),
			'state' => __( 'Stav', 'kct' ),
		);

		$columns += $this->is_route()
			? array(
				'length'     => __( 'Délka', 'kct' ),
				'elevation'  => __( 'Převýšení', 'kct' ),
				'difficulty' => __( 'Obtížnost', 'kct' ),
			)
			: array(
				'category' => __( 'Kategorie', 'kct' ),
				'city'     => __( 'Obec', 'kct' ),
			);

		return $columns + array(
			'article' => __( 'Článek na webu', 'kct' ),
			'updated' => __( 'Změněno', 'kct' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'title'   => array( 'title', false ),
			'updated' => array( 'updated', true ),
		);
	}

	public function prepare_items(): void {
		$rows = $this->items_all;

		$search = isset( $_REQUEST['s'] ) ? trim( (string) wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( '' !== $search ) {
			$rows = array_filter( $rows, static function ( $item ) use ( $search ) {
				return false !== mb_stripos( (string) ( $item['title'] ?? '' ), $search );
			} );
		}

		$access = isset( $_REQUEST['pristup'] ) ? sanitize_key( wp_unslash( $_REQUEST['pristup'] ) ) : '';
		if ( '' !== $access ) {
			$rows = array_filter( $rows, static function ( $item ) use ( $access ) {
				switch ( $access ) {
					case 'ceka':
						return 'pending' === Field::code( $item['status'] ?? null );
					case 's-clankem':
						return ! empty( $item['source']['source_url'] );
					case 'bez-clanku':
						return empty( $item['source']['source_url'] );
				}

				return $access === ( $item['access'] ?? '' );
			} );
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'updated';
		$desc    = 'asc' !== ( isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc' );

		usort( $rows, static function ( $a, $b ) use ( $orderby, $desc ) {
			$result = 'title' === $orderby
				? strnatcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) )
				: strcmp( (string) ( $a['updated_at'] ?? '' ), (string) ( $b['updated_at'] ?? '' ) );

			return $desc ? -$result : $result;
		} );

		$total = count( $rows );
		$page  = max( 1, (int) $this->get_pagenum() );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => self::PER_PAGE,
			'total_pages' => (int) ceil( $total / self::PER_PAGE ),
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
		$this->items           = array_slice( array_values( $rows ), ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current = isset( $_REQUEST['pristup'] ) ? sanitize_key( wp_unslash( $_REQUEST['pristup'] ) ) : '';
		$choices = array(
			''           => __( 'Vše', 'kct' ),
			'own'        => __( 'Vlastní', 'kct' ),
			'managed'    => __( 'Spravované za odbor', 'kct' ),
			'linked'     => __( 'Spárované', 'kct' ),
			'ceka'       => __( 'Čeká na schválení', 'kct' ),
			's-clankem'  => __( 'S článkem na webu', 'kct' ),
			'bez-clanku' => __( 'Bez článku na webu', 'kct' ),
		);
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="kct-pristup"><?php esc_html_e( 'Filtr', 'kct' ); ?></label>
			<select name="pristup" id="kct-pristup">
				<?php foreach ( $choices as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filtrovat', 'kct' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function no_items(): void {
		echo esc_html(
			$this->is_route()
				? __( 'Turinka nemá žádnou trasu vaší organizace.', 'kct' )
				: __( 'Turinka nemá žádné místo vaší organizace.', 'kct' )
		);
	}

	public function column_title( $item ): string {
		$title = (string) ( $item['title'] ?? __( '(bez názvu)', 'kct' ) );
		$url   = (string) ( $item['url'] ?? '' );

		$name = '' === $url
			? sprintf( '<strong>%s</strong>', esc_html( $title ) )
			: sprintf( '<strong><a href="%s" target="_blank" rel="noopener">%s</a></strong>', esc_url( $url ), esc_html( $title ) );

		$short = trim( (string) ( $item['short_description'] ?? '' ) );

		if ( '' !== $short ) {
			$name .= sprintf( '<br><span class="description">%s</span>', esc_html( $short ) );
		}

		$access = $this->access( $item );

		if ( '' !== $access ) {
			$name .= sprintf( '<br><span class="description">%s</span>', esc_html( $access ) );
		}

		$actions = array();

		// Upravit jde jen to, co web v Turince založil — formulář se vydává
		// na dvojici (web, source_id) a k cizí položce ho aplikace nedá.
		// U ostatních vede do aplikace název položky.
		$source_id = (string) ( $item['source']['source_id'] ?? '' );

		if ( '' !== $source_id ) {
			$actions['edit'] = sprintf(
				'<button type="button" class="button-link" data-kct-turinka-form="edit" data-source="%s">%s</button>',
				esc_attr( $source_id ),
				esc_html__( 'Upravit v Turince', 'kct' )
			);
		}

		if ( '' !== $url ) {
			$actions['view'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $url ),
				esc_html__( 'Otevřít v Turince', 'kct' )
			);
		}

		// Odebrat jde taky jen to, co web dodal — u cizí položky by aplikace
		// neměla co odpojit.
		if ( '' !== $source_id ) {
			// Klíč akce se stane třídou obalujícího elementu, a podle ní se
			// tlačítko obarví. Jádro červenou barvu dává jen odkazům
			// (`.row-actions .delete a`), tohle je tlačítko.
			$actions['kct-remove'] = sprintf(
				'<button type="button" class="button-link delete" data-kct-turinka-remove="1" data-source="%s" data-title="%s">%s</button>',
				esc_attr( $source_id ),
				esc_attr( $title ),
				esc_html__( 'Odebrat z Turinky', 'kct' )
			);
		}

		return $name . $this->row_actions( $actions );
	}

	/**
	 * Stav položky: viditelnost a moderace dohromady.
	 *
	 * Místo vzniká ke schválení, takže `pending` je u nich běžný stav, ne
	 * chyba — proto se vypisuje slovy, ne výstrahou.
	 */
	public function column_state( $item ): string {
		$visibility = Field::code( $item['visibility'] ?? null );

		// Popisky skládá Turinka, takže se tu neopisují — „Schváleno“ u místa
		// a „Publikováno“ u akce znamená v aplikaci totéž co v tabulce. Vlastní
		// slova zůstala jen tam, kde odpověď popisek nenese.
		$parts = array_filter( array(
			Field::label( $item['status'] ?? null ),
			'unlisted' === $visibility ? Field::label( $item['visibility'] ?? null ) : '',
		) );

		if ( ! $parts ) {
			$parts[] = 'unlisted' === $visibility
				? __( 'Jen přes odkaz', 'kct' )
				: __( 'Zveřejněná', 'kct' );
		}

		return esc_html( implode( ', ', $parts ) );
	}

	public function column_length( $item ): string {
		$km = (float) ( $item['length_km'] ?? 0 );

		return $km > 0
			/* translators: %s: délka trasy v kilometrech. */
			? esc_html( sprintf( __( '%s km', 'kct' ), number_format_i18n( $km, 1 ) ) )
			: '—';
	}

	public function column_elevation( $item ): string {
		$gain = (int) ( $item['elevation_gain_m'] ?? 0 );

		return $gain > 0
			/* translators: %s: převýšení v metrech. */
			? esc_html( sprintf( __( '%s m', 'kct' ), number_format_i18n( $gain ) ) )
			: '—';
	}

	public function column_difficulty( $item ): string {
		return $this->label( $item['difficulty'] ?? null );
	}

	public function column_category( $item ): string {
		return $this->label( $item['category'] ?? null );
	}

	public function column_city( $item ): string {
		// Okres přichází i se slovem „okres“ v hodnotě, takže se nepředsazuje
		// vlastní zkratka — vyšlo by „okr. okres Chomutov“.
		$parts = array_filter( array(
			trim( (string) ( $item['city'] ?? '' ) ),
			trim( (string) ( $item['district'] ?? '' ) ),
		) );

		return $parts ? esc_html( implode( ', ', $parts ) ) : '—';
	}

	/**
	 * Odkaz na článek, který web k položce dodal.
	 *
	 * Prázdno není chyba: článek k trase nebo místu mít nemusíme, a dokud ho
	 * nemáme, nemá Turinka kam odkazovat.
	 */
	public function column_article( $item ): string {
		$url = (string) ( $item['source']['source_url'] ?? '' );

		if ( '' === $url ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'bez článku', 'kct' ) . '</span>';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $url ),
			esc_html__( 'Zobrazit článek', 'kct' )
		);
	}

	public function column_updated( $item ): string {
		$stamp = strtotime( (string) ( $item['updated_at'] ?? '' ) );

		return $stamp ? esc_html( (string) wp_date( 'j. n. Y H:i', $stamp ) ) : '—';
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

	/**
	 * Popisek číselníkové hodnoty.
	 *
	 * Výpis nese popisek rovnou v odpovědi (`{value,label}`), takže se kvůli
	 * němu nikam nechodí — a protože ho skládá aplikace, nemůže se rozejít
	 * s tím, co je vidět v Turince.
	 *
	 * @param mixed $value
	 */
	private function label( $value ): string {
		$text = Field::label( $value );

		return '' !== $text ? esc_html( $text ) : '—';
	}

	/** Co s položkou web smí — pole `access` z API. */
	private function access( array $item ): string {
		switch ( (string) ( $item['access'] ?? '' ) ) {
			case 'own':
				return __( 'vlastní', 'kct' );
			case 'managed':
				return __( 'spravujete za odbor', 'kct' );
			case 'linked':
				return __( 'spárovaná, doplňujete prázdná pole', 'kct' );
		}

		return '';
	}
}
