import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	MediaPlaceholder,
	MediaReplaceFlow,
	BlockControls,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Vizuální editace bloku "Úvodní obrázek".
 *
 * Nadpis, text a popisek tlačítka se editují inline přímo v plátně (RichText),
 * obrázek na pozadí přes nativní MediaPlaceholder / MediaReplaceFlow. URL tlačítka
 * a "otevřít v novém okně" jsou v postranním panelu (InspectorControls).
 *
 * Ukládá STEJNÉ atributy jako původní WCF blok (background = ID přílohy,
 * link = {label,url,target}), takže stávající obsah i PHP šablona zůstávají beze změny.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { background, title, text, link } = attributes;

	// URL obrázku dopočítáme z jeho ID (ukládáme jen ID, jako dřív).
	const media = useSelect(
		( select ) => ( background ? select( 'core' ).getMedia( background ) : null ),
		[ background ]
	);
	const bgUrl = media?.source_url || '';

	const setLink = ( next ) => setAttributes( { link: { ...link, ...next } } );
	const onSelectMedia = ( m ) => setAttributes( { background: m?.id || 0 } );

	const blockProps = useBlockProps( {
		className: 'kct-block block-cover full-width',
		style: bgUrl ? { backgroundImage: `url(${ bgUrl })` } : undefined,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Odkaz tlačítka', 'kct' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'URL', 'kct' ) }
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

			{ background ? (
				<BlockControls>
					<MediaReplaceFlow
						mediaId={ background }
						mediaURL={ bgUrl }
						accept="image/*"
						allowedTypes={ [ 'image' ] }
						onSelect={ onSelectMedia }
						onReset={ () => setAttributes( { background: 0 } ) }
						name={ __( 'Změnit obrázek', 'kct' ) }
					/>
				</BlockControls>
			) : null }

			<div { ...blockProps }>
				{ ! background ? (
					<MediaPlaceholder
						icon="format-image"
						labels={ { title: __( 'Obrázek na pozadí', 'kct' ) } }
						accept="image/*"
						allowedTypes={ [ 'image' ] }
						onSelect={ onSelectMedia }
					/>
				) : null }
				<div className="container">
					<div className="content">
						<RichText
							tagName="h2"
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
						<RichText
							tagName="span"
							className="button white"
							value={ link?.label || '' }
							onChange={ ( label ) => setLink( { label } ) }
							placeholder={ __( 'Text tlačítka', 'kct' ) }
							allowedFormats={ [] }
						/>
					</div>
				</div>
			</div>
		</>
	);
}
