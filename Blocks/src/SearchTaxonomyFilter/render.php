<?php
/**
 * Search Taxonomy Filter Block - Frontend Render
 *
 * @package SearchPlugin
 */

// Get the selected taxonomy from block attributes
$selected_taxonomy = $attributes['selectedTaxonomy'] ?? '';

// If no taxonomy is selected, don't render anything
if (empty($selected_taxonomy)) {
    return;
}

// Parse the taxonomy (format: "posttype:taxonomy")
$taxonomy_parts = explode(':', $selected_taxonomy);
if (count($taxonomy_parts) !== 2) {
    return;
}

$post_type = $taxonomy_parts[0];
$taxonomy = $taxonomy_parts[1];

// Optimized taxonomy name resolution
$registered_taxonomies = get_object_taxonomies($post_type, 'names');

// Create a mapping for efficient lookup
$taxonomy_map = array_flip($registered_taxonomies);

// Direct validation - check exact match first
if (isset($taxonomy_map[$taxonomy])) {
    $actual_taxonomy = $taxonomy;
} else {
    // If no exact match, check for partial matches (for backward compatibility)
    $actual_taxonomy = null;
    foreach ($registered_taxonomies as $tax_name) {
        if (strpos($tax_name, $taxonomy) !== false) {
            $actual_taxonomy = $tax_name;
            break;
        }
    }
}

if (!$actual_taxonomy || !taxonomy_exists($actual_taxonomy)) {
    return;
}

// Get current URL parameters and filter relevant ones upfront
$current_url = home_url(add_query_arg(null, null));
$url_parts = wp_parse_url($current_url);
parse_str($url_parts['query'] ?? '', $all_query_params);

// Filter to get only non-taxonomy parameters for hidden inputs
$taxonomy_param_key = 'taxonomy_' . $actual_taxonomy;
$hidden_params = array_filter($all_query_params, function($key) use ($taxonomy_param_key) {
    return strpos($key, $taxonomy_param_key) !== 0;
}, ARRAY_FILTER_USE_KEY);

// Get currently selected terms (expecting term IDs)
$current_terms = array();
if (isset($all_query_params[$taxonomy_param_key])) {
    $current_terms = is_array($all_query_params[$taxonomy_param_key]) 
        ? $all_query_params[$taxonomy_param_key] 
        : array($all_query_params[$taxonomy_param_key]);
}

// Convert to strings for comparison
$current_terms = array_map('strval', $current_terms);

// Get possible terms
$terms = get_terms(array(
    'taxonomy' => $actual_taxonomy,
    'hide_empty' => false,
));

// Get taxonomy label
$taxonomy_object = get_taxonomy($actual_taxonomy);
$taxonomy_label = $taxonomy_object ? $taxonomy_object->labels->singular_name : ucwords(str_replace('_', ' ', $actual_taxonomy));

?>

<div class="wp-block-wordpress-search-taxonomy-filter">
    <div class="search-taxonomy-filter__container">
        <?php if (is_wp_error($terms)) : ?>
            <div class="taxonomy-filter-error">
                <?php echo esc_html__('Error loading taxonomy terms.', 'wordpress-search'); ?>
            </div>
        <?php elseif (empty($terms)) : ?>
            <div class="taxonomy-filter-empty">
                <?php echo esc_html__('No terms available in this taxonomy.', 'wordpress-search'); ?>
            </div>
        <?php else : ?>
            <form class="taxonomy-filter-form" method="get" data-taxonomy="<?php echo esc_attr($actual_taxonomy); ?>">
                <?php foreach ($hidden_params as $key => $value) : ?>
                    <?php if (is_array($value)) : ?>
                        <?php foreach ($value as $val) : ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($val); ?>">
                        <?php endforeach; ?>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <fieldset class="taxonomy-filter">
                    <div class="taxonomy-filter__header" onclick="toggleTaxonomyFilter(this)">
                        <legend class="taxonomy-filter__label"><?php echo esc_html($taxonomy_label); ?></legend>
                        <div class="taxonomy-filter__toggle"></div>
                    </div>
                    
                    <div class="taxonomy-filter__content">
                        <div class="taxonomy-filter__options">
                            <?php foreach ($terms as $term) : ?>
                                <?php
                                $checkbox_id = 'taxonomy_' . $actual_taxonomy . '_' . $term->term_id;
                                $is_checked = in_array(strval($term->term_id), $current_terms, true);
                                ?>
                                <div class="components-checkbox-control taxonomy-filter__option">
                                    <input 
                                        type="checkbox" 
                                        id="<?php echo esc_attr($checkbox_id); ?>"
                                        name="taxonomy_<?php echo esc_attr($actual_taxonomy); ?>[]" 
                                        value="<?php echo esc_attr($term->term_id); ?>"
                                        <?php checked($is_checked); ?>
                                        class="components-checkbox-control__input taxonomy-filter__checkbox"
                                    >
                                    <label class="components-checkbox-control__label taxonomy-filter__option-label" for="<?php echo esc_attr($checkbox_id); ?>">
                                        <?php echo esc_html($term->name); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </fieldset>
            </form>
        <?php endif; ?>
    </div>
</div> 