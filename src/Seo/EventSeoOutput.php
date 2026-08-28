<?php

namespace Kct\Seo;

/**
 * Výstup SEO hodnot do stránky.
 *
 * Dvě implementace: přes filtry Rank Mathu tam, kde je nainstalovaný,
 * a vlastním výpisem do wp_head tam, kde není (kctpodebrady).
 */
interface EventSeoOutput {

	/**
	 * @param array  $event     Pole akce z Events::get_event().
	 * @param string $canonical Kanonická adresa akce.
	 * @param bool   $is_single Jde o CPT příspěvek (true), nebo virtuální stránku (false)?
	 */
	public function render( array $event, string $canonical, bool $is_single ): void;
}
