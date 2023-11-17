<?php

use Kct\PostTypes\EventPostType;

?>
<p>
	<?php
	echo sprintf(
			__( '<a href="%s">Show all the books</a>', 'kct' ),
			get_post_type_archive_link( EventPostType::KEY )
	);
	?>
</p>
<?php the_content(); ?>
