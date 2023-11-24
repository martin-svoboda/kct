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

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>

	<style>
		<?php
		$primary_color   = get_theme_mod( 'primary_color', '#0178A3' );
		$secondary_color = '#1E3842';
		?>
		:root {
			--primary-color: <?= $primary_color ?>;
			--secondary-color: <?= $secondary_color ?>;
		}
	</style>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'kct' ); ?></a>

	<header id="masthead" class="site-header">
		<div class="container">
			<?php the_custom_logo(); ?>
			<div class="site-header-right">
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
					<?php endif;
					$secondary_logo = get_theme_mod( 'secondary_logo', '' );
					if ( $secondary_logo ) :
						$images = get_stylesheet_directory_uri() . '/images';
						?>
						<img src="<?php printf( '%s/logo_%s.png', $images, $secondary_logo ); ?>"
							 class="secondary-logo">
					<?php endif; ?>
				</div><!-- .site-branding -->

				<nav id="site-navigation" class="main-navigation">
					<button class="menu-toggle" aria-controls="primary-menu"
							aria-expanded="false"><?php esc_html_e( 'Primary Menu', 'kct' ); ?></button>
					<?php
					wp_nav_menu(
							array(
									'theme_location' => 'menu-1',
									'menu_id'        => 'primary-menu',
							)
					);
					?>
				</nav><!-- #site-navigation -->
			</div>
		</div>
	</header><!-- #masthead -->
