import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	MediaPlaceholder,
	MediaReplaceFlow,
	BlockControls,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Info karta — nativní blok do sloupců. Nadpis a text inline (RichText), obrázek
 * přes Media, barva a odkaz v postranním panelu. Render zajišťuje PHP (.cart markup).
 */

const COLORS = [
	{ value: '', label: __( 'Výchozí (primární)', 'kct' ) },
	{ value: '--primary-color', label: __( 'Primární barva', 'kct' ) },
	{ value: '--secondary-color', label: __( 'Sekundární barva', 'kct' ) },
	{ value: '--red-color', label: __( 'Červená', 'kct' ) },
	{ value: '--blue-color', label: __( 'Modrá', 'kct' ) },
	{ value: '--green-color', label: __( 'Zelená', 'kct' ) },
	{ value: '--yellow-color', label: __( 'Žlutá', 'kct' ) },
];

export default function Edit( { attributes, setAttributes } ) {
	const { image, title, text, link, color } = attributes;

	const media = useSelect( ( s ) => ( image ? s( 'core' ).getMedia( image ) : null ), [ image ] );
	const imgUrl = media?.source_url || '';

	const setLink = ( next ) => setAttributes( { link: { ...link, ...next } } );

	const blockProps = useBlockProps( {
		className: 'cart shadow',
		style: color ? { backgroundColor: `var(${ color })` } : undefined,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení karty', 'kct' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Barva boxu', 'kct' ) }
						value={ color }
						options={ COLORS }
						onChange={ ( v ) => setAttributes( { color: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'URL odkazu', 'kct' ) }
						value={ link?.url || '' }
						onChange={ ( url ) => setLink( { url } ) }
						placeholder="https://…"
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Otevřít v novém okně', 'kct' ) }
						checked={ link?.target === '_blank' }
						onChange={ ( on ) => setLink( { target: on ? '_blank' : '' } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			{ image ? (
				<BlockControls>
					<MediaReplaceFlow
						mediaId={ image }
						mediaURL={ imgUrl }
						accept="image/*"
						allowedTypes={ [ 'image' ] }
						onSelect={ ( m ) => setAttributes( { image: m?.id || 0 } ) }
						onReset={ () => setAttributes( { image: 0 } ) }
						name={ __( 'Změnit obrázek', 'kct' ) }
					/>
				</BlockControls>
			) : null }

			<div { ...blockProps }>
				{ image ? (
					<img src={ imgUrl } alt="" />
				) : (
					<MediaPlaceholder
						icon="format-image"
						labels={ { title: __( 'Obrázek / ikona', 'kct' ) } }
						accept="image/*"
						allowedTypes={ [ 'image' ] }
						onSelect={ ( m ) => setAttributes( { image: m?.id || 0 } ) }
					/>
				) }
				<div className="content">
					<RichText
						tagName="h3"
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
						placeholder={ __( 'Nadpis', 'kct' ) }
						allowedFormats={ [] }
					/>
					<RichText
						tagName="p"
						value={ text }
						onChange={ ( v ) => setAttributes( { text: v } ) }
						placeholder={ __( 'Text', 'kct' ) }
					/>
				</div>
			</div>
		</>
	);
}
