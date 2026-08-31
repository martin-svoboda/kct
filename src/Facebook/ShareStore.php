<?php

namespace Kct\Facebook;

/**
 * Stav odeslání jednoho objektu na Facebook.
 *
 * Dvě implementace: ShareState drží stav v post meta (aktuality a CPT akce),
 * DbShareState ve sloupci tabulky (akce z centrální databáze). Odesílání díky
 * tomu nemusí vědět, s čím pracuje.
 *
 * Klíč je celé číslo — u příspěvku jeho ID, u databázové akce její db_id.
 */
interface ShareStore {

	public function is_shared( int $id ): bool;

	/**
	 * Má se objekt odeslat? Vlastní volba u objektu přebíjí výchozí hodnotu.
	 */
	public function should_share( int $id, bool $default ): bool;

	/**
	 * Zabere zámek proti dvojímu odeslání. False = odesílá právě někdo jiný.
	 */
	public function claim( int $id ): bool;

	public function release( int $id ): void;

	public function mark_shared( int $id, string $fb_post_id ): void;

	public function mark_error( int $id, int $code, string $message ): void;
}
