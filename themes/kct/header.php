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
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.2/dist/leaflet.css" integrity="sha256-sA+zWATbFveLLNqWO2gtiw3HL/lh1giY/Inf1BJ0z14=" crossorigin="" />
	<script src="https://unpkg.com/leaflet@1.9.2/dist/leaflet.js" integrity="sha256-o9N1jGDZrf5tS+Ft4gbIK7mYMipq9lqpVJ91xHSyKhg=" crossorigin=""></script>

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'kct' ); ?></a>

	<header id="masthead" class="site-header">
		<div class="container">
			<div class="header-brand">
				<?php the_custom_logo(); ?>
				<div class="site-branding">
					<?php
					if ( is_front_page() && is_home() ) :
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
