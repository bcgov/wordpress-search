/**
 * WordPress dependencies
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
	PanelColorSettings,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ButtonGroup,
	Button,
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
 * @return {import('react').ReactElement} The editor interface for the block.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		buttonText,
		buttonStyle,
		buttonBackgroundColor,
		buttonTextColor,
		mobileBreakpoint,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-wordpress-search-search-modal',
	} );

	const INNER_BLOCKS_TEMPLATE = [];

	// Normalize legacy styles for class names, and only override via inline styles when user picked colors.
	const buttonPreviewStyle = {};
	let normalizedStyle = buttonStyle || 'primary';
	// Support legacy styles that were previously stored as fill/outline.
	if ( 'fill' === buttonStyle ) {
		normalizedStyle = 'primary';
	} else if ( 'outline' === buttonStyle ) {
		normalizedStyle = 'secondary';
	}

	if ( buttonBackgroundColor ) {
		buttonPreviewStyle.backgroundColor = buttonBackgroundColor;
	}
	if ( buttonTextColor ) {
		buttonPreviewStyle.color = buttonTextColor;
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Modal Settings', 'wordpress-search' ) }>
					<div className="dswp-search-modal__style-control">
						<p className="dswp-search-modal__style-label">
							{ __( 'Styles', 'wordpress-search' ) }
						</p>
						<ButtonGroup>
							<Button
								variant={
									'primary' === normalizedStyle
										? 'primary'
										: 'secondary'
								}
								onClick={ () =>
									setAttributes( { buttonStyle: 'primary' } )
								}
							>
								{ __( 'Primary', 'wordpress-search' ) }
							</Button>
							<Button
								variant={
									'secondary' === normalizedStyle
										? 'primary'
										: 'secondary'
								}
								onClick={ () =>
									setAttributes( {
										buttonStyle: 'secondary',
									} )
								}
							>
								{ __( 'Secondary', 'wordpress-search' ) }
							</Button>
						</ButtonGroup>
					</div>

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

				<PanelColorSettings
					title={ __( 'Color', 'wordpress-search' ) }
					colorSettings={ [
						{
							value: buttonTextColor,
							onChange: ( value ) =>
								setAttributes( {
									buttonTextColor: value || '',
								} ),
							label: __( 'Text', 'wordpress-search' ),
						},
						{
							value: buttonBackgroundColor,
							onChange: ( value ) =>
								setAttributes( {
									buttonBackgroundColor: value || '',
								} ),
							label: __( 'Background', 'wordpress-search' ),
						},
					] }
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="dswp-search-modal__container">
					<button
						type="button"
						className={ `dswp-search-modal__trigger dswp-search-modal__trigger--${ normalizedStyle }` }
						style={ buttonPreviewStyle }
						onClick={ ( e ) => e.preventDefault() }
					>
						<RichText
							tagName="span"
							value={ buttonText }
							onChange={ ( value ) =>
								setAttributes( { buttonText: value } )
							}
							placeholder={ __(
								'Add button text…',
								'wordpress-search'
							) }
							allowedFormats={ [] }
							multiline={ false }
							aria-label={ __(
								'Button text',
								'wordpress-search'
							) }
						/>
					</button>
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
