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

/**
 * Register block templates for WordPress Search plugin.
 *
 * When the search plugin is enabled, this registers a plugin template that uses
 * the 'search-with-search-plugin' template part. The theme's template registration
 * is handled by the theme itself, which checks if the plugin is active before registering.
 *
 * Note: Templates registered via code are not editable in the Site Editor UI.
 * All search templates must be kept with the theme for filter configurations.
 *
 * @since 1.0.0
 */
function wordpress_search_register_templates() {
	// Only register if WordPress 6.7+ template API is available
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}

	// Register the plugin's search-content template
	register_block_template(
		'wordpress-search//search-content',
		array(
			'title'       => __( 'WordPress Search Plugin Template', 'wordpress-search' ),
			'description' => __( 'Search results template with enhanced search functionality', 'wordpress-search' ),
			'content'     => '<!-- wp:template-part {"slug":"search-with-search-plugin","area":"uncategorized"} /-->',
		)
	);
}
add_action( 'init', 'wordpress_search_register_templates', 20 );

/**
 * Filter template part block data to use plugin's search template when plugin is active.
 *
 * This filter dynamically swaps template-part blocks with slug 'search' to use
 * 'search-with-search-plugin' when rendering on search pages. This ensures the
 * frontend uses the correct template part when the plugin is active.
 *
 * The filter works by modifying the block attributes before rendering, which is
 * more reliable than template registration alone since templates registered via
 * code may not work properly on the frontend.
 *
 * @param array $parsed_block The parsed block data.
 * @return array The modified block data.
 * @since 1.0.0
 */
function wordpress_search_filter_template_part_block( $parsed_block ) {
	// Only process template-part blocks
	if ( ! isset( $parsed_block['blockName'] ) || 'core/template-part' !== $parsed_block['blockName'] ) {
		return $parsed_block;
	}

	// Only modify blocks with slug 'search' on search pages
	$slug = $parsed_block['attrs']['slug'] ?? '';
	if ( 'search' === $slug && is_search() ) {
		$parsed_block['attrs']['slug'] = 'search-with-search-plugin';
	}

	return $parsed_block;
}
add_filter( 'render_block_data', 'wordpress_search_filter_template_part_block', 10, 1 );
