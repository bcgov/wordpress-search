<?php
/**
 * MetadataTaxonomySearch Class
 *
 * Handles auto-discovery and search functionality for metadata and taxonomies.
 * Automatically finds and searches all custom fields and public taxonomies.
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch;

/**
 * MetadataTaxonomySearch class.
 */
class MetadataTaxonomySearch {

    /**
     * Cache key for metadata keys.
     */
    const CACHE_KEY_META = 'wordpress_search_meta_keys';

    /**
     * Cache key for taxonomies.
     */
    const CACHE_KEY_TAX = 'wordpress_search_taxonomies';

    /**
     * Cache duration in seconds (1 hour).
     */
    const CACHE_DURATION = 3600;

    /**
     * Maximum number of metadata keys to include.
     */
    const MAX_META_KEYS = 50;

    /**
     * Flag to track if hooks are added.
     *
     * @var bool
     */
    private $hooks_added = false;

    /**
     * Initialize the metadata and taxonomy search functionality.
     */
    public function init() {
        // Add auto-discovery search functionality.
        add_filter( 'posts_search', array( $this, 'custom_search_query' ), 10, 2 );

        // Clear cache when metadata changes.
        add_action( 'added_post_meta', array( $this, 'clear_meta_cache' ) );
        add_action( 'updated_post_meta', array( $this, 'clear_meta_cache' ) );
        add_action( 'deleted_post_meta', array( $this, 'clear_meta_cache' ) );
    }

    /**
     * Custom search query that includes auto-discovered metadata and taxonomies.
     *
     * @param string    $search The search SQL.
     * @param \WP_Query $wp_query The WordPress query object.
     * @return string Modified search SQL.
     */
    public function custom_search_query( $search, $wp_query ) {
        // Only modify search queries and skip admin/ajax requests.
        if ( ! $wp_query->is_search() || is_admin() || wp_doing_ajax() ) {
            return $search;
        }

        // Skip if search query is empty.
        if ( empty( $wp_query->query_vars['s'] ) ) {
            return $search;
        }

        global $wpdb;

        $search_terms        = $wp_query->query_vars['s'];
        $terms_relation_type = apply_filters( 'wordpress_search_terms_relation_type', 'OR' );

        // Clean and prepare search terms.
        $terms = array_filter( array_map( 'trim', explode( ' ', $search_terms ) ) );
        if ( empty( $terms ) ) {
            return $search;
        }

        $search_clauses = array();

        foreach ( $terms as $term ) {
            $term      = $wpdb->esc_like( $term );
            $like_term = '%' . $term . '%';

            $term_clauses = array();

            // Search in post title.
            $term_clauses[] = $wpdb->prepare( "($wpdb->posts.post_title LIKE %s)", $like_term );

            // Search in post content.
            $term_clauses[] = $wpdb->prepare( "($wpdb->posts.post_content LIKE %s)", $like_term );

            // Search in post excerpt.
            $term_clauses[] = $wpdb->prepare( "($wpdb->posts.post_excerpt LIKE %s)", $like_term );

            // Add metadata search clauses.
            $meta_clauses = $this->build_metadata_clauses( $like_term );
            if ( ! empty( $meta_clauses ) ) {
                $term_clauses = array_merge( $term_clauses, $meta_clauses );
            }

            // Add taxonomy search clauses.
            $tax_clauses = $this->build_taxonomy_clauses( $like_term );
            if ( ! empty( $tax_clauses ) ) {
                $term_clauses = array_merge( $term_clauses, $tax_clauses );
            }

            if ( ! empty( $term_clauses ) ) {
                $search_clauses[] = '(' . implode( ' OR ', $term_clauses ) . ')';
            }
        }

        if ( ! empty( $search_clauses ) ) {
            $search = ' AND (' . implode( " $terms_relation_type ", $search_clauses ) . ')';

            if ( ! is_user_logged_in() ) {
                $search .= " AND ($wpdb->posts.post_password = '')";
            }

            // Add table joins only once.
            $this->add_search_hooks();
        }

        /**
         * Filter search query return by plugin.
         *
         * @param string $search SQL query.
         * @param object $wp_query global wp_query object.
         */
        return apply_filters( 'wordpress_search_posts_search', $search, $wp_query );
    }

    /**
     * Build metadata search clauses.
     *
     * @param string $like_term Prepared LIKE term.
     * @return array Array of SQL clauses.
     */
    private function build_metadata_clauses( $like_term ) {
        global $wpdb;

        $all_meta_keys = $this->get_all_metadata_keys();
        if ( empty( $all_meta_keys ) ) {
            return array();
        }

        $clauses = array();
        foreach ( $all_meta_keys as $key_slug ) {
            $clauses[] = $wpdb->prepare( '(espm.meta_key = %s AND espm.meta_value LIKE %s)', $key_slug, $like_term );
        }

        return $clauses;
    }

    /**
     * Build taxonomy search clauses.
     *
     * @param string $like_term Prepared LIKE term.
     * @return array Array of SQL clauses.
     */
    private function build_taxonomy_clauses( $like_term ) {
        global $wpdb;

        $all_taxonomies = $this->get_all_searchable_taxonomies();
        if ( empty( $all_taxonomies ) ) {
            return array();
        }

        $clauses = array();
        foreach ( $all_taxonomies as $tax ) {
            $clauses[] = $wpdb->prepare( '(estt.taxonomy = %s AND est.name LIKE %s)', $tax, $like_term );
        }

        return $clauses;
    }

