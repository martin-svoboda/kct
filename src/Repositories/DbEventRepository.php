<?php

namespace Kct\Repositories;

use Kct\Models\DbEventModel;
use KctDeps\Wpify\Model\CustomTableRepository;
use KctDeps\Wpify\Model\Exceptions\KeyNotFoundException;
use KctDeps\Wpify\Model\Exceptions\PrimaryKeyException;
use KctDeps\Wpify\Model\Exceptions\RepositoryNotInitialized;
use KctDeps\Wpify\Model\Exceptions\SqlException;

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

}
