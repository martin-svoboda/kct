import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Trasy — dynamický blok. Náhled v editoru vykresluje přímo PHP render
 * (ServerSideRender), nastavení je v postranním panelu.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { count, order, button } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení', 'kct' ) } initialOpen={ true }>
					<RangeControl
						label={ __( 'Počet zobrazených tras', 'kct' ) }
						value={ count || 6 }
						min={ 1 }
						max={ 20 }
						onChange={ ( v ) => setAttributes( { count: v } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Řazení', 'kct' ) }
						value={ order }
						options={ [
							{ value: 'date', label: 'Od nejnovější' },
							{ value: 'title', label: 'Podle názvu' },
						] }
						onChange={ ( v ) => setAttributes( { order: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Text tlačítka na výpis tras', 'kct' ) }
						value={ button || '' }
						onChange={ ( v ) => setAttributes( { button: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block="kct/routes" attributes={ attributes } />
		</div>
	);
}
