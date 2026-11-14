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
 * přes Media, barva proužku a odkaz v postranním panelu. Render zajišťuje PHP
 * (.cart markup).
 *
 * Hodnota barvy je KLÍČ, ne jméno CSS proměnné: na token ho přeloží PHP
 * (InfoboxItemBlock::ACCENTS), takže se z obsahu nedá do stylu dostat nic
 * jiného než barva ze seznamu. Tenhle seznam proto musí držet s tím v PHP.
 */

const ACCENTS = {
	'': 'var(--primary)',
	blue: 'var(--kct-blue)',
	red: 'var(--kct-red)',
	green: 'var(--kct-green)',
	yellow: 'var(--kct-yellow)',
	dark: 'var(--text)',
};

const COLORS = [
	{ value: '', label: __( 'Primární barva webu', 'kct' ) },
	{ value: 'blue', label: __( 'Modrá značka', 'kct' ) },
	{ value: 'red', label: __( 'Červená značka', 'kct' ) },
	{ value: 'green', label: __( 'Zelená značka', 'kct' ) },
	{ value: 'yellow', label: __( 'Žlutá značka', 'kct' ) },
	{ value: 'dark', label: __( 'Tmavá', 'kct' ) },
];

export default function Edit( { attributes, setAttributes } ) {
	const { image, title, text, link, color } = attributes;

	const media = useSelect( ( s ) => ( image ? s( 'core' ).getMedia( image ) : null ), [ image ] );
	const imgUrl = media?.source_url || '';

	const setLink = ( next ) => setAttributes( { link: { ...link, ...next } } );

	// Proužek nad kartou, ne podbarvení celé karty: editor má ukazovat totéž,
	// co uvidí návštěvník. Starší obsah nese klíče `--*-color`, které tenhle
	// seznam nezná — proužek se v editoru ukáže primární barvou, na webu
	// správnou, a srovná se to, jak kdo kartu uloží.
	const blockProps = useBlockProps( {
		className: 'cart shadow',
		style: { '--cart-accent': ACCENTS[ color ] || ACCENTS[ '' ] },
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Nastavení karty', 'kct' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Barva proužku nad kartou', 'kct' ) }
						value={ COLORS.some( ( c ) => c.value === color ) ? color : '' }
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
