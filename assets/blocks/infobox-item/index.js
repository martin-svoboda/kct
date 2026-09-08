import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/infobox-item/block.json';
import Edit from './edit';

// Dynamický blok — render zajišťuje PHP (templates/blocks/infobox-item.php), save vrací null.
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
