<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package kct
 */

// Tlačítko „Stát se členem" — nastavitelné v Přizpůsobení → Vzhled šablony.
// Prázdný odkaz => tlačítko se nezobrazí. Filtr kct_membership_url zůstává pro programové přepsání.
$kct_membership_url   = apply_filters( 'kct_membership_url', get_theme_mod( 'kct_membership_url', '' ) );
$kct_membership_label = get_theme_mod( 'kct_membership_label', __( 'Stát se členem', 'kct' ) );
$secondary_logo = get_theme_mod( 'secondary_logo', '' );

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php
	// Leaflet se tu dřív načítal z unpkg.com natvrdo na každé stránce, i když
	// mapu má jen archiv akcí a odborů. Mapa si ho dnes veze ve vlastním bundlu
	// (build/map.js + build/map.css), který zařazuje Frontend::setup_assets()
	// jen tam, kde je potřeba. Šablona tras si globální L načítá sama.
	wp_head();
	?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Přejít k obsahu', 'kct' ); ?></a>

	<header id="masthead" class="site-header">
		<div class="container">
			<div class="header-brand">
				<?php the_custom_logo(); ?>
				<div class="site-branding">
					<?php
					// Na titulní straně nese název webu H1 — jinde ho má obsah
					// (entry-title / archive-title). Původní podmínka žádala
					// zároveň is_home(), což platí jen pro klasický výpis blogu;
					// s nastavenou statickou titulní stránkou byla nesplnitelná
					// a domovská stránka tak zůstávala bez jediného H1 (hero
					// cover má titulek jako H2, viz templates/blocks/cover.php).
					if ( is_front_page() ) :
						?>
						<h1 class="site-title">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
							   rel="home"><?php bloginfo( 'name' ); ?></a>
						</h1>
					<?php
					else :
						?>
						<p class="site-title">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
							   rel="home"><?php bloginfo( 'name' ); ?></a>
						</p>
					<?php
					endif;
					$kct_description = get_bloginfo( 'description', 'display' );
					if ( $kct_description || is_customize_preview() ) :
						?>
						<p class="site-description"><?php echo $kct_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?></p>
					<?php endif; ?>
				</div><!-- .site-branding -->
			</div><!-- .header-brand -->

			<button class="menu-toggle" aria-controls="site-nav" aria-expanded="false">
				<span class="menu-toggle__box"><span class="menu-toggle__inner"></span></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'kct' ); ?></span>
			</button>

			<div class="site-nav" id="site-nav">
				<nav id="site-navigation" class="main-navigation">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'menu-1',
							'menu_id'        => 'primary-menu',
						)
					);
					?>
				</nav><!-- #site-navigation -->

				<?php
				// Vyhledávání — jen když je zapnuté v Customizeru (Vzhled šablony).
				// Formulář je schovaný atributem hidden a odkrývá ho js/header.js;
				// bez JS se dá hledat přes /?s=výraz, šablona search.php existuje.
				if ( get_theme_mod( 'kct_header_search', false ) ) :
					?>
					<div class="header-search">
						<button type="button" class="header-search__toggle" aria-expanded="false" aria-controls="header-search-form">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-4.2-4.2"></path></svg>
							<span class="screen-reader-text"><?php esc_html_e( 'Hledat', 'kct' ); ?></span>
						</button>
						<form role="search" method="get" id="header-search-form" class="header-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>" hidden>
							<label class="screen-reader-text" for="header-search-field"><?php esc_html_e( 'Hledat', 'kct' ); ?></label>
							<input type="search" id="header-search-field" class="header-search__field" name="s"
								   value="<?php echo esc_attr( get_search_query() ); ?>"
								   placeholder="<?php esc_attr_e( 'Hledat…', 'kct' ); ?>">
						</form>
					</div>
				<?php endif; ?>

				<?php if ( $kct_membership_url ) : ?>
					<a class="btn site-header__cta" href="<?= esc_url( $kct_membership_url ) ?>"><?= esc_html( $kct_membership_label ) ?></a>
				<?php endif; ?>
			</div><!-- .site-nav -->

			<?php
				if ( $kct_membership_url || $secondary_logo ) : ?>
					<div class="site-header__right-column">
						<?php if ( $kct_membership_url ) : ?>
							<a class="btn site-header__cta" href="<?= esc_url( $kct_membership_url ) ?>"><?= esc_html( $kct_membership_label ) ?></a>
						<?php endif; 
						if ( $secondary_logo ) :
								$images = get_stylesheet_directory_uri() . '/images';
								?>
								<img src="<?php printf( '%s/logo_%s.png', $images, $secondary_logo ); ?>"
									class="secondary-logo">
						<?php endif; ?>
					</div>
				<?php endif; ?>

			<span class="menu-backdrop" hidden></span>
		</div>
	</header><!-- #masthead -->
