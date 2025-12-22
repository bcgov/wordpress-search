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
 * Edit component for the Search Result Count block
 *
 * @return {JSX.Element} Element to render.
 */
export default function Edit() {
	return (
		<>
			<div { ...useBlockProps() }>
				<div className="search-result-count-preview">
					<div className="search-result-count-preview__header">
						<h4>
							{ __( 'Search Result Count', 'wordpress-search' ) }
						</h4>
						<p className="search-result-count-preview__description">
							{ __(
								'This block will display the number of search results in real time on the frontend.',
								'wordpress-search'
							) }
						</p>
					</div>
					<div className="search-result-count-preview__content">
						<div className="search-result-count-preview__count">
							<span className="search-result-count-preview__number">
								42
							</span>
							<span className="search-result-count-preview__label">
								{ __( 'results found', 'wordpress-search' ) }
							</span>
						</div>
						<div className="search-result-count-preview__example">
							<p>
								{ __(
									'Example: "42 results found" or "No results found"',
									'wordpress-search'
								) }
							</p>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
