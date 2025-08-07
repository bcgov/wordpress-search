<?php
/**
 * Search Results Sort Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchResultsSort;

// Get selected meta fields from block attributes.
$selected_meta_fields = $attributes['selectedMetaFields'] ?? [];

// Only render on search pages or when there's a search query.
if ( ! is_search() && empty( get_query_var( 's' ) ) ) {
    return;
}

// Don't render if no meta fields are selected in block settings.
if ( empty( $selected_meta_fields ) ) {
    return;
}

// Get available meta fields from the optimized API.
$meta_fields = [];

// Use the MetaFieldsAPI class for efficiency (shared cache, single query).
$meta_fields_api = new \Bcgov\WordpressSearch\MetaFieldsAPI();
$meta_fields     = $meta_fields_api->get_meta_fields_data();

// If no meta fields are available, don't render the block.
if ( empty( $meta_fields ) ) {
    return;
}

// Sort by label.
usort(
    $meta_fields,
    function ( $a, $b ) {
		return strcmp( $a['label'], $b['label'] );
	}
);

// Note: Nonce verification not required for URL-based sorting of public search results.
// This allows users to share and bookmark sorted search result URLs.
// Sorting public search results is a read-only operation similar to taxonomy filtering.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.

// Get current selections from URL (support both old and new formats).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
$current_meta_field = $_GET['sort_meta_field'] ?? '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
$current_sort = $_GET['sort_meta'] ?? 'off';

// Check for new simplified format.
if ( empty( $current_meta_field ) || 'off' === $current_sort ) {
    // Look for simplified format parameters: field_name=direction with proper sanitization.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
    foreach ( $_GET as $param_name => $param_value ) {
        // Sanitize input immediately.
        $sanitized_key   = sanitize_key( $param_name );
        $sanitized_value = sanitize_text_field( $param_value );

        // Validate parameter name (alphanumeric + underscore only).
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized_key ) ) {
            continue;
        }

        // Validate sort direction.
        if ( ! in_array( $sanitized_value, [ 'asc', 'desc' ], true ) ) {
            continue;
        }

        // Check if this looks like a meta field name (common patterns).
        if ( preg_match( '/^(document_|new_|sort_|relevance_|file_|date|time)/', $sanitized_key ) ||
            in_array( $sanitized_key, [ 'new_date', 'sort_relevance', 'relevance_date' ], true ) ) {

            // Convert back to the format expected by the frontend display.
            $current_meta_field = 'document:' . $sanitized_key; // Assume document post type for display.
            $current_sort       = $sanitized_value;
            break;
        }
    }
}

// Generate unique ID for this block instance.
$block_id = 'search-results-sort-' . wp_generate_uuid4();

// Ensure current sort is valid.
if ( ! in_array( $current_sort, [ 'off', 'asc', 'desc' ], true ) ) {
    $current_sort = 'off';
}

// Build the current URL without the sort parameters (both old and new formats).
$parameters_to_remove = [ 'sort_meta', 'sort_meta_field' ];

// Dynamically find and remove any simplified format parameters.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
foreach ( $_GET as $param_name => $param_value ) {
    $sanitized_key = sanitize_key( $param_name );

    // Remove parameters that look like meta field names with sort values.
    if ( preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized_key ) &&
        in_array( sanitize_text_field( $param_value ), [ 'asc', 'desc' ], true ) &&
        ( preg_match( '/^(document_|new_|sort_|relevance_|file_|date|time)/', $sanitized_key ) ||
         in_array( $sanitized_key, [ 'new_date', 'sort_relevance', 'relevance_date' ], true ) ) ) {
        $parameters_to_remove[] = $sanitized_key;
    }
}

$current_url = remove_query_arg( $parameters_to_remove );

// Sort options.
$sort_options = [
    'off'  => __( 'Default (No sorting)', 'wordpress-search' ),
    'desc' => __( 'Newest first', 'wordpress-search' ),
    'asc'  => __( 'Oldest first', 'wordpress-search' ),
];

// Meta field options for the dropdown - only include selected fields.
$meta_field_options = [
    '' => __( 'Select a field to sort by...', 'wordpress-search' ),
];

/**
 * Function to convert field names to user-friendly titles.
 *
 * @param string $field_value The field value in format 'posttype:fieldname'.
 * @return string Formatted field label for display.
 */
function format_field_label( $field_value ) {
    // Extract just the field name (remove post type prefix).
    if ( strpos( $field_value, ':' ) !== false ) {
        $parts      = explode( ':', $field_value );
        $field_name = end( $parts );
    } else {
        $field_name = $field_value;
    }

    // Convert underscores to spaces and title case.
    $formatted = str_replace( '_', ' ', $field_name );
    $formatted = ucwords( $formatted );

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
        if ( strcasecmp( $formatted, $search ) === 0 ) {
            return $replace;
        }
    }

    return $formatted;
}



// If we have meta fields from database query, filter by selected ones.
if ( ! empty( $meta_fields ) ) {
    foreach ( $meta_fields as $field ) {
        if ( in_array( $field['value'], $selected_meta_fields, true ) ) {
            $meta_field_options[ $field['value'] ] = format_field_label( $field['value'] );
        }
    }
} else {
    // Fallback: Create options directly from selected fields with formatted labels.
    foreach ( $selected_meta_fields as $field_value ) {
        $meta_field_options[ $field_value ] = format_field_label( $field_value );
    }
}



?>

<div class="wp-block-wordpress-search-searchresultssort" id="<?php echo esc_attr( $block_id ); ?>">
    <div class="search-results-sort">
        <div class="search-results-sort__controls">
            <div class="search-results-sort__field-group">
                <label for="<?php echo esc_attr( $block_id ); ?>-field-select" class="search-results-sort__label">
                    <?php echo esc_html__( 'Sort by field:', 'wordpress-search' ); ?>
                </label>
                
                <select 
                    id="<?php echo esc_attr( $block_id ); ?>-field-select" 
                    class="search-results-sort__field-select"
                    data-current-url="<?php echo esc_url( $current_url ); ?>"
                >
                    <?php foreach ( $meta_field_options as $value => $label ) : ?>
                        <option 
                            value="<?php echo esc_attr( $value ); ?>"
                            <?php selected( $current_meta_field, $value ); ?>
                        >
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ( ! empty( $current_meta_field ) ) : ?>
            <div class="search-results-sort__order-group">
                <label for="<?php echo esc_attr( $block_id ); ?>-order-select" class="search-results-sort__label">
                    <?php echo esc_html__( 'Order:', 'wordpress-search' ); ?>
                </label>
                
                <select 
                    id="<?php echo esc_attr( $block_id ); ?>-order-select" 
                    class="search-results-sort__order-select"
                    data-current-url="<?php echo esc_url( $current_url ); ?>"
                    data-current-field="<?php echo esc_attr( $current_meta_field ); ?>"
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
            <?php endif; ?>
        </div>
    </div>
</div>

