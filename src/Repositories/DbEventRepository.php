<?php

namespace Kct\Repositories;

use Kct\Models\DbEventModel;
use KctDeps\Wpify\Model\CustomTableRepository;
use KctDeps\Wpify\Model\Exceptions\KeyNotFoundException;
use KctDeps\Wpify\Model\Exceptions\PrimaryKeyException;
use KctDeps\Wpify\Model\Exceptions\RepositoryNotInitialized;
use KctDeps\Wpify\Model\Exceptions\SqlException;
use KctDeps\Wpify\Model\Interfaces\ModelInterface;

/**
 * @method DbEventModel create()
 */
class DbEventRepository extends CustomTableRepository {
	/**
	 * Table name.
	 *
	 * @var string $table_name
	 */
	public static string $table_name = 'db_events';

	/**
	 * Return table name.
	 *
	 * @return string
	 */
	public function table_name(): string {
		return self::$table_name;
	}

	/**
	 * @inheritDoc
	 */
	public function model(): string {
		return DbEventModel::class;
	}

	/**
	 * Get event by feed ID
	 *
	 * @param int $db_id XML feed ID
	 *
	 * @return DbEventModel|null
	 * @throws KeyNotFoundException
	 * @throws PrimaryKeyException
	 * @throws RepositoryNotInitialized
	 * @throws SqlException
	 * @throws \ReflectionException
	 */
	public function get_by_db_id( int $db_id ): ?DbEventModel {
		$args = array(
			'where' => $this->db()->prepare( 'db_id = %d', $db_id ),
		);

		$items = $this->find( $args );
		if ( ! empty( $items ) ) {
			return $items[0];
		}

		return null;
	}

	/**
	 * Get all events by date and type
	 *
	 * @param $date_from
	 * @param $date_to
	 * @param $type
	 *
	 * @return ModelInterface[]
	 * @throws KeyNotFoundException
	 * @throws PrimaryKeyException
	 * @throws RepositoryNotInitialized
	 * @throws SqlException
	 * @throws \ReflectionException
	 */
	public function find_all_by_date( $date_from = '', $date_to = '', $type = '' ) {
		if ( ! $date_from ) {
			$date_from = '2023-01-01';
		}
		$query = $this->db()->prepare( 'date >= %s', $date_from );

		if ( $date_to ) {
			$query .= $this->db()->prepare( ' AND date <= %s', $date_to );
		}
		if ( $type ) {
			$query .= $this->db()->prepare( ' AND JSON_CONTAINS(details, JSON_OBJECT("detailid", %s))', $type );
		}

		return $this->find_all( [ 'where' => $query ] );
	}
}
