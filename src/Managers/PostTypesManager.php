<?php

namespace Kct\Managers;

use Kct\PostTypes\DepartmentPostType;
use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PagePostType;
use Kct\PostTypes\PostPostType;

final class PostTypesManager {
	public function __construct(
		DepartmentPostType $department_post_type,
		EventPostType $book_post_type,
		PagePostType $page_post_type,
		PostPostType $post_post_type
	) {
	}
}
