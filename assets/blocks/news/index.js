import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/news/block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
