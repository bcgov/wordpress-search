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
}
	add_action( 'init', 'wordpress_search_init' );

/**
 * Handle search results sorting by meta fields
 * Supports both old format (sort_meta_field + sort_meta) and new simplified format (field_name=direction)
 */
function wordpress_search_handle_meta_sorting( $query ) {
    // Only modify main search queries on frontend
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
        return;
    }

    $meta_key    = '';
    $sort_order  = '';
    $sort_source = '';

    // Check for old format first (backward compatibility)
    $sort_meta       = $_GET['sort_meta'] ?? '';
    $sort_meta_field = $_GET['sort_meta_field'] ?? '';

    if ( in_array( $sort_meta, [ 'asc', 'desc' ], true ) && ! empty( $sort_meta_field ) ) {
        // Old format: sort_meta_field=document:new_date&sort_meta=asc
        if ( strpos( $sort_meta_field, ':' ) !== false ) {
            $parts    = explode( ':', $sort_meta_field );
            $meta_key = end( $parts ); // Get the meta field name part
        } else {
            $meta_key = $sort_meta_field;
        }
        $sort_order  = $sort_meta;
        $sort_source = 'old_format';
    } else {
        // Check for new simplified format: field_name=direction
        // Use a single pass through $_GET with proper sanitization
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search sorting
        foreach ( $_GET as $param_name => $param_value ) {
            // Sanitize input immediately
            $sanitized_key   = sanitize_key( $param_name );
            $sanitized_value = sanitize_text_field( $param_value );

            // Validate parameter name (alphanumeric + underscore only)
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized_key ) ) {
                continue;
            }

            // Validate sort direction
            if ( ! in_array( $sanitized_value, [ 'asc', 'desc' ], true ) ) {
                continue;
            }

            // Check if this looks like a meta field name (common patterns)
            if ( preg_match( '/^(document_|new_|sort_|relevance_|file_|date|time)/', $sanitized_key ) ||
                 in_array( $sanitized_key, [ 'new_date', 'sort_relevance', 'relevance_date' ], true ) ) {

                $meta_key    = $sanitized_key;
                $sort_order  = $sanitized_value;
                $sort_source = 'simplified_format';
                break; // Only use the first matching field
            }
        }
    }

    if ( ! empty( $meta_key ) && ! empty( $sort_order ) ) {
        // Set meta query to sort by the selected meta field
        $query->set( 'meta_key', $meta_key );

        // Determine if this is a date field for proper sorting
        $is_date_field = false;
        if ( strpos( strtolower( $meta_key ), 'date' ) !== false ||
             strpos( strtolower( $meta_key ), 'time' ) !== false ||
             strpos( strtolower( $meta_key ), 'relevance' ) !== false ) {
            $is_date_field = true;
        }

        $query->set( 'orderby', $is_date_field ? 'meta_value_datetime' : 'meta_value' );
        $query->set( 'order', strtoupper( $sort_order ) );

        // Include posts without the meta field at the end
        $meta_query   = $query->get( 'meta_query' ) ?: [];
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => $meta_key,
                'compare' => 'EXISTS',
            ],
            [
                'key'     => $meta_key,
                'compare' => 'NOT EXISTS',
            ],
        ];
        $query->set( 'meta_query', $meta_query );

    }
}
add_action( 'pre_get_posts', 'wordpress_search_handle_meta_sorting', 20 );

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
