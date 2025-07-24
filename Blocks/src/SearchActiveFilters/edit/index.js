/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl, PanelBody } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Search Active Filters block
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {JSX.Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { showCount, showClearAll } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody 
					title={__('Search Active Filters Settings', 'wordpress-search')} 
					initialOpen={true}
				>
					<ToggleControl
						label={__('Show filter count', 'wordpress-search')}
						checked={showCount}
						onChange={(value) => setAttributes({ showCount: value })}
						help={__('Display the number of applied filters.', 'wordpress-search')}
					/>
					<ToggleControl
						label={__('Show clear all button', 'wordpress-search')}
						checked={showClearAll}
						onChange={(value) => setAttributes({ showClearAll: value })}
						help={__('Display a "Clear all" button to remove all filters.', 'wordpress-search')}
					/>
				</PanelBody>
			</InspectorControls>
			
			<div {...useBlockProps()}>
									<div className="search-active-filters-preview">
						<div className="search-active-filters-preview__header">
							<h4>{__('Search Active Filters', 'wordpress-search')}</h4>
							<p className="search-active-filters-preview__description">
								{__('This block will display active search filters as removable chips on the frontend.', 'wordpress-search')}
							</p>
						</div>
					<div className="search-active-filters-preview__content">
						{showCount && (
							<div className="search-active-filters-preview__count">
								<span>{__('2 filters applied', 'wordpress-search')}</span>
								{showClearAll && (
									<button className="search-active-filters-preview__clear-all">
										{__('Clear all', 'wordpress-search')}
									</button>
								)}
							</div>
						)}
						<div className="search-active-filters-preview__chips">
							<div className="search-active-filters-preview__chip">
								<span>{__('Noise', 'wordpress-search')}</span>
								<button className="search-active-filters-preview__chip-remove">×</button>
							</div>
							<div className="search-active-filters-preview__chip">
								<span>{__('Chicken barn', 'wordpress-search')}</span>
								<button className="search-active-filters-preview__chip-remove">×</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	);
} 