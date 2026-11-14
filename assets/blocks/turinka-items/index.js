/**
 * Bloky „Trasy z Turinky“ a „Místa z Turinky“.
 *
 * Oba mají stejné nastavení i náhled (ServerSideRender), liší se jen tím, co
 * vypisují — proto jedna komponenta a dvě registrace.
 */
import { registerBlockType } from '@wordpress/blocks';
import routes from '../../../blocks/turinka-routes/block.json';
import places from '../../../blocks/turinka-places/block.json';
import Edit from './edit';

[ routes, places ].forEach( ( metadata ) => {
	registerBlockType( metadata.name, {
		...metadata,
		edit: Edit,
		save: () => null,
	} );
} );
