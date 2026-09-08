import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/action/block.json';
import Edit from './edit';

// Dynamický blok — render zajišťuje PHP, save vrací null.
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
