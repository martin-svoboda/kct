import { registerBlockType } from '@wordpress/blocks';
import metadata from '../../../blocks/cover/block.json';
import Edit from './edit';

/**
 * Dynamický blok — render zajišťuje PHP (render_callback → templates/blocks/cover.php),
 * proto save vrací null (mezi delimitery bloku se neukládá žádné HTML).
 */
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
