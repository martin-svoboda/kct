<?php

namespace Kct\Seo;

use Kct\Repositories\DbEventRepository;
use RankMath\Sitemap\Providers\Provider;
use RankMath\Sitemap\Router;

/**
 * Sitemapa detailů akcí pro Rank Math.
 *
 * Web vypisuje jen akce, pro které je kanonický — akce odborů s vlastním webem
 * patří do sitemapy toho webu. Akce převedené na CPT se vynechávají, jejich
 * virtuální adresa přesměrovává na příspěvek.
 */
class EventSitemapProvider implements Provider {

	const TYPE = 'akce-db';

	public function __construct(
		private DbEventRepository $db_event_repository,
		private CanonicalSites $sites
	) {
	}

	public function handles_type( $type ) {
		return self::TYPE === $type;
	}

	public function get_index_links( $max_entries ) {
		if ( ! $this->urls() ) {
			return array();
		}

		return array(
			array(
				'loc'     => Router::get_base_url( self::TYPE . '-sitemap.xml' ),
				'lastmod' => '',
			),
		);
	}

	public function get_sitemap_links( $type, $max_entries, $current_page ) {
		// Sitemapa se nestránkuje (249 položek proti limitu 50 000 v protokolu),
		// takže akce-db-sitemap2.xml a dál nemá co vracet. Rewrite pravidlo
		// Rank Mathu ale libovolné číslo pustí, a prázdný výstup se navíc
		// nezacachuje — bez tohohle by každá uhodnutá adresa vyrobila trvalý
		// soubor v uploads/rank-math/.
		if ( $current_page > 1 ) {
			return array();
		}

		return $this->urls();
	}

	/** @return array Seznam položek pro sitemapu. */
	private function urls(): array {
		$links = array();

		// '2000-01-01' = bez dolní hranice (výchozí u find_all_by_date() je
		// 2023-01-01, tabulka reálně začíná 2022-02-21) — proběhlé akce mají
		// v sitemapě zůstat, ne se z ní ztratit den po skončení.
		foreach ( $this->db_event_repository->find_all_by_date( '2000-01-01' ) as $db_event ) {
			$event = $db_event->to_array();

			// is_canonical_here() vrací false i pro akci spárovanou s CPT
			// příspěvkem (viz její docblok) — taková akce má permalink
			// příspěvku a patří do akce-sitemap.xml, ne sem. Nekontroluje se
			// to tu ještě jednou přes EventRepository::find_by_db_id() —
			// bylo by to zbytečné druhé zjišťování téhož a navíc dotaz navíc
			// pro každou z stovek akcí v cyklu.
			if ( ! $this->sites->is_canonical_here( $event ) ) {
				continue;
			}

			// Sloupec date v db_events je datum konání akce, ne datum poslední
			// změny záznamu — jako lastmod by u budoucích akcí lhal (vracel by
			// datum v budoucnosti). Rank Math prvek při prázdné hodnotě 'mod'
			// vynechá; chybějící <lastmod> je dovolený, nepravdivý není.
			// Nedoplňovat zpátky bez zdroje skutečného data poslední úpravy.
			$links[] = array(
				'loc' => $this->sites->url_for( $event ),
			);
		}

		return $links;
	}
}
