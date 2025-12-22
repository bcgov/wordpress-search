/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { CheckboxControl, PanelBody } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useEffect, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Search Taxonomy Filter block
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {JSX.Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	// Handle migration from old selectedTaxonomy to new selectedTaxonomies
	const { selectedTaxonomy, selectedTaxonomies } = attributes;

	// Use selectedTaxonomies if it exists (even if empty array), otherwise convert old selectedTaxonomy to array
	let currentSelectedTaxonomies;
	if (selectedTaxonomies !== undefined) {
		currentSelectedTaxonomies = selectedTaxonomies;
	} else if (selectedTaxonomy) {
		currentSelectedTaxonomies = [selectedTaxonomy];
	} else {
		currentSelectedTaxonomies = [];
	}

	// Only migrate once when the component first loads and we have old data
	useEffect(() => {
		// Only migrate if we have an old selectedTaxonomy but no selectedTaxonomies
		// AND if we haven't already migrated (check if selectedTaxonomies is undefined, not empty array)
		if (selectedTaxonomy && selectedTaxonomies === undefined) {
			setAttributes({ selectedTaxonomies: [selectedTaxonomy] });
		}
	}, [selectedTaxonomy, selectedTaxonomies, setAttributes]);

	const { taxonomies } = useSelect((select) => {
		const { getTaxonomies } = select('core');
		const allTaxonomies =
			getTaxonomies({ per_page: -1, context: 'view' }) || [];

		return {
			taxonomies: allTaxonomies,
		};
	}, []);

	// ──────────────────────────────────────────────────────────────────────
	// Build a Map of post_type → Set of taxonomy names
	// ──────────────────────────────────────────────────────────────────────
	const postTypeTaxMap = useMemo(() => {
		const map = new Map();
		for (const t of taxonomies || []) {
			if (!t?.name) {
				continue;
			}
			const types = t.types || t.object_type || [];
			for (const pt of types) {
				if (!map.has(pt)) {
					map.set(pt, new Set());
				}
				map.get(pt).add(t.name.toLowerCase());
			}
		}
		return map;
	}, [taxonomies]);

	// Format taxonomies for the checkbox controls
	const taxonomyOptions = useMemo(() => {
		return (taxonomies || [])
			.filter((tax) => {
				if (!tax || !tax.name) {
					return false;
				}

				// Filter out default WordPress taxonomies (category, post_tag) when they're only
				// associated with the 'post' post type. This prevents confusion when custom post types
				// have their own category taxonomies (e.g., doc_category).
				const taxonomyTypes = tax.types || tax.object_type || [];
				const isDefaultCategory = tax.name === 'category';
				const isDefaultPostTag = tax.name === 'post_tag';
				const onlyAssociatedWithPost =
					taxonomyTypes.length === 1 && taxonomyTypes[0] === 'post';

				// Exclude default category/post_tag if they're only for the 'post' post type
				if (
					(isDefaultCategory || isDefaultPostTag) &&
					onlyAssociatedWithPost
				) {
					return false;
				}

				// Also exclude default category if it's associated with a custom post type that has
				// a custom taxonomy with a similar name (e.g., doc_category for document post type).
				// This prevents the default category from appearing when a custom one exists.
				if (isDefaultCategory && taxonomyTypes.length > 0) {
					// Check each post type this taxonomy is associated with
					for (const postType of taxonomyTypes) {
						if (postType !== 'post') {
							const set = postTypeTaxMap.get(postType);
							if (set) {
								const prefix = postType.toLowerCase() + '_';
								// Fast check: any taxonomy for this post type that starts with prefix OR contains "_category"
								for (const taxName of set) {
									if (
										taxName.startsWith(prefix) ||
										taxName.includes('_category')
									) {
										return false; // custom alternative exists → hide default category
									}
								}
							}
						}
					}
				}

				return true;
			})
			.map((taxonomy) => {
				// Handle taxonomies that might not have types properly set
				if (!taxonomy.types || taxonomy.types.length === 0) {
					// Try to get object_type as fallback
					const objectTypes = taxonomy.object_type || [];
					if (objectTypes.length === 0) {
						return null;
					}
					// Use the first object type as fallback
					taxonomy.types = objectTypes;
				}

				// Get a nice label from the taxonomy object
				const taxonomyLabel =
					taxonomy.labels?.singular_name ||
					taxonomy.name ||
					__('Unknown', 'wordpress-search');

				// Get the post type label for clarity
				const postType = taxonomy.types[0];
				const postTypeLabel =
					postType.charAt(0).toUpperCase() + postType.slice(1);

				// Combine post type and taxonomy name for clarity
				const label = `${postTypeLabel}: ${
					taxonomyLabel.charAt(0).toUpperCase() +
					taxonomyLabel.slice(1)
				}`;

				// Use the actual taxonomy name without any prefix manipulation
				const value = `${taxonomy.types[0]}:${taxonomy.name}`;

				return {
					label,
					value,
				};
			})
			.filter(Boolean); // Remove any null entries from map
	}, [taxonomies, postTypeTaxMap]);

	// Handle taxonomy selection/deselection
	const handleTaxonomyChange = (taxonomyValue, isChecked) => {
		const newSelectedTaxonomies = isChecked
			? [...currentSelectedTaxonomies, taxonomyValue]
			: currentSelectedTaxonomies.filter((tax) => tax !== taxonomyValue);

		setAttributes({ selectedTaxonomies: newSelectedTaxonomies });
	};

	// Get the display names for the selected taxonomies
	const getSelectedTaxonomiesLabels = () => {
		if (
			!currentSelectedTaxonomies ||
			currentSelectedTaxonomies.length === 0
		) {
			return __('No taxonomies selected', 'wordpress-search');
		}

		const selectedLabels = currentSelectedTaxonomies.map((selectedTax) => {
			const selectedOption = taxonomyOptions.find(
				(option) => option.value === selectedTax
			);
			return selectedOption
				? selectedOption.label
				: __('Unknown taxonomy', 'wordpress-search');
		});

		return selectedLabels.join(', ');
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Taxonomy Filter Settings', 'wordpress-search')}
					initialOpen={true}
				>
					<div className="taxonomy-filter-settings">
						<p className="taxonomy-filter-settings__description">
							{__(
								'Select one or more taxonomies to create filters for:',
								'wordpress-search'
							)}
						</p>
						{taxonomyOptions.length > 0 ? (
							taxonomyOptions.map((option) => (
								<CheckboxControl
									key={option.value}
									label={option.label}
									checked={currentSelectedTaxonomies.includes(
										option.value
									)}
									onChange={(isChecked) =>
										handleTaxonomyChange(
											option.value,
											isChecked
										)
									}
								/>
							))
						) : (
							<p className="taxonomy-filter-settings__loading">
								{__('Loading taxonomies…', 'wordpress-search')}
							</p>
						)}
					</div>
				</PanelBody>
			</InspectorControls>

			<div {...useBlockProps()}>
				<div className="taxonomy-filter-preview">
					<div className="taxonomy-filter-preview__header">
						<h4>{__('Taxonomy Filter', 'wordpress-search')}</h4>
						<p className="taxonomy-filter-preview__description">
							{currentSelectedTaxonomies &&
							currentSelectedTaxonomies.length > 0
								? __('Selected:', 'wordpress-search') +
								  ' ' +
								  getSelectedTaxonomiesLabels()
								: __(
										'Configure taxonomies in the block settings →',
										'wordpress-search'
								  )}
						</p>
					</div>
					{currentSelectedTaxonomies &&
						currentSelectedTaxonomies.length > 0 && (
							<div className="taxonomy-filter-preview__content">
								<div className="taxonomy-filter-preview__placeholder">
									<p>
										{__(
											'Filter options will appear here on the frontend',
											'wordpress-search'
										)}
									</p>
								</div>
							</div>
						)}
				</div>
			</div>
		</>
	);
}
