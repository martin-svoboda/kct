import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/eyebrow/block.json';
import Edit from './edit';
import save from './save';

// Statický blok — save vrací markup (žádná PHP šablona).
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save,
} );
