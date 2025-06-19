/**
 * WordPress Block Editor Dependencies
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal Style Dependencies
 * Editor-specific styles for the search-bar block
 */
import '../editor.scss';

/**
 * Search Block Edit Component
 *
 * Renders the search-bar block interface in the WordPress block editor.
 * This is a static preview of how the search-bar block will appear on the frontend.
 * The form elements are intentionally disabled as they are for display purposes only.
 *
 * @return {JSX.Element} The editor interface for the search-bar block
 */
export default function Edit() {
	// Get the block props which include the necessary editor attributes and classes
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
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
								required
							/>
							<button
								type="button"
								className="dswp-search-bar__clear-button"
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
							className="dswp-search-bar__button dswp-search-bar__button--primary"
							disabled
						>
							Search
						</button>
					</div>
				</form>
			</div>
		</div>
	);
}
