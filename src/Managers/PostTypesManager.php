<?php

namespace Kct\Managers;

use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PagePostType;

final class PostTypesManager {
	public function __construct(
		EventPostType $book_post_type,
		PagePostType $page_post_type
	) {
	}
}
