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

export default function Edit({ attributes, setAttributes }) {
    const { selectedMetadata } = attributes;
    const [groupedOptions, setGroupedOptions] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    // Fetch post types
    const { postTypes } = useSelect((select) => {
        return {
            postTypes: select(coreStore).getPostTypes({ per_page: -1 }),
        };
    }, []);

    useEffect(() => {
        async function fetchMetadataForPostTypes() {
            if (!postTypes) return;

            const options = [];
            
            // For each post type, fetch its metadata
            for (const postType of postTypes) {
                // Skip if post type is not valid or doesn't have REST API support
                if (!postType?.slug || 
                    postType.slug === 'attachment' || 
                    postType.slug === 'wp_block' ||
                    postType.slug === 'wp_template' ||
                    postType.slug === 'wp_template_part' ||
                    postType.slug === 'wp_navigation' ||
                    postType.slug === 'wp_font_face' ||
                    postType.slug === 'menu_item' ||
                    postType.slug === 'wp_global_styles' ||  // Add wp_global_styles to excluded types
                    !postType.rest_base || 
                    !postType.rest_namespace) continue;

                try {
                    // Construct the correct REST API path
                    const apiPath = postType.rest_namespace === 'wp/v2' 
                        ? `/wp/v2/${postType.rest_base}`  // Standard WP post types
                        : `/${postType.rest_namespace}/${postType.rest_base}`; // Custom namespaced endpoints

                    // Use apiFetch to handle the REST API request
                    const posts = await apiFetch({
                        path: addQueryArgs(apiPath, {
                            context: 'edit',
                            per_page: 1
                        })
                    });
                    
                    if (Array.isArray(posts) && posts.length > 0) {
                        const samplePost = posts[0];
                        const metaKeys = Object.keys(samplePost.meta || {});

                        if (metaKeys.length > 0) {
                            // Create a group for this post type
                            options.push({
                                label: postType.labels.singular_name,
                                options: metaKeys.map(metaKey => ({
                                    label: metaKey,
                                    value: `${postType.slug}:${metaKey}`
                                }))
                            });
                        }
                    }
                } catch (error) {
                    console.error(`Error fetching metadata for ${postType.slug}:`, error);
                    // Only log the full error in development
                    if (process.env.NODE_ENV === 'development') {
                        console.debug('Full error:', error);
                    }
                }
            }

            setGroupedOptions(options);
            setIsLoading(false);
        }

        fetchMetadataForPostTypes();
    }, [postTypes]);

    // Get the current selected metadata label for display
    const getSelectedMetadataLabel = () => {
        if (!selectedMetadata) return '';
        
        for (const group of groupedOptions) {
            const found = group.options.find(option => option.value === selectedMetadata);
            if (found) {
                return `${group.label}: ${found.label}`;
            }
        }
        return selectedMetadata;
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
                    ) : groupedOptions.length > 0 ? (
                        <SelectControl
                            label={__('Select Metadata Field', 'wordpress-search')}
                            value={selectedMetadata}
                            options={[
                                { label: __('Select a field...', 'wordpress-search'), value: '' },
                                ...groupedOptions
                            ]}
                            onChange={(value) => setAttributes({ selectedMetadata: value })}
                        />
                    ) : (
                        <p>{__('No metadata fields found for any post types.', 'wordpress-search')}</p>
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
