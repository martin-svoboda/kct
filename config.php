<?php

use KctDeps\DI\Definition\Helper\CreateDefinitionHelper;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Model\Manager;
use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

return array(
	CustomFields::class      => ( new CreateDefinitionHelper() )
		->constructor( plugins_url( 'deps/wpify/custom-fields', __FILE__ ) ),
	WordPressTemplate::class => ( new CreateDefinitionHelper() )
		->constructor( array( __DIR__ . '/templates' ), 'kct' ),
	PluginUtils::class       => ( new CreateDefinitionHelper() )
		->constructor( __DIR__ . '/kct.php' ),
	Manager::class => ( new CreateDefinitionHelper() )
		->constructor( [] )
);
