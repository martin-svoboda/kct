<?php

namespace Kct\Facebook;

/**
 * Odeslání jednoho příspěvku na Facebook.
 *
 * Sdílí se fotkou — na dvakrát, nahráním a publikováním zvlášť (proč, viz
 * with_photo()). Když ji Facebook odmítne, spadne se na odkaz, aby se sdílení
 * kvůli obrázku neuskutečnilo. Logika je tady, ne u volajícího, protože ji
 * potřebují dvě cesty (příspěvky a databázové akce) a je to zrovna ta část,
 * kde by se kopie rozešly nepozorovaně — chybová větev se v provozu potká
 * zřídka.
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
			$result = $this->with_photo( $photo_message, $image_url );

			if ( null !== $result ) {
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
	 * Pokus o příspěvek s fotkou — nahrání a publikování zvlášť.
	 *
	 * Fotka se nejdřív nahraje nepublikovaná a teprve pak se na ni odkáže
	 * příspěvek na zdi. Jedním krokem to nejde: přímé publikování přes
	 * `/photos` vyrobí fotopříspěvek, který zapadne do alba stránky místo do
	 * výpisu příspěvků (viz GraphClient::upload_photo()).
	 *
	 * @return array|null Výsledek k vrácení, nebo null = zkus to odkazem.
	 */
	private function with_photo( string $photo_message, string $image_url ): ?array {
		$upload = $this->client->upload_photo(
			$this->credentials->page_id(),
			$this->credentials->token(),
			$image_url
		);

		if ( empty( $upload['ok'] ) ) {
			// Tady se na rozdíl od keep() nerozlišuje, jestli Facebook
			// odpověděl: nahrání nepublikované fotky příspěvek na zdi
			// nevytvoří, takže se po něm nemá co zdvojit a i nejistý výsledek
			// (kód 0, spadlé spojení) se dá bez obav dohnat odkazem.
			//
			// Neplatný token je výjimka: odkaz by dopadl stejně, tak se jím
			// neplýtvá a chyba se rovnou předá obsluze.
			if ( GraphClient::ERROR_INVALID_TOKEN === (int) ( $upload['code'] ?? 0 ) ) {
				return $upload;
			}

			error_log( sprintf(
				'kct: Facebook nepřijal fotku (%d: %s), zkouším odkazem.',
				(int) ( $upload['code'] ?? 0 ),
				(string) ( $upload['message'] ?? '' )
			) );

			return null;
		}

		$result = $this->client->publish(
			$this->credentials->page_id(),
			$this->credentials->token(),
			$photo_message,
			null,
			(string) $upload['id']
		);

		return $this->keep( $result ) ? $result : null;
	}

	/**
	 * Má se výsledek publikování příspěvku s fotkou brát jako konečný?
	 *
	 * Týká se druhého kroku, toho, který příspěvek na zdi skutečně vytváří.
	 * Úspěch je konečný. U neúspěchu záleží na tom, jestli Facebook odpověděl:
	 *
	 * - **Odpověděl a odmítl** (kód > 0) — příspěvek nevznikl a opakovat ho se
	 *   stejnou fotkou nemá smysl; pošle se odkaz, ať sdílení proběhne aspoň
	 *   takhle.
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
			'kct: Facebook odmítl příspěvek s fotkou (%d: %s), zkouším odkazem.',
			$code,
			(string) ( $result['message'] ?? '' )
		) );

		return false;
	}
}
