<?php

namespace Kct;

use KctDeps\Wpify\CustomFields\CustomFields;

/**
 * Class Settings
 *
 * @package Wpify\Settings
 */
class Settings {
	/**
	 * @var CustomFields
	 */
	public $wcf;

	/**
	 * @var array
	 */
	public $options = array();

	/**
	 * Option key, and option page slug
	 *
	 * @var string
	 */
	const KEY = 'kct_options';

	public function __construct( CustomFields $wcf ) {
		$this->wcf = $wcf;

		$this->setup();
	}

	public function setup() {
		$this->wcf->create_options_page( array(
			'parent_slug' => 'options-general.php',
			'page_title'  => __( 'Kct Settings', 'kct' ),
			'menu_title'  => __( 'Kct', 'kct' ),
			'menu_slug'   => self::KEY,
			'capability'  => 'manage_options',
			'items'       => array(
				array(
					'id'    => self::KEY,
					'type'  => 'group',
					'items' => array(
						array(
							'title' => __( 'Some URL', 'wp-plugin' ),
							'id'    => 'some_url',
							'type'  => 'url',
						),
						array(
							'title' => __( 'Some HTML', 'wp-plugin' ),
							'id'    => 'some_html',
							'type'  => 'wysiwyg',
						),
						array(
							'title' => __( 'Multi attachments', 'wp-plugin' ),
							'id'    => 'some_attachments',
							'type'  => 'multi_attachment',
						),
					),
				),
			),
		) );
	}
}
