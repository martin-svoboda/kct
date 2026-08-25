/**
 * Registrace nativních KČT Gutenberg bloků v editoru.
 * Každý blok má vlastní složku v assets/blocks/ a registruje se importem níže.
 */
import { registerBlockStyle } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import '../blocks/cover';
import '../blocks/action';
import '../blocks/image-content';
import '../blocks/events';
import '../blocks/news';
import '../blocks/infobox';
import '../blocks/infobox-item';
import '../blocks/eyebrow';

// Styl sloupců „Karty stejné výšky“ – karty uvnitř se roztáhnou na jednotnou výšku.
// Použij na řádek sloupců s Info kartami; jinde (karta vedle jiného obsahu) nezapínej.
registerBlockStyle( 'core/columns', {
	name: 'kct-cards',
	label: __( 'Karty stejné výšky', 'kct' ),
} );
