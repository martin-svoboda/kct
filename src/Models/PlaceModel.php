<?php

namespace Kct\Models;

use Kct\Repositories\PlaceRepository;
use KctDeps\Wpify\Model\Attributes\Meta;
use KctDeps\Wpify\Model\Post;

/**
 * @method PlaceRepository model_repository()
 */
class PlaceModel extends Post {

	#[Meta]
	public string $location = '';


	public function to_array( array $props = array(), array $recursive = array() ): array {
		$data = parent::to_array( $props, $recursive );
//		if ( $this->gpx ) {
//
//		}

		return $data;
	}
}
