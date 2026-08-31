<?php

namespace Kct\Og;

/**
 * Místní kopie ikon typů akcí.
 *
 * Ikony leží na doméně centrální databáze KČT
 * (https://www.akcekct.kct-db.cz/imagesakce/*.png). Stahovat je při každém
 * vykreslení karty by znamenalo síťový požadavek na cizí server uprostřed
 * generování obrázku, proto se každá stáhne jednou a dál se čte z disku.
 * Typů je 28 a každá ikona kolem kilobajtu, takže je to pár desítek kB.
 */
class IconCache {

	private const DIR = 'icons';

	/** Kolik sekund čekat na cizí server, než se ikona vzdá. */
	private const TIMEOUT = 5;

	/** Nejvyšší přijímaná velikost ikony v bajtech. */
	private const MAX_BYTES = 262144;

	/** Jak dlouho se po neúspěchu nezkouší stahovat znovu. */
	private const FAIL_TTL = 600;

	public function __construct( private OgImageStore $store ) {
	}

	/**
	 * Cesta k místní kopii ikony, nebo null, když ji nejde získat.
	 *
	 * @param string $url Adresa ikony z pole akce.
	 */
	public function path( string $url ): ?string {
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return null;
		}

		$key  = sha1( $url );
		$dir  = $this->store->dir() . '/' . self::DIR;
		$file = $dir . '/' . $key . '.png';

		if ( file_exists( $file ) ) {
			return $file;
		}

		// Když je cizí server nedostupný, nemá smysl na něj čekat u každé
		// další ikony a u každého dalšího vykreslení. Bez téhle pojistky by
		// výpadek znamenal několik pětisekundových čekání za jednu stránku.
		$failed = 'kct_og_icon_fail_' . $key;

		if ( get_transient( $failed ) ) {
			return null;
		}

		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$response = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) );
		$body     = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Ověření, že opravdu přišel obrázek. Cizí server může na chybu vrátit
		// HTML se stavem 200 a Imagick by na tom spadl uprostřed kreslení.
		if ( 200 !== $code || '' === $body || strlen( $body ) > self::MAX_BYTES || ! @getimagesizefromstring( $body ) ) {
			set_transient( $failed, 1, self::FAIL_TTL );

			return null;
		}

		$tmp = $file . '.tmp';

		if ( false === file_put_contents( $tmp, $body ) ) {
			return null;
		}

		if ( ! rename( $tmp, $file ) ) {
			wp_delete_file( $tmp );

			return null;
		}

		return $file;
	}
}
