<?php
/**
 * Metadata Filter Handler Class
 *
 * Handles all metadata filtering functionality for the WordPress Search plugin.
 *
 * @package WordPressSearch
 * @since 1.0.0
 */

namespace Bcgov\WordpressSearch;

/**
 * Class MetadataFilter
 *
 * Handles metadata filtering for search results.
 */
class MetadataFilter {

    /**
     * Initialize the metadata filter functionality.
     */
    public function init(): void {
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'pre_get_posts', array( $this, 'handle_metadata_filtering' ) );
    }

    /**
     * Add metadata filter query variables to WordPress
     *
     * @param array $vars Array of query variables.
     * @return array Modified array of query variables.
     */
    public function add_query_vars( array $vars ): array {
        // Get all possible metadata fields from database to create allowlist.
        $allowed_metadata_fields = $this->get_all_metadata_fields();

        foreach ( $allowed_metadata_fields as $field_name ) {
            $vars[] = 'metadata_' . $field_name;
        }

        return $vars;
    }

    /**
     * Get all metadata field names that exist in the database
     *
     * @return array Array of field names.
     */
    private function get_all_metadata_fields(): array {
        global $wpdb;

        // Query all unique meta keys for published posts.
        $results = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE p.post_status = 'publish' 
            AND meta_key NOT LIKE '\_%' 
            ORDER BY meta_key"
        );

        $fields = is_array( $results ) ? array_map( 'sanitize_key', $results ) : array();

        return $fields;
    }

    /**
     * Modify WordPress queries to handle metadata filtering
     *
     * This function checks for metadata filter parameters in the URL
     * and modifies the main query accordingly to filter results.
     *
     * @param \WP_Query $query The WordPress query object.
     */
    public function handle_metadata_filtering( \WP_Query $query ): void {
        // Only modify the main query on the frontend (not admin).
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        // Check if we have any metadata filter parameters in the URL.
        $metadata_filters = $this->get_metadata_filters_from_url();

        // If we have metadata filters, modify the query.
        if ( ! empty( $metadata_filters ) ) {
            // Determine the post type from the selected metadata.
            $target_post_type = $this->get_post_type_from_metadata_filters();

            // Ensure we're querying the correct post type.
            $current_post_type = $query->get( 'post_type' );
            if ( empty( $current_post_type ) || 'post' === $current_post_type ) {
                // If no post type is set or it's set to 'post', change it to the target post type.
                $query->set( 'post_type', $target_post_type );
            }

            $meta_query = $this->build_meta_query( $metadata_filters );

            // Get existing meta query if any.
            $existing_meta_query = $query->get( 'meta_query' );

            if ( ! empty( $existing_meta_query ) ) {
                // Merge with existing meta query.
                $meta_query = array(
                    'relation' => 'AND',
                    $existing_meta_query,
                    $meta_query,
                );
            }

            // Set the meta query.
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Extract metadata filters from WordPress query variables
     *
     * @return array Array of metadata filters.
     */
    private function get_metadata_filters_from_url(): array {
        $metadata_filters = array();

        // Get all allowed metadata fields.
        $allowed_fields = $this->get_all_metadata_fields();

        // Check each allowed field for query variable.
        foreach ( $allowed_fields as $field_name ) {
            $query_var = 'metadata_' . $field_name;
            $raw_value = get_query_var( $query_var );

            if ( ! empty( $raw_value ) ) {
                // Sanitize values immediately.
                if ( is_array( $raw_value ) ) {
                    $sanitized_values = array_map( 'sanitize_text_field', $raw_value );
                } else {
                    $sanitized_values = array( sanitize_text_field( $raw_value ) );
                }

                // Remove empty values after sanitization.
                $sanitized_values = array_filter( $sanitized_values );

                if ( ! empty( $sanitized_values ) ) {
                    $metadata_filters[ $field_name ] = $sanitized_values;
                }
            }
        }

        return $metadata_filters;
    }

    /**
     * Get allowed metadata fields from blocks on current page
     *
     * @return array Array of allowed field names.
     */
    private function get_allowed_metadata_fields(): array {
        global $post;

        $allowed_fields = array();

        // If we don't have a post object, return empty array.
        if ( ! $post || ! has_blocks( $post->post_content ) ) {
            return $allowed_fields;
        }

        // Parse blocks to find SearchMetadataFilter blocks.
        $blocks         = parse_blocks( $post->post_content );
        $allowed_fields = $this->extract_metadata_fields_from_blocks( $blocks );

        return array_unique( $allowed_fields );
    }

    /**
     * Recursively extract metadata field names from blocks
     *
     * @param array $blocks Array of block data to search through.
     * @return array Array of field names.
     */
    private function extract_metadata_fields_from_blocks( array $blocks ): array {
        $field_names = array();

        foreach ( $blocks as $block ) {
            if ( 'wordpress-search/search-metadata-filter' === $block['blockName'] ) {
                $selected_metadata = $block['attrs']['selectedMetadata'] ?? '';

                if ( ! empty( $selected_metadata ) && false !== strpos( $selected_metadata, ':' ) ) {
                    // Extract field name from "posttype:fieldname" format.
                    $parts = explode( ':', $selected_metadata );
                    if ( 2 === count( $parts ) ) {
                        $field_names[] = sanitize_key( $parts[1] ); // Sanitize field name.
                    }
                }
            }

            // Check nested blocks recursively.
            if ( ! empty( $block['innerBlocks'] ) ) {
                $nested_fields = $this->extract_metadata_fields_from_blocks( $block['innerBlocks'] );
                $field_names   = array_merge( $field_names, $nested_fields );
            }
        }

        return $field_names;
    }

    /**
     * Build meta query array from metadata filters
     *
     * @param array $metadata_filters Array of metadata filters.
     * @return array Meta query array.
     */
    private function build_meta_query( array $metadata_filters ): array {
        $meta_query = array();

        // If there are multiple metadata filters, use AND relation.
        if ( 1 < count( $metadata_filters ) ) {
            $meta_query['relation'] = 'AND';
        }

        foreach ( $metadata_filters as $field_name => $values ) {
            // Validate field name and values.
            if ( empty( $field_name ) || empty( $values ) ) {
                continue;
            }

            // Use IN comparison for both single and multiple values (already sanitized at source).
            $meta_query[] = array(
                'key'     => $field_name,
                'value'   => $values,
                'compare' => 'IN',
            );
        }

        return $meta_query;
    }

    /**
     * Get the post type from metadata filters by checking the page for block attributes
     *
     * This function looks for SearchMetadataFilter blocks on the current page
     * and extracts the post type from their selectedMetadata attributes.
     *
     * @return string The post type to query.
     */
    private function get_post_type_from_metadata_filters(): string {
        global $post;

        // Default to 'document' for backward compatibility.
        $default_post_type = 'document';

        // If we don't have a post object, return default.
        if ( ! $post || ! has_blocks( $post->post_content ) ) {
            return $default_post_type;
        }

        // Parse blocks to find SearchMetadataFilter blocks.
        $blocks = parse_blocks( $post->post_content );

        return $this->extract_post_type_from_blocks( $blocks, $default_post_type );
    }

    /**
     * Recursively extract post type from SearchMetadataFilter blocks
     *
     * @param array  $blocks Array of block data to search through.
     * @param string $default_post_type Default post type to return if none found.
     * @return string The post type found or default.
     */
    private function extract_post_type_from_blocks( array $blocks, string $default_post_type = 'document' ): string {
        foreach ( $blocks as $block ) {
            if ( 'wordpress-search/search-metadata-filter' === $block['blockName'] ) {
                $selected_metadata = $block['attrs']['selectedMetadata'] ?? '';

                if ( ! empty( $selected_metadata ) && false !== strpos( $selected_metadata, ':' ) ) {
                    // Extract post type from "posttype:fieldname" format.
                    $parts = explode( ':', $selected_metadata );
                    if ( 2 === count( $parts ) ) {
                        return $parts[0]; // Return the post type.
                    }
                }
            }

            // Check nested blocks recursively.
            if ( ! empty( $block['innerBlocks'] ) ) {
                $nested_post_type = $this->extract_post_type_from_blocks( $block['innerBlocks'], $default_post_type );
                if ( $default_post_type !== $nested_post_type ) {
                    return $nested_post_type;
                }
            }
        }

        return $default_post_type;
    }



    /**
     * Get metadata values for a post type and field (with security validation)
     *
     * This method validates that the field actually exists in the database
     * before returning values, providing security against arbitrary field enumeration.
     *
     * @param string $post_type The post type to query.
     * @param string $field_name The meta field name to get values for.
     * @return array Array of unique meta values.
     */
    public function get_metadata_values( string $post_type, string $field_name ): array {
        // Validate inputs.
        if ( empty( $post_type ) || empty( $field_name ) ) {
            return array();
        }

        // Sanitize inputs.
        $post_type  = sanitize_key( $post_type );
        $field_name = sanitize_key( $field_name );

        // Security: Only allow fields that actually exist in the database for this post type.
        if ( ! $this->field_exists_for_post_type( $post_type, $field_name ) ) {
            return array();
        }

        return $this->get_metadata_values_direct( $post_type, $field_name );
    }



    /**
     * Check if a metadata field exists for a given post type
     *
     * @param string $post_type The post type to check.
     * @param string $field_name The meta field name to check.
     * @return bool True if field exists, false otherwise.
     */
    private function field_exists_for_post_type( string $post_type, string $field_name ): bool {
        global $wpdb;

        // Query to check if this meta key exists for this post type.
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->postmeta} pm 
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE p.post_type = %s 
                AND pm.meta_key = %s 
                AND p.post_status = 'publish'
                LIMIT 1",
                $post_type,
                $field_name
            )
        );

        return $result > 0;
    }

    /**
     * Get metadata values for a post type and field (direct database query)
     *
     * @param string $post_type The post type to query.
     * @param string $field_name The meta field name to get values for.
     * @return array Array of unique meta values.
     */
    private function get_metadata_values_direct( string $post_type, string $field_name ): array {
        global $wpdb;

        // Validate inputs.
        if ( empty( $post_type ) || empty( $field_name ) ) {
            return array();
        }

        // Query to get all unique values for this meta key.
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm 
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE p.post_type = %s 
                AND pm.meta_key = %s 
                AND pm.meta_value != '' 
                AND p.post_status = 'publish'
                ORDER BY pm.meta_value ASC",
                $post_type,
                $field_name
            )
        );

        // Check for database errors.
        if ( $wpdb->last_error ) {
            // Log error but continue execution.
            return array();
        }

        // Process results.
        $values = is_array( $results ) ? array_filter( $results ) : array();

        return $values;
    }
}
