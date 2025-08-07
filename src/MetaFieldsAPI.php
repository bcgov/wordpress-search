<?php
/**
 * Meta Fields API - Custom REST endpoint to fetch metadata fields
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch;

class MetaFieldsAPI {

    /**
     * Initialize the API endpoints
     */
    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        
        // Invalidate cache when post meta is updated
        add_action( 'added_post_meta', [ $this, 'invalidate_cache' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'invalidate_cache' ], 10, 4 );
        add_action( 'deleted_post_meta', [ $this, 'invalidate_cache' ], 10, 4 );
    }

    	/**
         * Register custom REST API routes
         */
	public function register_routes() {
		register_rest_route(
            'wordpress-search/v1',
            '/meta-fields',
            [
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_meta_fields' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
        );
	}


    /**
     * Get all available meta fields for all post types
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    /**
     * Get meta fields data (internal method without REST overhead)
     *
     * @return array Array of meta field data
     */
    public function get_meta_fields_data() {
        // Check for cached results first (12 hour cache)
        $cache_key = 'wordpress_search_meta_fields';
        $cached_fields = get_transient( $cache_key );
        
        if ( false !== $cached_fields ) {
            return $cached_fields;
        }

        global $wpdb;

        try {
            $meta_fields = [];

            // Get all public post types
            $post_types = get_post_types( [ 'public' => true ], 'objects' );
            $post_type_names = array_keys( $post_types );

            if ( empty( $post_type_names ) ) {
                return [];
            }

            // Single optimized query for all post types at once
            // NOTE: For large sites, consider adding these database indexes for better performance:
            // CREATE INDEX idx_postmeta_key_type ON wp_postmeta(meta_key, post_id);
            // CREATE INDEX idx_posts_type_status ON wp_posts(post_type, post_status);
            $placeholders = implode( ',', array_fill( 0, count( $post_type_names ), '%s' ) );
            $query = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key, p.post_type
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_type IN ($placeholders)
                 AND pm.meta_key NOT LIKE '\_%'
                 AND pm.meta_key NOT IN ('_edit_lock', '_edit_last', '_wp_desired_post_slug', '_wp_trash_meta_status', '_wp_trash_meta_time')
                 ORDER BY p.post_type, pm.meta_key",
                ...$post_type_names
            );

            $results = $wpdb->get_results( $query );

            // Group results by post type for efficient processing
            foreach ( $results as $row ) {
                $post_type = $post_types[ $row->post_type ] ?? null;
                if ( $post_type && ! empty( $row->meta_key ) ) {
                    $meta_fields[] = [
                        'label'    => $post_type->label . ': ' . $row->meta_key,
                        'value'    => $row->post_type . ':' . $row->meta_key,
                        'postType' => $row->post_type,
                        'metaKey'  => $row->meta_key,
                    ];
                }
            }

            // Sort by label
            usort(
                $meta_fields,
                function ( $a, $b ) {
                    return strcmp( $a['label'], $b['label'] );
                }
            );

            // Cache results for 12 hours
            set_transient( $cache_key, $meta_fields, 12 * HOUR_IN_SECONDS );

            return $meta_fields;

        } catch ( Exception $e ) {
            return [];
        }
    }

    /**
     * REST API endpoint wrapper
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_meta_fields( $request ) {
        try {
            $meta_fields = $this->get_meta_fields_data();
            return rest_ensure_response( $meta_fields );
        } catch ( Exception $e ) {
            return new WP_Error(
                'meta_fields_error',
                'Could not fetch meta fields: ' . $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }
    
    /**
     * Invalidate the meta fields cache when post meta changes
     *
     * @param int    $meta_id    ID of the metadata entry.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public function invalidate_cache( $meta_id, $post_id, $meta_key, $meta_value ) {
        // Only invalidate for non-private meta keys (same filter as our query)
        if ( ! str_starts_with( $meta_key, '_' ) ) {
            delete_transient( 'wordpress_search_meta_fields' );
        }
    }
}
