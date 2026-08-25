import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	MediaPlaceholder,
	MediaReplaceFlow,
	MediaUpload,
	MediaUploadCheck,
	BlockControls,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, TextControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * CTA blok — nadpis, text a popisek tlačítka inline (RichText), obrázek na pozadí
 * i v popředí nativně (Media), pozice obrázku / prolnutí / URL v postranním panelu.
 * Ukládá stejné atributy jako původní WCF blok.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { title, text, link, background, image, image_position, gradient } = attributes;

	const bg = useSelect( ( s ) => ( background ? s( 'core' ).getMedia( background ) : null ), [ background ] );
	const fg = useSelect( ( s ) => ( image ? s( 'core' ).getMedia( image ) : null ), [ image ] );
	const bgUrl = bg?.source_url || '';
	const fgUrl = fg?.source_url || '';

	const setLink = ( next ) => setAttributes( { link: { ...link, ...next } } );

	const blockProps = useBlockProps( {
		className: `kct-block block-action full-width ${ gradient ? 'gradient' : '' }`,
		style: bgUrl ? { backgroundImage: `url(${ bgUrl })` } : undefined,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení', 'kct' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Pozice obrázku', 'kct' ) }
						value={ image_position }
						options={ [
							{ value: 'right', label: 'Vpravo uvnitř kontejneru' },
							{ value: 'right-absolute', label: 'Vpravo bez odsazení' },
						] }
						onChange={ ( v ) => setAttributes( { image_position: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'S horním prolnutím', 'kct' ) }
						checked={ !! gradient }
						onChange={ ( v ) => setAttributes( { gradient: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Obrázek do popředí', 'kct' ) } initialOpen={ false }>
					<MediaUploadCheck>
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ image }
							onSelect={ ( m ) => setAttributes( { image: m?.id || 0 } ) }
							render={ ( { open } ) => (
								<div>
									{ fgUrl ? <img src={ fgUrl } alt="" style={ { maxWidth: '100%', marginBottom: 8 } } /> : null }
									<Button variant="secondary" onClick={ open }>
										{ image ? __( 'Změnit obrázek', 'kct' ) : __( 'Vybrat obrázek', 'kct' ) }
									</Button>
									{ image ? (
										<Button variant="link" isDestructive onClick={ () => setAttributes( { image: 0 } ) }>
											{ __( 'Odebrat', 'kct' ) }
										</Button>
									) : null }
								</div>
							) }
						/>
					</MediaUploadCheck>
				</PanelBody>
				<PanelBody title={ __( 'Odkaz tlačítka', 'kct' ) } initialOpen={ false }>
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
						onSelect={ ( m ) => setAttributes( { background: m?.id || 0 } ) }
						onReset={ () => setAttributes( { background: 0 } ) }
						name={ __( 'Změnit pozadí', 'kct' ) }
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
						onSelect={ ( m ) => setAttributes( { background: m?.id || 0 } ) }
					/>
				) : null }
				<div className={ `container ${ image_position }` }>
					<div className="content block-action__content">
						<span className="kct-tricolor block-action__eyebrow" aria-hidden="true"><span></span><span></span><span></span></span>
						<RichText
							tagName="h2"
							className="block-action__title"
							value={ title }
							onChange={ ( v ) => setAttributes( { title: v } ) }
							placeholder={ __( 'Nadpis', 'kct' ) }
							allowedFormats={ [] }
						/>
						<RichText
							tagName="p"
							className="block-action__text"
							value={ text }
							onChange={ ( v ) => setAttributes( { text: v } ) }
							placeholder={ __( 'Text', 'kct' ) }
						/>
						<RichText
							tagName="span"
							className="button"
							value={ link?.label || '' }
							onChange={ ( label ) => setLink( { label } ) }
							placeholder={ __( 'Text tlačítka', 'kct' ) }
							allowedFormats={ [] }
						/>
					</div>
					{ fgUrl ? <img src={ fgUrl } alt="" /> : null }
				</div>
			</div>
		</>
	);
}
