<?php

namespace Kct\Repositories;

use Kct\Models\EventModel;
use Kct\PostTypes\EventPostType;
use KctDeps\Wpify\Model\PostRepository;

/**
 * @method EventModel get( $object = null )
 */
class EventRepository extends PostRepository {
	static function post_type(): string {
		return EventPostType::KEY;
	}

	/**
	 * @inheritDoc
	 */
	public function model(): string {
		return EventModel::class;
	}
}
