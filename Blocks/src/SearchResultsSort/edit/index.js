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
 * @return {JSX.Element} Element to render.
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
							style={{
								padding: '12px',
								background: '#f0f0f0',
								borderRadius: '4px',
								marginBottom: '8px',
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
						<div className="search-results-sort__controls">
							<div className="search-results-sort__field-group">
								<svg
									className="search-results-sort__icon"
									width="24"
									height="24"
									viewBox="0 0 24 24"
									fill="none"
									xmlns="http://www.w3.org/2000/svg"
									aria-hidden="true"
								>
									{/* Arrow line */}
									<line
										x1="6"
										y1="4"
										x2="6"
										y2="20"
										stroke="currentColor"
										strokeWidth="2"
									/>
									{/* Arrow head */}
									<polyline
										points="3,17 6,20 9,17"
										fill="none"
										stroke="currentColor"
										strokeWidth="2"
									/>

									{/* Horizontal bars (representing sort levels) */}
									<line
										x1="12"
										y1="6"
										x2="20"
										y2="6"
										stroke="currentColor"
										strokeWidth="2"
									/>
									<line
										x1="12"
										y1="10"
										x2="18"
										y2="10"
										stroke="currentColor"
										strokeWidth="2"
									/>
									<line
										x1="12"
										y1="14"
										x2="16"
										y2="14"
										stroke="currentColor"
										strokeWidth="2"
									/>
								</svg>

								<label
									className="search-results-sort__label"
									htmlFor="preview-sort-select"
								>
									{__('Sort by:', 'wordpress-search')}
								</label>

								<select
									id="preview-sort-select"
									className="search-results-sort__sort-select"
									disabled
									style={{ opacity: 0.7 }}
								>
									<option>
										{__(
											'Title (Alphabetical)',
											'wordpress-search'
										)}
									</option>
									<option>
										{__(
											'Title (Reverse Alphabetical)',
											'wordpress-search'
										)}
									</option>
									{selectedMetaField && (
										<>
											<option
												selected={sortOrder === 'asc'}
											>
												{`${formatFieldLabel(
													selectedMetaField
												)} (${__(
													'Ascending',
													'wordpress-search'
												)})`}
											</option>
											<option
												selected={sortOrder === 'desc'}
											>
												{`${formatFieldLabel(
													selectedMetaField
												)} (${__(
													'Descending',
													'wordpress-search'
												)})`}
											</option>
										</>
									)}
								</select>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
