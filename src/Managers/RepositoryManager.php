<?php

namespace Kct\Managers;

use Kct\Repositories\DbEventRepository;
use KctDeps\DI\Container;
use KctDeps\Wpify\Model\Manager;

class RepositoryManager {
	public function __construct(
		private Manager $manager,
		Container $container,
		DbEventRepository $db_event_repository
	) {
		foreach ( $manager->get_repositories() as $repository ) {
			$container->set( $repository::class, $repository );
		}

		$this->manager->register_repository( $db_event_repository );
	}
}
