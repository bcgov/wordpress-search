/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { SelectControl, PanelBody } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

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
	const { selectedTaxonomy } = attributes;

	const { taxonomies } = useSelect((select) => {
		const { getTaxonomies } = select('core');
		const allTaxonomies =
			getTaxonomies({ per_page: -1, context: 'view' }) || [];
		return {
			taxonomies: allTaxonomies,
		};
	}, []);

	// Format taxonomies for the select control
	const taxonomyOptions = [
		{ label: __('Select a taxonomy…', 'wordpress-search'), value: '' },
		...(taxonomies || [])
			.filter((tax) => {
				if (!tax) {
					return false;
				}
				return true;
			})
			.map((taxonomy) => {
				if (!taxonomy.types || !taxonomy.types[0]) {
					return null;
				}

				// Get a nice label from the taxonomy object
				const label =
					taxonomy.labels?.singular_name ||
					taxonomy.name ||
					__('Unknown', 'wordpress-search');

				// Use the actual taxonomy name without any prefix manipulation
				const value = `${taxonomy.types[0]}:${taxonomy.name}`;

				return {
					label: label.charAt(0).toUpperCase() + label.slice(1), // Capitalize first letter
					value,
				};
			})
			.filter(Boolean), // Remove any null entries from map
	];

	// Get the display name for the selected taxonomy
	const getSelectedTaxonomyLabel = () => {
		if (!selectedTaxonomy) {
			return __('No taxonomy selected', 'wordpress-search');
		}
		
		const selectedOption = taxonomyOptions.find(option => option.value === selectedTaxonomy);
		return selectedOption ? selectedOption.label : __('Unknown taxonomy', 'wordpress-search');
	};

	return (
		<>
			<InspectorControls>
				<PanelBody 
					title={__('Taxonomy Filter Settings', 'wordpress-search')} 
					initialOpen={true}
				>
					<SelectControl
						label={__('Select Taxonomy', 'wordpress-search')}
						value={selectedTaxonomy}
						options={taxonomyOptions}
						onChange={(value) => {
							setAttributes({ selectedTaxonomy: value });
						}}
						help={__('Choose which taxonomy to use for filtering search results.', 'wordpress-search')}
					/>
				</PanelBody>
			</InspectorControls>
			
			<div {...useBlockProps()}>
				<div className="taxonomy-filter-preview">
					<div className="taxonomy-filter-preview__header">
						<h4>{__('Taxonomy Filter', 'wordpress-search')}</h4>
						<p className="taxonomy-filter-preview__description">
							{selectedTaxonomy 
								? __('Selected: ', 'wordpress-search') + getSelectedTaxonomyLabel()
								: __('Configure taxonomy in the block settings →', 'wordpress-search')
							}
						</p>
					</div>
					{selectedTaxonomy && (
						<div className="taxonomy-filter-preview__content">
							<div className="taxonomy-filter-preview__placeholder">
								<p>{__('Filter options will appear here on the frontend', 'wordpress-search')}</p>
							</div>
						</div>
					)}
				</div>
			</div>
		</>
	);
}
