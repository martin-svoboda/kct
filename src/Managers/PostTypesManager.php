<?php

namespace Kct\Managers;

use Kct\PostTypes\DepartmentPostType;
use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PagePostType;
use Kct\PostTypes\PostPostType;
// use Kct\PostTypes\RoadPostType;      trasy vypnuté, viz níž

final class PostTypesManager {
	/** Option s číslem sady typů příspěvků, podle které se obnovují adresy. */
	const VERSION_OPTION = 'kct_post_types_version';

	/**
	 * Zvyšuje se, když typ příspěvku přibude nebo zmizí.
	 *
	 * 1 → 2: přibyly trasy.
	 * 2 → 3: trasy zase vypnuté, viz poznámka níž.
	 */
	const VERSION = '3';

	// Trasy (CPT `trasa`) jsou vypnuté. Model, ve kterém je trasa příspěvkem
	// webu, narazil na strop: výpis umí jen vlastní trasy odboru, kdežto čtecí
	// API Turinky vidí trasy celé oblasti, a karta z příspěvku nemá z čeho
	// vzít mapu, profil ani čísla, která výpis z API nese. Počítá se s tím, že
	// trasy i místa budou na webu z API — blokem do stránky nebo příspěvku.
	//
	// Soubory zůstávají na místě, jen se neinstancují: PostTypes/RoadPostType.php,
	// Repositories/RoadRepository.php, Models/RoadModel.php, Features/Routes.php,
	// Blocks/RoutesBlock.php, Api/TurinkaRouteFormApi.php, Turinka/RouteMetabox.php,
	// themes/kct/archive-trasa.php a template-parts/content-trasa.php + route-*.php.
	//
	// Místa (CPT `misto`) jsou vypnutá — počítá se s tím, že je nahradí výpis
	// z aplikace Turinka. Typ byl registrovaný s nulovým obsahem, takže
	// v administraci visela prázdná položka menu jako nedodělek. Soubory
	// zůstávají na místě, jen se neinstancují: PostTypes/PlacePostType.php,
	// Repositories/PlaceRepository.php, Models/PlaceModel.php.
	//
	// Pozor při zapínání zpátky: parametr konstruktoru sám o sobě nestačí,
	// RepositoryManager repozitáře ještě registruje ve svém těle.
	public function __construct(
		DepartmentPostType $department_post_type,
		EventPostType $event_post_type,
		PagePostType $page_post_type,
		PostPostType $post_post_type
		// RoadPostType $road_post_type      viz poznámka výše
		// PlacePostType $place_post_type    viz poznámka výše
	) {
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 100 );
	}

	/**
	 * Obnoví přepisovací pravidla, když se sada typů příspěvků změnila.
	 *
	 * WordPress skládá pravidla jen při aktivaci pluginu nebo při uložení
	 * trvalých odkazů. Weby, kam se šablona nasazuje rsyncem, ale žádnou
	 * aktivaci neprojdou — nový typ příspěvku by na nich vracel 404, dokud by
	 * někdo ručně neotevřel Nastavení → Trvalé odkazy. Proto se pravidla
	 * obnovují jednou po změně čísla sady, ne při každém načtení: skládání
	 * pravidel je drahé.
	 *
	 * Měkký flush (`false`) schválně: `.htaccess` se na produkci upravuje
	 * ručně a plugin do něj nemá co zapisovat.
	 */
	public function maybe_flush_rewrites(): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}
}
