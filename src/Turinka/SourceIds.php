<?php

namespace Kct\Turinka;

/**
 * Vlastní identifikátory položek, které web zakládá v Turince.
 *
 * Položku určuje dvojice (klient, `source_id`) — číslo si volí web a Turinka
 * podle něj pozná, že jde o tutéž věc: opakovaný zápis je úprava, ne druhá
 * trasa. U akcí tím byl id příspěvku, jenže trasy ani místa web jako příspěvky
 * nedrží, takže si identifikátor musí vyrobit sám.
 *
 * Tvar je `kct-r-7` / `kct-p-3`: písmeno typu odliší trasy od míst a předpona
 * je tam proto, aby se nový identifikátor nikdy nepotkal s id příspěvku, pod
 * kterým web kdysi posílal trasy z vlastního typu obsahu.
 *
 * Drží se jen počítadlo, ne seznam: co se v Turince založilo, vrací její výpis
 * (`mine=1`) i s `source_id`, takže si web nemusí vést vlastní tabulku.
 */
class SourceIds {
	const OPTION = 'kct_turinka_source_seq';

	/** Písmeno v identifikátoru podle typu obsahu. */
	const LETTERS = array(
		'routes' => 'r',
		'places' => 'p',
	);

	/**
	 * Další volný identifikátor pro daný typ.
	 *
	 * Počítadlo je společné pro oba typy — přeskočené číslo nevadí, důležité
	 * je, aby se žádné nepoužilo dvakrát.
	 */
	public function next( string $type ): string {
		$letter = self::LETTERS[ $type ] ?? 'x';
		$next   = (int) get_option( self::OPTION, 0 ) + 1;

		update_option( self::OPTION, $next, false );

		return sprintf( 'kct-%s-%d', $letter, $next );
	}
}
