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

// Get the selected metadata field from block attributes
$selected_metadata = $attributes['selectedMetadata'] ?? '';

// If no metadata field is selected, don't render anything
if (empty($selected_metadata)) {
    return;
}

// Parse the metadata field (format: "posttype:fieldname")
$metadata_parts = explode(':', $selected_metadata);
if (count($metadata_parts) !== 2) {
    return;
}

$post_type = $metadata_parts[0];
$field_name = $metadata_parts[1];

// Get current URL parameters
$current_url = $_SERVER['REQUEST_URI'];
$url_parts = parse_url($current_url);
parse_str($url_parts['query'] ?? '', $query_params);

// Get currently selected values for this metadata field
$current_values = array();
if (isset($query_params['metadata_' . $field_name]) && is_array($query_params['metadata_' . $field_name])) {
    $current_values = $query_params['metadata_' . $field_name];
} elseif (isset($query_params['metadata_' . $field_name])) {
    $current_values = array($query_params['metadata_' . $field_name]);
}

// Fetch all possible values for this metadata field from the document post type
if (!function_exists('Bcgov\WordpressSearch\SearchMetadataFilter\get_metadata_values')) {
    function get_metadata_values($post_type, $field_name) {
        global $wpdb;
        
        // Validate inputs
        if (empty($post_type) || empty($field_name)) {
            return array();
        }
        
        // Query to get all unique values for this meta key
        $query = $wpdb->prepare("
            SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE p.post_type = %s 
            AND pm.meta_key = %s 
            AND pm.meta_value != '' 
            AND p.post_status = 'publish'
            ORDER BY pm.meta_value ASC
        ", $post_type, $field_name);
        
        $results = $wpdb->get_col($query);
        
        // Check for database errors
        if ($wpdb->last_error) {
            error_log('Database error in get_metadata_values: ' . $wpdb->last_error);
            return array();
        }
        
        // Return filtered results or empty array if no results
        return is_array($results) ? array_filter($results) : array();
    }
}

$possible_values = get_metadata_values($post_type, $field_name);

// If no values found, don't render anything
if (empty($possible_values)) {
    return;
}

// Get a human-readable label for the field
$field_label = ucwords(str_replace('_', ' ', $field_name));

// Helper function to generate clear filter URL
if (!function_exists('Bcgov\WordpressSearch\SearchMetadataFilter\get_clear_filter_url')) {
    function get_clear_filter_url($current_url, $query_params, $field_name) {
        // Remove all parameters that start with 'metadata_' + field_name
        $filtered_params = array_filter($query_params, function($key) use ($field_name) {
            return strpos($key, 'metadata_' . $field_name) !== 0;
        }, ARRAY_FILTER_USE_KEY);
        
        // Build the new URL
        $base_url = strtok($current_url, '?');
        return empty($filtered_params) ? $base_url : $base_url . '?' . http_build_query($filtered_params);
    }
}

?>

<div class="wp-block-wordpress-search-metadata-filter">
    <div class="search-metadata-filter__container">
        <form class="metadata-filter-form" method="get">
            <!-- Preserve existing query parameters -->
            <?php foreach ($query_params as $key => $value): ?>
                <?php if (strpos($key, 'metadata_' . $field_name) !== 0): ?>
                    <?php if (is_array($value)): ?>
                        <?php foreach ($value as $val): ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($val); ?>">
                        <?php endforeach; ?>
                    <?php else: ?>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <fieldset class="metadata-filter">
                <legend class="metadata-filter__label">
                    <?php echo esc_html__('Filter by', 'wordpress-search'); ?> <?php echo esc_html($field_label); ?>:
                </legend>
                
                <div class="metadata-filter__options">
                    <?php foreach ($possible_values as $value): ?>
                        <?php 
                        $checkbox_id = 'metadata_' . $field_name . '_' . sanitize_title($value);
                        $is_checked = in_array($value, $current_values);
                        ?>
                        <label class="metadata-filter__option" for="<?php echo esc_attr($checkbox_id); ?>">
                            <input 
                                type="checkbox" 
                                id="<?php echo esc_attr($checkbox_id); ?>"
                                name="metadata_<?php echo esc_attr($field_name); ?>[]" 
                                value="<?php echo esc_attr($value); ?>"
                                <?php checked($is_checked); ?>
                                class="metadata-filter__checkbox"
                            >
                            <span class="metadata-filter__option-label">
                                <?php echo esc_html($value); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="metadata-filter__actions">
                    <button type="submit" class="metadata-filter__apply-button">
                        <?php echo esc_html__('Apply Filters', 'wordpress-search'); ?>
                    </button>
                    
                    <?php if (!empty($current_values)): ?>
                        <a href="<?php echo esc_url(get_clear_filter_url($current_url, $query_params, $field_name)); ?>" class="metadata-filter__clear-button">
                            <?php echo esc_html__('Clear Filters', 'wordpress-search'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </fieldset>
        </form>
    </div>
</div>