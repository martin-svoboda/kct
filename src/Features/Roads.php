<?php

namespace Kct\Features;

use DateTime;
use Kct\Models\DbEventModel;
use Kct\Models\EventModel;
use Kct\Repositories\DbEventRepository;
use Kct\Repositories\DepartmentRepository;
use Kct\Repositories\EventRepository;
use Kct\Repositories\SettingsRepository;
use Kct\Settings;
use WP_Post;

class Roads {

	public $db;

	public function __construct() {
		add_filter( 'upload_mimes', array( $this, 'allow_gpx_upload' ) );
	}

	public function allow_gpx_upload( $mimes ) {
		$mimes['gpx'] = 'application/gpx+xml';

		return $mimes;
	}
}
