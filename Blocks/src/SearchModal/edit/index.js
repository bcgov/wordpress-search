/**
 * WordPress dependencies
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RangeControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.scss';

/**
 * Search Modal Block Editor Component
 *
 * Renders the editor interface for the Search Modal block.
 * This component provides a container that can hold other blocks
 * and shows them in a modal interface on the frontend.
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update block attributes
 * @return {JSX.Element} The editor interface for the block.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { buttonText, buttonStyle, mobileBreakpoint } = attributes;

	// Get the block props which include the necessary editor attributes and classes
	const blockProps = useBlockProps( {
		className: 'wp-block-wordpress-search-search-modal',
	} );

	/**
	 * Template for InnerBlocks
	 * No default template - starts completely empty
	 */
	const INNER_BLOCKS_TEMPLATE = [];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Modal Settings', 'wordpress-search' ) }>
					<TextControl
						label={ __( 'Button Text', 'wordpress-search' ) }
						value={ buttonText }
						onChange={ ( value ) =>
							setAttributes( { buttonText: value } )
						}
						help={ __(
							'Text displayed on the button that opens the modal',
							'wordpress-search'
						) }
					/>

					<SelectControl
						label={ __( 'Button Style', 'wordpress-search' ) }
						value={ buttonStyle }
						onChange={ ( value ) =>
							setAttributes( { buttonStyle: value } )
						}
						options={ [
							{
								label: __( 'Primary', 'wordpress-search' ),
								value: 'primary',
							},
							{
								label: __( 'Secondary', 'wordpress-search' ),
								value: 'secondary',
							},
							{
								label: __( 'Outline', 'wordpress-search' ),
								value: 'outline',
							},
							{
								label: __( 'Link', 'wordpress-search' ),
								value: 'link',
							},
						] }
						help={ __(
							'Visual style of the trigger button',
							'wordpress-search'
						) }
					/>

					<RangeControl
						label={ __(
							'Mobile Breakpoint (px)',
							'wordpress-search'
						) }
						value={ mobileBreakpoint }
						onChange={ ( value ) =>
							setAttributes( { mobileBreakpoint: value } )
						}
						min={ 320 }
						max={ 1200 }
						step={ 10 }
						help={ __(
							'Screen width below which mobile behavior applies (button shows, content hidden)',
							'wordpress-search'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="dswp-search-modal__container">
					{ /* Content Area - Clean Group-like appearance */ }
					<div className="dswp-search-modal__content-preview">
						<div
							{ ...useInnerBlocksProps(
								{ className: 'dswp-search-modal__body' },
								{
									template: INNER_BLOCKS_TEMPLATE,
									templateLock: false,
									renderAppender:
										InnerBlocks.ButtonBlockAppender,
								}
							) }
						/>
					</div>
				</div>
			</div>
		</>
	);
}
