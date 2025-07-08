/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { SelectControl } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';

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

	return (
		<div {...useBlockProps()}>
			<SelectControl
				label={__('Select Taxonomy', 'wordpress-search')}
				value={selectedTaxonomy}
				options={taxonomyOptions}
				onChange={(value) => {
					setAttributes({ selectedTaxonomy: value });
				}}
			/>
		</div>
	);
}
