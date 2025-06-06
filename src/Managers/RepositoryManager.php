<?php

namespace Kct\Managers;

use Kct\Repositories\DbEventRepository;
use Kct\Repositories\DepartmentRepository;
use Kct\Repositories\EventRepository;
use Kct\Repositories\RoadRepository;
use KctDeps\DI\Container;
use KctDeps\Wpify\Model\Manager;

class RepositoryManager {
	public function __construct(
		private Manager $manager,
		Container $container,
		DbEventRepository $db_event_repository,
		EventRepository $event_repository,
		DepartmentRepository $department_repository,
		RoadRepository $road_repository
	) {
		foreach ( $manager->get_repositories() as $repository ) {
			$container->set( $repository::class, $repository );
		}

		$this->manager->register_repository( $db_event_repository );
		$this->manager->register_repository( $event_repository );
		$this->manager->register_repository( $department_repository );
		$this->manager->register_repository( $road_repository );
	}
}
