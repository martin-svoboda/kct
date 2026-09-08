<?php

namespace Kct\Models;

use Kct\Repositories\DepartmentRepository;
use KctDeps\Wpify\Model\Attributes\Meta;
use KctDeps\Wpify\Model\Post;

/**
 * @method DepartmentRepository model_repository()
 */
class RoadModel extends Post {

	#[Meta]
	public string $gpx = '';


	public function to_array( array $props = array(), array $recursive = array() ): array {
		$data = parent::to_array( $props, $recursive );
//		if ( $this->gpx ) {
//
//		}

		return $data;
	}
}
