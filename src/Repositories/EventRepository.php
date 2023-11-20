<?php

namespace Kct\Repositories;

use Kct\Models\EventModel;
use Kct\PostTypes\EventPostType;
use KctDeps\Wpify\Model\Exceptions\KeyNotFoundException;
use KctDeps\Wpify\Model\Exceptions\PrimaryKeyException;
use KctDeps\Wpify\Model\Exceptions\RepositoryNotInitialized;
use KctDeps\Wpify\Model\Exceptions\SqlException;
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

	/**
	 * Get event by feed ID
	 *
	 * @param int $db_id XML feed ID
	 *
	 * @return EventModel|null
	 * @throws KeyNotFoundException
	 * @throws PrimaryKeyException
	 * @throws RepositoryNotInitialized
	 * @throws SqlException
	 * @throws \ReflectionException
	 */
	public function get_by_id( int $id ): ?EventModel {
		$args = array(
			'post__in' => [ $id ],
		);

		$items = $this->find( $args );
		if ( ! empty( $items ) ) {
			return $items[0];
		}

		return null;
	}
}
