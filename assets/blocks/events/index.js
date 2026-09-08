import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/events/block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
