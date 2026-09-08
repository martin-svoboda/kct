<?php

namespace Kct\Managers;

use Kct\Api\DepartmentsApi;
use Kct\Api\KctApi;
use Kct\Api\TurinkaTokenApi;

final class ApiManager {
	public function __construct(
		KctApi $events_api,
		TurinkaTokenApi $turinka_token_api
	) {
	}
}
