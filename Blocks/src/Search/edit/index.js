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
 * search-bar Block Edit Component
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
		<div { ...blockProps }>
			<div className="dswp-search-bar__container dswp-search-bar__container--editor">
				<div className="dswp-search-bar__preview-overlay">
					<form
						role="search-bar"
						method="get"
						className="dswp-search-bar__form"
					>
						<div className="dswp-search-bar__input-container">
							<input
								type="search-bar"
								name="s"
								placeholder="search-bar..."
								className="dswp-search-bar__input"
								disabled
								required
							/>
							<button
								type="submit"
								className="dswp-search-bar__button dswp-search-bar__button--primary dswp-search-bar__button--right"
								disabled
							>
								search-bar
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	);
}
