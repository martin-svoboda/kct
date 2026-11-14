<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package kct
 */

if ( ! function_exists( 'kct_posted_on' ) ) :
	/**
	 * Prints HTML with meta information for the current post-date/time.
	 */
	function kct_posted_on() {
		$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time>';

		$time_string = sprintf(
			$time_string,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() )
//			esc_attr( get_the_modified_date( DATE_W3C ) ),
//			esc_html( get_the_modified_date() )
		);

		echo '<span class="posted-on">' . $time_string . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	}
endif;

if ( ! function_exists( 'kct_posted_by' ) ) :
	/**
	 * Prints HTML with meta information for the current author.
	 */
	function kct_posted_by() {
		$byline = sprintf(
		/* translators: %s: post author. */
			esc_html_x( 'Napsal %s', 'post author', 'kct' ),
			'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
		);

		echo '<span class="byline"> ' . $byline . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	}
endif;

if ( ! function_exists( 'kct_entry_footer' ) ) :
	/**
	 * Prints HTML with meta information for the categories, tags and comments.
	 */
	function kct_entry_footer() {
		// Hide category and tag text for pages.
		if ( 'post' === get_post_type() ) {

			/* translators: used between list items, there is a space after the comma */
			$tags_list = get_the_tag_list( '', esc_html_x( ', ', 'list item separator', 'kct' ) );
			if ( $tags_list ) {
				/* translators: 1: list of tags. */
				printf( '<span class="tags-links">' . esc_html__( 'Štítky: %1$s', 'kct' ) . '</span>', $tags_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<span class="comments-link">';
			comments_popup_link(
				sprintf(
					wp_kses(
					/* translators: %s: post title */
						__( 'Napsat komentář<span class="screen-reader-text"> k %s</span>', 'kct' ),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					wp_kses_post( get_the_title() )
				)
			);
			echo '</span>';
		}

		edit_post_link(
			sprintf(
				wp_kses(
				/* translators: %s: Name of current post. Only visible to screen readers */
					__( 'Upravit <span class="screen-reader-text">%s</span>', 'kct' ),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				wp_kses_post( get_the_title() )
			),
			'<span class="edit-link">',
			'</span>'
		);
	}
endif;

if ( ! function_exists( 'kct_post_thumbnail' ) ) :
	/**
	 * Displays an optional post thumbnail.
	 *
	 * Wraps the post thumbnail in an anchor element on index views, or a div
	 * element when on single views.
	 */
	function kct_post_thumbnail() {
		if ( post_password_required() || is_attachment() ) {
			return;
		}


		if ( is_singular( 'post' ) && get_the_ID() === get_queried_object_id() ) :
			?>

            <div class="post-thumbnail">
				<?php the_post_thumbnail(); ?>
            </div><!-- .post-thumbnail -->

		<?php else : ?>

            <a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php
				// Náhradní logo platí pro kterýkoli typ obsahu: prázdná dlaždice
				// karty vypadá na výpisu jako chyba, a trasa i stránka ji mít můžou
				// stejně jako článek.
				if ( ! has_post_thumbnail() ) {
					?>
                    <div class="thumbnail-replacement">
                        <?php
                        // Náhradní logo pro příspěvky bez vlastního obrázku.
                        // Vejde se do stejné dlaždice jako náhled, takže stačí
                        // 'medium' — v plné velikosti se sem tahalo logo
                        // v tiskovém rozlišení.
                        $custom_logo_id = get_theme_mod( 'custom_logo' );
                        echo wp_get_attachment_image( $custom_logo_id, 'medium', false );
                        ?>
                    </div>
					<?php
				} else {
					// 'medium_large' = 768 px. Dřív tu byl název 'post-thumbnail',
					// jenže takovou velikost nikdo neregistruje (set_post_thumbnail_size()
					// se nikde nevolá) a jádro na neznámý název odpoví plnou velikostí.
					// Do dlaždice vysoké 200 px se tak posílaly soubory o šířce
					// 2560 px — u výpisu aktualit několikanásobek potřebných dat.
					// Vizuálně se nemění nic: obrázek má v CSS object-fit: cover.
					the_post_thumbnail(
						'medium_large',
						array(
							'alt' => the_title_attribute(
								array(
									'echo' => false,
								)
							),
						)
					);
				}
				?>
            </a>

		<?php
		endif; // End is_singular().
	}
endif;

if ( ! function_exists( 'kct_render_event_item' ) ) :
	/**
	 * Vykreslí jednu položku výpisu akcí (`<li>`). Jediný zdroj markupu —
	 * používá blok akcí (blocks/events.php), archiv /akce/ i AJAX endpoint.
	 *
	 * Úroveň nadpisu se předává zvenčí, protože karta stojí jednou pod
	 * nadpisem sekce (blok akcí, související akce — tam je h3 správně)
	 * a jednou přímo pod titulkem stránky (archiv /akce/, kde h3 za h1
	 * přeskakuje úroveň a výpis se pro odečítač obrazovky rozpadá).
	 *
	 * @param array $event Data akce (viz EventModel/DbEventModel::to_array()).
	 * @param int   $heading_level Úroveň nadpisu karty, 2 až 6.
	 */
	function kct_render_event_item( array $event, int $heading_level = 3 ): void {
		$h = 'h' . max( 2, min( 6, $heading_level ) );
		$fd = $event['formated_date'] ?? kct_format_event_date( $event['date'] ?? '', $event['finish']['date'] ?? '' );
		?>
		<li>
			<a href="<?php echo esc_url( $event['permalink'] ?? '#' ); ?>" class="event">
				<?php if ( ! empty( $event['date'] ) ) : ?>
					<div class="date event__date event-date<?php echo ! empty( $fd['is_range'] ) ? ' event-date--range' : ''; ?>">
						<span class="event-date__head"><?php echo esc_html( ! empty( $fd['is_range'] ) ? $fd['days_label'] : $fd['day_abbr'] ); ?></span>
						<span class="event-date__body">
							<span class="event-date__part">
								<span class="event-date__num"><?php echo esc_html( $fd['day'] ); ?></span>
								<span class="event-date__mon"><?php echo esc_html( $fd['month'] ); ?></span>
							</span>
							<?php if ( ! empty( $fd['is_range'] ) ) : ?>
								<span class="event-date__sep">–</span>
								<span class="event-date__part">
									<span class="event-date__num"><?php echo esc_html( $fd['end_day'] ); ?></span>
									<span class="event-date__mon"><?php echo esc_html( $fd['end_month'] ); ?></span>
								</span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $event['image']['url'] ) ) : ?>
					<?php
					// Prázdný alt schválně: náhled stojí uvnitř téhož odkazu jako
					// nadpis akce, takže název by odečítač obrazovky přečetl dvakrát
					// — jednou jako obrázek, jednou jako nadpis. Obrázek tu nenese
					// žádný údaj, který by v kartě nebyl napsaný.
					?>
					<img class="event__thumb" src="<?php echo esc_url( $event['image']['url'] ); ?>" alt="" loading="lazy">
				<?php endif; ?>
				<div class="content-box">
					<div class="content">
						<<?php echo $h; ?> class="event__title"><?php echo ! empty( $event['year'] ) ? esc_html( $event['year'] ) . '. ' : ''; ?><?php echo esc_html( $event['title'] ?? '' ); ?></<?php echo $h; ?>>
						<p class="event__meta">
							<?php
							$first_line = array();
							if ( ! empty( $event['organiser']['name'] ) ) {
								$first_line[] = $event['organiser']['name'];
							}
							$place = array();
							if ( ! empty( $event['place'] ) ) {
								$place[] = $event['place'];
							}
							if ( ! empty( $event['district'] ) ) {
								$place[] = 'okr. ' . $event['district'];
							}
							if ( ! empty( $place ) ) {
								$first_line[] = implode( ', ', $place );
							}
							echo esc_html( implode( ' – ', $first_line ) );
							?>
						</p>
						<p class="event__tags">
							<?php
							$details = array();
							if ( ! empty( $event['details'] ) ) {
								foreach ( $event['details'] as $detail ) {
									if ( empty( $detail['km'] ) ) {
										continue;
									}
									$acronym = '';
									foreach ( explode( ' ', $detail['name'] ) as $w ) {
										$acronym .= mb_substr( $w, 0, 1 );
									}
									$details[] = strtoupper( $acronym ) . ': ' . $detail['km'];
								}
							}
							echo esc_html( implode( '; ', $details ) );
							?>
						</p>
					</div>
					<div class="icons">
						<?php
						if ( ! empty( $event['details'] ) ) {
							foreach ( $event['details'] as $detail ) {
								if ( empty( $detail['icon'] ) ) {
									continue;
								}
								printf( '<img src="%s" alt="%s" width="30" height="30">', esc_url( $detail['icon'] ), esc_attr( $detail['name'] ) );
							}
						}
						if ( ! empty( $event['proposal']['url'] ) ) : ?>
							<?php
							// Odznak „akce má propozice". Není to odkaz na PDF: celá
							// karta vede na detail akce, kde propozice jsou. Proto
							// jen název pro odečítač obrazovky a kreslení skryté —
							// bez toho byl odznak pro nevidomého návštěvníka tichý
							// a vidící ho naopak měl za druhý, nefunkční odkaz.
							?>
							<div class="proposal-icon">
								<span class="screen-reader-text"><?php esc_html_e( 'Akce má propozice', 'kct' ); ?></span>
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
									<path fill="currentColor" d="M17.924 7.154h-.514l.027-1.89a.464.464 0 0 0-.12-.298L12.901.134A.393.393 0 0 0 12.618 0h-9.24a.8.8 0 0 0-.787.784v6.37h-.515c-.285 0-.56.118-.76.328A1.14 1.14 0 0 0 1 8.275v5.83c0 .618.482 1.12 1.076 1.12h.515v3.99A.8.8 0 0 0 3.38 20h13.278c.415 0 .78-.352.78-.784v-3.99h.487c.594 0 1.076-.503 1.076-1.122v-5.83c0-.296-.113-.582-.315-.792a1.054 1.054 0 0 0-.76-.328ZM3.95 1.378h6.956v4.577a.4.4 0 0 0 .11.277a.37.37 0 0 0 .267.115h4.759v.807H3.95V1.378Zm0 17.244v-3.397h12.092v3.397H3.95ZM12.291 1.52l.385.434l2.58 2.853l.143.173h-2.637c-.2 0-.325-.033-.378-.1c-.053-.065-.084-.17-.093-.313V1.52ZM3 14.232v-6h1.918c.726 0 1.2.03 1.42.09c.34.09.624.286.853.588c.228.301.343.69.343 1.168c0 .368-.066.678-.198.93c-.132.25-.3.447-.503.59a1.72 1.72 0 0 1-.62.285c-.285.057-.698.086-1.239.086h-.779v2.263H3Zm1.195-4.985v1.703h.654c.471 0 .786-.032.945-.094a.786.786 0 0 0 .508-.762a.781.781 0 0 0-.19-.54a.823.823 0 0 0-.48-.266c-.142-.027-.429-.04-.86-.04h-.577Zm4.04-1.015h2.184c.493 0 .868.038 1.127.115c.347.103.644.288.892.552c.247.265.436.589.565.972c.13.384.194.856.194 1.418c0 .494-.06.92-.182 1.277c-.148.437-.36.79-.634 1.06c-.207.205-.487.365-.84.48c-.263.084-.616.126-1.057.126H8.235v-6ZM9.43 9.247v3.974h.892c.334 0 .575-.019.723-.057c.194-.05.355-.132.482-.25c.128-.117.233-.31.313-.579c.081-.269.121-.635.121-1.099c0-.464-.04-.82-.12-1.068a1.377 1.377 0 0 0-.34-.581a1.132 1.132 0 0 0-.553-.283c-.167-.038-.494-.057-.98-.057H9.43Zm4.513 4.985v-6H18v1.015h-2.862v1.42h2.47v1.015h-2.47v2.55h-1.195Z"/>
								</svg>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</a>
		</li>
		<?php
	}
endif;

if ( ! function_exists( 'kct_events_per_page' ) ) :
	/**
	 * Kolik akcí je na jedné části výpisu.
	 *
	 * Výpis akcí nechodí přes WP_Query (sdružuje příspěvky s databází KČT), takže
	 * `posts_per_page` z nastavení WordPressu se na něj nevztahuje a stránkování
	 * si musí šablona udělat sama.
	 */
	function kct_events_per_page(): int {
		/**
		 * Umožní webu změnit délku jedné části výpisu akcí.
		 *
		 * @param int $per_page Výchozí počet akcí na stránku.
		 */
		return max( 1, (int) apply_filters( 'kct_events_per_page', 25 ) );
	}
endif;

if ( ! function_exists( 'kct_events_more' ) ) :
	/**
	 * Dotažení dalších akcí pod výpis.
	 *
	 * Výpis se nesmí pod rukama hnout: kdo dočetl k desáté akci, má tam zůstat
	 * stát. Proto tu nejsou čísla stránek — další část se přidá pod už načtené
	 * karty a nic nad nimi se nepřekreslí.
	 *
	 * Je to odkaz, ne tlačítko: bez JavaScriptu vede na další část výpisu
	 * s vlastní adresou. Se skriptem se kliknutí odchytí a karty se dotáhnou
	 * (viz assets/apps/events-archive.js), takže na něj většinou ani nedojde —
	 * skript dotahuje sám, jakmile se k němu výpis dočte.
	 *
	 * @param int   $total     Celkový počet nalezených akcí.
	 * @param int   $page      Kolikátá část je právě vypsaná, od 1.
	 * @param array $base_args Parametry filtru, které musí odkaz zachovat.
	 */
	function kct_events_more( int $total, int $page, array $base_args = array() ): string {
		$per_page = kct_events_per_page();
		$shown    = min( $total, max( 1, $page ) * $per_page );
		$rest     = $total - $shown;

		if ( $rest < 1 ) {
			return '';
		}

		$args             = $base_args;
		$args['kct_page'] = $page + 1;

		$query = http_build_query( array_filter( $args, static function ( $v ) {
			return '' !== $v && null !== $v;
		} ) );

		return sprintf(
			'<div class="events-more" data-events-more data-next="%1$d"><a class="events-more__button" href="%2$s" rel="next">%3$s</a></div>',
			$page + 1,
			esc_url( get_post_type_archive_link( 'akce' ) . ( $query ? '?' . $query : '' ) ),
			esc_html(
				sprintf(
					/* translators: %d: počet akcí, které ještě nejsou vypsané. */
					_n( 'Zobrazit další akci', 'Zobrazit další akce (zbývá %d)', $rest, 'kct' ),
					$rest
				)
			)
		);
	}
endif;

if ( ! function_exists( 'kct_events_quick_ranges' ) ) :
	/**
	 * Rychlé rozsahy nad filtrem akcí: tento víkend, tento měsíc, rok dopředu.
	 *
	 * Turista uvažuje „kam se dostanu tuhle sobotu“, ne „od data do data“ —
	 * datumová pole to umí, ale vyžadují, aby si ten rozsah člověk spočítal
	 * a naťukal. Jsou to odkazy, takže fungují i bez JavaScriptu.
	 *
	 * @param string $today    Dnešní datum (Y-m-d).
	 * @param string $date_from Zobrazený začátek rozsahu.
	 * @param string $date_to   Zobrazený konec rozsahu.
	 * @param string $type      Zvolený typ akce, zachová se.
	 */
	function kct_events_quick_ranges( string $today, string $date_from, string $date_to, string $type = '' ): array {
		$ts = strtotime( $today );

		// „Tento víkend“ nikdy nezačíná v minulosti: v sobotu a v neděli se myslí
		// ode dneška, jinak nejbližší sobota. Jinak by v neděli rozsah začínal
		// včerejškem a výpis by nabídl akci, která už proběhla.
		$dow = (int) gmdate( 'N', $ts );

		if ( 7 === $dow ) {
			$weekend_from = $today;
			$weekend_to   = $today;
		} elseif ( 6 === $dow ) {
			$weekend_from = $today;
			$weekend_to   = gmdate( 'Y-m-d', strtotime( '+1 day', $ts ) );
		} else {
			$weekend_from = gmdate( 'Y-m-d', strtotime( '+' . ( 6 - $dow ) . ' day', $ts ) );
			$weekend_to   = gmdate( 'Y-m-d', strtotime( '+' . ( 7 - $dow ) . ' day', $ts ) );
		}

		$ranges = array(
			array(
				'label' => __( 'Tento víkend', 'kct' ),
				'from'  => $weekend_from,
				'to'    => $weekend_to,
			),
			array(
				'label' => __( 'Tento měsíc', 'kct' ),
				'from'  => $today,
				'to'    => gmdate( 'Y-m-t', $ts ),
			),
			array(
				'label' => __( 'Rok dopředu', 'kct' ),
				'from'  => $today,
				'to'    => gmdate( 'Y-m-d', strtotime( '+1 year', $ts ) ),
			),
		);

		foreach ( $ranges as &$range ) {
			$args = array(
				'date_from' => $range['from'],
				'date_to'   => $range['to'],
			);

			if ( '' !== $type ) {
				$args['type'] = $type;
			}

			$range['url']     = get_post_type_archive_link( 'akce' ) . '?' . http_build_query( $args );
			$range['current'] = ( $range['from'] === $date_from && $range['to'] === $date_to );
		}

		return $ranges;
	}
endif;

if ( ! function_exists( 'kct_sort_by_title' ) ) :
	/**
	 * Seřadí pole záznamů abecedně podle `title`, česky.
	 *
	 * `sort()` řadí podle bajtů, takže by Čáslav skončila až za Zručí. Správné
	 * pořadí umí `Collator` z rozšíření intl; to ale na cizím hostingu být
	 * nemusí, a proto je pod ním záložní cesta: diakritika se pro srovnání
	 * odstraní, čímž vyjde pořadí sice ne dokonale české (Ch patří za H, což
	 * tohle nepozná), ale pořád abecední — kdežto bez ní vznikne pořadí, které
	 * vypadá jako chyba.
	 *
	 * @param array $items Záznamy s klíčem `title`.
	 */
	function kct_sort_by_title( array $items ): array {
		if ( class_exists( 'Collator' ) ) {
			$collator = new Collator( 'cs_CZ' );

			usort( $items, static function ( $a, $b ) use ( $collator ) {
				return (int) $collator->compare( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
			} );

			return $items;
		}

		usort( $items, static function ( $a, $b ) {
			$key = static function ( $item ) {
				return remove_accents( (string) ( $item['title'] ?? '' ) );
			};

			return strcasecmp( $key( $a ), $key( $b ) );
		} );

		return $items;
	}
endif;

if ( ! function_exists( 'kct_plural' ) ) :
	/**
	 * Vybere český tvar podle počtu: 1 / 2–4 / 5 a více.
	 *
	 * `_n()` z jádra umí dva tvary, protože počítá s angličtinou. Čeština má
	 * tři a bez nich vznikne „Nalezeny 5 akce"; tahle funkce je jediné místo,
	 * kde se to ve šabloně řeší.
	 *
	 * @param int    $count Počet.
	 * @param string $one   Tvar pro 1, s %d.
	 * @param string $few   Tvar pro 2 až 4, s %d.
	 * @param string $many  Tvar pro 0 a 5 a více, s %d.
	 */
	function kct_plural( int $count, string $one, string $few, string $many ): string {
		if ( 1 === $count ) {
			return sprintf( $one, $count );
		}

		if ( $count >= 2 && $count <= 4 ) {
			return sprintf( $few, $count );
		}

		return sprintf( $many, $count );
	}
endif;

if ( ! function_exists( 'kct_events_found_label' ) ) :
	/**
	 * Věta „kolik akcí výpis našel".
	 *
	 * Vypisuje se nad výpisem a je zároveň jediná zpráva, kterou po filtrování
	 * dostane odečítač obrazovky — výpis se mění skriptem, takže bez ní se
	 * nevidomému návštěvníkovi stránka tiše přestaví a on se o tom nedozví.
	 */
	function kct_events_found_label( int $count ): string {
		if ( $count <= 0 ) {
			return __( 'Žádná akce nevyhovuje', 'kct' );
		}

		return kct_plural(
			$count,
			/* translators: %d je počet akcí (1). */
			__( 'Nalezena %d akce', 'kct' ),
			/* translators: %d je počet akcí (2 až 4). */
			__( 'Nalezeny %d akce', 'kct' ),
			/* translators: %d je počet akcí (5 a více). */
			__( 'Nalezeno %d akcí', 'kct' )
		);
	}
endif;

if ( ! function_exists( 'kct_search_found_label' ) ) :
	/**
	 * Věta „kolik výsledků hledání našlo".
	 *
	 * Bez ní stránka neřekne, jestli je výsledek jeden nebo sto dvacet, a u
	 * stránkovaného výpisu ani to, kolikátou část člověk vidí.
	 */
	function kct_search_found_label( int $count ): string {
		return kct_plural(
			$count,
			/* translators: %d je počet výsledků (1). */
			__( 'Nalezen %d výsledek', 'kct' ),
			/* translators: %d je počet výsledků (2 až 4). */
			__( 'Nalezeny %d výsledky', 'kct' ),
			/* translators: %d je počet výsledků (0 a 5 a více). */
			__( 'Nalezeno %d výsledků', 'kct' )
		);
	}
endif;

if ( ! function_exists( 'kct_post_type_label' ) ) :
	/**
	 * Jméno typu obsahu pro štítek u výsledku hledání.
	 *
	 * Bere ho z registrace typu, ne z vlastního seznamu — šablona se instaluje
	 * i na weby s typy, o kterých neví, a pevný seznam by je zamlčel.
	 */
	function kct_post_type_label( ?string $post_type = null ): string {
		$object = get_post_type_object( $post_type ?: get_post_type() );

		return $object && ! empty( $object->labels->singular_name )
			? (string) $object->labels->singular_name
			: '';
	}
endif;

if ( ! function_exists( 'kct_events_empty_html' ) ) :
	/**
	 * Hlášení prázdného výpisu akcí.
	 *
	 * Jedno místo pro obě cesty — PHP archiv i AJAX endpoint. Dřív byl ten text
	 * napsaný v obou a mohl se rozejít.
	 *
	 * Omluva tu nestojí schválně. Nad výpisem už je `kct_events_found_label()`
	 * s větou „žádná akce nevyhovuje“, takže druhá věta o tomtéž jen jinými
	 * slovy nepřidá nic — a prázdno po rychlé volbě (třeba víkend bez akce)
	 * není chyba, za kterou by se měl web omlouvat. Místo toho se nabídne
	 * cesta dál: nejbližší akce za koncem zvoleného rozmezí a odkaz, který
	 * rozmezí zruší. Typ akce se do výhledu přenáší — kdo si vybral cyklo,
	 * nechce vidět nejbližší pochod.
	 *
	 * @param array $filter Rozmezí a typ, podle kterých se právě filtrovalo:
	 *                      `date_from`, `date_to`, `type`.
	 */
	function kct_events_empty_html( array $filter = array() ): string {
		$html = '<div class="events-empty">';

		$next = kct_next_event_after( $filter );

		if ( $next ) {
			// Plné datum, ne zkratka z kartičky. Ta stojí ve sloupci vedle
			// názvu, kde si ji člověk přečte jako datum; uprostřed věty by
			// „21. lis“ bylo jen zadrhnutí.
			$when = date_i18n( 'j. F', (int) strtotime( (string) ( $next['date'] ?? '' ) ) );

			$html .= '<p class="events-empty__next">' . sprintf(
				/* translators: 1: datum nejbližší akce („21. listopadu“), 2: odkaz s názvem akce. */
				esc_html__( 'Nejbližší akce je %1$s: %2$s', 'kct' ),
				esc_html( $when ),
				'<a href="' . esc_url( $next['permalink'] ?? '#' ) . '">' . esc_html( $next['title'] ?? '' ) . '</a>'
			) . '</p>';
		}

		$archive = get_post_type_archive_link( 'akce' );

		if ( $archive ) {
			$html .= '<p class="events-empty__all"><a href="' . esc_url( $archive ) . '">'
				. esc_html__( 'Zobrazit všechny nadcházející akce', 'kct' ) . '</a></p>';
		} elseif ( ! $next ) {
			// Bez výhledu i bez archivu by byl rámeček prázdný.
			$html .= '<p>' . esc_html__( 'Žádná nadcházející akce zatím není.', 'kct' ) . '</p>';
		}

		return $html . '</div>';
	}
endif;

if ( ! function_exists( 'kct_next_event_after' ) ) :
	/**
	 * První akce za koncem zvoleného rozmezí, nebo null.
	 *
	 * Hledá se od dne po `date_to` (bez něj ode dneška) rok dopředu — dál
	 * dohlédne málokterý výpis a odpověď „nejbližší akce je za čtrnáct
	 * měsíců“ stejně nikomu nepomůže. Volá se jedině při prázdném výpisu,
	 * takže běžnou stránku nezdržuje.
	 *
	 * @param array $filter Viz kct_events_empty_html().
	 */
	function kct_next_event_after( array $filter = array() ): ?array {
		if ( ! function_exists( 'kct_container' ) ) {
			return null;
		}

		$today = current_time( 'Y-m-d' );
		$to    = isset( $filter['date_to'] ) ? (string) $filter['date_to'] : '';
		$start = $to ? date( 'Y-m-d', strtotime( $to . ' +1 day' ) ) : $today;

		if ( $start < $today ) {
			$start = $today;
		}

		try {
			$events = kct_container()
				->get( \Kct\Features\Events::class )
				->get_events(
					$start,
					date( 'Y-m-d', strtotime( $start . ' +1 year' ) ),
					isset( $filter['type'] ) ? (string) $filter['type'] : ''
				);
		} catch ( \Throwable $e ) {
			return null;
		}

		return $events[0] ?? null;
	}
endif;

if ( ! function_exists( 'wp_body_open' ) ) :
	/**
	 * Shim for sites older than 5.2.
	 *
	 * @link https://core.trac.wordpress.org/ticket/12563
	 */
	function wp_body_open() {
		do_action( 'wp_body_open' );
	}
endif;

if ( ! function_exists( 'kct_event_sidebar_image' ) ) :
	/**
	 * Obrázek akce v postranním oddílu detailu.
	 *
	 * Obrázek sem chodí jako hotová adresa, ne jako ID přílohy — akce může
	 * přijít z databáze akcí, kde je obrázek cizí soubor. Pro soubor z vlastní
	 * knihovny se ale ID dohledat dá, a pak má smysl pustit ho přes jádro:
	 * dostane `srcset` a prohlížeč si do úzkého sloupce stáhne menší variantu
	 * místo originálu z fotoaparátu.
	 *
	 * `sizes` musí šířku sloupce popsat samo. Výchozí hodnota jádra mluví
	 * o šířce obsahu, jenže obrázek stojí vedle něj v užším sloupci, takže by
	 * se vybral soubor zbytečně velký.
	 *
	 * @param string $url   Adresa obrázku.
	 * @param string $title Název akce — do `alt` i `title`.
	 */
	function kct_event_sidebar_image( string $url, string $title ): string {
		$sizes         = '(max-width: 767px) 100vw, 400px';
		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id ) {
			$html = wp_get_attachment_image(
				$attachment_id,
				'large',
				false,
				array(
					'alt'   => $title,
					'title' => $title,
					'sizes' => $sizes,
				)
			);

			if ( $html ) {
				return $html;
			}
		}

		// Cizí soubor: velikost za nás nikdo nespočítá, ale aspoň se nemusí
		// stahovat dřív, než se k oddílu dojde.
		return sprintf(
			'<img src="%1$s" alt="%2$s" title="%2$s" loading="lazy" decoding="async">',
			esc_url( $url ),
			esc_attr( $title )
		);
	}
endif;
