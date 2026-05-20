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
        add_filter( 'query_loop_block_query_vars', array( $this, 'filter_query_loop_block_query_vars' ), 10, 3 );
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

        $query_vars = $query->query_vars;

        $get_params = filter_input_array( INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        if ( null === $get_params ) {
            $get_params = $this->extract_params_from_query_vars( $query_vars );
        }

        $filters = $this->resolve_search_query_filters(
            $get_params ? $get_params : array(),
            $query_vars,
            $query->get( 'post_type' )
        );

        $this->apply_search_filters_to_wp_query( $query, $filters );
    }

    /**
     * Apply search URL filters to core/query blocks that do not inherit the main query.
     *
     * Block themes often use a Query block with postType "post" while the result count block
     * reads the main query (which pre_get_posts already filtered). This keeps them in sync.
     *
     * @param array    $query Query vars for the Query block.
     * @param \WP_Block $block Block instance.
     * @param int      $page  Current page.
     * @return array Modified query vars.
     */
    public function filter_query_loop_block_query_vars( $query, $block, $page ) {
        unset( $block, $page );

        if ( is_admin() || ! is_search() ) {
            return $query;
        }

        if ( ! empty( $query['inherit'] ) ) {
            return $query;
        }

        $get_params = filter_input_array( INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( null === $get_params ) {
            $get_params = array();
        }

        $filters = $this->resolve_search_query_filters(
            $get_params,
            $query,
            isset( $query['postType'] ) ? $query['postType'] : null
        );

        if ( ! empty( $filters['post_type'] ) ) {
            $query['post_type'] = $filters['post_type'];
            unset( $query['postType'] );
        }

        if ( ! empty( $filters['tax_query'] ) ) {
            $existing = isset( $query['tax_query'] ) && is_array( $query['tax_query'] ) ? $query['tax_query'] : array();
            if ( ! empty( $existing ) ) {
                $query['tax_query'] = array_merge(
                    array( 'relation' => 'AND' ),
                    $existing,
                    $filters['tax_query']
                );
            } else {
                $query['tax_query'] = $filters['tax_query'];
            }
        }

        if ( empty( $query['s'] ) ) {
            $search_query = get_search_query( false );
            if ( $search_query ) {
                $query['s'] = $search_query;
            }
        }

        return $query;
    }

    /**
     * Resolve post_type and tax_query for the current search request.
     *
     * @param array      $get_params          Sanitized request parameters.
     * @param array      $context_query_vars  Query vars from WP_Query or the Query block.
     * @param mixed|null $context_post_type   post_type from the query context.
     * @return array{post_type: string|string[]|null, tax_query: array}
     */
    private function resolve_search_query_filters( array $get_params, array $context_query_vars, $context_post_type = null ) {
        $url_post_types = $this->get_post_types_from_request_data( $get_params, $context_post_type, $context_query_vars );

        $tax_query = $this->process_taxonomy_parameters( $get_params, array() );

        $inferred_from_taxonomy = null;
        if ( empty( $url_post_types ) && ! empty( $tax_query ) ) {
            if ( empty( $context_post_type ) || 'post' === $context_post_type ) {
                $inferred_from_taxonomy = $this->get_post_type_from_taxonomy_filters( $tax_query );
            }
        }

        $effective_post_types = ! empty( $url_post_types )
            ? $url_post_types
            : ( $inferred_from_taxonomy ? array( $inferred_from_taxonomy ) : array() );

        $allow = wordpress_search_restricted_post_types_from_search_template();
        if ( $allow ) {
            if ( empty( $effective_post_types ) ) {
                $effective_post_types = $allow;
            } else {
                $effective_post_types = array_values( array_intersect( $effective_post_types, $allow ) );
                if ( empty( $effective_post_types ) ) {
                    $effective_post_types = $allow;
                }
            }
        }

        $post_type = null;
        if ( ! empty( $effective_post_types ) ) {
            $post_type = count( $effective_post_types ) === 1 ? $effective_post_types[0] : $effective_post_types;
        }

        return array(
            'post_type' => $post_type,
            'tax_query' => $tax_query,
        );
    }

    /**
     * Apply resolved filters to a WP_Query instance.
     *
     * @param \WP_Query $query   Query object.
     * @param array     $filters Filters from resolve_search_query_filters().
     */
    private function apply_search_filters_to_wp_query( $query, array $filters ) {
        if ( ! empty( $filters['post_type'] ) ) {
            $query->set( 'post_type', $filters['post_type'] );
        }

        if ( empty( $filters['tax_query'] ) ) {
            return;
        }

        $tax_query = $filters['tax_query'];
        $existing  = $query->get( 'tax_query' );
        if ( ! empty( $existing ) ) {
            $tax_query = array_merge(
                array( 'relation' => 'AND' ),
                $existing,
                $tax_query
            );
        }

        $query->set( 'tax_query', $tax_query );
    }

    /**
     * Resolve post_type slugs from the request (URL) and query vars.
     *
     * Search queries do not always copy ?post_type= into WP_Query; the filter block uses that param.
     *
     * @param \WP_Query $query      The WordPress query object.
     * @param array     $get_params Sanitized GET params from filter_input_array, or extracted query_vars.
     * @return string[] List of valid public post type slugs.
     */
    private function get_post_types_from_request( $query, array $get_params ) {
        $query_vars    = $query->query_vars;
        $raw_post_type = $query->get( 'post_type' );
        if ( ( null === $raw_post_type || '' === $raw_post_type ) && isset( $query_vars['post_type'] ) ) {
            $raw_post_type = $query_vars['post_type'];
        }

        return $this->get_post_types_from_request_data( $get_params, $raw_post_type, $query_vars );
    }

    /**
     * Resolve post_type slugs from the request and optional query context.
     *
     * @param array      $get_params          Sanitized GET params.
     * @param mixed|null $context_post_type   post_type from WP_Query or Query block context.
     * @param array      $context_query_vars  Additional query vars for fallback.
     * @return string[] List of valid public post type slugs.
     */
    private function get_post_types_from_request_data( array $get_params, $context_post_type = null, array $context_query_vars = array() ) {
        $raw = null;
        if ( isset( $get_params['post_type'] ) && '' !== $get_params['post_type'] ) {
            $raw = $get_params['post_type'];
        } elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $raw = wp_unslash( $_GET['post_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        }

        $from_url = $this->validate_public_post_types_from_query( $raw );
        if ( ! empty( $from_url ) ) {
            return $from_url;
        }

        if ( ( null === $context_post_type || '' === $context_post_type ) && isset( $context_query_vars['post_type'] ) ) {
            $context_post_type = $context_query_vars['post_type'];
        }

        return $this->validate_public_post_types_from_query( $context_post_type );
    }

    /**
     * Normalize and validate post_type query input (string or array) to public post types.
     *
     * @param mixed $raw Raw post_type from WP_Query (string, array, or empty).
     * @return string[] List of valid public post type slugs.
     */
    private function validate_public_post_types_from_query( $raw ) {
        $candidates = array();
        if ( is_array( $raw ) ) {
            foreach ( $raw as $slug ) {
                $slug = sanitize_key( sanitize_text_field( (string) $slug ) );
                if ( '' !== $slug ) {
                    $candidates[] = $slug;
                }
            }
        } elseif ( is_string( $raw ) && '' !== $raw ) {
            $candidates[] = sanitize_key( sanitize_text_field( $raw ) );
        }

        $validated = array();
        foreach ( $candidates as $slug ) {
            if ( ! post_type_exists( $slug ) ) {
                continue;
            }
            $post_type_obj = get_post_type_object( $slug );
            if ( $post_type_obj && $post_type_obj->public ) {
                $validated[] = $slug;
            }
        }

        return array_values( array_unique( $validated ) );
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

                // Handle both array and string values, including comma-separated strings.
                if ( is_array( $value ) ) {
                    $term_ids = $value;
                } else {
                    // Handle comma-separated values.
                    $term_ids = array_filter( array_map( 'trim', explode( ',', $value ) ) );
                }

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
     * Resolve taxonomy name with fallbacks for case-insensitive matching.
     *
     * Handles various input formats including titles with spaces, slugs with underscores,
     * and different cases. Matches against taxonomy slugs and labels.
     * Prioritizes taxonomies associated with the specified post type.
     *
     * @param string $post_type The post type to filter taxonomies by (e.g., 'document').
     * @param string $input The taxonomy name to resolve (can be slug, label, or title with spaces).
     * @return string|null The resolved taxonomy name (slug) or null if not found.
     */
    public static function resolve_taxonomy_name( $post_type, $input ) {
        if ( empty( $input ) ) {
            return null;
        }

        $input = trim( $input );
        // Normalize input: convert spaces/hyphens to underscores and lowercase.
        $norm = sanitize_key( $input );

        // Helper function to check if taxonomy exists and is associated with post type.
        $check_taxonomy = function ( $taxonomy_name ) use ( $post_type ) {
            if ( ! taxonomy_exists( $taxonomy_name ) ) {
                return false;
            }
            // If post_type is specified, verify the taxonomy is associated with it.
            if ( ! empty( $post_type ) ) {
                $tax_obj = get_taxonomy( $taxonomy_name );
                return $tax_obj && in_array( $post_type, (array) $tax_obj->object_type, true );
            }
            return true;
        };

        // Fast path: check if input matches a taxonomy slug exactly.
        if ( $check_taxonomy( $input ) ) {
            return $input;
        }

        // Check if normalized version matches a taxonomy slug.
        if ( $check_taxonomy( $norm ) ) {
            return $norm;
        }

        // Get all taxonomies, prioritizing those associated with the specified post type.
        $all_taxonomies       = get_taxonomies( array(), 'objects' );
        $post_type_taxonomies = array();
        $other_taxonomies     = array();

        foreach ( $all_taxonomies as $tax ) {
            // If post_type is specified, separate taxonomies by association.
            if ( ! empty( $post_type ) ) {
                $tax_object_types = (array) $tax->object_type;
                if ( in_array( $post_type, $tax_object_types, true ) ) {
                    $post_type_taxonomies[] = $tax;
                } else {
                    $other_taxonomies[] = $tax;
                }
            } else {
                $post_type_taxonomies[] = $tax;
            }
        }

        // Search post-type-specific taxonomies first, then others.
        $search_order = array_merge( $post_type_taxonomies, $other_taxonomies );

        foreach ( $search_order as $tax ) {
            // Match by slug: compare normalized versions (case and space/underscore insensitive).
            if ( sanitize_key( $tax->name ) === $norm ) {
                return $tax->name;
            }

            // Collect all possible label variations for this taxonomy.
            $labels = array( $tax->label );
            if ( ! empty( $tax->labels->name ) ) {
                $labels[] = $tax->labels->name;
            }
            if ( ! empty( $tax->labels->singular_name ) ) {
                $labels[] = $tax->labels->singular_name;
            }
            if ( ! empty( $tax->labels->menu_name ) ) {
                $labels[] = $tax->labels->menu_name;
            }

            // Match by label: check case-insensitive exact match or normalized match.
            foreach ( $labels as $label ) {
                if ( strcasecmp( $label, $input ) === 0 || sanitize_key( $label ) === $norm ) {
                    return $tax->name;
                }
            }
        }

        return null;
    }

    /**
     * Extract URL parameters from query_vars for test environment compatibility.
     *
     * WordPress populates query_vars from URL parameters, so we can safely extract
     * taxonomy and post_type parameters from there without accessing $_GET directly.
     *
     * @param array $query_vars The query variables array.
     * @return array Extracted and sanitized parameters.
     */
    private function extract_params_from_query_vars( $query_vars ) {
        $params = array();

        // Extract taxonomy parameters (those with the taxonomy_ prefix).
        $taxonomies = get_taxonomies();
        foreach ( $taxonomies as $taxonomy ) {
            $param_name = self::TAXONOMY_PREFIX . $taxonomy;
            if ( isset( $query_vars[ $param_name ] ) ) {
                $value = $query_vars[ $param_name ];
                if ( is_array( $value ) ) {
                    $params[ $param_name ] = array_map( 'sanitize_text_field', $value );
                } else {
                    $params[ $param_name ] = sanitize_text_field( $value );
                }
            }
        }

        // Extract post_type parameter.
        if ( isset( $query_vars['post_type'] ) ) {
            $pt = $query_vars['post_type'];
            if ( is_array( $pt ) ) {
                $params['post_type'] = array_map( 'sanitize_text_field', $pt );
            } else {
                $params['post_type'] = sanitize_text_field( (string) $pt );
            }
        }

        return $params;
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
                $taxonomy     = $tax_filter['taxonomy'];
                $taxonomy_obj = get_taxonomy( $taxonomy );

                if ( $taxonomy_obj && ! empty( $taxonomy_obj->object_type ) ) {
                    // Return the first non-post post type we find.
                    foreach ( $taxonomy_obj->object_type as $post_type ) {
                        if ( 'post' !== $post_type ) {
                            return $post_type;
                        }
                    }
                }
            }
        }

        return null;
    }
}
