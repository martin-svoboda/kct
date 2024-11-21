<?php
/**
 * Template part for displaying posts
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

/** @var \Kct\Models\DepartmentModel $department */
$department = kct_container()->get( \Kct\Repositories\DepartmentRepository::class )->get( get_the_ID() );

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
			<?php if ( $department->logo ) {
				echo wp_get_attachment_image( $department->logo, 'medium', array( "class" => "department-logo" ) );
			} ?>
			<div>
				odbor č. <?php echo $department->department_id ?>
				<h1 class="entry-title"><?= $department->title ?></h1>
			</div>
		</div>
	</header><!-- .entry-header -->

	<div class="department-content-wrap">
		<div class="entry-content">
			<h2>Nadcházející akce odboru</h2>
			<?php
			$template = kct_container()->get( KctDeps\Wpify\Template\WordPressTemplate::class );
			$args     = array(
				'count'      => 3,
				'department' => $department->department_id,
			);
			echo $template->render( 'blocks/events', null, $args );
			//dump( $department['to']_array() );

			the_content();

			?>
		</div><!-- .entry-content -->
		<div class="department-sidebar">

			<?php // kct_post_thumbnail(); ?>

			<h3>Kontaktní informace odboru</h3>
			<p><?php printf( '%s, %s %s, %s', $department->street, $department->zip, $department->town, $department->state ) ?></p>
			<?php if ( $department->phones ) {
				$phones = [];
				foreach ( $department->phones as $phone ) {
					$phones[] = sprintf( '<a href="tel:%1$s">%1$s</a>', $phone );
				}
				?>
				<p>Tel.: <?php echo implode( ', ', $phones ) ?></p>
			<?php } ?>
			<?php if ( $department->emails ) {
				$emails = [];
				foreach ( $department->emails as $email ) {
					// Zakrýt e-mailovou adresu pomocí obfuscation
					$obfuscated_email = str_replace( "@", " [zav] ", $email );
					$emails[]         = sprintf( '<span class="email-obfuscated" data-email="%1$s">Skryto</span>', $obfuscated_email );
				}
				?>
				<p>E-mail: <?php echo implode( ', ', $emails ) ?></p>
			<?php } ?>
			<?php if ( $department->web ) { ?>
				<p><?php printf( '<a href="%s" class="button" target="_blank">%s</a>', esc_url( $department->web ), __( 'Web odboru' ) ) ?></p>
			<?php } ?>
			<?php if ( $department->lng && $department->lat ) { ?>
				<div>
					<iframe style="border:none"
							src="https://frame.mapy.cz/?x=<?= $department->lng ?>&y=<?= $department->lat ?>&z=13"
							width="800" height="400" frameborder="0"></iframe>
				</div>
			<?php } ?>
		</div>
	</div>

	<footer class="entry-footer">
		<?php kct_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-<?php the_ID(); ?> -->
