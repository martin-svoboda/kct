<?php

namespace Kct\PostTypes;

/**
 * České štítky vlastních typů obsahu.
 *
 * Proč vlastní generátor: `AbstractCustomPostType::generate_labels()` skládá
 * štítky z anglických vzorů („Add New %s“, „Edit %s“) a dosazuje do nich náš
 * název v prvním pádě. V angličtině to vyjde, v češtině z toho je „Add New
 * Akce“ a po přeložení vzorů „Přidat nový Akce“.
 *
 * Čeština potřebuje pády a rod, a ty se z prvního pádu jednotného a množného
 * čísla odvodit nedají — „akci“, „odbor“, „místo“ a „trasu“ nemají společné
 * pravidlo. Tvary podstatného jména proto každý typ obsahu uvádí sám;
 * koncovky přídavných jmen a příčestí se dopočítají z rodu, protože tam
 * pravidlo je.
 *
 * Do `deps/` se nesahá: je to cizí knihovna a scoper ji při každém sestavení
 * přepíše.
 */
class CzechLabels {

	/**
	 * Sestaví pole štítků pro register_post_type().
	 *
	 * @param array $forms {
	 *     Tvary názvu typu obsahu.
	 *
	 *     @type string $rod       Rod: 'f' ženský, 'm' mužský neživotný, 'n' střední.
	 *     @type string $nom_j     1. pád jednotného čísla, s velkým písmenem: „Akce“, „Odbor“.
	 *     @type string $acc_j     4. pád jednotného čísla, malým písmenem: „akci“, „odbor“.
	 *     @type string $gen_j     2. pád jednotného čísla: „akce“, „odboru“.
	 *     @type string $lok_j     6. pád jednotného čísla: „akci“, „odboru“.
	 *     @type string $nom_m     1. pád množného čísla, s velkým písmenem: „Akce“, „Odbory“.
	 *     @type string $gen_m     2. pád množného čísla: „akcí“, „odborů“.
	 *     @type string $acc_m     4. pád množného čísla: „akce“, „odbory“.
	 *     @type string $menu      Volitelně název položky v menu; jinak 1. pád množného čísla.
	 * }
	 *
	 * @return array Štítky pro register_post_type().
	 */
	public static function build( array $forms ): array {
		$rod = $forms['rod'];

		$nom_j = $forms['nom_j'];
		$acc_j = $forms['acc_j'];
		$gen_j = $forms['gen_j'];
		$lok_j = $forms['lok_j'];
		$nom_m = $forms['nom_m'];
		$gen_m = $forms['gen_m'];
		$acc_m = $forms['acc_m'];

		// Přídavná jména a ukazovací zájmena podle rodu.
		$novy_1    = array( 'f' => 'Nová', 'm' => 'Nový', 'n' => 'Nové' )[ $rod ];
		$novy_4    = array( 'f' => 'novou', 'm' => 'nový', 'n' => 'nové' )[ $rod ];
		$nadrazeny = array( 'f' => 'Nadřazená', 'm' => 'Nadřazený', 'n' => 'Nadřazené' )[ $rod ];
		$tato_3    = array( 'f' => 'této', 'm' => 'tomuto', 'n' => 'tomuto' )[ $rod ];

		// Příčestí trpné: „akce byla zveřejněna“, „odbor byl zveřejněn“.
		$koncovka  = array( 'f' => 'a', 'm' => '', 'n' => 'o' )[ $rod ];
		$zverejnen = 'zveřejněn' . $koncovka;
		$aktual    = 'aktualizován' . $koncovka;
		$naplan    = 'naplánován' . $koncovka;
		$vracen    = 'vrácen' . $koncovka;

		// Množné číslo: „Žádné akce nenalezeny“, ale „Žádná místa nenalezena“.
		$zadne      = 'n' === $rod ? 'Žádná' : 'Žádné';
		$nenalezeny = 'n' === $rod ? 'nenalezena' : 'nenalezeny';

		return array(
			'name'                     => $nom_m,
			'singular_name'            => $nom_j,
			'menu_name'                => $forms['menu'] ?? $nom_m,
			'name_admin_bar'           => $nom_j,
			'add_new'                  => 'Přidat ' . $acc_j,
			'add_new_item'             => 'Přidat ' . $novy_4 . ' ' . $acc_j,
			'edit_item'                => 'Upravit ' . $acc_j,
			'new_item'                 => $novy_1 . ' ' . mb_strtolower( $nom_j ),
			'view_item'                => 'Zobrazit ' . $acc_j,
			'view_items'               => 'Zobrazit ' . $acc_m,
			'search_items'             => 'Hledat ' . $acc_m,
			'not_found'                => $zadne . ' ' . mb_strtolower( $nom_m ) . ' ' . $nenalezeny . '.',
			'not_found_in_trash'       => 'V koši nejsou ' . mb_strtolower( $zadne ) . ' ' . mb_strtolower( $nom_m ) . '.',
			'parent_item_colon'        => $nadrazeny . ' ' . mb_strtolower( $nom_j ) . ':',
			'all_items'                => ( 'n' === $rod ? 'Všechna ' : 'Všechny ' ) . mb_strtolower( $nom_m ),
			'archives'                 => 'Archiv ' . $gen_m,
			'attributes'               => 'Vlastnosti ' . $gen_j,
			'insert_into_item'         => 'Vložit do ' . $gen_j,
			'uploaded_to_this_item'    => 'Nahráno k ' . $tato_3 . ' ' . $lok_j,
			'featured_image'           => 'Náhledový obrázek',
			'set_featured_image'       => 'Nastavit náhledový obrázek',
			'remove_featured_image'    => 'Odebrat náhledový obrázek',
			'use_featured_image'       => 'Použít jako náhledový obrázek',
			'filter_items_list'        => 'Filtrovat seznam ' . $gen_m,
			'filter_by_date'           => 'Filtrovat podle data',
			'items_list_navigation'    => 'Navigace v seznamu ' . $gen_m,
			'items_list'               => 'Seznam ' . $gen_m,
			'item_published'           => $nom_j . ' ' . $zverejnen . '.',
			'item_published_privately' => $nom_j . ' ' . $zverejnen . ' soukromě.',
			'item_reverted_to_draft'   => $nom_j . ' ' . $vracen . ' do konceptů.',
			'item_scheduled'           => $nom_j . ' ' . $naplan . '.',
			'item_updated'             => $nom_j . ' ' . $aktual . '.',
			'item_link'                => 'Odkaz na ' . $acc_j,
			'item_link_description'    => 'Odkaz na ' . $acc_j . '.',
		);
	}
}
