import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from '../../../blocks/image-content/block.json';
import Edit from './edit';

// Wrapper renderuje PHP, ale vnořené bloky se ukládají do post_content (InnerBlocks.Content).
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
