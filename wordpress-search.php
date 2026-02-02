<?php
/**
 * Plugin Name: WordPress Search
 * Plugin URI: https://github.com/bcgov/wordpress-search
 * Author: govwordpress@gov.bc.ca
 * Author URI: https://citz-gdx.atlassian.net/browse/DSWP-114
 * Description: WordPress wordpress-search plugin is a plugin that adds custom functionality to your WordPress site dev1.0.1.
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
$local_composer = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $local_composer ) ) {
    require_once $local_composer;
}

// Check if the required classes exist.
if ( ! class_exists( 'Bcgov\\WordpressSearch\\TaxonomyFilter' ) ) {
    wp_die( 'WordPress Search Plugin Error: The required class Bcgov\\WordpressSearch\\TaxonomyFilter could not be found. Please ensure the plugin is properly installed and all dependencies are loaded.' );
}

if ( ! class_exists( 'Bcgov\\WordpressSearch\\MetadataTaxonomySearch' ) ) {
    wp_die( 'WordPress Search Plugin Error: The required class Bcgov\\WordpressSearch\\MetadataTaxonomySearch could not be found. Please ensure the plugin is properly installed and all dependencies are loaded.' );
}

if ( ! class_exists( 'Bcgov\\WordpressSearch\\SearchHighlight' ) ) {
    wp_die( 'WordPress Search Plugin Error: The required class Bcgov\\WordpressSearch\\SearchHighlight could not be found. Please ensure the plugin is properly installed and all dependencies are loaded.' );
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
	register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchTaxonomyFilter' );
	register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchActiveFilters' );
	register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchResultCount' );
	register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchResultsSort' );
	register_block_type( plugin_dir_path( __FILE__ ) . 'Blocks/build/SearchModal' );

	// Initialize filter functionality.
	$wordpress_search_taxonomy_filter = new \Bcgov\WordpressSearch\TaxonomyFilter();
	$wordpress_search_taxonomy_filter->init();

	// Initialize metadata and taxonomy search functionality.
	$wordpress_search_metadata_taxonomy_search = new \Bcgov\WordpressSearch\MetadataTaxonomySearch();
	$wordpress_search_metadata_taxonomy_search->init();
	// Initialize enhanced search highlight functionality.
	$wordpress_search_highlight = new \Bcgov\WordpressSearch\SearchHighlight();
	$wordpress_search_highlight->init();

	// Initialize meta fields API.
	$wordpress_search_meta_fields_api = new \Bcgov\WordpressSearch\MetaFieldsAPI();
	$wordpress_search_meta_fields_api->init();

	// Initialize search results sorting.
	$wordpress_search_results_sort = new \Bcgov\WordpressSearch\SearchResultsSort();
	$wordpress_search_results_sort->init();
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


// Wordpress-search plugin
// When the search plugin is enabled, the 'design-system-wordpress-theme//search-content' is unregister,
// and updates it with plugin which calls the search-with-search-plugin template part.
// for now this will have to be part of the theme, so it can be overwritten for the filter configurations.
add_action( 'init', 'wordpress_search_register_templates', 99 );
/**
 * Registers the WordPress Search plugin template.
 *
 * Unregisters the default design system search template and registers
 * the plugin's search template that uses the search-with-search-plugin template part.
 */
function wordpress_search_register_templates() {
	unregister_block_template( 'design-system-wordpress-theme//search-content' );
	register_block_template(
		'wordpress-search//search-content',
		[
			'title'       => __( 'WordPress Search Plugin Template', 'wordpress-search' ),
			'description' => __( 'Search results', 'wordpress-search' ),
			'content'     => '<!-- wp:template-part {"slug":"search-with-search-plugin","area":"uncategorized"} /-->',
		],
	);
}
