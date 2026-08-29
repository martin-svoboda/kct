<?php
/**
 * kct Theme Customizer
 *
 * @package kct
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function kct_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport        = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';
	//$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'blogname',
			array(
				'selector'        => '.site-title a',
				'render_callback' => 'kct_customize_partial_blogname',
			)
		);
		$wp_customize->selective_refresh->add_partial(
			'blogdescription',
			array(
				'selector'        => '.site-description',
				'render_callback' => 'kct_customize_partial_blogdescription',
			)
		);
	}

	// Skin / vzhled šablony
	$wp_customize->add_section( 'kct_appearance', array(
		'title'    => __( 'Vzhled šablony', 'kct' ),
		'priority' => 30,
	) );
	$wp_customize->add_setting( 'kct_skin', array(
		'default'           => 'photo',
		'transport'         => 'postMessage',
		'sanitize_callback' => function ( $v ) {
			return in_array( $v, array( 'photo', 'magazine', 'cards' ), true ) ? $v : 'photo';
		},
	) );
	$wp_customize->add_control( 'kct_skin', array(
		'section' => 'kct_appearance',
		'label'   => __( 'Styl šablony', 'kct' ),
		'type'    => 'radio',
		'choices' => array(
			'photo'    => __( 'Obrazový', 'kct' ),
			'magazine' => __( 'Časopisový', 'kct' ),
			'cards'    => __( 'Kartový', 'kct' ),
		),
	) );

	// Průhledné menu přes hero (obecně) — detail příspěvku a další hero sekce
	$wp_customize->add_setting( 'kct_hero_transparent', array(
		'default'           => false,
		'transport'         => 'refresh',
		'sanitize_callback' => function ( $v ) { return (bool) $v; },
	) );
	$wp_customize->add_control( 'kct_hero_transparent', array(
		'section'     => 'kct_appearance',
		'label'       => __( 'Průhledné menu přes hero', 'kct' ),
		'description' => __( 'Menu se u hero sekcí (detail příspěvku) překrývá přes obrázek. Při scrollu se podbarví.', 'kct' ),
		'type'        => 'checkbox',
	) );

	// Nadpisy (H1) archivů — editovatelné per web. Prázdné = výchozí popisek CPT.
	$wp_customize->add_setting( 'kct_akce_archive_title', array(
		'default'           => '',
		'transport'         => 'refresh',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'kct_akce_archive_title', array(
		'section'     => 'kct_appearance',
		'label'       => __( 'Nadpis stránky Akce', 'kct' ),
		'description' => __( 'Nadpis (H1) na výpisu akcí. Prázdné = výchozí „Akce".', 'kct' ),
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'kct_odbory_archive_title', array(
		'default'           => '',
		'transport'         => 'refresh',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'kct_odbory_archive_title', array(
		'section'     => 'kct_appearance',
		'label'       => __( 'Nadpis stránky Odbory', 'kct' ),
		'description' => __( 'Nadpis (H1) na výpisu odborů. Prázdné = výchozí „Odbory".', 'kct' ),
		'type'        => 'text',
	) );

	// Mapa ve výpisu akcí — některé odbory ji nechtějí (per-web nastavení).
	$wp_customize->add_setting( 'kct_events_map', array(
		'default'           => true,
		'transport'         => 'refresh',
		'sanitize_callback' => function ( $v ) { return (bool) $v; },
	) );
	$wp_customize->add_control( 'kct_events_map', array(
		'section'     => 'kct_appearance',
		'label'       => __( 'Zobrazit mapu ve výpisu akcí', 'kct' ),
		'description' => __( 'Mapa s akcemi nad výpisem na stránce /akce/. Vypni, pokud ji odbor nechce.', 'kct' ),
		'type'        => 'checkbox',
	) );

	// Vyhledávání v hlavičce. Výchozí vypnuto — hlavička je na každém webu sítě
	// jinak zaplněná, tak ať si ho zapne ten, komu se tam vejde.
	$wp_customize->add_setting( 'kct_header_search', array(
		'default'           => false,
		'transport'         => 'refresh',
		'sanitize_callback' => function ( $v ) { return (bool) $v; },
	) );
	$wp_customize->add_control( 'kct_header_search', array(
		'section'     => 'kct_appearance',
		'label'       => __( 'Vyhledávání v hlavičce', 'kct' ),
		'description' => __( 'Ikona lupy vedle menu; po kliknutí se rozbalí vyhledávací pole. Bez ní se dá hledat jen přes adresu /?s=výraz.', 'kct' ),
		'type'        => 'checkbox',
	) );

	// Tlačítko „Stát se členem" v hlavičce (prázdný odkaz = tlačítko se nezobrazí)
	$wp_customize->add_setting( 'kct_membership_url', array(
		'default'           => '',
		'transport'         => 'refresh',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'kct_membership_url', array(
		'section'     => 'kct_appearance',
		'label'       => __( 'Odkaz tlačítka v hlavičce', 'kct' ),
		'description' => __( 'Odkaz tlačítka „Stát se členem". Když zůstane prázdné, tlačítko se v hlavičce nezobrazí.', 'kct' ),
		'type'        => 'url',
	) );

	$wp_customize->add_setting( 'kct_membership_label', array(
		'default'           => __( 'Stát se členem', 'kct' ),
		'transport'         => 'refresh',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'kct_membership_label', array(
		'section' => 'kct_appearance',
		'label'   => __( 'Text tlačítka v hlavičce', 'kct' ),
		'type'    => 'text',
	) );

	// Primary color (prázdné = použij default zvoleného skinu)
	$wp_customize->add_setting( 'primary_color', array(
		'default'           => '',
		'transport'         => 'refresh',
		'sanitize_callback' => 'sanitize_hex_color',
	) );

	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'primary_color', array(
		'section' => 'colors',
		'label'   => esc_html__( 'Primární barva', 'kct' ),
	) ) );

	// Secondary logo
	$wp_customize->add_setting( 'secondary_logo', array(
		'default'   => '',
		'transport' => 'refresh'
	) );

	$wp_customize->add_control( 'secondary_logo', array(
		'label'   => __( 'Druhé logo v hlavičce', 'kct' ),
		'section' => 'title_tagline',
		'type'    => 'select',
		'choices' => array(
			''             => __( 'Žádné', 'kct' ),
			'kct'          => __( 'KČT', 'kct' ),
			'dobra-znacka' => __( 'Vaše dobrá značka', 'kct' ),
		),
	) );
}

add_action( 'customize_register', 'kct_customize_register' );

/**
 * Render the site title for the selective refresh partial.
 *
 * @return void
 */
function kct_customize_partial_blogname() {
	bloginfo( 'name' );
}

/**
 * Render the site tagline for the selective refresh partial.
 *
 * @return void
 */
function kct_customize_partial_blogdescription() {
	bloginfo( 'description' );
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function kct_customize_preview_js() {
	wp_enqueue_script( 'kct-customizer', get_template_directory_uri() . '/js/customizer.js', array( 'customize-preview' ), _S_VERSION, true );
}

add_action( 'customize_preview_init', 'kct_customize_preview_js' );
