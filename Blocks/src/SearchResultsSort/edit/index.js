/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody, CheckboxControl } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

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
	const { selectedMetaFields } = attributes;
	const [availableMetaFields, setAvailableMetaFields] = useState([]);
	const [isLoading, setIsLoading] = useState(true);

	// Function to format field labels for display
	const formatFieldLabel = (fieldValue) => {
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
	};

	// Fetch available metadata fields - this runs automatically
	const fetchMetaFields = async () => {
		setIsLoading(true);
		try {
			// Fetch data - server handles caching efficiently
			const metaFieldsResponse = await apiFetch({
				path: '/wordpress-search/v1/meta-fields',
				method: 'GET',
			});

			if (metaFieldsResponse && metaFieldsResponse.length > 0) {
				setAvailableMetaFields(metaFieldsResponse);
			} else {
				setAvailableMetaFields([]);
			}
		} catch (error) {
			setAvailableMetaFields([]);
		} finally {
			setIsLoading(false);
		}
	};

	// Auto-fetch when component mounts
	useEffect(() => {
		fetchMetaFields();
	}, []);

	// Refresh when selected fields change (for when user makes changes)
	useEffect(() => {
		// Only fetch if we don't have any available fields yet
		if (availableMetaFields.length === 0) {
			fetchMetaFields();
		}
	}, [selectedMetaFields, availableMetaFields.length]);

	// Handle checkbox change
	const handleMetaFieldToggle = (fieldValue, isChecked) => {
		const newSelectedFields = isChecked
			? [...selectedMetaFields, fieldValue]
			: selectedMetaFields.filter((field) => field !== fieldValue);

		setAttributes({ selectedMetaFields: newSelectedFields });
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Available Sort Fields', 'wordpress-search')}
					initialOpen={true}
				>
					<p className="components-base-control__help">
						{__(
							'Select which metadata fields should be available for sorting on the frontend. Fields are automatically refreshed.',
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

					{!isLoading &&
						availableMetaFields.map((field) => (
							<CheckboxControl
								key={field.value}
								label={formatFieldLabel(field.value)}
								checked={selectedMetaFields.includes(
									field.value
								)}
								onChange={(isChecked) =>
									handleMetaFieldToggle(
										field.value,
										isChecked
									)
								}
							/>
						))}

					{selectedMetaFields.length === 0 && !isLoading && (
						<div className="components-notice components-notice--warning">
							<p>
								{__(
									'No fields selected. Users will not see any sort options.',
									'wordpress-search'
								)}
							</p>
						</div>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...useBlockProps()}>
				<div className="wp-block-wordpress-search-searchresultssort">
					<div className="search-results-sort">
						<div className="search-results-sort__controls">
							<div className="search-results-sort__field-group">
								<label
									className="search-results-sort__label"
									htmlFor="preview-field-select"
								>
									{__('Sort by field:', 'wordpress-search')}
								</label>
								<select
									id="preview-field-select"
									className="search-results-sort__field-select"
									disabled
									style={{ opacity: 0.7 }}
								>
									<option>
										{__(
											'Select a field to sort by…',
											'wordpress-search'
										)}
									</option>
									{selectedMetaFields.map((fieldValue) => (
										<option
											key={fieldValue}
											value={fieldValue}
										>
											{formatFieldLabel(fieldValue)}
										</option>
									))}
								</select>
							</div>

							{selectedMetaFields.length > 0 && (
								<div className="search-results-sort__order-group">
									<label
										className="search-results-sort__label"
										htmlFor="preview-order-select"
									>
										{__('Order:', 'wordpress-search')}
									</label>
									<select
										id="preview-order-select"
										className="search-results-sort__order-select"
										disabled
										style={{ opacity: 0.7 }}
									>
										<option value="off">
											{__(
												'Default (No sorting)',
												'wordpress-search'
											)}
										</option>
										<option value="desc">
											{__(
												'Newest first',
												'wordpress-search'
											)}
										</option>
										<option value="asc">
											{__(
												'Oldest first',
												'wordpress-search'
											)}
										</option>
									</select>
								</div>
							)}
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
