<?php

namespace Kct\Features;

/**
 * Titulek stránky u výpisů vlastních typů obsahu šablony.
 *
 * Rank Math skládá titulek archivu ze šablony `%pt_plural% Archive %sep%
 * %sitename%`, takže web, který si ji pro daný typ nepřenastavil, má v titulku
 * anglické „Archive“ — „Trasy Archive“. Pro web odboru je to výchozí stav:
 * šablonu si nainstaluje a nastavení Rank Mathu po něm nikdo nechce.
 *
 * Tahle feature proto titulek složí sama — z nadpisu archivu, který má web
 * v Přizpůsobení, a z oddělovače, který má nastavený v Rank Mathu. Sahá na něj
 * **jen tehdy, když si web vlastní šablonu titulku pro ten typ nenastavil**;
 * jakmile ji má, platí ona a tahle feature mlčí.
 */
class ArchiveSeo {
	/**
	 * Typy obsahu šablony a nastavení Přizpůsobení s nadpisem jejich archivu.
	 *
	 * Vlastní typy obsahu, ne příspěvky a stránky: u nich Rank Math titulek
	 * skládá správně a nastavení archivu nemají.
	 */
	const ARCHIVES = array(
		'akce'   => 'kct_akce_archive_title',
		'odbory' => 'kct_odbory_archive_title',
		'trasa'  => 'kct_trasa_archive_title',
	);

	public function __construct() {
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_title' ) );
	}

	/**
	 * @param string $title Hodnota z Rank Mathu.
	 * @return string
	 */
	public function filter_title( $title ) {
		if ( ! class_exists( '\RankMath\Helper' ) ) {
			return $title;
		}

		foreach ( self::ARCHIVES as $post_type => $theme_mod ) {
			if ( ! is_post_type_archive( $post_type ) ) {
				continue;
			}

			// Web s vlastní šablonou titulku si ji řídí sám.
			if ( '' !== (string) \RankMath\Helper::get_settings( "titles.pt_{$post_type}_archive_title" ) ) {
				return $title;
			}

			$own = (string) ( get_theme_mod( $theme_mod ) ?: post_type_archive_title( '', false ) );

			if ( '' === $own ) {
				return $title;
			}

			$separator = html_entity_decode(
				\RankMath\Helper::get_settings( 'titles.title_separator' ) ?: '-',
				ENT_QUOTES | ENT_HTML5,
				'UTF-8'
			);

			return $own . ' ' . $separator . ' ' . get_bloginfo( 'name' );
		}

		return $title;
	}
}
