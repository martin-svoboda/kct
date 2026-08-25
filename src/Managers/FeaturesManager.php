<?php

namespace Kct\Managers;

use Kct\Features\Events;
use Kct\Features\FacebookShare;
use Kct\Features\OpenGraph;
use Kct\Features\Roads;

final class FeaturesManager {
	public function __construct(
		Events $events,
		Roads $roads,
		FacebookShare $facebook_share,
		OpenGraph $open_graph
	) {
	}
}
