<?php
/**
 * Search Results Sort Handler
 *
 * Handles meta field sorting for search results.
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch;

/**
 * Class SearchResultsSort
 *
 * Manages sorting of search results by meta fields with dynamic field detection.
 */
class SearchResultsSort {

    /**
     * Initialize the sorting functionality.
     */
    public function init() {
        add_action( 'pre_get_posts', [ $this, 'handle_meta_sorting' ], 20 );
    }

    /**
     * Handle search results sorting by meta fields.
     * Supports both old format (sort_meta_field + sort_meta) and new simplified format (field_name=direction).
     *
     * @param \WP_Query $query The WordPress query object.
     */
    public function handle_meta_sorting( $query ) {
        // Only modify main search queries on frontend.
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        // Note: Nonce verification not required for URL-based sorting of public search results.
        // This allows users to share and bookmark sorted search result URLs.
        // Sorting public search results is a read-only operation similar to taxonomy filtering.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.

        $meta_key    = '';
        $sort_order  = '';
        $sort_source = '';

        // Check for old format first (backward compatibility).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
        $sort_meta = $_GET['sort_meta'] ?? '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
        $sort_meta_field = $_GET['sort_meta_field'] ?? '';

        if ( in_array( $sort_meta, [ 'asc', 'desc' ], true ) && ! empty( $sort_meta_field ) ) {
            // Old format: sort_meta_field=document:new_date&sort_meta=asc.
            if ( strpos( $sort_meta_field, ':' ) !== false ) {
                $parts    = explode( ':', $sort_meta_field );
                $meta_key = end( $parts ); // Get the meta field name part.
            } else {
                $meta_key = $sort_meta_field;
            }
            $sort_order  = $sort_meta;
            $sort_source = 'old_format';
        } else {
            // Check for new simplified format: field_name=direction.
            // Use a single pass through $_GET with proper sanitization.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
            foreach ( $_GET as $param_name => $param_value ) {
                // Sanitize input immediately.
                $sanitized_key   = sanitize_key( $param_name );
                $sanitized_value = sanitize_text_field( $param_value );

                // Validate parameter name (alphanumeric + underscore only).
                if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized_key ) ) {
                    continue;
                }

                // Validate sort direction.
                if ( ! in_array( $sanitized_value, [ 'asc', 'desc' ], true ) ) {
                    continue;
                }

                // Check if this parameter name exists as a meta field in the database.
                // This makes the system fully dynamic - any real meta field will be recognized.
                if ( $this->meta_field_exists( $sanitized_key ) ) {
                    $meta_key    = $sanitized_key;
                    $sort_order  = $sanitized_value;
                    $sort_source = 'simplified_format';
                    break; // Only use the first matching field.
                }
            }
        }

        if ( ! empty( $meta_key ) && ! empty( $sort_order ) ) {
            $this->apply_meta_sorting( $query, $meta_key, $sort_order );
        }
    }

    /**
     * Check if a meta field exists in the database.
     *
     * @param string $meta_key The meta key to check.
     * @return bool True if the meta field exists, false otherwise.
     */
    private function meta_field_exists( $meta_key ) {
        global $wpdb;

        $meta_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
                $meta_key
            )
        );

        return $meta_exists > 0;
    }

    /**
     * Apply meta field sorting to the query.
     *
     * @param \WP_Query $query The WordPress query object.
     * @param string    $meta_key The meta key to sort by.
     * @param string    $sort_order The sort order (asc or desc).
     */
    private function apply_meta_sorting( $query, $meta_key, $sort_order ) {
        // Set meta query to sort by the selected meta field.
        $query->set( 'meta_key', $meta_key );

        // Determine if this is a date field for proper sorting.
        $is_date_field = $this->is_date_field( $meta_key );

        $query->set( 'orderby', $is_date_field ? 'meta_value_datetime' : 'meta_value' );
        $query->set( 'order', strtoupper( $sort_order ) );

        // Include posts without the meta field at the end.
        $meta_query = $query->get( 'meta_query' );
        if ( empty( $meta_query ) ) {
            $meta_query = [];
        }
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => $meta_key,
                'compare' => 'EXISTS',
            ],
            [
                'key'     => $meta_key,
                'compare' => 'NOT EXISTS',
            ],
        ];
        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Determine if a meta field should be treated as a date field.
     *
     * @param string $meta_key The meta key to check.
     * @return bool True if it's a date field, false otherwise.
     */
    private function is_date_field( $meta_key ) {
        $meta_key_lower = strtolower( $meta_key );

        return strpos( $meta_key_lower, 'date' ) !== false ||
               strpos( $meta_key_lower, 'time' ) !== false ||
               strpos( $meta_key_lower, 'relevance' ) !== false;
    }
}
