<?php
/**
 * Search Results Sort Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchResultsSort;

// Get selected meta field and sort order from block attributes.
$selected_meta_field = $attributes['selectedMetaField'] ?? '';
$sort_order          = $attributes['sortOrder'] ?? 'newest';

// Only render on search pages or when there's a search query.
if ( ! is_search() && empty( get_query_var( 's' ) ) ) {
    return;
}

// Get available meta fields from the optimized API.
$meta_fields = [];

// Use the MetaFieldsAPI class for efficiency (shared cache, single query).
$meta_fields_api = new \Bcgov\WordpressSearch\MetaFieldsAPI();
$meta_fields     = $meta_fields_api->get_meta_fields_data();

// Note: Nonce verification not required for URL-based sorting of public search results.
// This allows users to share and bookmark sorted search result URLs.
// Sorting public search results is a read-only operation similar to taxonomy filtering.
// The $_GET parameters are properly sanitized below.

// Get search query to determine default sort.
$search_query = get_query_var( 's' );
$has_keyword  = ! empty( $search_query ) && trim( $search_query ) !== '';

// Determine default sort based on whether there's a search keyword.
// If there's a keyword, default to relevance. If no keyword, default to title alphabetical.
$default_sort = $has_keyword ? 'relevance' : 'title_asc';

// Get current selection from URL.
$current_sort = $default_sort;

// Check for sorting parameters.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
if ( isset( $_GET['sort'] ) ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
    $sort_param = sanitize_text_field( $_GET['sort'] );
    if ( in_array( $sort_param, [ 'relevance', 'title_asc', 'title_desc' ], true ) ) {
        $current_sort = $sort_param;
    }
}

// Check for metadata sorting.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
if ( $selected_meta_field && isset( $_GET['meta_sort'] ) ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting, parameters sanitized below.
    $meta_sort_param = sanitize_text_field( $_GET['meta_sort'] );
    if ( in_array( $meta_sort_param, [ 'newest', 'oldest', 'asc', 'desc' ], true ) ) {
        $current_sort = 'meta_' . $meta_sort_param;
    }
}

// Generate unique ID for this block instance.
$block_id = 'search-results-sort-' . \wp_generate_uuid4();

// Build the current URL without the sort parameters.
$current_url = remove_query_arg( [ 'sort', 'meta_sort', 'meta_field' ] );

/**
 * Function to convert field names to user-friendly titles.
 *
 * @param string $field_value The field value in format 'posttype:fieldname'.
 * @return string Formatted field label for display.
 */
function format_field_label( $field_value ) {
    // Extract just the field name (remove post type prefix).
    if ( \strpos( $field_value, ':' ) !== false ) {
        $parts      = \explode( ':', $field_value );
        $field_name = end( $parts );
    } else {
        $field_name = $field_value;
    }

    // Convert underscores to spaces and title case.
    $formatted = \str_replace( '_', ' ', $field_name );
    $formatted = \ucwords( $formatted );

    // Handle common field name patterns.
    $replacements = [
        'Sort Relevance'     => 'Sort Relevance',
        'Document File Name' => 'File Name',
        'Document File Url'  => 'File URL',
        'Document File Size' => 'File Size',
        'Document File Type' => 'File Type',
        'Post Date'          => 'Publication Date',
        'Page Order'         => 'Page Order',
        'This2'              => 'This 2',  // Handle numbered fields.
    ];

    foreach ( $replacements as $search => $replace ) {
        if ( \strcasecmp( $formatted, $search ) === 0 ) {
            return $replace;
        }
    }

    return $formatted;
}

// Build sort options - relevance only shows when there's a keyword.
$sort_options = [];

// Only include relevance option when there's a search keyword.
if ( $has_keyword ) {
    $sort_options['relevance'] = __( 'Best match', 'wordpress-search' );
}

// Title sorting options are always available.
$sort_options['title_asc']  = __( 'Alphabetical (A-Z)', 'wordpress-search' );
$sort_options['title_desc'] = __( 'Alphabetical (Z-A)', 'wordpress-search' );

// Add metadata options if configured.
if ( $selected_meta_field ) {
    $field_label = format_field_label( $selected_meta_field );

    if ( 'newest' === $sort_order || 'oldest' === $sort_order ) {
        $sort_options['meta_newest'] = $field_label . ' (' . __( 'Newest', 'wordpress-search' ) . ')';
        $sort_options['meta_oldest'] = $field_label . ' (' . __( 'Oldest', 'wordpress-search' ) . ')';
    } else {
        $sort_options['meta_asc']  = $field_label . ' (' . __( 'Asc', 'wordpress-search' ) . ')';
        $sort_options['meta_desc'] = $field_label . ' (' . __( 'Desc', 'wordpress-search' ) . ')';
    }
}

// Styles are provided via block.json view/style and compiled SCSS. Avoid inline CSS here.
?>

<div
    class="wp-block-wordpress-search-searchresultssort"
    id="<?php echo esc_attr( $block_id ); ?>"
    data-current-url="<?php echo esc_url( $current_url ); ?>"
    <?php echo $selected_meta_field ? 'data-meta-field="' . esc_attr( $selected_meta_field ) . '"' : ''; ?>
>
    <div class="search-results-sort">
        <div class="search-results-sort__accordion">
            <button
                type="button"
                class="search-results-sort__header"
                aria-expanded="true"
                aria-controls="<?php echo esc_attr( $block_id ); ?>-sort-options"
            >
                <span class="search-results-sort__title">
                    <?php echo esc_html__( 'Sort by', 'wordpress-search' ); ?>
                </span>
                <span class="search-results-sort__toggle" aria-hidden="true"></span>
            </button>

            <div
                id="<?php echo esc_attr( $block_id ); ?>-sort-options"
                class="search-results-sort__content"
            >
                <div class="search-results-sort__options" role="radiogroup" aria-label="<?php echo esc_attr__( 'Sort options', 'wordpress-search' ); ?>">
                    <?php foreach ( $sort_options as $value => $label ) : ?>
                        <?php $option_id = $block_id . '-sort-option-' . sanitize_html_class( $value ); ?>
                        <label class="search-results-sort__option" for="<?php echo esc_attr( $option_id ); ?>">
                            <input
                                id="<?php echo esc_attr( $option_id ); ?>"
                                type="radio"
                                name="<?php echo esc_attr( $block_id ); ?>-sort"
                                class="search-results-sort__option-input"
                                value="<?php echo esc_attr( $value ); ?>"
                                <?php checked( $current_sort, $value ); ?>
                            />
                            <span class="search-results-sort__option-label">
                                <?php echo esc_html( $label ); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

