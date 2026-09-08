import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const blockProps = useBlockProps.save( { className: 'kct-eyebrow' } );

	return (
		<div { ...blockProps }>
			<span className="kct-tricolor" aria-hidden="true"><span></span><span></span><span></span></span>
			<RichText.Content tagName="span" className="kct-eyebrow__text" value={ attributes.text } />
		</div>
	);
}
