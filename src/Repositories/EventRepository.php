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


	public function find_all_published_by_date( $date_from = '', $date_to = '', $type = '' ) {
		if ( $date_from ) {
			$meta_query[] = [
				'key'     => 'date',
				'value'   => $date_from,
				'compare' => '>=',
				'type'    => 'DATE',
			];
		}

		if ( $date_to ) {
			$meta_query[] = [
				'key'     => 'date',
				'value'   => $date_to,
				'compare' => '<=',
				'type'    => 'DATE',
			];
		}

		if ( $type ) {
			$meta_query[] = [
				'key'     => 'details',
				'value'   => sprintf( '"detailid";s:%s:"%s";', strlen( $type ), $type ),
				'compare' => 'LIKE',
			];
		}

		$args = [];
		if ( $meta_query ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				$meta_query
			);
		}

		return $this->find_published( $args );
	}
}
