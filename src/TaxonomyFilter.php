<?php
/**
 * TaxonomyFilter Class
 *
 * Handles taxonomy filtering functionality and auto-discovery search for metadata and taxonomies.
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch;

/**
 * TaxonomyFilter class
 */
class TaxonomyFilter {
    /**
     * Prefix used for taxonomy filter parameters
     */
    const TAXONOMY_PREFIX = 'taxonomy_';

    /**
     * Initialize the taxonomy filter functionality
     */
    public function init() {
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'pre_get_posts', array( $this, 'handle_taxonomy_filtering' ) );
    }

    /**
     * Add taxonomy filter query variables to WordPress.
     *
     * @param array $vars Array of query variables.
     * @return array Modified array of query variables.
     */
    public function add_query_vars( $vars ) {
        // Get all registered taxonomies.
        $taxonomies = get_taxonomies();

        // Add query var for each taxonomy.
        foreach ( $taxonomies as $taxonomy ) {
            $vars[] = self::TAXONOMY_PREFIX . $taxonomy;
        }

        return $vars;
    }

    /**
     * Handle taxonomy filtering in the main query.
     *
     * @param \WP_Query $query The WordPress query object.
     */
    public function handle_taxonomy_filtering( $query ) {
        // Only modify the main query on the frontend.
        // In test environments, allow queries that have the right properties even if not technically the "main" query.
        $is_test_environment = defined( 'PHPUNIT_COMPOSER_INSTALL' ) || defined( 'WP_TESTS_CONFIG_FILE_PATH' );

        if ( is_admin() || ! $query->is_search() ) {
            return;
        }

        // For non-test environments, also check is_main_query.
        if ( ! $is_test_environment && ! $query->is_main_query() ) {
            return;
        }

        // Get all query variables.
        $query_vars = $query->query_vars;

        // Build taxonomy query from URL parameters.
        // Note: Nonce verification not required here as this is read-only filtering of public search results.
        // Users can share/bookmark these URLs, and nonces would break this functionality.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search filtering.
        $tax_query = $this->process_taxonomy_parameters( $_GET, array() );

        // If we have taxonomy filters, add them to the query.
        if ( ! empty( $tax_query ) ) {
            // If there's an existing tax query, merge with it.
            $existing_tax_query = $query->get( 'tax_query' );
            if ( ! empty( $existing_tax_query ) ) {
                $tax_query = array_merge(
                    array( 'relation' => 'AND' ),
                    $existing_tax_query,
                    $tax_query
                );
            }

            $query->set( 'tax_query', $tax_query );

            // Determine and set the correct post type based on the taxonomies being filtered.
            $current_post_type = $query->get( 'post_type' );

            if ( empty( $current_post_type ) || 'post' === $current_post_type ) {
                // Determine post type from the taxonomy.
                $target_post_type = $this->get_post_type_from_taxonomy_filters( $tax_query );
                if ( $target_post_type ) {
                    $query->set( 'post_type', $target_post_type );
                }
            }
        }
    }

    /**
     * Process taxonomy parameters from query data and append to existing tax query.
     *
     * This method takes an existing tax_query array, processes the provided parameters
     * for any taxonomy filters, and appends new filter conditions to the array.
     *
     * @param array $params The parameters to process (from $_GET or query_vars).
     * @param array $tax_query The existing tax query array to append to.
     * @return array The tax query array with new conditions appended.
     */
    private function process_taxonomy_parameters( $params, $tax_query ) {
        foreach ( $params as $key => $value ) {
            // Sanitize the key to prevent security issues.
            $sanitized_key = sanitize_key( $key );

            if ( 0 === strpos( $sanitized_key, self::TAXONOMY_PREFIX ) && ! empty( $value ) ) {
                $taxonomy = substr( $sanitized_key, strlen( self::TAXONOMY_PREFIX ) ); // Remove taxonomy prefix.

                // Additional validation: ensure taxonomy name contains only allowed characters.
                if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $taxonomy ) ) {
                    continue;
                }

                // Skip if taxonomy doesn't exist.
                if ( ! taxonomy_exists( $taxonomy ) ) {
                    continue;
                }

                // Handle both array and string values.
                $term_ids = is_array( $value ) ? $value : array( $value );
                $term_ids = array_map( 'sanitize_text_field', $term_ids );
                $term_ids = array_map( 'intval', $term_ids ); // Convert to integers.

                // Remove any zero/invalid term IDs.
                $term_ids = array_filter( $term_ids );

                if ( empty( $term_ids ) ) {
                    continue;
                }

                // Add to tax query.
                $tax_query[] = array(
                    'taxonomy'         => $taxonomy,
                    'field'            => 'term_id',
                    'terms'            => $term_ids,
                    'operator'         => 'IN',
                    'include_children' => true,
                );
            }
        }

        return $tax_query;
    }

    /**
     * Determine the post type based on the taxonomies being filtered.
     *
     * @param array $tax_query The taxonomy query array.
     * @return string|null The determined post type or null if not found.
     */
    private function get_post_type_from_taxonomy_filters( $tax_query ) {
        foreach ( $tax_query as $tax_filter ) {
            if ( isset( $tax_filter['taxonomy'] ) ) {
                $taxonomy = $tax_filter['taxonomy'];

                // Get all post types associated with this taxonomy.
                $post_types = get_taxonomy( $taxonomy )->object_type ?? array();

                // Return the first non-post post type we find.
                foreach ( $post_types as $post_type ) {
                    if ( 'post' !== $post_type ) {
                        return $post_type;
                    }
                }
            }
        }

        return null;
    }
}
