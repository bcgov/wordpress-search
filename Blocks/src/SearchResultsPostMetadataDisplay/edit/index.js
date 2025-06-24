/**
 * WordPress Block Editor Dependencies
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';
import '../editor.scss';

export default function Edit() {
	// Get the block props which include the necessary editor attributes and classes
	const blockProps = useBlockProps();

	return (
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
	);
}
