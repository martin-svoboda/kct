<?php

namespace Kct\Seo;

use Kct\Models\DepartmentModel;
use Kct\Repositories\DepartmentRepository;

/**
 * Najde odkaz na stránku odboru, který pořádá danou akci.
 *
 * Odbory (CPT `odbory`) existují jen na hlavním webu sítě —
 * DepartmentPostType::register_post_type() se registruje jen tam
 * (`is_main_site()`), takže odbor tam jako jediný má i vlastní stránku.
 * Na jiném webu v síti (kde se detail akce pořádané cizím odborem taky
 * zobrazuje) proto stránku odboru najde jen přes switch_to_blog() na hlavní
 * web — stejný princip cross-site čtení jako CanonicalSites, jen bez vlastní
 * síťové cache. Ta by tu byla zbytečná složitost: CanonicalSites mapu staví
 * kvůli sitemapě, která projíždí stovky akcí najednou, kdežto tady jde o
 * jediné volání na jedno zobrazení detailu akce — jeden dotaz navíc na
 * stránku, bez nutnosti mapu kdekoli invalidovat.
 */
class DepartmentLink {

	public function __construct(
		private CanonicalSites $sites,
		private DepartmentRepository $department_repository
	) {
	}

	/**
	 * Permalink stránky odboru, který akci pořádá.
	 *
	 * Prázdný řetězec, když se odbor nedá dohledat — akce bez vyplněného
	 * pořadatele, nebo pořadatel bez vlastní CPT stránky (import odborů podle
	 * `Departments::import_departments()` vytváří jen odbory z vlastní
	 * oblasti). Volající pak text pořadatele nechá jako holý text.
	 *
	 * @param array $event Pole akce z Events::get_event().
	 */
	public function for_event( array $event ): string {
		// CanonicalSites::department_of() už řeší nekonzistenci mezi virtuální
		// stránkou (klíč `department` rovnou v poli) a CPT akcí (klíč chybí,
		// dohledá se přes db_id) — přepisovat tu stejnou logiku znovu by bylo
		// riziko, že se časem rozejde s tím, jak podle pořadatele určuje
		// kanonický web CanonicalSites sama.
		$code = $this->sites->department_of( $event );

		if ( '' === $code ) {
			return '';
		}

		$main_site_id = get_main_site_id();

		if ( get_current_blog_id() === $main_site_id ) {
			return $this->permalink_for_code( $code );
		}

		try {
			switch_to_blog( $main_site_id );
			$permalink = $this->permalink_for_code( $code );
		} finally {
			restore_current_blog();
		}

		return $permalink;
	}

	/**
	 * Adresa stránky odboru sestavená ručně, ne přes get_permalink().
	 *
	 * DepartmentPostType registruje CPT `odbory` jen na hlavním webu sítě
	 * (`is_main_site()`) — a to natrvalo, podle webu, který byl aktuální při
	 * bootu PHP procesu. switch_to_blog() na hlavní web tohle nepřehodnotí:
	 * post typy a jejich rewrite pravidla se registrují jen jednou na 'init'
	 * a zůstávají svázané s webem, na kterém request skutečně začal. Na
	 * vedlejším webu (kde `odbory` vůbec nejsou zaregistrované) by tak
	 * get_permalink() i po switch_to_blog() spadl na ošklivý tvar
	 * "?p={ID}" místo hezké adresy — ověřeno na kctricany.test.
	 *
	 * Adresa se proto skládá ručně ze slugu. Rewrite slug CPT `odbory` je bez
	 * vlastního nastavení stejný jako klíč typu (DepartmentPostType::get_args()
	 * nenastavuje 'rewrite'), což odpovídá i skutečným permalinkům
	 * (ověřeno: .../odbory/kct-odbor-slany/). Nespoléhá se tak na stav CPT
	 * registrace, funguje stejně na hlavním i na vedlejším webu — a pro
	 * konzistenci se stejnou cestou počítá i tady, ne jen v cross-site větvi.
	 */
	private function permalink_for_code( string $code ): string {
		/** @var DepartmentModel|null $department */
		$department = $this->department_repository->get_by_department_id( (int) $code );

		if ( ! $department instanceof DepartmentModel ) {
			return '';
		}

		// `find()` pod get_by_department_id() nefiltruje post_status (výchozí
		// 'any') — smazaný odbor (Departments::import_departments() ho při
		// smazání v exportu přeřadí do koše) by jinak dostal odkaz na
		// nedostupnou stránku.
		if ( 'publish' !== $department->post_status || '' === $department->slug ) {
			return '';
		}

		return trailingslashit( get_home_url( get_current_blog_id() ) ) . 'odbory/' . $department->slug . '/';
	}
}
