<?php
/**
 * Sdílená drobečková navigace (Rank Math) — jediná implementace pro celý web.
 * Vkládá se vždy DOVNITŘ hero/hlavičkového bloku dané šablony (nikdy jako
 * samostatný pruh nad ním — to bylo předchozí, zrušené řešení).
 *
 * Barevně je bezobsažná — dědí `color` z obalového bloku (viz
 * assets/styles/core/components/breadcrumbs.scss), takže funguje jak na
 * tmavé variantě hero (fotka na pozadí, bílý text), tak na světlé (plochá
 * hlavička, tmavý text) beze změny.
 *
 * @param array $args {
 *     @type bool $muted Ztlumit odkaz/oddělovač na nižší opacitu (bezpečné
 *                        jen tam, kde je na to podle výpočtu v breadcrumbs.scss
 *                        dost kontrastní rezerva — světlá varianta). Výchozí
 *                        false = plná opacita, bezpečný default i pro tmavou
 *                        variantu s menší rezervou.
 * }
 *
 * @package kct
 */

// Na úvodní stránce a na 404 drobečky nedávají smysl.
if ( is_front_page() || is_404() ) {
	return;
}

// Rank Math nemusí být na webu vůbec aktivní (např. kctpodebrady).
if ( ! function_exists( 'rank_math_get_breadcrumbs' ) ) {
	return;
}

$kct_muted = ! empty( $args['muted'] );

// Rank Math aktivní neznamená modul drobečků zapnutý — když je v nastavení
// vypnutý, funkce vrátí prázdný řetězec a nesmí se vykreslit ani prázdný
// obal (to byla podstata předchozí chyby — viditelný pruh bez obsahu).
$kct_crumbs = rank_math_get_breadcrumbs(
	array(
		'wrap_before' => '<nav class="breadcrumbs' . ( $kct_muted ? ' breadcrumbs--muted' : '' ) . '" aria-label="' . esc_attr__( 'Drobečková navigace', 'kct' ) . '"><p>',
		'wrap_after'  => '</p></nav>',
		'separator'   => '/',
	)
);

if ( ! $kct_crumbs ) {
	return;
}

echo wp_kses_post( $kct_crumbs );
