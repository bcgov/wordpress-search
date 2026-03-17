import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function Save({ attributes }) {
	const {
		buttonText = 'Open Modal',
		buttonStyle = 'primary',
		mobileBreakpoint = 768,
	} = attributes;

	// Build CSS classes
	const containerClasses = 'wp-block-wordpress-search-search-modal';

	const blockProps = useBlockProps.save({
		className: containerClasses,
		'data-mobile-breakpoint': mobileBreakpoint,
		'data-button-text': buttonText,
		'data-button-style': buttonStyle,
		style: {
			'--mobile-breakpoint': `${mobileBreakpoint}px`,
		},
	});

	const innerBlocksProps = useInnerBlocksProps.save();

	return (
		<div {...blockProps}>
			<div {...innerBlocksProps} />
		</div>
	);
}
