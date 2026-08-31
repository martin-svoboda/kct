<?php

namespace Kct\Facebook;

/**
 * Odeslání jednoho příspěvku na Facebook.
 *
 * Sdílí se fotkou; když ji Facebook odmítne, spadne se na odkaz, aby se
 * sdílení kvůli obrázku neuskutečnilo. Logika je tady, ne u volajícího,
 * protože ji potřebují dvě cesty (příspěvky a databázové akce) a je to zrovna
 * ta část, kde by se kopie rozešly nepozorovaně — chybová větev se v provozu
 * potká zřídka.
 */
class Publisher {

	public function __construct(
		private Credentials $credentials,
		private GraphClient $client
	) {
	}

	/**
	 * @param string      $message       Text pro odeslání odkazem.
	 * @param string      $photo_message Text pro odeslání fotkou (nese odkaz v sobě).
	 * @param string|null $link          Odkaz pro odeslání odkazem; null u obsahu bez detailu.
	 * @param string|null $image_url     Adresa sdílecího obrázku; null = rovnou odkazem.
	 *
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	public function send( string $message, string $photo_message, ?string $link, ?string $image_url ): array {
		if ( null !== $image_url && '' !== $image_url ) {
			$result = $this->client->publish_photo(
				$this->credentials->page_id(),
				$this->credentials->token(),
				$photo_message,
				$image_url
			);

			if ( $this->keep( $result ) ) {
				return $result;
			}
		}

		return $this->client->publish(
			$this->credentials->page_id(),
			$this->credentials->token(),
			$message,
			$link
		);
	}

	/**
	 * Má se výsledek odeslání fotky brát jako konečný?
	 *
	 * Úspěch ano. U neúspěchu záleží na tom, jestli Facebook odpověděl:
	 *
	 * - **Odpověděl a odmítl** (kód > 0) — fotka se mu nelíbí a opakovat ji
	 *   nemá smysl; pošle se odkaz, ať sdílení proběhne aspoň takhle.
	 * - **Neodpověděl** (kód 0, chyba spojení nebo časový limit) — neví se,
	 *   jestli příspěvek na zdi vznikl. Odeslat po tom ještě odkaz by mohlo
	 *   znamenat dva příspěvky za sebou, proto se to nechá spadnout do
	 *   běžného opakování, které je chráněné kontrolou is_shared().
	 *
	 * Neplatný token je výjimka v druhou stranu: odkaz by dopadl stejně, tak
	 * se jím neplýtvá a rovnou se předá obsluze chyb.
	 *
	 * @param array{ok: bool, code?: int, message?: string} $result
	 */
	private function keep( array $result ): bool {
		if ( ! empty( $result['ok'] ) ) {
			return true;
		}

		$code = (int) ( $result['code'] ?? 0 );

		if ( 0 === $code || GraphClient::ERROR_INVALID_TOKEN === $code ) {
			return true;
		}

		error_log( sprintf(
			'kct: Facebook odmítl fotku (%d: %s), zkouším odkazem.',
			$code,
			(string) ( $result['message'] ?? '' )
		) );

		return false;
	}
}
