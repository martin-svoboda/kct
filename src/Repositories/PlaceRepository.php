<?php

namespace Kct\Repositories;

use Kct\Models\PlaceModel;
use Kct\PostTypes\PlacePostType;
use KctDeps\Wpify\Model\PostRepository;

/**
 * @method PlaceModel get( $object = null )
 */
class PlaceRepository extends PostRepository {
	public function post_type(): string {
		return PlacePostType::KEY;
	}

	public function post_types(): array {
		return array( $this->post_type() );
	}

	/**
	 * @inheritDoc
	 */
	public function model(): string {
		return PlaceModel::class;
	}
}
