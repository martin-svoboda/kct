<?php

namespace Kct\Models;

use Kct\Repositories\DepartmentRepository;
use KctDeps\Wpify\Model\Attributes\Meta;
use KctDeps\Wpify\Model\Post;

/**
 * @method DepartmentRepository model_repository()
 */
class DepartmentModel extends Post {
	#[Meta]
	public int $department_id = 0;

	#[Meta]
	public int $region_id = 0;

	#[Meta]
	public string $name = '';

	#[Meta]
	public string $street = '';

	#[Meta]
	public string $zip = '';

	#[Meta]
	public string $town = '';

	#[Meta]
	public string $state = '';

	#[Meta]
	public string $web = '';

	#[Meta]
	public array $phones = array();

	#[Meta]
	public array $emails = array();

	#[Meta]
	public float $lng = 0;

	#[Meta]
	public float $lat = 0;

	#[Meta]
	public string $deleted = '';

	#[Meta]
	public string $changed = '';

	#[Meta]
	public string $logo = '';


	public function to_array( array $props = array(), array $recursive = array() ): array {
		$data = parent::to_array( $props, $recursive );
		if ( $this->logo ) {
			$data['image'] = array(
				'url' => wp_get_attachment_url( $this->logo ),
			);
		}

		return $data;
	}
}
