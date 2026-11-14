<?php

namespace Kct\Turinka;

/**
 * Skládá to, co web o trase posílá do Turinky.
 *
 * Web vlastní dvě věci: text a odkaz na článek. Všechno ostatní — stopu,
 * profil, obtížnost, viditelnost, obrázky, vazby na akce i NÁZEV — zadává
 * redakce ve formuláři Turinky, a vynechaný klíč pro API znamená „nesahat“.
 * Proto je tenhle payload tak krátký: každé pole navíc by bylo další místo,
 * kde se redakci ztratí práce odvedená ve formuláři.
 *
 * Název je výjimka jen jednou: při prvním odeslání se pošle, aby trasa
 * spárovaná bez formuláře nezůstala bez jména.
 *
 * Nic neposílá, nic neukládá a o WordPressu neví nic — text dostává už
 * vykreslený.
 */
class RoutePayload {
	/**
	 * Zápis trasy.
	 *
	 * @param string $description Text příspěvku už vykreslený do HTML.
	 * @param bool   $published   Odpublikovaný příspěvek si odkaz bere zpět:
	 *                            stránka zmizela, takže není kam odkázat.
	 * @param bool   $with_title  Název se posílá JEN při prvním odeslání.
	 */
	public function route( int $post_id, string $title, string $description, string $permalink, bool $published, bool $with_title = true ): array {
		$payload = array( 'source_id' => (string) $post_id );

		// Název trasy patří formuláři Turinky, ne webu. Posílá se jedině při
		// prvním odeslání, aby trasa nezůstala bez jména; od té chvíle se klíč
		// vynechává, což pro API znamená „nesahat“.
		//
		// Bez toho se dvě místa přetahovala o totéž pole: redakce trasu
		// přejmenovala ve formuláři, pak uložila příspěvek na webu a jméno se
		// tiše vrátilo na titulek příspěvku. Změřeno, ne odhadnuto.
		if ( $with_title ) {
			$payload['title'] = $title;
		}

		// Prázdný text se neposílá, ne nuluje: trasa bez textu je pořád trasa a
		// vynechaný klíč nechá v Turince to, co tam je.
		if ( '' !== wp_strip_all_tags( $description ) ) {
			$payload['description'] = $description;
		}

		// Odkaz se posílá vždy, i jako `null`. Vynechaný klíč pro API znamená
		// „nesahat“, takže by odpublikovanému příspěvku zůstal v Turince odkaz
		// na stránku, která vrací 404; `null` je výslovné zrušení.
		$payload['source_url'] = $published && '' !== $permalink ? $permalink : null;

		return $payload;
	}

	/**
	 * Předvyplnění formuláře.
	 *
	 * Jen název. Neplatné předvyplnění vrací API 422 hned při vydání lístku,
	 * takže se sem nedává nic, co zápis doplní beztak.
	 */
	public function prefill( string $title ): array {
		$title = trim( $title );

		return '' !== $title ? array( 'title' => $title ) : array();
	}
}