    /**
     * Add search-specific hooks.
     */
    private function add_search_hooks() {
        if ( ! $this->hooks_added ) {
            add_filter( 'posts_join', array( $this, 'search_join_tables' ) );
            add_filter( 'posts_distinct', array( $this, 'search_distinct' ) );
            $this->hooks_added = true;
        }
    }

    /**
     * Join necessary tables for metadata and taxonomy search.
     *
     * @param string $join The JOIN clause of the query.
     * @return string Modified JOIN clause.
     */
    public function search_join_tables( $join ) {
        global $wpdb;

        // Only add joins if we have data to search.
        $all_meta_keys = $this->get_all_metadata_keys();
        if ( ! empty( $all_meta_keys ) ) {
            $join .= " LEFT JOIN $wpdb->postmeta espm ON ($wpdb->posts.ID = espm.post_id)";
        }

        $all_taxonomies = $this->get_all_searchable_taxonomies();
        if ( ! empty( $all_taxonomies ) ) {
            $join .= " LEFT JOIN $wpdb->term_relationships estr ON ($wpdb->posts.ID = estr.object_id)";
            $join .= " LEFT JOIN $wpdb->term_taxonomy estt ON (estr.term_taxonomy_id = estt.term_taxonomy_id)";
            $join .= " LEFT JOIN $wpdb->terms est ON (estt.term_id = est.term_id)";
        }

        return $join;
    }

    /**
     * Make search results distinct.
     *
     * @return string
     */
    public function search_distinct() {
        return 'DISTINCT';
    }

    /**
     * Get all metadata keys for auto-discovery with caching.
     *
     * @return array Array of metadata keys.
     */
    private function get_all_metadata_keys() {
        // Try to get from cache first.
        $cached_keys = get_transient( self::CACHE_KEY_META );
        if ( false !== $cached_keys ) {
            return $cached_keys;
        }

        global $wpdb;

        // Get all meta keys excluding WordPress internal ones (prefixed with _).
        $wp_es_fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE pm.meta_key NOT LIKE %s 
                 AND p.post_status = 'publish' 
                 AND pm.meta_value != '' 
                 ORDER BY pm.meta_key ASC 
                 LIMIT %d",
                '\_%',
                self::MAX_META_KEYS
            )
        );

        $meta_keys = array();
        if ( is_array( $wp_es_fields ) && ! empty( $wp_es_fields ) ) {
            foreach ( $wp_es_fields as $field ) {
                if ( isset( $field->meta_key ) && ! empty( $field->meta_key ) ) {
                    $meta_keys[] = sanitize_key( $field->meta_key );
                }
            }
        }

        // Filter sensitive or unwanted keys.
        $meta_keys = $this->filter_metadata_keys( $meta_keys );

        /**
         * Filter all metadata keys for auto-inclusion in search.
         *
         * @param array $meta_keys Array of all metadata keys.
         */
        $meta_keys = apply_filters( 'wordpress_search_auto_meta_keys', $meta_keys );

        // Cache the results.
        set_transient( self::CACHE_KEY_META, $meta_keys, self::CACHE_DURATION );

        return $meta_keys;
    }

    /**
     * Filter out sensitive or unwanted metadata keys.
     *
     * @param array $meta_keys Raw metadata keys.
     * @return array Filtered metadata keys.
     */
    private function filter_metadata_keys( $meta_keys ) {
        $excluded_patterns = array(
            'password',
            'token',
            'secret',
            'api_key',
            'private',
            'session',
            'nonce',
            'wp_',
            'woocommerce_',
        );

        $filtered_keys = array();
        foreach ( $meta_keys as $key ) {
            $exclude = false;
            foreach ( $excluded_patterns as $pattern ) {
                if ( false !== stripos( $key, $pattern ) ) {
                    $exclude = true;
                    break;
                }
            }
            if ( ! $exclude ) {
                $filtered_keys[] = $key;
            }
        }

        return $filtered_keys;
    }

    /**
     * Get all searchable taxonomies with caching.
     *
     * @return array Array of taxonomy names.
     */
    private function get_all_searchable_taxonomies() {
        // Try to get from cache first.
        $cached_taxonomies = get_transient( self::CACHE_KEY_TAX );
        if ( false !== $cached_taxonomies ) {
            return $cached_taxonomies;
        }

        // Get public taxonomies that have UI.
        $tax_args = apply_filters(
            'wordpress_search_tax_args',
            array(
                'show_ui' => true,
                'public'  => true,
            )
        );

        $all_taxonomies = get_taxonomies( $tax_args, 'objects' );
        $all_taxonomies = apply_filters( 'wordpress_search_tax', $all_taxonomies );

        $taxonomy_names = array();
        if ( is_array( $all_taxonomies ) && ! empty( $all_taxonomies ) ) {
            foreach ( $all_taxonomies as $tax_name => $tax_obj ) {
                if ( ! empty( $tax_name ) ) {
                    $taxonomy_names[] = sanitize_key( $tax_name );
                }
            }
        }

        /**
         * Filter all taxonomies for auto-inclusion in search.
         *
         * @param array $taxonomy_names Array of all taxonomy names.
         */
        $taxonomy_names = apply_filters( 'wordpress_search_auto_taxonomies', $taxonomy_names );

        // Cache the results.
        set_transient( self::CACHE_KEY_TAX, $taxonomy_names, self::CACHE_DURATION );

        return $taxonomy_names;
    }

    /**
     * Clear metadata cache when post meta changes
     */
    public function clear_meta_cache() {
        delete_transient( self::CACHE_KEY_META );
    }
}
