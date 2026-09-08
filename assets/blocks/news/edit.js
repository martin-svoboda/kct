import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Aktuality / Novinky — dynamický blok. Náhled vykresluje PHP render (ServerSideRender),
 * nastavení v postranním panelu.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { button } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení', 'kct' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Text tlačítka na archiv', 'kct' ) }
						value={ button || '' }
						onChange={ ( v ) => setAttributes( { button: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block="kct/news" attributes={ attributes } />
		</div>
	);
}
