<?php

namespace Kct\Models;

use Kct\Repositories\EventRepository;
use KctDeps\Wpify\Model\Attributes\Meta;
use KctDeps\Wpify\Model\Attributes\ReadOnlyProperty;
use KctDeps\Wpify\Model\Post;

/**
 * @method EventRepository model_repository()
 */
class EventModel extends Post {
	#[Meta]
	public ?int $db_id;

	#[Meta]
	public ?float $year;

	#[Meta]
	public ?string $place;

	#[Meta]
	public ?string $district;

//	#[Meta]
//	public ?string $web;
//
//	#[Meta]
//	public ?float $region;
//
//	#[Meta]
//	public ?float $department;

	#[Meta]
	public ?array $organiser;

	#[Meta]
	public ?array $start;

	#[Meta]
	public ?array $finish;

	#[Meta]
	public ?array $contact;

	#[Meta]
	public ?array $details;

	#[Meta]
	public ?array $proposal;

	#[ReadOnlyProperty]
	public ?string $date;

//	#[ReadOnlyProperty]
//	public string $title;
//
//	#[ReadOnlyProperty]
//	public string $content;

	public function get_date() {
		return isset( $this->start ) && !empty( $this->start ) && $this->start['date'] ? $this->start['date'] : '';
	}

//	public function to_array( array $props = array(), array $recursive = array() ): array {
//		$data              = parent::to_array( $props, $recursive );
//		$data['title']     = $this->title;
//		$data['image']     = $this->featured_image_id;
//
//		return $data;
//	}
}
