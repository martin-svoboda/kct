<?php

namespace Kct\Managers;

use Kct\PostTypes\DepartmentPostType;
use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PagePostType;
use Kct\PostTypes\PostPostType;

final class PostTypesManager {
	// Trasy (CPT `trasa`) a Místa (CPT `misto`) jsou vypnuté — počítá se s tím,
	// že je nahradí API a widgety aplikace Turinka. Oba typy byly registrované
	// s nulovým obsahem, takže v administraci visely prázdné položky menu jako
	// nedodělek. Soubory zůstávají na místě, jen se neinstancují: PostTypes/
	// {Road,Place}PostType.php, Repositories/{Road,Place}Repository.php,
	// Models/{Road,Place}Model.php, Features/Roads.php a v šabloně
	// template-parts/content-trasa.php.
	//
	// Pozor při zapínání zpátky: parametr konstruktoru sám o sobě nestačí,
	// RepositoryManager repozitáře ještě registruje ve svém těle.
	public function __construct(
		DepartmentPostType $department_post_type,
		EventPostType $event_post_type,
		PagePostType $page_post_type,
		PostPostType $post_post_type
		// RoadPostType $road_post_type,    viz poznámka výše
		// PlacePostType $place_post_type
	) {
	}
}
