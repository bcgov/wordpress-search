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
if ( ! class_exists( 'Bcgov\\WordpressSearch\\TaxonomyFilter' ) ) {
    $local_composer  = __DIR__ . '/vendor/autoload.php';
    $server_composer = __DIR__ . '/../../../../vendor/autoload.php';
    if ( file_exists( $local_composer ) || file_exists( $server_composer ) ) {
        if ( file_exists( $server_composer ) ) {
            require_once $server_composer;
        }
        if ( ! class_exists( 'Bcgov\\WordpressSearch\\TaxonomyFilter' ) ) {
            require_once $local_composer;
        }
    }
}

/**
 * Render callback for SearchTaxonomyFilter block
 *
 * @return string The rendered block content.
 */
function wordpress_search_render_taxonomy_filter_block() {
    ob_start();
    $render_file = plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchTaxonomyFilter/render.php';

    if ( file_exists( $render_file ) ) {
        include $render_file;
    }

    return ob_get_clean();
}

/**
 * Initialize the plugin
 *
 * This function is called when the plugin is loaded.
 * It registers blocks and initializes the filter functionality.
 */
function wordpress_search_init() {
    // Register blocks.
    register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/Search' );
    register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchPostFilter' );
    register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchResultsPostMetadataDisplay' );

    // Register taxonomy filter block.
    $block_path = plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchTaxonomyFilter';

    register_block_type(
        $block_path,
        array(
            'render_callback' => 'wordpress_search_render_taxonomy_filter_block',
        )
    );

    // Initialize filter functionality.
    $wordpress_search_taxonomy_filter = new \Bcgov\WordpressSearch\TaxonomyFilter();
    $wordpress_search_taxonomy_filter->init();
}
add_action( 'init', 'wordpress_search_init' );

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
