/**
 * WordPress Block Editor Dependencies
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls, FontSizePicker } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { fontSize } = attributes;
	
	// Build font size style - handle both preset and custom values
	let fontSizeStyle;
	if ( fontSize ) {
		// Check if it's a custom value with units (px, em, rem, etc.)
		if ( /\d+(px|em|rem|%|vh|vw)/.test( fontSize ) ) {
			// Custom value - use directly
			fontSizeStyle = fontSize;
		} else {
			// Preset size - use CSS custom property
			fontSizeStyle = `var(--wp--preset--font-size--${fontSize})`;
		}
	}
	
	// Get the block props which include the necessary editor attributes and classes
	const blockProps = useBlockProps({
		style: {
			fontSize: fontSizeStyle,
		}
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Typography Settings', 'wordpress-search')}>
					<FontSizePicker
						value={fontSize}
						onChange={(newFontSize) => setAttributes({ fontSize: newFontSize })}
						withSlider
					/>
				</PanelBody>
			</InspectorControls>
			
			<div {...blockProps}>
				<div className="wp-block-wordpress-search-search-results-post-metadata-display">
					<div className="post-metadata preview">
						<h4 className="metadata-title">Post Metadata (Preview)</h4>
						<div className="metadata-list">
							<div className="metadata-item">
								<span className="metadata-key">
									Custom Field 1:
								</span>
								<span className="metadata-value">Sample Value</span>
							</div>
							<div className="metadata-item">
								<span className="metadata-key">
									Custom Field 2:
								</span>
								<span className="metadata-value">
									Another Value
								</span>
							</div>
							<div className="metadata-item">
								<span className="metadata-key">Category:</span>
								<span className="metadata-value">
									Example Category
								</span>
							</div>
						</div>
						<p className="editor-note">
							<em>
								This block will dynamically display all metadata for
								each post in the query loop on the frontend.
							</em>
						</p>
					</div>
				</div>
			</div>
		</>
	);
}
