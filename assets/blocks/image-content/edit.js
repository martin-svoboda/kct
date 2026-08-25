import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	MediaPlaceholder,
	MediaReplaceFlow,
	MediaUpload,
	MediaUploadCheck,
	BlockControls,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Obrázek s obsahem vedle — obsah se skládá z nativních vnořených bloků (InnerBlocks),
 * obrázek na pozadí i v popředí přes Media. Pozice obrázku v postranním panelu.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { background, image, image_position } = attributes;

	const bg = useSelect( ( s ) => ( background ? s( 'core' ).getMedia( background ) : null ), [ background ] );
	const fg = useSelect( ( s ) => ( image ? s( 'core' ).getMedia( image ) : null ), [ image ] );
	const bgUrl = bg?.source_url || '';
	const fgUrl = fg?.source_url || '';

	const blockProps = useBlockProps( {
		className: 'kct-block block-image-with-content full-width',
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
							{ value: 'left', label: 'Vlevo uvnitř kontejneru' },
							{ value: 'left-absolute', label: 'Vlevo bez odsazení' },
							{ value: 'right', label: 'Vpravo uvnitř kontejneru' },
							{ value: 'right-absolute', label: 'Vpravo bez odsazení' },
						] }
						onChange={ ( v ) => setAttributes( { image_position: v } ) }
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
					<div className="content">
						<InnerBlocks />
					</div>
					{ fgUrl ? <img src={ fgUrl } alt="" /> : null }
				</div>
			</div>
		</>
	);
}
