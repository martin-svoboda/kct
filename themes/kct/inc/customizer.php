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
