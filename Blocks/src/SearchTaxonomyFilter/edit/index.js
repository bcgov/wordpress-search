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
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
    const { selectedTaxonomy } = attributes;

    const { taxonomies } = useSelect((select) => {
        const { getTaxonomies } = select('core');
        const allTaxonomies = getTaxonomies({ per_page: -1, context: 'view' }) || [];
        console.log('All taxonomies:', allTaxonomies);
        return {
            taxonomies: allTaxonomies,
        };
    }, []);

    // Format taxonomies for the select control
    const taxonomyOptions = [
        { label: __('Select a taxonomy...', 'wordpress-search'), value: '' },
        ...(taxonomies || [])
            .filter(tax => {
                if (!tax) {
                    console.log('Found null/undefined taxonomy');
                    return false;
                }
                // Log each taxonomy for debugging
                console.log('Processing taxonomy:', {
                    name: tax.name,
                    slug: tax.slug,
                    types: tax.types,
                    labels: tax.labels
                });
                return true;
            })
            .map((taxonomy) => {
                if (!taxonomy.types || !taxonomy.types[0]) {
                    console.log('Taxonomy missing types:', taxonomy);
                    return null;
                }

                // Get a nice label from the taxonomy object
                const label = taxonomy.labels?.singular_name || taxonomy.name || __('Unknown', 'wordpress-search');

                // Use the actual taxonomy name without any prefix manipulation
                const value = `${taxonomy.types[0]}:${taxonomy.name}`;

                // Log the mapping for debugging
                console.log('Creating taxonomy option:', {
                    taxonomyName: taxonomy.name,
                    label,
                    value
                });

                return {
                    label: label.charAt(0).toUpperCase() + label.slice(1), // Capitalize first letter
                    value,
                };
            })
            .filter(Boolean), // Remove any null entries from map
    ];

    // Log the final options for debugging
    console.log('Taxonomy options:', taxonomyOptions);
    console.log('Current selected taxonomy:', selectedTaxonomy);

    return (
        <div {...useBlockProps()}>
            <SelectControl
                label={__('Select Taxonomy', 'wordpress-search')}
                value={selectedTaxonomy}
                options={taxonomyOptions}
                onChange={(value) => {
                    console.log('Selected taxonomy value:', value);
                    setAttributes({ selectedTaxonomy: value });
                }}
            />
        </div>
    );
} 