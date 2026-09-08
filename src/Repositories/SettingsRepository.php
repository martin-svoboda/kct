<?php

namespace Kct\Repositories;

use Kct\Settings;

class SettingsRepository {
	private $options = [];

	public function get_key() {
		return Settings::KEY;
	}

	/**
	 * Retrieves the value of the specified option.
	 *
	 * @param string $key     The option key to retrieve.
	 * @param mixed  $default (optional) The default value to return if the option key does not exist. Default is null.
	 *
	 * @return mixed The value of the specified option, or the default value if the option key does not exist.
	 */
	public function get_option( $key = '', $default = null ) {
		if ( ! $this->options ) {
			$this->get_options();
		}

		if ( isset( $this->options[ $key ] ) ) {
			return $this->options[ $key ];
		}

		return $default ?: false;
	}

	/**
	 * Retrieves the options from the database.
	 *
	 * @return array The options from the database, or an empty array if no options exist.
	 */
	public function get_options() {
		if ( ! $this->options ) {
			$this->options = get_option( Settings::KEY, array() );
		}

		return $this->options;
	}

	/**
	 * Sestaví adresu jednoho exportu z centrální Databáze akcí KČT.
	 *
	 * Adresa exportu není veřejná a je zároveň jediné, co k datům pouští —
	 * žádný token ani přihlášení tam zatím není. V kódu proto být nesmí,
	 * jinak by ji dostal každý, kdo si šablonu stáhne. Vyplní ji do nastavení
	 * ten, komu ji ústředí KČT na vyžádání sdělí; dokud je pole prázdné,
	 * import se nespustí.
	 *
	 * Do pole se dá stejně dobře vložit adresa složky i celá adresa jednoho
	 * z exportů — koncový soubor si stejně doplňujeme sami, tak ho z konce
	 * zahodíme, ať to nemusí nikdo řešit.
	 *
	 * @param string $endpoint Název souboru exportu, např. `akceexport1x.php`.
	 *
	 * @return string Celá adresa, nebo prázdný řetězec, když adresa není nastavená.
	 */
	public function export_url( string $endpoint ): string {
		$base = trim( (string) $this->get_option( 'db_export_url' ) );

		if ( ! $base ) {
			return '';
		}

		if ( str_ends_with( strtolower( $base ), '.php' ) ) {
			$base = dirname( $base );
		}

		return esc_url_raw( trailingslashit( $base ) . $endpoint );
	}

	/**
	 * Determines the type of code based on its length.
	 *
	 * @return string The code type. Possible values are 'region', 'department', or an empty string.
	 */
	public function code_type() {
		$code = $this->get_option( 'id_code' );
		if ( ! $code ) {
			return '';
		}

		$numlength = strlen( (string) $code );

		return match ( $numlength ) {
			3 => 'region',
			6 => 'department',
			default => '',
		};
	}

	/**
	 * Zahodí nastavení drženou v paměti.
	 *
	 * Repozitář je v kontejneru singleton a options si drží po celý proces,
	 * takže po switch_to_blog() by dál vracel nastavení předchozího webu —
	 * včetně Page ID a tokenu Facebooku. Kdo přepíná weby, musí zavolat tohle.
	 */
	public function reset(): void {
		$this->options = [];
	}
}
