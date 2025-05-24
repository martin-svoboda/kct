<?php
/**
 * Template part for displaying posts
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

/** @var \Kct\Models\RoadModel $department */
$road     = kct_container()->get( \Kct\Repositories\RoadRepository::class )->get( get_the_ID() );
$gpx_file = $road->gpx;
$gpx_url  = '';

if ( $gpx_file ) {
	$gpx_url = wp_get_attachment_url( $gpx_file );
}

$image_url = '';
if ( is_single() && has_post_thumbnail() ) {
	$image_url = get_the_post_thumbnail_url( null, 'full' );
} /*elseif ( isset( $department['image'] ) && $department['image'] ) {
	$image_url = $department['image']['url'];
}*/
?>

<article id="post-<?php echo get_the_ID(); ?>" class="department-post">
	<header
		class="entry-header full-width <?= $image_url ? 'large' : '' ?>" <?php if ( $image_url ) { ?> style="background-image: url('<?= $image_url ?>')" <?php } ?>>
		<div class="container">
			<div>
				<h1 class="entry-title"><?= $road->title ?></h1>
			</div>
		</div>
	</header><!-- .entry-header -->

	<?php if ( $gpx_url ) : ?>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js" defer></script>
		<link rel="stylesheet" href="https://unpkg.com/@raruto/leaflet-elevation/dist/leaflet-elevation.css" />
		<script src="https://unpkg.com/@raruto/leaflet-elevation/dist/leaflet-elevation.js"></script>


		<div id="map" style="width: 100%; height: 600px;"></div>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const map = L.map('map').setView([49.9, 14.4], 13);

				// Mapy.cz tiles
				L.tileLayer('https://api.mapy.cz/v1/maptiles/outdoor/256/{z}/{x}/{y}?apikey=IVelrOn442cgk26I87WOwie-2jnq_fdhNT_o8qmT74o', {
					attribution: '&copy; Seznam.cz a.s.',
					maxZoom: 18,
				}).addTo(map);

				// Elevation control
				const controlElevation = L.control.elevation({
					position: "bottomright",
					theme: "steelblue-theme",
					collapsed: false,
					height: 200,
				}).addTo(map);

				// GPX loading
				new L.GPX("<?= esc_url( $gpx_url ); ?>", {
					async: true,
					marker_options: {
						startIconUrl: null,
						endIconUrl: null,
						shadowUrl: null,
					}
				})
					.on("loaded", function (e) {
						map.fitBounds(e.target.getBounds());
					})
					.on("addline", function (e) {
						controlElevation.addData(e.line);
					})
					.addTo(map);
			});
		</script>
	<?php endif; ?>

	<div class="department-content-wrap">
		<div class="entry-content">
			<?php
			the_content();
			?>
		</div><!-- .entry-content -->
		<div class="department-sidebar">

			<h3>Kontaktní informace odboru</h3>

		</div>
	</div>

	<footer class="entry-footer">
		<?php kct_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-<?php the_ID(); ?> -->
