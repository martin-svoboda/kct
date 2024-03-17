<?php

namespace Kct;

use Kct\Features\Departments;
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
}
