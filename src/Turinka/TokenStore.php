<?php

namespace Kct\Turinka;

/**
 * Přístup k Turince: stav, token, vlastník a rozsah oprávnění.
 *
 * Všechno v jedné option, která se **neautoloaduje** — na frontendu není k ničemu
 * a token nemá co ležet v paměti každého požadavku.
 *
 * Konstanta ve wp-config.php jako u Facebooku tu možná není: token přichází
 * automaticky callbackem po schválení žádosti, takže ho nemá kdo do souboru
 * napsat. Proto se šifruje, viz encrypt().
 *
 * Přístup je per web, ne síťový — každý odbor má vlastní doménu i vlastníka.
 */
class TokenStore {
	const OPTION = 'kct_turinka_access';

	const STATE_NONE    = 'none';
	const STATE_PENDING = 'pending';
	const STATE_ACTIVE  = 'active';

	/**
	 * Stav přístupu: žádný, čeká na schválení, nebo funkční.
	 */
	public function state(): string {
		$data = $this->data();

		if ( '' !== $this->token() ) {
			return self::STATE_ACTIVE;
		}

		return self::STATE_PENDING === ( $data['state'] ?? '' ) ? self::STATE_PENDING : self::STATE_NONE;
	}

	public function has_token(): bool {
		return '' !== $this->token();
	}

	/**
	 * Token pro hlavičku Authorization.
	 *
	 * Vrací tajemství — nikdy ho nelogovat ani nikam nevypisovat.
	 */
	public function token(): string {
		$stored = $this->data()['token'] ?? null;

		return is_array( $stored ) ? $this->decrypt( $stored ) : '';
	}

	public function domain(): string {
		return (string) ( $this->data()['domain'] ?? '' );
	}

	/**
	 * @return string[]
	 */
	public function scopes(): array {
		return (array) ( $this->data()['scopes'] ?? array() );
	}

	public function has_scope( string $scope ): bool {
		return in_array( $scope, $this->scopes(), true );
	}

	/**
	 * Vlastník přístupu tak, jak ho hlásí Turinka: type, id, name.
	 */
	public function owner(): array {
		return (array) ( $this->data()['owner'] ?? array() );
	}

	/**
	 * Poznamená, že žádost odešla. Token ještě žádný není.
	 */
	public function mark_pending( string $domain ): void {
		$this->save( array(
			'state'        => self::STATE_PENDING,
			'domain'       => $domain,
			'requested_at' => time(),
		) );
	}

	/**
	 * Uloží ověřený token. Volá se z callbacku i z ručního vložení.
	 *
	 * @param array $identity Odpověď `/integrations/me` — doména, vlastník, scopes.
	 */
	public function activate( string $token, array $identity ): void {
		$this->save( array(
			'state'       => self::STATE_ACTIVE,
			'domain'      => (string) ( $identity['domain'] ?? '' ),
			'owner'       => (array) ( $identity['owner'] ?? array() ),
			'scopes'      => (array) ( $identity['scopes'] ?? array() ),
			'token'       => $this->encrypt( $token ),
			'received_at' => time(),
		) );
	}

	/**
	 * Obnoví vlastníka a oprávnění podle Turinky, token nechá být.
	 *
	 * Správce Turinky může přístupu změnit rozsah nebo vlastníka; „Ověřit
	 * připojení" je jediná chvíle, kdy se to plugin dozví.
	 */
	public function refresh( array $identity ): void {
		$data = $this->data();

		$data['domain'] = (string) ( $identity['domain'] ?? $data['domain'] ?? '' );
		$data['owner']  = (array) ( $identity['owner'] ?? array() );
		$data['scopes'] = (array) ( $identity['scopes'] ?? array() );

		$this->save( $data );
	}

	public function forget(): void {
		delete_option( self::OPTION );
	}

	private function data(): array {
		return (array) get_option( self::OPTION, array() );
	}

	private function save( array $data ): void {
		update_option( self::OPTION, $data, false );
	}

	/**
	 * Zašifruje token klíčem odvozeným ze solí ve wp-config.php.
	 *
	 * Chrání to před jediným, ale nejběžnějším únikem: zálohou databáze.
	 * Kdo má i wp-config.php, má stejně všechno — a proti tomu tahle vrstva
	 * nechrání a chránit nemá.
	 *
	 * Bez rozšíření openssl se uloží jak je; ukládat se to musí tak jako tak
	 * a tichý pád na plaintext je lepší než web, který se nedá propojit. Že
	 * se tak stalo, je vidět v `enc`.
	 */
	private function encrypt( string $token ): array {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return array( 'enc' => 'none', 'value' => $token );
		}

		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $token, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv );

		return array( 'enc' => 'aes-256-cbc', 'value' => base64_encode( $iv . $cipher ) );
	}

	private function decrypt( array $stored ): string {
		$value = (string) ( $stored['value'] ?? '' );

		if ( '' === $value || 'aes-256-cbc' !== ( $stored['enc'] ?? '' ) ) {
			return $value;
		}

		$raw = base64_decode( $value, true );

		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}

		$token = openssl_decrypt(
			substr( $raw, 16 ),
			'aes-256-cbc',
			$this->key(),
			OPENSSL_RAW_DATA,
			substr( $raw, 0, 16 )
		);

		return false === $token ? '' : $token;
	}

	/**
	 * Soli jsou v každé instalaci jiné a mimo databázi — přesně to, co se od
	 * klíče čeká.
	 */
	private function key(): string {
		$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );

		return hash( 'sha256', $salt, true );
	}
}
