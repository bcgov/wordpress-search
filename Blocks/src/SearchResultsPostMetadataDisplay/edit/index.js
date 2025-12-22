/**
 * WordPress Block Editor Dependencies
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
	useBlockProps,
	InspectorControls,
	useSetting,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { fontSize } = attributes;

	// Get font sizes from WordPress theme settings
	const fontSizes = useSetting( 'typography.fontSizes' ) || [];

	// Build font size options from WordPress settings
	const fontSizeOptions = [
		{ label: __( 'Default', 'wordpress-search' ), value: '' },
		...fontSizes.map( ( size ) => ( {
			label: size.name,
			value: size.slug,
		} ) ),
	];

	// Build font size style - handle both preset and custom values
	let fontSizeStyle;
	if ( fontSize ) {
		// Check if it's a custom value with units (px, em, rem, etc.)
		if ( /\d+(px|em|rem|%|vh|vw)/.test( fontSize ) ) {
			// Custom value - use directly
			fontSizeStyle = fontSize;
		} else {
			// Preset size - use CSS custom property
			fontSizeStyle = `var(--wp--preset--font-size--${ fontSize })`;
		}
	}

	// Get the block props which include the necessary editor attributes and classes
	const blockProps = useBlockProps( {
		style: {
			fontSize: fontSizeStyle,
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Typography Settings', 'wordpress-search' ) }
				>
					<SelectControl
						label={ __( 'Font Size', 'wordpress-search' ) }
						value={ fontSize }
						options={ fontSizeOptions }
						onChange={ ( newFontSize ) =>
							setAttributes( { fontSize: newFontSize } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="wp-block-wordpress-search-search-results-post-metadata-display">
					<div className="post-metadata">
						<div className="metadata-list">
							<div className="metadata-item">
								<span className="metadata-label">Example metadata:</span>
								<span className="metadata-value">Lorem ipsum dolor sit amet, consectetur adipiscing elit</span>
							</div>
							<div className="metadata-item">
								<span className="metadata-label">Example taxonomy:</span>
								<span className="metadata-value">Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
