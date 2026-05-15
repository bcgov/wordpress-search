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
 * Version: 1.1.0
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

/**
 * Resolve core template-part slug to plugin template part (matches render_block_data swap).
 *
 * @param string $slug Template part slug attribute.
 * @param string $area Template part area attribute.
 * @return string
 */
function wordpress_search_resolve_plugin_template_part_slug( $slug, $area ) {
	$slug = (string) $slug;
	$area = (string) $area;
	if ( 'uncategorized' === $area ) {
		if ( 'search-bar' === $slug ) {
			return 'search-bar-with-search-plugin';
		}
		if ( 'search' === $slug ) {
			return 'search-with-search-plugin';
		}
	}
	return $slug;
}

/**
 * Public post types restricted by Search Post Type Filter blocks in the search template (intersection if several).
 *
 * Themes/plugins may short-circuit parsing via {@see 'wordpress_search_restricted_post_types'} returning a non-empty array of slugs.
 *
 * @return string[]|null
 */
function wordpress_search_restricted_post_types_from_search_template() {
	static $memo = false;
	if ( false !== $memo ) {
		return $memo;
	}
	$memo = null;

	$forced = apply_filters( 'wordpress_search_restricted_post_types', null );
	if ( is_array( $forced ) && $forced ) {
		$out = array();
		foreach ( $forced as $slug ) {
			$slug = sanitize_key( (string) $slug );
			$obj  = ( $slug && post_type_exists( $slug ) ) ? get_post_type_object( $slug ) : null;
			if ( $obj && $obj->public ) {
				$out[] = $slug;
			}
		}
		if ( $out ) {
			$memo = array_values( array_unique( $out ) );
			return $memo;
		}
	}

	if ( ! function_exists( 'get_block_template' ) || ! function_exists( 'parse_blocks' ) ) {
		return $memo;
	}

	$stylesheet = get_stylesheet();
	$template   = get_block_template( $stylesheet . '//search', 'wp_template' );
	if ( ! $template || empty( $template->content ) ) {
		return $memo;
	}

	$lists = array();
	$walk  = function ( $blocks, $theme ) use ( &$walk, &$lists ) {
		foreach ( $blocks as $b ) {
			$n = $b['blockName'] ?? '';
			if ( 'wordpress-search/search-post-type-filter' === $n ) {
				$sel = $b['attrs']['selectedPostTypes'] ?? array();
				if ( is_array( $sel ) && $sel ) {
					$ok = array();
					foreach ( $sel as $slug ) {
						$slug = sanitize_key( (string) $slug );
						$obj  = ( $slug && post_type_exists( $slug ) ) ? get_post_type_object( $slug ) : null;
						if ( $obj && $obj->public ) {
							$ok[] = $slug;
						}
					}
					if ( $ok ) {
						$lists[] = $ok;
					}
				}
			} elseif ( 'core/template-part' === $n ) {
				$slug = wordpress_search_resolve_plugin_template_part_slug(
					(string) ( $b['attrs']['slug'] ?? '' ),
					(string) ( $b['attrs']['area'] ?? '' )
				);
				$th   = (string) ( $b['attrs']['theme'] ?? $theme );
				if ( $slug ) {
					$part = get_block_template( $th . '//' . $slug, 'wp_template_part' );
					if ( $part && ! empty( $part->content ) ) {
						$walk( parse_blocks( $part->content ), $th );
					}
				}
			}
			if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'], $theme );
			}
		}
	};
	$walk( parse_blocks( $template->content ), $stylesheet );

	if ( ! $lists ) {
		return $memo;
	}
	$acc = array_shift( $lists );
	foreach ( $lists as $next ) {
		$acc = array_values( array_intersect( $acc, $next ) );
		if ( ! $acc ) {
			return $memo;
		}
	}
	$memo = array_values( array_unique( $acc ) );
	return $memo;
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


// Wordpress-search plugin: use render_block_data to swap template parts when the plugin is active.
/**
 * Swaps template parts to the plugin versions when the plugin is active.
 * - Search bar: slug search-bar → search-bar-with-search-plugin (in header).
 * - Search content: slug search → search-with-search-plugin (in search-content template).
 */
add_filter(
	'render_block_data',
	function ( $parsed_block ) {
		if ( 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}
		$attrs = $parsed_block['attrs'] ?? array();
		$slug  = $attrs['slug'] ?? '';
		$area  = $attrs['area'] ?? '';
		$new   = wordpress_search_resolve_plugin_template_part_slug( $slug, $area );
		if ( $new !== (string) $slug ) {
			$parsed_block['attrs']['slug'] = $new;
		}
		return $parsed_block;
	},
	10,
	1
);
