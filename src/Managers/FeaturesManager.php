<?php

namespace Kct\Managers;

use Kct\Features\Events;
use Kct\Features\EventSeo;
use Kct\Features\FacebookShare;
use Kct\Features\Lightbox;
use Kct\Features\OpenGraph;
use Kct\Features\Roads;

final class FeaturesManager {
	public function __construct(
		Events $events,
		Roads $roads,
		FacebookShare $facebook_share,
		OpenGraph $open_graph,
		Lightbox $lightbox,
		EventSeo $event_seo
	) {
	}
}
