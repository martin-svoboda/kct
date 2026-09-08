import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, MediaUpload, MediaUploadCheck, InspectorControls } from '@wordpress/block-editor';
import { Button, TextControl, ToggleControl, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, Fragment } from '@wordpress/element';

/**
 * Info boxy — PEVNĚ 3 karty (bez přidávání/mazání a bez volby barvy).
 * Nadpis, text a obrázek se editují inline v kartě; URL odkazů boxů 1–3 jsou
 * v postranním panelu (InspectorControls). Atribut boxes[] zůstává ve tvaru,
 * který čte PHP šablona (image = ID, link = {url,target}).
 */

const EMPTY_BOX = { image: 0, title: '', text: '', link: {} };

// Vždy vrátí právě 3 karty (dorovná / ořízne).
function ensureThree( list ) {
	const a = Array.isArray( list ) ? list : [];
	return [ 0, 1, 2 ].map( ( i ) => ( { ...EMPTY_BOX, ...( a[ i ] || {} ) } ) );
}

function BoxImage( { id, onSelect, onRemove } ) {
	const media = useSelect( ( s ) => ( id ? s( 'core' ).getMedia( id ) : null ), [ id ] );
	const url = media?.source_url || '';

	return (
		<MediaUploadCheck>
			<MediaUpload
				allowedTypes={ [ 'image' ] }
				value={ id }
				onSelect={ ( m ) => onSelect( m?.id || 0 ) }
				render={ ( { open } ) => (
					<div className="kct-infobox-image">
						{ url ? (
							<img src={ url } alt="" onClick={ open } style={ { cursor: 'pointer', maxWidth: '100%' } } />
						) : (
							<Button variant="secondary" onClick={ open }>{ __( 'Obrázek / ikona', 'kct' ) }</Button>
						) }
						{ id ? (
							<Button variant="link" isDestructive onClick={ onRemove }>{ __( 'Odebrat obrázek', 'kct' ) }</Button>
						) : null }
					</div>
				) }
			/>
		</MediaUploadCheck>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const boxes = ensureThree( attributes.boxes );

	// Uloženou hodnotu jednorázově normalizuj na 3 karty (starý obsah mohl mít jiný počet).
	useEffect( () => {
		if ( ! Array.isArray( attributes.boxes ) || attributes.boxes.length !== 3 ) {
			setAttributes( { boxes } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const update = ( i, patch ) =>
		setAttributes( { boxes: boxes.map( ( b, idx ) => ( idx === i ? { ...b, ...patch } : b ) ) } );
	const updateLink = ( i, patch ) => update( i, { link: { ...( boxes[ i ].link || {} ), ...patch } } );

	const blockProps = useBlockProps( { className: 'kct-block infoboxes' } );

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={ __( 'Odkazy boxů', 'kct' ) } initialOpen={ true }>
					{ [ 0, 1, 2 ].map( ( i ) => (
						<Fragment key={ i }>
							<TextControl
								label={ sprintfBox( i ) }
								value={ boxes[ i ].link?.url || '' }
								onChange={ ( url ) => updateLink( i, { url } ) }
								placeholder="https://…"
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Otevřít v novém okně', 'kct' ) }
								checked={ boxes[ i ].link?.target === '_blank' }
								onChange={ ( on ) => updateLink( i, { target: on ? '_blank' : '' } ) }
								__nextHasNoMarginBottom
							/>
							{ i < 2 ? <hr style={ { margin: '12px 0', opacity: 0.4 } } /> : null }
						</Fragment>
					) ) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="infoboxes__inner">
					{ boxes.map( ( box, i ) => (
						<div key={ i } className="cart shadow">
							<BoxImage
								id={ box.image }
								onSelect={ ( id ) => update( i, { image: id } ) }
								onRemove={ () => update( i, { image: 0 } ) }
							/>
							<div className="content">
								<RichText
									tagName="h3"
									value={ box.title }
									onChange={ ( v ) => update( i, { title: v } ) }
									placeholder={ __( 'Nadpis', 'kct' ) }
									allowedFormats={ [] }
								/>
								<RichText
									tagName="p"
									value={ box.text }
									onChange={ ( v ) => update( i, { text: v } ) }
									placeholder={ __( 'Text', 'kct' ) }
								/>
							</div>
						</div>
					) ) }
				</div>
			</div>
		</Fragment>
	);
}

function sprintfBox( i ) {
	/* translators: %d: box number */
	return __( 'URL boxu', 'kct' ) + ' ' + ( i + 1 );
}
