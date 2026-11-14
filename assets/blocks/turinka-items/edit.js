import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Nastavení výpisu z Turinky. Náhled vykresluje PHP (ServerSideRender), takže
 * v editoru stojí přesně to, co pak bude na webu — včetně toho, že prázdný
 * výpis nevykreslí nic.
 */
export default function Edit( { attributes, setAttributes, name } ) {
	const { count, scope, difficulty, heading } = attributes;
	const blockProps = useBlockProps();
	const isRoutes = name === 'kct/turinka-routes';

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení', 'kct' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Co vypsat', 'kct' ) }
						value={ scope }
						options={ [
							{ value: 'mine', label: __( 'Jen obsah našeho odboru nebo oblasti', 'kct' ) },
							{ value: 'region', label: __( 'Celou oblast', 'kct' ) },
							{ value: 'all', label: __( 'Vše veřejné v Turince', 'kct' ) },
						] }
						onChange={ ( v ) => setAttributes( { scope: v } ) }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ isRoutes ? __( 'Počet tras', 'kct' ) : __( 'Počet míst', 'kct' ) }
						value={ count || 6 }
						min={ 1 }
						max={ 24 }
						onChange={ ( v ) => setAttributes( { count: v } ) }
						__nextHasNoMarginBottom
					/>
					{ isRoutes && (
						<SelectControl
							label={ __( 'Obtížnost', 'kct' ) }
							value={ difficulty || '' }
							options={ [
								{ value: '', label: __( 'Všechny', 'kct' ) },
								{ value: 'easy', label: __( 'Snadná', 'kct' ) },
								{ value: 'moderate', label: __( 'Střední', 'kct' ) },
								{ value: 'difficult', label: __( 'Náročná', 'kct' ) },
							] }
							onChange={ ( v ) => setAttributes( { difficulty: v } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<TextControl
						label={ __( 'Nadpis nad výpisem', 'kct' ) }
						value={ heading || '' }
						onChange={ ( v ) => setAttributes( { heading: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ name } attributes={ attributes } />
		</div>
	);
}
