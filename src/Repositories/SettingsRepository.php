<?php

namespace Kct\Repositories;

use Kct\Settings;

class SettingsRepository {
	private $options = [];

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
}
