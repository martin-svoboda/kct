import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/infobox/block.json';
import Edit from './edit';

// Dynamický blok — render zajišťuje PHP (templates/blocks/infoboxes.php), save vrací null.
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
