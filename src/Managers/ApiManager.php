<?php

namespace Kct\Managers;

use Kct\Api\DepartmentsApi;
use Kct\Api\KctApi;
// use Kct\Api\TurinkaRouteFormApi;     trasy vypnuté, viz PostTypesManager
use Kct\Api\TurinkaEventRoutesApi;
use Kct\Api\TurinkaFormApi;
use Kct\Api\TurinkaTokenApi;

final class ApiManager {
	public function __construct(
		KctApi $events_api,
		TurinkaTokenApi $turinka_token_api,
		TurinkaFormApi $turinka_form_api,
		TurinkaEventRoutesApi $turinka_event_routes_api
		// TurinkaRouteFormApi $turinka_route_form_api   trasy vypnuté; formulář
		// Turinky se bude vydávat znovu, až bude odkud (viz PostTypesManager)
	) {
	}
}
