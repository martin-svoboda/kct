<?php

namespace Kct\Repositories;

use Kct\Settings;

class SettingsRepository {
	private $options = [];

	/**
	 * @param string $key
	 * @param null   $default
	 *
	 * @return string|array
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
	 * Get all options
	 *
	 * @return array|mixed
	 */
	public function get_options() {
		if ( ! $this->options ) {
			$this->options = get_option( Settings::KEY, array() );
		}

		return $this->options;
	}
}
