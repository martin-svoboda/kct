<?php

namespace Kct\Managers;

use Kct\Features\Events;
use Kct\Features\Roads;

final class FeaturesManager {
	public function __construct(
		Events $events,
		Roads $roads
	) {
	}
}
