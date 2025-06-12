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

// Special handling for document post type metadata fields
const DOCUMENT_METADATA_FIELDS = [
    'document_file_id',
    'document_file_name',
    'document_file_size',
    'document_file_type',
    'document_file_url'
];

export default function Edit({ attributes, setAttributes }) {
    const { selectedMetadata } = attributes;
    const [groupedOptions, setGroupedOptions] = useState([]);
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

            const options = [];
            
            // For each post type, fetch its metadata
            for (const postType of postTypes) {
                try {
                    let metaKeys = [];
                    
                    if (postType.slug === 'document') {
                        // For documents, use the predefined metadata fields
                        metaKeys = DOCUMENT_METADATA_FIELDS;
                        
                        options.push({
                            label: postType.labels.singular_name,
                            options: metaKeys.map(metaKey => ({
                                label: metaKey,
                                value: `${postType.slug}:${metaKey}`
                            }))
                        });
                    } else {
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
                            metaKeys = Object.keys(samplePost.metadata || samplePost.meta || {});

                            if (metaKeys.length > 0) {
                                options.push({
                                    label: postType.labels.singular_name,
                                    options: metaKeys.map(metaKey => ({
                                        label: metaKey,
                                        value: `${postType.slug}:${metaKey}`
                                    }))
                                });
                            }
                        }
                    }
                } catch (error) {
                    console.error(`Error fetching metadata for ${postType.slug}:`, error);
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
