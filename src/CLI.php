<?php

namespace Kct;

use Kct\Features\Departments;
use Kct\Features\Events;
use WP_CLI;
use WP_CLI_Command;

class CLI extends WP_CLI_Command {

	public function __construct() {
		parent::__construct();
		WP_CLI::add_command( 'kct', self::class );
	}

	public function import_departments() {
		$departments = kct_container()->get( Departments::class );
		$departments->import_departments();
	}

	public function import_events() {
		$events = kct_container()->get( Events::class );
		$events->import_db_events();
	}

	public function update_events() {
		$events = kct_container()->get( Events::class );
		$events->import_db_events( true );
	}
}
