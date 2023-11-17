<?php

namespace Kct\Models;

use Kct\Repositories\EventRepository;
use KctDeps\Wpify\Model\Attributes\Meta;
use KctDeps\Wpify\Model\Post;

/**
 * @method EventRepository model_repository()
 */
class EventModel extends Post {
	#[Meta]
	public ?string $isbn;

	#[Meta]
	public ?string $author_name;

	#[Meta]
	public ?int $rating;
}
