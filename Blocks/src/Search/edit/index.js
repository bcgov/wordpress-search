/**
 * WordPress Block Editor Dependencies
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Internal Style Dependencies
 * Editor-specific styles for the search-bar block
 */
import '../editor.scss';

/**
 * Search Block Edit Component
 *
 * Renders the search-bar block interface in the WordPress block editor.
 * Fill vs outline follows core Button: block.json `styles` → `is-style-*` on the block wrapper.
 *
 * @return {import('react').ReactElement} The editor interface for the search-bar block
 */
export default function Edit() {
	const blockProps = useBlockProps();
	const blockClassName = blockProps.className ?? '';
	const isOutline = /\bis-style-outline\b/.test( blockClassName );
	const buttonModifierClass = isOutline
		? 'dswp-search-bar__button--outline is-style-outline'
		: 'dswp-search-bar__button--fill';

	return (
		<div { ...blockProps }>
			<div className="dswp-search-bar__container">
				<form
					role="search"
					method="get"
					className="dswp-search-bar__form"
				>
					<div className="dswp-search-bar__input-container">
						<div className="dswp-search-bar__input-wrapper">
							<div className="dswp-search-bar__search-icon">
								<svg
									xmlns="http://www.w3.org/2000/svg"
									width="24"
									height="24"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									strokeWidth="2"
									strokeLinecap="round"
									strokeLinejoin="round"
								>
									<circle cx="11" cy="11" r="8"></circle>
									<line
										x1="21"
										y1="21"
										x2="16.65"
										y2="16.65"
									></line>
								</svg>
							</div>
							<input
								type="search"
								name="s"
								placeholder="Search term"
								className="dswp-search-bar__input"
								disabled
							/>
							<button
								type="button"
								className="dswp-search-bar__clear-button"
								aria-label={ __(
									'Clear search',
									'wordpress-search'
								) }
							>
								<svg
									xmlns="http://www.w3.org/2000/svg"
									width="24"
									height="24"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									strokeWidth="2"
									strokeLinecap="round"
									strokeLinejoin="round"
								>
									<line x1="18" y1="6" x2="6" y2="18"></line>
									<line x1="6" y1="6" x2="18" y2="18"></line>
								</svg>
							</button>
						</div>
						<button
							type="submit"
							className={ `dswp-search-bar__button wp-element-button wp-block-button__link ${ buttonModifierClass }` }
							disabled
						>
							{ __( 'Search', 'wordpress-search' ) }
						</button>
					</div>
				</form>
			</div>
		</div>
	);
}
