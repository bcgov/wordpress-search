<?php
/**
 * Plugin Name: WordPress Search
 * Plugin URI: https://github.com/bcgov/wordpress-search
 * Author: govwordpress@gov.bc.ca
 * Author URI: https://citz-gdx.atlassian.net/browse/DSWP-114
 * Description: WordPress wordpress-search plugin is a plugin that adds custom functionality to your WordPress site.
 * Requires at least: 6.4.4
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Version: 1.0.0
 * License: Apache License Version 2.0
 * License URI: LICENSE
 * Text Domain: wordpress-search
 * Tags:
 *
 * @package WordPressSearch
 */

/**
 * Register custom block category for search blocks
 *
 * @param array $categories Array of block categories.
 * @return array Modified array of block categories.
 */
function bcgovwp_register_search_block_category( $categories ) {
    return array_merge(
        array(
            array(
                'slug'  => 'search',
                'title' => __( 'Search', 'wordpress-search' ),
                'icon'  => 'search',
            ),
        ),
        $categories
    );
}
add_filter( 'block_categories_all', 'bcgovwp_register_search_block_category', 10, 1 );

/**
 * The function register_plugin_blocks registers block types from metadata in block.json files
 * found in subdirectories of the Blocks/build folder.
 */
function register_plugin_blocks() {
    // Define the path to the build directory.
    $build_dir = plugin_dir_path( __FILE__ ) . 'Blocks/build/';

    // Use glob to find all block.json files in the subdirectories of the build folder.
    $block_files = glob( $build_dir . '*/block.json' );
    // Loop through each block.json file.
    foreach ( $block_files as $block_file ) {
        // Register the block type from the metadata in block.json.
        register_block_type_from_metadata( $block_file );
    }
}
// Hook the function into the 'init' action.
add_action( 'init', 'register_plugin_blocks' );

/**
 * Modify WordPress queries to handle metadata filtering
 *
 * This function checks for metadata filter parameters in the URL
 * and modifies the main query accordingly to filter results.
 */
function handle_metadata_filtering( $query ) {
    // Only modify the main query on the frontend (not admin)
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // Check if we have any metadata filter parameters
    $metadata_filters = array();
    
    foreach ( $_GET as $key => $value ) {
        // Look for parameters that start with 'metadata_'
        if ( strpos( $key, 'metadata_' ) === 0 && ! empty( $value ) ) {
            // Extract the field name (remove 'metadata_' prefix)
            $field_name = substr( $key, 9 ); // Remove 'metadata_' (9 characters)
            
            // Ensure we have an array of values
            $values = is_array( $value ) ? $value : array( $value );
            
            // Remove empty values
            $values = array_filter( $values );
            
            if ( ! empty( $values ) ) {
                $metadata_filters[ $field_name ] = $values;
            }
        }
    }



    // If we have metadata filters, modify the query
    if ( ! empty( $metadata_filters ) ) {
        // Ensure we're querying the document post type
        $current_post_type = $query->get( 'post_type' );
        if ( empty( $current_post_type ) || $current_post_type === 'post' ) {
            // If no post type is set or it's set to 'post', change it to 'document'
            $query->set( 'post_type', 'document' );
        }
        $meta_query = array();
        
        // If there are multiple metadata filters, use AND relation
        if ( count( $metadata_filters ) > 1 ) {
            $meta_query['relation'] = 'AND';
        }
        
        foreach ( $metadata_filters as $field_name => $values ) {
            // Try to find the actual meta key in the database
            $actual_meta_key = find_actual_meta_key( $field_name );
            
            if ( count( $values ) === 1 ) {
                // Single value - simple comparison
                $meta_query[] = array(
                    'key'     => $actual_meta_key,
                    'value'   => $values[0],
                    'compare' => '='
                );
            } else {
                // Multiple values - use IN comparison
                $meta_query[] = array(
                    'key'     => $actual_meta_key,
                    'value'   => $values,
                    'compare' => 'IN'
                );
            }
        }
        

        
        // Get existing meta query if any
        $existing_meta_query = $query->get( 'meta_query' );
        
        if ( ! empty( $existing_meta_query ) ) {
            // Merge with existing meta query
            $meta_query = array(
                'relation' => 'AND',
                $existing_meta_query,
                $meta_query
            );
        }
        
        // Set the meta query
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'handle_metadata_filtering' );

/**
 * Find the actual meta key in the database
 * 
 * This function tries different variations of the field name to find
 * the actual meta key that exists in the database.
 */
function find_actual_meta_key( $field_name ) {
    global $wpdb;
    
    // List of possible variations to try
    $variations = array(
        $field_name,                                    // Original name (e.g., "category_select")
        str_replace( '_select', '', $field_name ),      // Remove "_select" suffix (e.g., "category")
        str_replace( '_filter', '', $field_name ),      // Remove "_filter" suffix
        $field_name . '_select',                        // Add "_select" suffix
        $field_name . '_filter',                        // Add "_filter" suffix
    );
    
    // Remove duplicates
    $variations = array_unique( $variations );
    
    // Check which variation exists in the database
    foreach ( $variations as $variation ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = %s 
             AND p.post_type = 'document' 
             AND p.post_status = 'publish'
             LIMIT 1",
            $variation
        ) );
        
        if ( $exists > 0 ) {
            return $variation;
        }
    }
    
    // If no variation found, return the original field name
    return $field_name;
}



/**  // Example.
* use Bcgov\WordPressSearch\{
**     {ClassName},
** };
** //Initialize
** ${feature_name} = new {ClassName}();
** ${feature_name}->init();
*/
