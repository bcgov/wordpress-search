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

// Ensure WordPress is loaded.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoload classes using Composer.
if ( ! class_exists( 'Bcgov\\WordpressSearch\\MetadataFilter' ) ) {
    $local_composer  = __DIR__ . '/vendor/autoload.php';
    $server_composer = __DIR__ . '/../../../../vendor/autoload.php';
    if ( file_exists( $local_composer ) || file_exists( $server_composer ) ) {
        if ( file_exists( $server_composer ) ) {
            require_once $server_composer;
        }
        if ( ! class_exists( 'Bcgov\\WordpressSearch\\MetadataFilter' ) ) {
            require_once $local_composer;
        }
    }
}

/**
 * Render callback for SearchMetadataFilter block
 *
 * @return string Rendered block output.
 */
function wordpress_search_render_metadata_filter_block() {
    // Make the metadata filter instance available to the render template.
    global $wordpress_search_metadata_filter;
    $plugin_instance = $wordpress_search_metadata_filter;

    // Start output buffering.
    ob_start();

    // Include the render template.
    include plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchMetadataFilter/render.php';

    // Return the buffered output.
    return ob_get_clean();
}

/**
 * The function wordpress_search_register_blocks registers block types from metadata in block.json files
 * found in subdirectories of the Blocks/build folder.
 */
function wordpress_search_register_blocks() {
    // Define the path to the build directory.
    $build_dir = plugin_dir_path( __FILE__ ) . 'Blocks/build/';

    // Use glob to find all block.json files in the subdirectories of the build folder.
    $block_files = glob( $build_dir . '*/block.json' );

    // Loop through each block.json file.
    foreach ( $block_files as $block_file ) {
        // Register the block type from the metadata in block.json.
        $block_type = register_block_type_from_metadata( $block_file );

        // Override render callback for SearchMetadataFilter to inject dependencies.
        if ( 'wordpress-search/search-metadata-filter' === $block_type->name ) {
            $block_type->render_callback = 'wordpress_search_render_metadata_filter_block';
        }
    }
}
// Hook the function into the 'init' action.
add_action( 'init', 'wordpress_search_register_blocks' );

/**
 * Register custom block category for search blocks.
 *
 * @param array $categories Array of block categories.
 * @return array Modified array of block categories.
 */
function wordpress_search_register_block_category( $categories ) {
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
add_filter( 'block_categories_all', 'wordpress_search_register_block_category', 10, 1 );

// Initialize the metadata filter functionality.
$wordpress_search_metadata_filter = new \Bcgov\WordpressSearch\MetadataFilter();
$wordpress_search_metadata_filter->init();
