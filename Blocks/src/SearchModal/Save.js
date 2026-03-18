import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 *
 * @param root0
 * @param root0.attributes
 */
export default function Save({ attributes }) {
	const {
		buttonText = 'Open Modal',
		buttonStyle = 'primary',
		buttonBackgroundColor = '',
		buttonTextColor = '',
		mobileBreakpoint = 768,
	} = attributes;

	const containerClasses = 'wp-block-wordpress-search-search-modal';

	const blockProps = useBlockProps.save({
		className: containerClasses,
		'data-mobile-breakpoint': mobileBreakpoint,
		'data-button-text': buttonText,
		'data-button-style': buttonStyle,
		'data-button-background-color': buttonBackgroundColor || undefined,
		'data-button-text-color': buttonTextColor || undefined,
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
