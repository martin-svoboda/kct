<?php

namespace Kct\Repositories;

use Kct\Models\RoadModel;
use Kct\PostTypes\RoadPostType;
use KctDeps\Wpify\Model\PostRepository;

/**
 * @method RoadModel get( $object = null )
 */
class RoadRepository extends PostRepository {
	public function post_type(): string {
		return RoadPostType::KEY;
	}

	public function post_types(): array {
		return array( $this->post_type() );
	}

	/**
	 * @inheritDoc
	 */
	public function model(): string {
		return RoadModel::class;
	}
}
