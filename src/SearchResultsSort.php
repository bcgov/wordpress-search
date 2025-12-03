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
 * Manages sorting of search results by meta fields and title with dynamic field detection.
 */
class SearchResultsSort {

	/**
	 * Initialize the sorting functionality.
	 */
	public function init() {
		add_action( 'pre_get_posts', array( $this, 'handle_sorting' ), 20 );
	}

	/**
	 * Handle search results sorting by meta fields and title.
	 * Supports new format: sort=title_asc/title_desc and meta_sort=newest/oldest/asc/desc.
	 *
	 * @param \WP_Query $query The WordPress query object.
	 */
	public function handle_sorting( $query ) {
		// Only modify main search queries on frontend.
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		// Check for sorting parameter.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
		if ( isset( $_GET['sort'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
			$sort_param = sanitize_text_field( $_GET['sort'] );
			
			// If relevance is selected, don't override - let MetadataTaxonomySearch handle it.
			if ( 'relevance' === $sort_param ) {
				return;
			}
			
			// Apply title sorting if selected.
			if ( in_array( $sort_param, array( 'title_asc', 'title_desc' ), true ) ) {
				$this->apply_title_sorting( $query, $sort_param );
				return;
			}
		}

		// Check for metadata sorting.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
		if ( isset( $_GET['meta_sort'] ) && isset( $_GET['meta_field'] ) ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
			$meta_sort_param = sanitize_text_field( $_GET['meta_sort'] );
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
			$meta_field = sanitize_text_field( $_GET['meta_field'] );

			if ( in_array( $meta_sort_param, array( 'newest', 'oldest', 'asc', 'desc' ), true ) && ! empty( $meta_field ) ) {
				// Extract the field name if it's in posttype:fieldname format.
				if ( strpos( $meta_field, ':' ) !== false ) {
					$parts      = explode( ':', $meta_field );
					$meta_field = end( $parts );
				}

				$this->apply_meta_sorting( $query, $meta_field, $meta_sort_param );
				return;
			}
		}

		// Legacy support for old format (backward compatibility).
		$this->handle_legacy_sorting( $query );
	}

	/**
	 * Handle legacy sorting format for backward compatibility.
	 *
	 * @param \WP_Query $query The WordPress query object.
	 */
	private function handle_legacy_sorting( $query ) {
		// Check for old format: sort_meta_field=document:new_date&sort_meta=asc.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
		if ( isset( $_GET['sort_meta'] ) && isset( $_GET['sort_meta_field'] ) ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
			$sort_meta = sanitize_text_field( $_GET['sort_meta'] );
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
			$sort_meta_field = sanitize_text_field( $_GET['sort_meta_field'] );

			if ( in_array( $sort_meta, array( 'asc', 'desc' ), true ) && ! empty( $sort_meta_field ) ) {
				// Extract the meta field name if it's in posttype:fieldname format.
				if ( strpos( $sort_meta_field, ':' ) !== false ) {
					$parts    = explode( ':', $sort_meta_field );
					$meta_key = end( $parts );
				} else {
					$meta_key = $sort_meta_field;
				}

				$this->apply_meta_sorting( $query, $meta_key, $sort_meta );
			}
		}
	}

	/**
	 * Apply title sorting to the query.
	 *
	 * @param \WP_Query $query The WordPress query object.
	 * @param string    $sort_param The sort parameter (title_asc or title_desc).
	 */
	private function apply_title_sorting( $query, $sort_param ) {
		$order = ( 'title_asc' === $sort_param ) ? 'ASC' : 'DESC';

		$query->set( 'orderby', 'title' );
		$query->set( 'order', $order );
	}

	/**
	 * Apply meta field sorting to the query.
	 *
	 * @param \WP_Query $query The WordPress query object.
	 * @param string    $meta_key_or_direction The meta key or sort direction.
	 * @param string    $sort_order The sort order (asc, desc, newest, oldest).
	 */
	private function apply_meta_sorting( $query, $meta_key_or_direction, $sort_order = '' ) {
		// Handle the case where meta_key_or_direction might be the sort direction.
		if ( empty( $sort_order ) ) {
			$sort_order = $meta_key_or_direction;
			$meta_key   = $this->get_default_meta_key();
		} else {
			$meta_key = $meta_key_or_direction;
		}

		// Convert newest/oldest to asc/desc for date fields.
		if ( 'newest' === $sort_order ) {
			$sort_order = 'desc';
		} elseif ( 'oldest' === $sort_order ) {
			$sort_order = 'asc';
		}

		if ( empty( $meta_key ) || ! in_array( $sort_order, array( 'asc', 'desc' ), true ) ) {
			return;
		}

		// Determine if this is a date field for proper sorting.
		$is_date_field = $this->is_date_field( $meta_key );

		if ( $is_date_field ) {
			// For date fields, use a meta query with DATE type for proper sorting.
			$meta_query = $query->get( 'meta_query' );
			if ( empty( $meta_query ) ) {
				$meta_query = array();
			}

			// Add meta query clause with a key for ordering.
			$meta_query['date_clause'] = array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
				'type'    => 'DATE',
			);

			$query->set( 'meta_query', $meta_query );
			$query->set( 'orderby', array( 'date_clause' => strtoupper( $sort_order ) ) );
		} else {
			// For non-date fields, use the original approach.
			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', strtoupper( $sort_order ) );

			// Include posts without the meta field at the end.
			$meta_query = $query->get( 'meta_query' );
			if ( empty( $meta_query ) ) {
				$meta_query = array();
			}
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				),
			);
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Get the default meta key for sorting when none is specified.
	 * This should be overridden by the block configuration.
	 *
	 * @return string The default meta key.
	 */
	private function get_default_meta_key() {
		// Return a common meta field or empty string.
		return 'relevance_date';
	}

	/**
	 * Get the configured metadata field from the block settings.
	 * This is a simplified approach - in a real implementation, you might want to
	 * get this from the actual block attributes or a more sophisticated method.
	 *
	 * @return string|null The configured meta field name or null if not found.
	 */
	private function get_configured_meta_field() {
		// For now, we'll use a common field name. In a real implementation,
		// you might want to get this from the block attributes or database.
		// You could also check for common date fields that exist in your posts.

		// Check if any common date fields exist in the database.
		$common_date_fields = array( 'new_date', 'relevance_date', 'date', 'post_date' );

		foreach ( $common_date_fields as $field ) {
			if ( $this->meta_field_exists( $field ) ) {
				return $field;
			}
		}

		return null;
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
