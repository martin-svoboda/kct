<?php

namespace Kct\Managers;

use Kct\Features\AdminHelp;
use Kct\Features\AdminMenu;
use Kct\Features\ArchiveSeo;
use Kct\Features\DbEventShare;
use Kct\Features\DepartmentSeo;
use Kct\Features\EventRoutes;
use Kct\Features\Events;
use Kct\Features\EventsOverview;
use Kct\Features\EventSeo;
use Kct\Features\FacebookShare;
use Kct\Features\ImageMetadata;
use Kct\Features\ImageUploads;
use Kct\Features\Lightbox;
use Kct\Features\OgImages;
use Kct\Features\OpenGraph;
// use Kct\Features\Routes;             trasy vypnuté, viz PostTypesManager
use Kct\Features\Turinka;
use Kct\Features\TurinkaOverview;
use Kct\Updates\UpdateChecker;

final class FeaturesManager {
	public function __construct(
		Events $events,
		EventRoutes $event_routes,
		EventsOverview $events_overview,
		// Routes $routes,                    trasy vypnuté, viz PostTypesManager
		FacebookShare $facebook_share,
		OpenGraph $open_graph,
		Lightbox $lightbox,
		EventSeo $event_seo,
		ImageMetadata $image_metadata,
		ImageUploads $image_uploads,
		DepartmentSeo $department_seo,
		ArchiveSeo $archive_seo,
		OgImages $og_images,
		DbEventShare $db_event_share,
		Turinka $turinka,
		TurinkaOverview $turinka_overview,
		AdminMenu $admin_menu,
		AdminHelp $admin_help,
		UpdateChecker $update_checker
	) {
	}
}
