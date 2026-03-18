/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import metadata from './block.json';

/**
 * Register block type
 */
registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	// Handle migration from older single-select attribute to new multi-select array
	deprecated: [
		{
			attributes: {
				selectedTaxonomy: {
					type: 'string',
					default: '',
				},
			},
			save: () => null,
			migrate: (attributes) => {
				const { selectedTaxonomy } = attributes || {};
				return {
					selectedTaxonomies: selectedTaxonomy
						? [selectedTaxonomy]
						: [],
				};
			},
		},
	],
});
