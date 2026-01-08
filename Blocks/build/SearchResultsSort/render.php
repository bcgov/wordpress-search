<?php
/**
 * Search Results Sort Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchResultsSort;

// Get selected meta field and sort order from block attributes.
$selected_meta_field = $attributes['selectedMetaField'] ?? '';
$sort_order          = $attributes['sortOrder'] ?? 'asc';

// Store this block's configuration for the query handler to use.
// This is stored in an option so it persists across requests.
if ( ! empty( $selected_meta_field ) ) {
	$config = array(
		'selectedMetaField' => $selected_meta_field,
		'sortOrder'         => $sort_order,
	);
	// Store in a WordPress option that persists across requests.
	update_option( 'wordpress_search_sort_block_config', $config );

	// Also allow filter to override.
	$config = apply_filters( 'wordpress_search_sort_block_config', $config, $attributes );
}

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

// Get URL parameters first.
$sort_param      = filter_input( INPUT_GET, 'sort', FILTER_SANITIZE_STRING );
$meta_sort_param = filter_input( INPUT_GET, 'meta_sort', FILTER_SANITIZE_STRING );

// Determine default sort based on whether there's a search keyword.
// If keyword exists → relevance, otherwise use block config or title_asc.
$default_sort = 'title_asc';
if ( $has_keyword ) {
    $default_sort = 'relevance';
} elseif ( $selected_meta_field ) {
    $default_sort = 'meta_' . $sort_order;
}

// Get current selection from URL - URL parameters take priority.
$current_sort = $default_sort;

// Check for standard sorting parameters (title and relevance).
if ( ! empty( $sort_param ) ) {
    if ( in_array( $sort_param, [ 'relevance', 'title_asc', 'title_desc' ], true ) ) {
        $current_sort = $sort_param;
    }
}

// Check for metadata sorting - only if no standard sort parameter is set.
if ( $selected_meta_field && ! empty( $meta_sort_param ) && empty( $sort_param ) ) {
    if ( in_array( $meta_sort_param, [ 'asc', 'desc' ], true ) ) {
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
    $sort_options['relevance'] = __( 'Relevance (Best Match)', 'wordpress-search' );
}

// Title sorting options are always available.
$sort_options['title_asc']  = __( 'Title (Alphabetical)', 'wordpress-search' );
$sort_options['title_desc'] = __( 'Title (Reverse Alphabetical)', 'wordpress-search' );

// Add metadata options if configured.
if ( $selected_meta_field ) {
    $field_label = format_field_label( $selected_meta_field );

    // Always add both asc and desc options for any metadata field.
    $sort_options['meta_asc']  = $field_label . ' (' . __( 'Ascending', 'wordpress-search' ) . ')';
    $sort_options['meta_desc'] = $field_label . ' (' . __( 'Descending', 'wordpress-search' ) . ')';
}

// Styles are provided via block.json view/style and compiled SCSS. Avoid inline CSS here.
?>

<div class="wp-block-wordpress-search-searchresultssort" id="<?php echo esc_attr( $block_id ); ?>" <?php echo $selected_meta_field ? 'data-meta-field="' . esc_attr( $selected_meta_field ) . '"' : ''; ?>>
    <div class="search-results-sort">
        <div class="search-results-sort__controls">
            <div class="search-results-sort__field-group">
                <svg class="search-results-sort__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <!-- Arrow line -->
                    <line x1="6" y1="4" x2="6" y2="20" stroke="currentColor" stroke-width="2"/>
                    <!-- Arrow head -->
                    <polyline points="3,17 6,20 9,17" fill="none" stroke="currentColor" stroke-width="2"/>
                    
                    <!-- Horizontal bars (representing sort levels) -->
                    <line x1="12" y1="6" x2="20" y2="6" stroke="currentColor" stroke-width="2"/>
                    <line x1="12" y1="10" x2="18" y2="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="12" y1="14" x2="16" y2="14" stroke="currentColor" stroke-width="2"/>
                </svg>
                
                <label for="<?php echo esc_attr( $block_id ); ?>-sort-select" class="search-results-sort__label">
                    <?php echo esc_html__( 'Sort by:', 'wordpress-search' ); ?>
                </label>
                
                <select 
                    id="<?php echo esc_attr( $block_id ); ?>-sort-select" 
                    class="search-results-sort__sort-select"
                    data-current-url="<?php echo esc_url( $current_url ); ?>"
                >
                    <?php foreach ( $sort_options as $value => $label ) : ?>
                        <option 
                            value="<?php echo esc_attr( $value ); ?>"
                            <?php selected( $current_sort, $value ); ?>
                        >
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>