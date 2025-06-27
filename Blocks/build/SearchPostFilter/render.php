<?php
/**
 * Search Post Type Filter Block - Frontend Render
 *
 * Renders the frontend interface for the Search Post Type Filter block.
 * This file handles the server-side rendering of the post type filter buttons,
 * managing the active states and URL generation for filtering.
 *
 * @package SearchPlugin
 * @subpackage SearchPosTypetFilter
 */

namespace Bcgov\WordpressSearch\SearchPosTypetFilter;

/**
 * Get selected post types from block attributes
 *
 * @var array Array of selected post type slugs from block settings
 */
$selected_post_types = $attributes['selectedPostTypes'] ?? [];

/**
 * Fetch all available post types from WordPress
 * Includes all post types except for WordPress internal ones
 * This ensures custom post types like "document" are always included
 *
 * @var WP_Post_Type[] Array of post type objects
 */
$all_post_types_raw = get_post_types( [], 'objects' );

/**
 * List of WordPress internal post types to exclude from the filter
 *
 * @var array Array of post type slugs to exclude
 */
$excluded_post_types = [
    'attachment',
    'revision',
    'nav_menu_item',
    'custom_css',
    'customize_changeset',
    'oembed_cache',
    'user_request',
    'wp_block',
    'wp_template',
    'wp_template_part',
    'wp_navigation',
    'wp_font_face',
    'wp_font_family',
    'wp_global_styles',
];

/**
 * Filter post types to include all except WordPress internal ones.
 *
 * @var WP_Post_Type[] Array of filtered post type objects.
 */
$all_post_types = [];
foreach ( $all_post_types_raw as $post_type_slug => $post_type_data ) {
    // Skip only the WordPress internal post types.
    if ( in_array( $post_type_slug, $excluded_post_types, true ) ) {
        continue;
    }

    // Include all other post types (this will include custom post types like "document").
    // We're being very inclusive here since this is a search plugin.
    $all_post_types[ $post_type_slug ] = $post_type_data;
}

/**
 * Filter post types based on block settings.
 * If no post types are selected, show all available post types.
 *
 * @var WP_Post_Type[] Array of post type objects to display.
 */
$post_types = [];
if ( empty( $selected_post_types ) ) {
    // If no post types are selected, show all public post types.
    $post_types = $all_post_types;
} else {
    // Filter to only include selected post types.
    foreach ( $selected_post_types as $selected_slug ) {
        if ( isset( $all_post_types[ $selected_slug ] ) ) {
            $post_types[ $selected_slug ] = $all_post_types[ $selected_slug ];
        }
    }
}

// If no valid post types are found, don't render the block.
if ( empty( $post_types ) ) {
    return;
}

/**
 * Get the currently selected post type from URL parameters.
 * Defaults to 'any' if no post type is specified.
 *
 * @var string Sanitized post type name.
 */
$current_post_type = 'any';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not required for reading post type from URL
if ( isset( $_GET['post_type'] ) ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_post_type = sanitize_key( $_GET['post_type'] );
}
?>

<div class="wp-block-wordpress-search-search-post-type-filter">
    <div class="dswp-search-post-type-filter__container">
        <?php
        /**
         * Loop through each selected post type and create a filter button.
         * Each button includes:
         * - Dynamic active state based on current selection.
         * - URL with post_type parameter.
         * - Escaped post type label for display.
         */
        foreach ( $post_types as $post_type_item ) :
            $is_active    = $current_post_type === $post_type_item->name;
            $button_class = 'dswp-search-post-type-filter__button';
            if ( $is_active ) {
                $button_class .= ' dswp-search-post-type-filter__button--active';
            }
			?>
            <a 
                href="<?php echo esc_url( add_query_arg( [ 'post_type' => $post_type_item->name ] ) ); ?>" 
                class="<?php echo esc_attr( $button_class ); ?>"
            >
                <?php echo esc_html( $post_type_item->labels->name ); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>