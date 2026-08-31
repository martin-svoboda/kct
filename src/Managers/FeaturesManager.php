<?php

namespace Kct\Managers;

use Kct\Features\DepartmentSeo;
use Kct\Features\Events;
use Kct\Features\EventSeo;
use Kct\Features\FacebookShare;
use Kct\Features\ImageMetadata;
use Kct\Features\ImageUploads;
use Kct\Features\Lightbox;
use Kct\Features\OgImages;
use Kct\Features\OpenGraph;

final class FeaturesManager {
	public function __construct(
		Events $events,
		// Roads $roads,   trasy vypnuté, viz PostTypesManager. Jediné, co dělal,
		//                 bylo povolení nahrávání GPX — to má i Frontend.
		FacebookShare $facebook_share,
		OpenGraph $open_graph,
		Lightbox $lightbox,
		EventSeo $event_seo,
		ImageMetadata $image_metadata,
		ImageUploads $image_uploads,
		DepartmentSeo $department_seo,
		OgImages $og_images
	) {
	}
}
