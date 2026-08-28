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
				printf( '<span class="tags-links">' . esc_html__( 'Tagged %1$s', 'kct' ) . '</span>', $tags_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<span class="comments-link">';
			comments_popup_link(
				sprintf(
					wp_kses(
					/* translators: %s: post title */
						__( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'kct' ),
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
				if ( ! has_post_thumbnail() && get_post_type(get_the_ID()) == 'post' ) {
					?>
                    <div class="thumbnail-replacement">
                        <?php
                        $custom_logo_id = get_theme_mod( 'custom_logo' );
                        echo wp_get_attachment_image( $custom_logo_id, 'full', false );
                        ?>
                    </div>
					<?php
				} else {
					the_post_thumbnail(
						'post-thumbnail',
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
	 * @param array $event Data akce (viz EventModel/DbEventModel::to_array()).
	 */
	function kct_render_event_item( array $event ): void {
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
					<img class="event__thumb" src="<?php echo esc_url( $event['image']['url'] ); ?>" alt="<?php echo esc_attr( $event['title'] ?? '' ); ?>" loading="lazy">
				<?php endif; ?>
				<div class="content-box">
					<div class="content">
						<h3 class="event__title"><?php echo ! empty( $event['year'] ) ? esc_html( $event['year'] ) . '. ' : ''; ?><?php echo esc_html( $event['title'] ?? '' ); ?></h3>
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
							<div class="proposal-icon">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20">
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

if ( ! function_exists( 'kct_footer_directory_columns' ) ) :
	/**
	 * Sestaví sloupce rozcestníku v patičce z obsahu, který web reálně má.
	 *
	 * Odkazy se skládají dynamicky (podle slugu/ID, ne natvrdo napsanou adresou),
	 * takže funkce přežije přejmenování stránek a funguje i na odborových webech
	 * sítě, kde většina z nich neexistuje — chybějící odkaz i celý prázdný sloupec
	 * se z výstupu prostě vypustí.
	 *
	 * @return array Pole sloupců ve tvaru array( array( 'title' => string, 'links' => array( array( 'label' => string, 'url' => string ) ) ) ).
	 */
	function kct_footer_directory_columns() {
		/**
		 * Odkaz na stránku podle (případně hierarchické) cesty, nebo null, pokud stránka na webu neexistuje.
		 *
		 * @param string $path  Slug, nebo cesta typu 'rodic/potomek'.
		 * @param string $label Text odkazu.
		 * @return array|null
		 */
		$kct_page_link = function ( $path, $label ) {
			$page = get_page_by_path( $path );
			if ( ! $page ) {
				return null;
			}
			$url = get_permalink( $page );
			if ( ! $url ) {
				return null;
			}
			return array(
				'label' => $label,
				'url'   => $url,
			);
		};

		/**
		 * Odkaz na archiv daného typu obsahu, nebo null, pokud na webu archiv neexistuje.
		 *
		 * @param string $post_type Typ obsahu.
		 * @param string $label     Text odkazu.
		 * @return array|null
		 */
		$kct_archive_link = function ( $post_type, $label ) {
			$url = get_post_type_archive_link( $post_type );
			if ( ! $url ) {
				return null;
			}
			return array(
				'label' => $label,
				'url'   => $url,
			);
		};

		/**
		 * Odkaz na stránku s výpisem příspěvků (Aktuality), nebo null, pokud není nastavená.
		 *
		 * @param string $label Text odkazu.
		 * @return array|null
		 */
		$kct_posts_page_link = function ( $label ) {
			$page_id = (int) get_option( 'page_for_posts' );
			if ( ! $page_id ) {
				return null;
			}
			$url = get_permalink( $page_id );
			if ( ! $url ) {
				return null;
			}
			return array(
				'label' => $label,
				'url'   => $url,
			);
		};

		$columns = array(
			array(
				'title' => esc_html__( 'Turistika a značení', 'kct' ),
				'links' => array_filter(
					array(
						$kct_page_link( 'turisticke-znaceni', esc_html__( 'Turistické značení', 'kct' ) ),
						$kct_page_link( 'turisticke-znaceni/bezpecnost-na-turistickych-znacenych-trasach', esc_html__( 'Bezpečnost na turistických značených trasách', 'kct' ) ),
						$kct_page_link( 'turisticke-odznaky-a-vykonnostni-turistika', esc_html__( 'Turistické odznaky a výkonnostní turistika', 'kct' ) ),
						$kct_page_link( 'oto-a-tto-stredoceskeho-kraje', esc_html__( 'OTO a TTO Středočeského kraje', 'kct' ) ),
						$kct_page_link( 'program-oblasti/turisticke-zavody', esc_html__( 'Turistické závody', 'kct' ) ),
						$kct_page_link( 'turisticka-oblast-brdy-a-podbrdsko', esc_html__( 'Turistická oblast Brdy a Podbrdsko', 'kct' ) ),
						$kct_page_link( 'vyznamne-turisticke-cesty-ve-stredoceske-oblasti', esc_html__( 'Významné turistické cesty ve Středočeské oblasti', 'kct' ) ),
					)
				),
			),
			array(
				'title' => esc_html__( 'O oblasti', 'kct' ),
				'links' => array_filter(
					array(
						$kct_page_link( 'o-oblasti', esc_html__( 'O oblasti', 'kct' ) ),
						$kct_page_link( 'o-oblasti/sin-slavy', esc_html__( 'Síň slávy', 'kct' ) ),
						$kct_page_link( 'osobnosti-a-odbory-uvedene-do-sine-slavy', esc_html__( 'Osobnosti a odbory uvedené do Síně slávy', 'kct' ) ),
						$kct_page_link( 'sekretariat-a-vybor-stredoceske-oblasti', esc_html__( 'Vedení oblasti', 'kct' ) ),
						$kct_page_link( 'o-oblasti/clenove-vyboru-a-aktiviste-oblasti', esc_html__( 'Členové výboru a kontakty', 'kct' ) ),
						$kct_page_link( 'clensvi-v-kct', esc_html__( 'Členství v KČT', 'kct' ) ),
						$kct_page_link( 'pojisteni-clenu-kct', esc_html__( 'Pojištění členů KČT', 'kct' ) ),
					)
				),
			),
			array(
				'title' => esc_html__( 'Aktuální dění', 'kct' ),
				'links' => array_filter(
					array(
						$kct_archive_link( 'akce', esc_html__( 'Kalendář akcí', 'kct' ) ),
						$kct_posts_page_link( esc_html__( 'Aktuality a zprávy', 'kct' ) ),
						$kct_archive_link( 'odbory', esc_html__( 'Odbory', 'kct' ) ),
					)
				),
			),
		);

		// Vyřadit sloupce bez odkazů (typicky odborové weby, kde většina stránek neexistuje).
		return array_values(
			array_filter(
				$columns,
				function ( $column ) {
					return ! empty( $column['links'] );
				}
			)
		);
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
