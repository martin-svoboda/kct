<?php

namespace Kct\Repositories;

use Kct\Models\DbEventModel;
use KctDeps\Wpify\Model\CustomTableRepository;

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
}
