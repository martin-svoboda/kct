<?php
/**
 * The template for displaying archive pages
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

get_header();

// Výpis odborů je statický → renderujeme server-side v PHP (SEO + bez závislosti
// na Reactu). React už jen vykreslí mapu (markery), a to z těchto dat (níže),
// takže nedělá druhý REST dotaz.
$kct_departments = kct_container()->get( \Kct\Repositories\DepartmentRepository::class )->find_published_to_array();

// Odfiltruj prázdný placeholder ([[null]]) i neúplné položky.
$kct_departments = array_values( array_filter( (array) $kct_departments, static function ( $d ) {
	return is_array( $d ) && ! empty( $d['title'] );
} ) );

// Data pro mapu — jen pole potřebná pro markery (bez telefonů/e-mailů apod.).
$kct_map_data = array_map( static function ( $d ) {
	return array(
		'id'        => $d['id'] ?? null,
		'title'     => $d['title'] ?? '',
		'permalink' => $d['permalink'] ?? '',
		'town'      => $d['town'] ?? '',
		'lat'       => $d['lat'] ?? 0,
		'lng'       => $d['lng'] ?? 0,
	);
}, $kct_departments );
?>
<script>window.kctMarkers = <?php echo wp_json_encode( $kct_map_data ); ?>;</script>

<div class="kct-map"><div id="map"></div></div>

<div class="container pt-0">
	<main id="primary" class="site-main" style="width:100%">
		<header class="archive-header">
			<h1 class="entry-title"><?php echo esc_html( get_theme_mod( 'kct_odbory_archive_title' ) ?: post_type_archive_title( '', false ) ); ?></h1>
		</header>
		<div class="departments">
			<?php if ( empty( $kct_departments ) ) : ?>
				<div>Nebyli nalezeny žádné odbory.</div>
			<?php else : ?>
				<ul class="departments-list">
					<?php foreach ( $kct_departments as $kct_d ) : ?>
						<li>
							<a href="<?php echo esc_url( $kct_d['permalink'] ?? '#' ); ?>" class="department" title="<?php echo esc_attr( $kct_d['title'] ); ?>">
								<?php if ( ! empty( $kct_d['image']['url'] ) ) : ?>
									<img src="<?php echo esc_url( $kct_d['image']['url'] ); ?>" title="<?php echo esc_attr( $kct_d['title'] ); ?>" alt="<?php echo esc_attr( $kct_d['title'] ); ?>">
								<?php endif; ?>
								<div class="content">
									<h3><?php echo esc_html( $kct_d['title'] ); ?></h3>
									<p>Odbor č. <?php echo esc_html( $kct_d['department_id'] ?? '' ); ?> | <?php echo esc_html( $kct_d['town'] ?? '' ); ?></p>
								</div>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</main>
</div>
<?php
get_footer();
