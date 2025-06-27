/**
 * WordPress dependencies
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { PanelBody, CheckboxControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../editor.scss';

/**
 * Search Post Type Filter Block Editor Component
 *
 * Renders the editor interface for the Search Post Type Filter block.
 * This component displays a preview of how the post type filter buttons
 * will appear on the frontend, using selected post types from the block settings.
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update block attributes
 * @return {JSX.Element} The editor interface for the block.
 */
export default function Edit({ attributes, setAttributes }) {
	const { selectedPostTypes } = attributes;

	// Get the block props which include the necessary editor attributes and classes
	const blockProps = useBlockProps();

	/**
	 * Fetch available post types from WordPress core data
	 *
	 * Uses the WordPress data API to get all registered post types
	 * and filters them to include all post types except for WordPress internal ones.
	 * This ensures custom post types like "document" are always included.
	 */
	const postTypes = useSelect((select) => {
		const types = select('core').getPostTypes({ per_page: -1 });

		// List of WordPress internal post types to exclude from the filter
		const excludedPostTypes = [
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_navigation',
			'wp_font_face',
			'wp_font_family',
			'wp_global_styles',
		];

		return (
			types?.filter((type) => {
				// Exclude only the WordPress internal post types
				if (excludedPostTypes.includes(type.slug)) {
					return false;
				}

				// Include all other post types (this will include custom post types like "document")
				// We're being very inclusive here since this is a search plugin
				return true;
			}) || []
		);
	}, []);

	/**
	 * Handle post type selection/deselection
	 *
	 * @param {string}  postTypeSlug - The slug of the post type to toggle
	 * @param {boolean} isChecked    - Whether the checkbox is checked
	 */
	const handlePostTypeToggle = (postTypeSlug, isChecked) => {
		let updatedPostTypes;

		if (isChecked) {
			// Add post type if it's not already selected
			updatedPostTypes = [...selectedPostTypes, postTypeSlug];
		} else {
			// Remove post type from selection
			updatedPostTypes = selectedPostTypes.filter(
				(slug) => slug !== postTypeSlug
			);
		}

		setAttributes({ selectedPostTypes: updatedPostTypes });
	};

	/**
	 * Get filtered post types based on selection
	 * If no post types are selected, show all available post types
	 */
	const getDisplayedPostTypes = () => {
		if (selectedPostTypes.length === 0) {
			return postTypes;
		}

		return postTypes.filter((postType) =>
			selectedPostTypes.includes(postType.slug)
		);
	};

	const displayedPostTypes = getDisplayedPostTypes();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Post Type Filter Settings', 'wordpress-search')}
					initialOpen={true}
				>
					<p>
						{__(
							'Select which post types to show in the filter. If none are selected, all post types will be shown.',
							'wordpress-search'
						)}
					</p>

					{postTypes.length > 0 ? (
						postTypes.map((postType) => (
							<CheckboxControl
								key={postType.slug}
								label={postType.name}
								checked={selectedPostTypes.includes(
									postType.slug
								)}
								onChange={(isChecked) =>
									handlePostTypeToggle(
										postType.slug,
										isChecked
									)
								}
							/>
						))
					) : (
						<Placeholder>
							{__('Loading post types…', 'wordpress-search')}
						</Placeholder>
					)}

					{selectedPostTypes.length > 0 && (
						<p
							style={{
								marginTop: '16px',
								fontSize: '12px',
								color: '#666',
							}}
						>
							{__('Selected:', 'wordpress-search')}{' '}
							{selectedPostTypes.length}{' '}
							{__('post types', 'wordpress-search')}
						</p>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="dswp-search-post-type-filter__container dswp-search-post-type-filter__container--editor">
					{displayedPostTypes.length > 0 ? (
						displayedPostTypes.map((postType) => (
							<button
								key={postType.slug}
								className="dswp-search-post-type-filter__button"
								onClick={(e) => e.preventDefault()}
								disabled
							>
								{postType.name}
							</button>
						))
					) : (
						<div
							style={{
								padding: '16px',
								textAlign: 'center',
								color: '#666',
							}}
						>
							{selectedPostTypes.length === 0
								? __(
										'Select post types in the block settings sidebar →',
										'wordpress-search'
								  )
								: __(
										'No matching post types found',
										'wordpress-search'
								  )}
						</div>
					)}
				</div>
			</div>
		</>
	);
}
