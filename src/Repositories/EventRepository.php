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
	public function post_type(): string {
		return EventPostType::KEY;
	}

	public function post_types(): array {
		return array( $this->post_type() );
	}

	/**
	 * @inheritDoc
	 */
	public function model(): string {
		return EventModel::class;
	}

	public function find_all_published_by_date( $date_from = '', $date_to = '' ) {
		$date_query = [];
		$args       = [];

		if ( $date_from ) {
			$date_query['after'] = strtotime( $date_from );
		}
		if ( $date_to ) {
			$date_query['before'] = strtotime( $date_to );
		}

		if ( $date_query ) {
			$args = array( 'date_query' => $date_query );
		}

		return $this->find_published( $args );
	}
}
