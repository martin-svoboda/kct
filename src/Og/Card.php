<?php

namespace Kct\Og;

/**
 * Jedno rozvržení sdílecího obrázku.
 *
 * Karta zná svoje rozměry a formát, ale neví, kam se výsledek uloží ani jak
 * se klíčuje — to je věc OgImageService a OgImageStore.
 */
interface Card {

	/**
	 * Vykreslí kartu a vrátí binární obsah obrázku.
	 *
	 * @param array $data Data karty; tvar popisuje docblock konkrétní karty.
	 */
	public function render( array $data ): string;

	public function width(): int;

	public function height(): int;

	/** Přípona souboru bez tečky, tedy `png` nebo `jpg`. */
	public function extension(): string;
}
