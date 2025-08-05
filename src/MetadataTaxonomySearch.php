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
 * MetadataTaxonomySearch class
 */
class MetadataTaxonomySearch {

    /**
     * Initialize the metadata and taxonomy search functionality
     */
    public function init() {
        // Add auto-discovery search functionality.
        add_filter( 'posts_search', array( $this, 'custom_search_query' ), 10, 2 );
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

        // Explode search terms.
        $terms = explode( ' ', $search_terms );
        if ( empty( $terms ) ) {
            return $search;
        }

        $search    = '';
        $searchand = '';

        foreach ( $terms as $term ) {
            $term = esc_sql( $wpdb->esc_like( $term ) );
            $term = '%' . $term . '%';

            $search .= $searchand . '(';
            $or      = '';

            // Search in post title.
            $search .= $wpdb->prepare( "($wpdb->posts.post_title LIKE %s)", $term );
            $or      = ' OR ';

            // Search in post content.
            $search .= $or;
            $search .= $wpdb->prepare( "($wpdb->posts.post_content LIKE %s)", $term );

            // Search in post excerpt.
            $search .= $or;
            $search .= $wpdb->prepare( "($wpdb->posts.post_excerpt LIKE %s)", $term );

            // Auto-include ALL metadata keys for search.
            $all_meta_keys = $this->get_all_metadata_keys();
            if ( ! empty( $all_meta_keys ) ) {
                $meta_key_or = '';

                foreach ( $all_meta_keys as $key_slug ) {
                    $search     .= $or . $meta_key_or;
                    $search     .= $wpdb->prepare( '(espm.meta_key = %s AND espm.meta_value LIKE %s)', $key_slug, $term );
                    $or          = '';
                    $meta_key_or = ' OR ';
                }

                $or = ' OR ';
            }

            // Auto-include ALL public taxonomies for search.
            $all_taxonomies = $this->get_all_searchable_taxonomies();
            if ( ! empty( $all_taxonomies ) ) {
                $tax_or = '';

                foreach ( $all_taxonomies as $tax ) {
                    $search .= $or . $tax_or;
                    $search .= $wpdb->prepare( '(estt.taxonomy = %s AND est.name LIKE %s)', $tax, $term );
                    $or      = '';
                    $tax_or  = ' OR ';
                }
            }

            $search   .= ')';
            $searchand = " $terms_relation_type ";
        }

        if ( ! empty( $search ) ) {
            $search = " AND ({$search}) ";
            if ( ! is_user_logged_in() ) {
                $search .= " AND ($wpdb->posts.post_password = '') ";
            }
        }

        // Add table joins.
        add_filter( 'posts_join', array( $this, 'search_join_tables' ) );

        // Request distinct results.
        add_filter(
            'posts_distinct',
            function () {
				return 'DISTINCT';
			}
        );

        /**
         * Filter search query return by plugin.
         *
         * @param string $search SQL query.
         * @param object $wp_query global wp_query object.
         */
        return apply_filters( 'wordpress_search_posts_search', $search, $wp_query );
    }

    /**
     * Join necessary tables for metadata and taxonomy search.
     *
     * @param string $join The JOIN clause of the query.
     * @return string Modified JOIN clause.
     */
    public function search_join_tables( $join ) {
        global $wpdb;

        // Always join post meta table since we search all metadata.
        $all_meta_keys = $this->get_all_metadata_keys();
        if ( ! empty( $all_meta_keys ) ) {
            $join .= " LEFT JOIN $wpdb->postmeta espm ON ($wpdb->posts.ID = espm.post_id) ";
        }

        // Always join taxonomies tables since we search all taxonomies.
        $all_taxonomies = $this->get_all_searchable_taxonomies();
        if ( ! empty( $all_taxonomies ) ) {
            $join .= " LEFT JOIN $wpdb->term_relationships estr ON ($wpdb->posts.ID = estr.object_id) ";
            $join .= " LEFT JOIN $wpdb->term_taxonomy estt ON (estr.term_taxonomy_id = estt.term_taxonomy_id) ";
            $join .= " LEFT JOIN $wpdb->terms est ON (estt.term_id = est.term_id) ";
        }

        return $join;
    }

    /**
     * Get all metadata keys for auto-discovery.
     * Auto-discovery of all custom fields.
     *
     * @return array Array of metadata keys.
     */
    private function get_all_metadata_keys() {
        global $wpdb;

        // Get all meta keys excluding WordPress internal ones (prefixed with _).
        $base_query     = "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE %s ORDER BY meta_key ASC";
        $prepared_query = $wpdb->prepare( $base_query, '\_%' );

        $wp_es_fields = $wpdb->get_results(
            apply_filters(
                'wordpress_search_meta_keys_query',
                $prepared_query
            )
        );

        $meta_keys = array();

        if ( is_array( $wp_es_fields ) && ! empty( $wp_es_fields ) ) {
            foreach ( $wp_es_fields as $field ) {
                if ( isset( $field->meta_key ) ) {
                    $meta_keys[] = $field->meta_key;
                }
            }
        }

        /**
         * Filter all metadata keys for auto-inclusion in search.
         *
         * @param array $meta_keys Array of all metadata keys.
         */
        return apply_filters( 'wordpress_search_auto_meta_keys', $meta_keys );
    }

    /**
     * Get all searchable taxonomies.
     * Auto-discovery of all public taxonomies.
     *
     * @return array Array of taxonomy names.
     */
    private function get_all_searchable_taxonomies() {
        // Get public taxonomies that have UI.
        $tax_args = apply_filters(
            'wordpress_search_tax_args',
            array(
                'show_ui' => true,
                'public'  => true,
            )
        );

        $all_taxonomies = apply_filters( 'wordpress_search_tax', get_taxonomies( $tax_args, 'objects' ) );
        $taxonomy_names = array();

        if ( is_array( $all_taxonomies ) && ! empty( $all_taxonomies ) ) {
            foreach ( $all_taxonomies as $tax_name => $tax_obj ) {
                $taxonomy_names[] = $tax_name;
            }
        }

        /**
         * Filter all taxonomies for auto-inclusion in search.
         *
         * @param array $taxonomy_names Array of all taxonomy names.
         */
        return apply_filters( 'wordpress_search_auto_taxonomies', $taxonomy_names );
    }
}
