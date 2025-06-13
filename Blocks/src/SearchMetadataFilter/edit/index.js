/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { SelectControl, Placeholder, Spinner, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

// List of internal WordPress post types to exclude
const EXCLUDED_POST_TYPES = [
    'attachment',
    'wp_block',
    'wp_template',
    'wp_template_part',
    'wp_navigation',
    'wp_font_face',
    'wp_font_family',
    'menu_item',
    'wp_global_styles',
    'revision',
    'customize_changeset',
    'nav_menu_item',
    'custom_css',
    'oembed_cache'
];

// List of metadata fields to exclude
const EXCLUDED_METADATA_FIELDS = [
    'document_file_id',
    'document_file_name',
    'document_file_size',
    'document_file_type',
    'document_file_url',
    'footnotes',
    'show_inpage_nav'
];

export default function Edit({ attributes, setAttributes }) {
    const { selectedMetadata } = attributes;
    const [metadataOptions, setMetadataOptions] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    // Fetch post types with expanded query
    const { postTypes } = useSelect((select) => {
        const types = select(coreStore).getPostTypes({
            per_page: -1,
        });
        
        const filteredTypes = types?.filter(type => {
            const isExcluded = EXCLUDED_POST_TYPES.includes(type.slug);
            const hasRestSupport = Boolean(type.rest_base) && Boolean(type.rest_namespace);
            const hasCustomFields = type.supports?.['custom-fields'] === true;
            
            return !isExcluded && hasRestSupport && hasCustomFields;
        });
        
        return { postTypes: filteredTypes };
    }, []);

    useEffect(() => {
        async function fetchMetadataForPostTypes() {
            if (!postTypes) {
                return;
            }

            const options = new Set(); // Use Set to avoid duplicate metadata fields
            
            // Create promises for all post type API calls
            const apiPromises = postTypes.map(async (postType) => {
                try {
                    // For other post types, use the standard REST API
                    const apiPath = postType.rest_namespace === 'wp/v2' 
                        ? `/wp/v2/${postType.rest_base}`
                        : `/${postType.rest_namespace}/${postType.rest_base}`;
                    
                    const queryParams = {
                        context: 'edit',
                        per_page: 1,
                        orderby: 'date',
                        order: 'desc'
                    };
                    
                    const fullPath = addQueryArgs(apiPath, queryParams);

                    const posts = await apiFetch({
                        path: fullPath,
                        parse: true
                    });

                    if (Array.isArray(posts) && posts.length > 0) {
                        const samplePost = posts[0];
                        const metaKeys = Object.keys(samplePost.metadata || samplePost.meta || {});

                        return metaKeys
                            .filter(metaKey => !EXCLUDED_METADATA_FIELDS.includes(metaKey))
                            .map(metaKey => ({
                                label: metaKey,
                                value: `${postType.slug}:${metaKey}`
                            }));
                    }
                    
                    return [];
                } catch (error) {
                    console.error(`Error fetching metadata for ${postType.slug}:`, error);
                    return []; // Return empty array on error to continue processing other post types
                }
            });

            // Execute all API calls in parallel
            try {
                const results = await Promise.all(apiPromises);
                
                // Flatten results and add to options set
                results.flat().forEach(option => {
                    options.add(option);
                });
            } catch (error) {
                console.error('Error fetching metadata for post types:', error);
            }

            // Convert Set to Array and sort alphabetically by label
            const sortedOptions = Array.from(options).sort((a, b) => a.label.localeCompare(b.label));
            setMetadataOptions(sortedOptions);
            setIsLoading(false);
        }

        fetchMetadataForPostTypes();
    }, [postTypes]);

    // Get the current selected metadata label for display
    const getSelectedMetadataLabel = () => {
        if (!selectedMetadata) return '';
        
        const found = metadataOptions.find(option => option.value === selectedMetadata);
        return found ? found.label : selectedMetadata;
    };

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Metadata Filter Settings', 'wordpress-search')}
                    initialOpen={true}
                >
                    {isLoading ? (
                        <Placeholder>
                            <Spinner />
                            {__('Loading metadata fields...', 'wordpress-search')}
                        </Placeholder>
                    ) : metadataOptions.length > 0 ? (
                        <SelectControl
                            label={__('Select Metadata Field', 'wordpress-search')}
                            value={selectedMetadata}
                            options={[
                                { label: __('Select a field...', 'wordpress-search'), value: '' },
                                ...metadataOptions
                            ]}
                            onChange={(value) => setAttributes({ selectedMetadata: value })}
                        />
                    ) : (
                        <p>
                            {__('No metadata fields found. Make sure your post types:', 'wordpress-search')}
                            <ul>
                                <li>{__('Have custom fields enabled', 'wordpress-search')}</li>
                                <li>{__('Have REST API support', 'wordpress-search')}</li>
                                <li>{__('Have at least one post with meta values', 'wordpress-search')}</li>
                                <li>{__('Have meta fields registered with show_in_rest enabled', 'wordpress-search')}</li>
                            </ul>
                        </p>
                    )}
                </PanelBody>
            </InspectorControls>
            
            <div className="wp-block-wordpress-search-metadata-filter">
                {selectedMetadata ? (
                    <div className="metadata-filter-preview">
                        {__('Metadata Filter:', 'wordpress-search')} <strong>{getSelectedMetadataLabel()}</strong>
                    </div>
                ) : (
                    <div className="metadata-filter-placeholder">
                        {__('Select a metadata field in the block settings sidebar →', 'wordpress-search')}
                    </div>
                )}
            </div>
        </>
    );
}
