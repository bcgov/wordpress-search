<?php
/**
 * Search Metadata Filter Block - Frontend Render
 *
 * Renders the frontend interface for the Search Metadata Filter block.
 * This file handles the server-side rendering of the metadata filter checkboxes,
 * showing all possible values for the selected metadata field.
 *
 * @package SearchPlugin
 * @subpackage SearchMetadataFilter
 */

namespace Bcgov\WordpressSearch\SearchMetadataFilter;

// Get the selected metadata field from block attributes.
$selected_metadata = $attributes['selectedMetadata'] ?? '';

// If no metadata field is selected, don't render anything.
if ( empty( $selected_metadata ) ) {
    return;
}

// Parse the metadata field (format: "posttype:fieldname").
$metadata_parts = explode( ':', $selected_metadata );
if ( count( $metadata_parts ) !== 2 ) {
    return;
}

$metadata_post_type = $metadata_parts[0];
$field_name         = $metadata_parts[1];

// Get current URL parameters using WordPress functions.
$current_url = home_url( add_query_arg( null, null ) );
$url_parts   = wp_parse_url( $current_url );
parse_str( $url_parts['query'] ?? '', $query_params );

// Get currently selected values for this metadata field.
$current_values = array();
if ( isset( $query_params[ 'metadata_' . $field_name ] ) && is_array( $query_params[ 'metadata_' . $field_name ] ) ) {
    $current_values = $query_params[ 'metadata_' . $field_name ];
} elseif ( isset( $query_params[ 'metadata_' . $field_name ] ) ) {
    $current_values = array( $query_params[ 'metadata_' . $field_name ] );
}

// Get possible values using instance method.
$metadata_filter = new \Bcgov\WordpressSearch\MetadataFilter();
$possible_values = $metadata_filter->get_metadata_values( $metadata_post_type, $field_name );

// If no values found, don't render anything.
if ( empty( $possible_values ) ) {
    return;
}

// Get a human-readable label for the field.
$field_label = ucwords( str_replace( '_', ' ', $field_name ) );

// Generate clear filter URL inline.
$filtered_params = array_filter(
    $query_params,
    function ( $key ) use ( $field_name ) {
		return strpos( $key, 'metadata_' . $field_name ) !== 0;
	},
    ARRAY_FILTER_USE_KEY
);
$clear_url       = empty( $filtered_params ) ? strtok( $current_url, '?' ) : strtok( $current_url, '?' ) . '?' . http_build_query( $filtered_params );

?>

<div class="wp-block-wordpress-search-metadata-filter">
    <div class="search-metadata-filter__container">
        <form class="metadata-filter-form" method="get">
            <!-- Preserve existing query parameters -->
            <?php foreach ( $query_params as $key => $value ) : ?>
                <?php if ( strpos( $key, 'metadata_' . $field_name ) !== 0 ) : ?>
                    <?php if ( is_array( $value ) ) : ?>
                        <?php foreach ( $value as $val ) : ?>
                            <input type="hidden" name="<?php echo esc_attr( $key ); ?>[]" value="<?php echo esc_attr( $val ); ?>">
                        <?php endforeach; ?>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <fieldset class="metadata-filter">
                <div class="metadata-filter__header" onclick="toggleMetadataFilter(this)">
                    <legend class="metadata-filter__label">
                        <?php echo esc_html( $field_label ); ?>
                    </legend>
                    <div class="metadata-filter__toggle"></div>
                </div>
                
                <div class="metadata-filter__content">
                    <div class="metadata-filter__options">
                    <?php foreach ( $possible_values as $value ) : ?>
                        <?php
                        $checkbox_id = 'metadata_' . $field_name . '_' . sanitize_title( $value );
                        $is_checked  = in_array( $value, $current_values, true );
                        ?>
                        <div class="components-checkbox-control metadata-filter__option">
                            <input 
                                type="checkbox" 
                                id="<?php echo esc_attr( $checkbox_id ); ?>"
                                name="metadata_<?php echo esc_attr( $field_name ); ?>[]" 
                                value="<?php echo esc_attr( $value ); ?>"
                                <?php checked( $is_checked ); ?>
                                class="components-checkbox-control__input metadata-filter__checkbox"
                                onchange="this.form.submit()"
                            >
                            <label class="components-checkbox-control__label metadata-filter__option-label" for="<?php echo esc_attr( $checkbox_id ); ?>">
                                <?php echo esc_html( $value ); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </fieldset>
        </form>
    </div>
</div>

<script>
function toggleMetadataFilter(header) {
    const content = header.nextElementSibling;
    const toggle = header.querySelector('.metadata-filter__toggle');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        toggle.classList.remove('collapsed');
    } else {
        content.classList.add('collapsed');
        toggle.classList.add('collapsed');
    }
}
</script>