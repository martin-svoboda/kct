<?php

namespace Kct\Repositories;

use Kct\Models\DepartmentModel;
use Kct\PostTypes\DepartmentPostType;
use KctDeps\Wpify\Model\PostRepository;

/**
 * @method DepartmentModel get( $object = null )
 */
class DepartmentRepository extends PostRepository {
	public function post_type(): string {
		return DepartmentPostType::KEY;
	}

	public function post_types(): array {
		return array( $this->post_type() );
	}

	/**
	 * @inheritDoc
	 */
	public function model(): string {
		return DepartmentModel::class;
	}

	public function get_by_department_id( $department_id ) {
		$args = array(
			'meta_key'   => 'department_id',
			'meta_value' => $department_id
		);

		$items = $this->find( $args );
		if ( ! empty( $items ) ) {
			return $items[0];
		}

		return null;
	}

	public function find_published_to_array() {
		$items = $this->find_published();

		if ( empty( $items ) ) {
			return [ [ null ] ];
		}

		$array_items = [];
		/** @var DepartmentModel $item */
		foreach ( $items as $item ) {
			$array_items[] = $item->to_array();
		}

		return $array_items;
	}
}
