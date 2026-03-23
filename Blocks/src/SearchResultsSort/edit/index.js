/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody, RadioControl, SelectControl } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Search Results Sort block
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {import('react').ReactElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { selectedMetaField, sortOrder } = attributes;
	const [availableMetaFields, setAvailableMetaFields] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	// Function to check if a field should be excluded from sorting options
	const shouldExcludeField = useCallback((field) => {
		// Get the meta key (field name without post type prefix)
		let metaKey = field.metaKey || field.value;
		if (metaKey.includes(':')) {
			const parts = metaKey.split(':');
			metaKey = parts[parts.length - 1];
		}

		// Convert to lowercase for case-insensitive matching
		const metaKeyLower = metaKey.toLowerCase();

		// Fields to exclude
		const excludedPatterns = [
			'file_name',
			'file_size',
			'file_type',
			'file_url',
			'file_versions',
			'file_id',
			'document_file_name',
			'document_file_size',
			'document_file_type',
			'document_file_url',
			'document_file_versions',
			'document_file_id',
		];

		// Check if the meta key matches any excluded pattern
		return excludedPatterns.some((pattern) =>
			metaKeyLower.includes(pattern.toLowerCase())
		);
	}, []);

	// Function to format field labels for display
	const formatFieldLabel = useCallback((fieldValue) => {
		// Extract just the field name (remove post type prefix)
		let fieldName = fieldValue;
		if (fieldValue.includes(':')) {
			const parts = fieldValue.split(':');
			fieldName = parts[parts.length - 1];
		}

		// Convert underscores to spaces and title case
		let formatted = fieldName.replace(/_/g, ' ');
		formatted = formatted.replace(/\b\w/g, (l) => l.toUpperCase());

		// Handle common field name patterns
		const replacements = {
			'Sort Relevance': 'Sort Relevance',
			'Document File Name': 'File Name',
			'Document File Url': 'File URL',
			'Document File Size': 'File Size',
			'Document File Type': 'File Type',
			'Post Date': 'Publication Date',
			'Page Order': 'Page Order',
			This2: 'This 2',
		};

		if (replacements[formatted]) {
			return replacements[formatted];
		}

		return formatted;
	}, []);

	const getSortOrderLabel = ( order ) => {
		const labels = {
			newest: __( 'Newest', 'wordpress-search' ),
			oldest: __( 'Oldest', 'wordpress-search' ),
			asc: __( 'Asc', 'wordpress-search' ),
			desc: __( 'Desc', 'wordpress-search' ),
		};
		return labels[ order ] ?? __( 'Desc', 'wordpress-search' );
	};

	// Fetch available metadata fields - this runs automatically
	const fetchMetaFields = useCallback(async () => {
		setIsLoading(true);
		try {
			// Fetch data - server handles caching efficiently
			const metaFieldsResponse = await apiFetch({
				path: '/wordpress-search/v1/meta-fields',
				method: 'GET',
			});

			if (metaFieldsResponse && metaFieldsResponse.length > 0) {
				// Filter out excluded fields
				const filteredFields = metaFieldsResponse.filter(
					(field) => !shouldExcludeField(field)
				);
				setAvailableMetaFields(filteredFields);
			} else {
				setAvailableMetaFields([]);
			}
		} catch (error) {
			setAvailableMetaFields([]);
		} finally {
			setIsLoading(false);
		}
	}, [shouldExcludeField]);

	// Auto-fetch when component mounts
	useEffect(() => {
		fetchMetaFields();
	}, [fetchMetaFields]);

	// Refresh when selected fields change (for when user makes changes)
	useEffect(() => {
		// Only fetch if we don't have any available fields yet
		if (availableMetaFields.length === 0) {
			fetchMetaFields();
		}
	}, [selectedMetaField, availableMetaFields.length, fetchMetaFields]);

	// Handle radio button change for metadata field
	const handleMetaFieldChange = (fieldValue) => {
		setAttributes({ selectedMetaField: fieldValue });
	};

	// Handle sort order change
	const handleSortOrderChange = (newSortOrder) => {
		setAttributes({ sortOrder: newSortOrder });
	};

	// Create options for the sort order selector
	const sortOrderOptions = [
		{ label: __('Ascending', 'wordpress-search'), value: 'asc' },
		{ label: __('Descending', 'wordpress-search'), value: 'desc' },
	];

	// Create options for metadata fields with radio buttons
	const metaFieldOptions = availableMetaFields.map((field) => ({
		label: formatFieldLabel(field.value),
		value: field.value,
	}));

	const previewSortOptions = [
		{
			label: __( 'Best match', 'wordpress-search' ),
			value: 'relevance',
		},
		{
			label: __( 'Alphabetical (A-Z)', 'wordpress-search' ),
			value: 'title_asc',
		},
		{
			label: __( 'Alphabetical (Z-A)', 'wordpress-search' ),
			value: 'title_desc',
		},
	];

	if ( selectedMetaField ) {
		previewSortOptions.push( {
			label: `${ formatFieldLabel(
				selectedMetaField
			) } (${ getSortOrderLabel( sortOrder ) })`,
			value: 'meta_sort',
		} );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Sort Configuration', 'wordpress-search')}
					initialOpen={true}
				>
					<p className="components-base-control__help">
						{__(
							'Configure the default sorting behavior for this block.',
							'wordpress-search'
						)}
					</p>

					{!isLoading && availableMetaFields.length === 0 && (
						<div
							style={ {
								padding: '0.75rem',
								background: '#f0f0f0',
								borderRadius: '4px',
								marginBottom: '0.5rem',
								textAlign: 'center',
							}}
						>
							<p
								style={{
									margin: 0,
									fontSize: '14px',
									color: '#666',
								}}
							>
								{__(
									'No metadata fields found. Check that your posts have custom metadata.',
									'wordpress-search'
								)}
							</p>
						</div>
					)}

					{isLoading && (
						<p>
							{__(
								'Loading available fields…',
								'wordpress-search'
							)}
						</p>
					)}

					{!isLoading && availableMetaFields.length > 0 && (
						<>
							<RadioControl
								label={__(
									'Default Metadata Field for Sorting',
									'wordpress-search'
								)}
								help={__(
									'Select which metadata field should be used as the default sort option. Leave empty to show only title sorting options.',
									'wordpress-search'
								)}
								selected={selectedMetaField}
								options={[
									{
										label: __(
											'None (Title sorting only)',
											'wordpress-search'
										),
										value: '',
									},
									...metaFieldOptions,
								]}
								onChange={handleMetaFieldChange}
							/>

							{selectedMetaField && (
								<SelectControl
									label={__(
										'Default Sort Order for Metadata',
										'wordpress-search'
									)}
									help={__(
										'Choose the default sort order when the metadata field is selected.',
										'wordpress-search'
									)}
									value={sortOrder}
									options={sortOrderOptions}
									onChange={handleSortOrderChange}
								/>
							)}
						</>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...useBlockProps()}>
				<div className="wp-block-wordpress-search-searchresultssort">
					<div className="search-results-sort">
						<div className="search-results-sort__accordion">
							<button
								type="button"
								className="search-results-sort__header"
								aria-expanded="true"
								onClick={ ( event ) => event.preventDefault() }
							>
								<span className="search-results-sort__title">
									{ __( 'Sort by', 'wordpress-search' ) }
								</span>
								<span
									className="search-results-sort__toggle"
									aria-hidden="true"
								></span>
							</button>
							<div className="search-results-sort__content">
								<div className="search-results-sort__options">
									{ previewSortOptions.map(
										( option, index ) => {
											const optionId = `preview-sort-option-${ index }`;
											return (
												<label
													key={ option.value }
													htmlFor={ optionId }
													className="search-results-sort__option"
												>
													<input
														id={ optionId }
														type="radio"
														name="preview-sort-options"
														className="search-results-sort__option-input"
														checked={ index === 0 }
														readOnly
													/>
													<span className="search-results-sort__option-label">
														{ option.label }
													</span>
												</label>
											);
										}
									) }
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
