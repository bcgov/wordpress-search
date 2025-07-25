/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Search Active Filters block
 *
 * @return {JSX.Element} Element to render.
 */
export default function Edit() {
	return (
		<>
			<div {...useBlockProps()}>
				<div className="search-active-filters-preview">
					<div className="search-active-filters-preview__header">
						<h4>
							{__('Search Active Filters', 'wordpress-search')}
						</h4>
						<p className="search-active-filters-preview__description">
							{__(
								'This block will display active search filters as removable chips on the frontend.',
								'wordpress-search'
							)}
						</p>
					</div>
					<div className="search-active-filters-preview__content">
						<div className="search-active-filters-preview__count">
							<span>
								{__('2 filters applied', 'wordpress-search')}
							</span>
							<button className="search-active-filters-preview__clear-all">
								{__('Clear all', 'wordpress-search')}
							</button>
						</div>
						<div className="search-active-filters-preview__chips">
							<div className="search-active-filters-preview__chip">
								<span>{__('Noise', 'wordpress-search')}</span>
								<button className="search-active-filters-preview__chip-remove">
									×
								</button>
							</div>
							<div className="search-active-filters-preview__chip">
								<span>
									{__('Chicken barn', 'wordpress-search')}
								</span>
								<button className="search-active-filters-preview__chip-remove">
									×
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
