<?php
/**
 * Meta Fields API - Custom REST endpoint to fetch metadata fields
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;

/**
 * Class MetaFieldsAPI
 *
 * Provides a REST API endpoint to fetch all available metadata fields
 * from the database for use in the SearchResultsSort block.
 */
class MetaFieldsAPI {

    /**
     * Initialize the API endpoints.
     */
    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register custom REST API routes.
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
     * Get meta fields data (internal method without REST overhead).
     *
     * @return array Array of meta field data.
     */
    public function get_meta_fields_data() {
        global $wpdb;

        try {
            $meta_fields = [];

            $post_types      = get_post_types( [ 'public' => true ], 'objects' );
            $post_type_names = array_keys( $post_types );

            if ( empty( $post_type_names ) ) {
                return [];
            }

            // Use a different approach: build separate queries for each post type and union them.
            $all_results = [];

            foreach ( $post_type_names as $post_type ) {
                $type_results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT DISTINCT pm.meta_key, p.post_type
                         FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE p.post_type = %s
                           AND pm.meta_key NOT LIKE %s
                           AND pm.meta_key NOT IN (
                               '_edit_lock',
                               '_edit_last',
                               '_wp_desired_post_slug',
                               '_wp_trash_meta_status',
                               '_wp_trash_meta_time'
                           )",
                        $post_type,
                        '\\_%'
                    )
                );

                if ( $type_results ) {
                    $all_results = array_merge( $all_results, $type_results );
                }
            }

            // Remove duplicates using array keys for O(1) lookup performance.
            $unique_results = [];
            $seen_keys      = [];

            foreach ( $all_results as $row ) {
                $key = $row->post_type . ':' . $row->meta_key;
                if ( ! isset( $seen_keys[ $key ] ) ) {
                    $seen_keys[ $key ] = true;
                    $unique_results[]  = $row;
                }
            }

            // Sort by post_type, then meta_key.
            usort(
                $unique_results,
                function ( $a, $b ) {
                    $type_compare = strcmp( $a->post_type, $b->post_type );
                    return 0 !== $type_compare ? $type_compare : strcmp( $a->meta_key, $b->meta_key );
                }
            );

            foreach ( $unique_results as $row ) {
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

            usort(
                $meta_fields,
                function ( $a, $b ) {
                    return strcmp( $a['label'], $b['label'] );
                }
            );

            return $meta_fields;

        } catch ( Exception $e ) {
            return [];
        }
    }

    /**
     * REST API endpoint wrapper.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_meta_fields( WP_REST_Request $request ) {
        unset( $request ); // Parameter required by WordPress REST API callback signature.

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
}
