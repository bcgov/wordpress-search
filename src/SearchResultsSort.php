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
		add_filter( 'posts_orderby', array( $this, 'fix_meta_orderby' ), 25, 2 );
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
		$sort_param = filter_input( INPUT_GET, 'sort', FILTER_SANITIZE_STRING );
		if ( ! empty( $sort_param ) ) {

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

		// Check for metadata sorting BEFORE defaulting to title sorting.
		// This allows metadata sorting to work even when there's no search keyword.
		$meta_sort_param = filter_input( INPUT_GET, 'meta_sort', FILTER_SANITIZE_STRING );
		$meta_field      = filter_input( INPUT_GET, 'meta_field', FILTER_SANITIZE_STRING );

		if ( ! empty( $meta_sort_param ) && ! empty( $meta_field ) ) {

			if ( in_array( $meta_sort_param, array( 'asc', 'desc' ), true ) && ! empty( $meta_field ) ) {
				// Extract the field name if it's in posttype:fieldname format.
				if ( strpos( $meta_field, ':' ) !== false ) {
					$parts      = explode( ':', $meta_field );
					$meta_field = end( $parts );
				}

				$this->apply_meta_sorting( $query, $meta_field, $meta_sort_param );
				return;
			}
		}

		// If no URL parameters are set, check for block configuration.
		// This applies the default sort from block settings on initial page load.
		$sort_param    = filter_input( INPUT_GET, 'sort', FILTER_SANITIZE_STRING );
		$meta_sort_get = filter_input( INPUT_GET, 'meta_sort', FILTER_SANITIZE_STRING );
		$sort_meta_get = filter_input( INPUT_GET, 'sort_meta', FILTER_SANITIZE_STRING );
		$has_url_sort  = ! empty( $sort_param ) || ! empty( $meta_sort_get ) || ! empty( $sort_meta_get );
		if ( ! $has_url_sort ) {
			// Check if there's a search query - if so, let relevance sorting handle it.
			$search_query        = $query->get( 's' );
			$has_search_keywords = ! empty( $search_query ) && trim( $search_query ) !== '';

			if ( ! $has_search_keywords ) {
				$block_config = $this->get_block_config();
				if ( $block_config && ! empty( $block_config['selectedMetaField'] ) ) {
					// Use the configured meta field and sort order from block settings.
					$meta_field = $block_config['selectedMetaField'];
					$sort_order = $block_config['sortOrder'] ?? 'asc';

					// Extract the field name if it's in posttype:fieldname format.
					if ( strpos( $meta_field, ':' ) !== false ) {
						$parts      = explode( ':', $meta_field );
						$meta_field = end( $parts );
					}

					$this->apply_meta_sorting( $query, $meta_field, $sort_order );
					return;
				}
			}
		}

		// If no sort parameter is set and no block config, check if we should default to title sorting.
		$search_query = $query->get( 's' );
		if ( empty( $search_query ) || trim( $search_query ) === '' ) {
			// No keyword search and no block config - default to alphabetical title sorting.
			$this->apply_title_sorting( $query, 'title_asc' );
			return;
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
		$sort_meta       = filter_input( INPUT_GET, 'sort_meta', FILTER_SANITIZE_STRING );
		$sort_meta_field = filter_input( INPUT_GET, 'sort_meta_field', FILTER_SANITIZE_STRING );

		if ( ! empty( $sort_meta ) && ! empty( $sort_meta_field ) ) {

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

		if ( empty( $meta_key ) || ! in_array( $sort_order, array( 'asc', 'desc' ), true ) ) {
			return;
		}

		// Determine if this is a date field for proper sorting.
		$is_date_field = $this->is_date_field( $meta_key );

		// Get existing meta_query to avoid conflicts.
		$meta_query = $query->get( 'meta_query' );
		if ( empty( $meta_query ) ) {
			$meta_query = array();
		}

		// Ensure meta_query is an associative array to avoid conflicts.
		if ( ! empty( $meta_query ) && isset( $meta_query[0] ) && is_array( $meta_query[0] ) && ! isset( $meta_query['relation'] ) ) {
			// It's a numeric array, convert to associative.
			$new_meta_query = array();
			foreach ( $meta_query as $index => $clause ) {
				if ( is_numeric( $index ) ) {
					$new_meta_query[ 'clause_' . $index ] = $clause;
				} else {
					$new_meta_query[ $index ] = $clause;
				}
			}
			$meta_query = $new_meta_query;
		}

		if ( $is_date_field ) {
			// For date fields, use a meta query with DATE type that includes posts with and without the field.
			$meta_query['date_sort_clause'] = array(
				'relation' => 'OR',
				array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
					'type'    => 'DATE',
				),
				array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				),
			);

			$query->set( 'meta_query', $meta_query );
			$query->set( 'orderby', array( 'date_sort_clause' => strtoupper( $sort_order ) ) );
			$query->set( 'wordpress_search_date_meta_key', $meta_key );
			$query->set( 'wordpress_search_date_sort_order', strtoupper( $sort_order ) );
			$query->set( 'wordpress_search_meta_sort_applied', true );
		} else {
			// For non-date fields, use meta_query to include posts with and without the field.
			$meta_query['string_sort_clause'] = array(
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
			// Use meta_key to ensure the JOIN happens, then override orderby via filter.
			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', strtoupper( $sort_order ) );
			$query->set( 'wordpress_search_string_meta_key', $meta_key );
			$query->set( 'wordpress_search_string_sort_order', strtoupper( $sort_order ) );
			$query->set( 'wordpress_search_meta_sort_applied', true );
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

	/**
	 * Get block configuration from WordPress option.
	 *
	 * @return array|null Block configuration or null if not found.
	 */
	private function get_block_config() {
		$config = get_option( 'wordpress_search_sort_block_config' );
		if ( is_array( $config ) && ! empty( $config['selectedMetaField'] ) ) {
			return $config;
		}
		return null;
	}

	/**
	 * Fix meta orderby to include posts without meta field.
	 *
	 * @param string    $orderby The ORDER BY clause.
	 * @param \WP_Query $query The WordPress query object.
	 * @return string Modified ORDER BY clause.
	 */
	public function fix_meta_orderby( $orderby, $query ) {
		// Only modify search queries on frontend.
		if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() ) {
			return $orderby;
		}

		global $wpdb;

		// Check if we're sorting by a string meta field that needs special handling.
		$string_meta_key = $query->get( 'wordpress_search_string_meta_key' );
		if ( ! empty( $string_meta_key ) ) {
			$sort_order = $query->get( 'wordpress_search_string_sort_order' );
			if ( empty( $sort_order ) ) {
				$sort_order = 'ASC';
			}

			// WordPress creates mt1 for the EXISTS clause in our OR meta_query.
			// We need to order by mt1.meta_value, with NULLs (from NOT EXISTS) at the end.
			if ( 'DESC' === strtoupper( $sort_order ) ) {
				// DESC: NULLs last, values descending.
				$orderby = "CASE WHEN mt1.meta_value IS NULL THEN 1 ELSE 0 END, mt1.meta_value DESC, {$wpdb->posts}.ID ASC";
			} else {
				// ASC: NULLs last, values ascending.
				$orderby = "CASE WHEN mt1.meta_value IS NULL THEN 1 ELSE 0 END, mt1.meta_value ASC, {$wpdb->posts}.ID ASC";
			}
		}

		// Check if we're sorting by a date meta field that needs special handling.
		$date_meta_key = $query->get( 'wordpress_search_date_meta_key' );
		if ( ! empty( $date_meta_key ) ) {
			$sort_order = $query->get( 'wordpress_search_date_sort_order' );
			if ( empty( $sort_order ) ) {
				$sort_order = 'DESC';
			}

			// For date fields, use proper DATE casting with NULL handling.
			if ( 'DESC' === strtoupper( $sort_order ) ) {
				// DESC: NULLs last, dates descending.
				$orderby = "CASE WHEN mt1.meta_value IS NULL THEN 1 ELSE 0 END, CAST(mt1.meta_value AS DATE) DESC, {$wpdb->posts}.ID ASC";
			} else {
				// ASC: NULLs last, dates ascending.
				$orderby = "CASE WHEN mt1.meta_value IS NULL THEN 1 ELSE 0 END, CAST(mt1.meta_value AS DATE) ASC, {$wpdb->posts}.ID ASC";
			}
		}

		return $orderby;
	}
}
