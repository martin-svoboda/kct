import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Předtitulek — malý text s trikolórou nad nadpisem sekce.
 * Statický blok (save vrací markup), text se edituje inline.
 */
export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'kct-eyebrow' } );

	return (
		<div { ...blockProps }>
			<span className="kct-tricolor" aria-hidden="true"><span></span><span></span><span></span></span>
			<RichText
				tagName="span"
				className="kct-eyebrow__text"
				value={ attributes.text }
				onChange={ ( text ) => setAttributes( { text } ) }
				placeholder={ __( 'Předtitulek…', 'kct' ) }
				allowedFormats={ [] }
			/>
		</div>
	);
}
