import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Kalendář akcí — dynamický blok. Náhled v editoru vykresluje přímo PHP render
 * (ServerSideRender), nastavení je v postranním panelu. Žádný WCF formulář.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { time_period, count, button } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení', 'kct' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Časové období', 'kct' ) }
						value={ time_period }
						options={ [
							{ value: 'future', label: 'Budoucí' },
							{ value: 'past', label: 'Minulé' },
						] }
						onChange={ ( v ) => setAttributes( { time_period: v } ) }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Počet zobrazených akcí', 'kct' ) }
						value={ count || 5 }
						min={ 1 }
						max={ 20 }
						onChange={ ( v ) => setAttributes( { count: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Text tlačítka na kalendář akcí', 'kct' ) }
						value={ button || '' }
						onChange={ ( v ) => setAttributes( { button: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block="kct/events" attributes={ attributes } />
		</div>
	);
}
