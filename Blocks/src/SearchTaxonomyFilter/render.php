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

// Get the actual registered taxonomy name
$registered_taxonomies = get_object_taxonomies($post_type, 'names');

// Find the matching taxonomy
$actual_taxonomy = null;
foreach ($registered_taxonomies as $tax_name) {
    if ($tax_name === $taxonomy) {
        $actual_taxonomy = $tax_name;
        break;
    }
}

if (!$actual_taxonomy) {
    foreach ($registered_taxonomies as $tax_name) {
        // Check if the registered taxonomy contains the selected taxonomy
        if (strpos($tax_name, $taxonomy) !== false) {
            $actual_taxonomy = $tax_name;
            break;
        }
    }
}

if (!$actual_taxonomy) {
    return;
}

$taxonomy = $actual_taxonomy;

// Verify the taxonomy exists
if (!taxonomy_exists($taxonomy)) {
    return;
}

// Get current URL parameters
$current_url = home_url(add_query_arg(null, null));
$url_parts = wp_parse_url($current_url);
parse_str($url_parts['query'] ?? '', $query_params);

// Get currently selected terms (expecting term IDs)
$current_terms = array();
if (isset($query_params['taxonomy_' . $taxonomy]) && is_array($query_params['taxonomy_' . $taxonomy])) {
    $current_terms = $query_params['taxonomy_' . $taxonomy];
} elseif (isset($query_params['taxonomy_' . $taxonomy])) {
    $current_terms = array($query_params['taxonomy_' . $taxonomy]);
}

// Convert to strings for comparison
$current_terms = array_map('strval', $current_terms);

// Get possible terms
$terms = get_terms(array(
    'taxonomy' => $taxonomy,
    'hide_empty' => false,
));

// Get taxonomy label
$taxonomy_object = get_taxonomy($taxonomy);
$taxonomy_label = $taxonomy_object ? $taxonomy_object->labels->singular_name : ucwords(str_replace('_', ' ', $taxonomy));

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
            <form class="taxonomy-filter-form" method="get">
                <?php foreach ($query_params as $key => $value) : ?>
                    <?php if (strpos($key, 'taxonomy_' . $taxonomy) !== 0) : ?>
                        <?php if (is_array($value)) : ?>
                            <?php foreach ($value as $val) : ?>
                                <input type="hidden" name="<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($val); ?>">
                            <?php endforeach; ?>
                        <?php else : ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php endif; ?>
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
                                $checkbox_id = 'taxonomy_' . $taxonomy . '_' . $term->term_id;
                                $is_checked = in_array(strval($term->term_id), $current_terms, true);
                                ?>
                                <div class="components-checkbox-control taxonomy-filter__option">
                                    <input 
                                        type="checkbox" 
                                        id="<?php echo esc_attr($checkbox_id); ?>"
                                        name="taxonomy_<?php echo esc_attr($taxonomy); ?>[]" 
                                        value="<?php echo esc_attr($term->term_id); ?>"
                                        <?php checked($is_checked); ?>
                                        class="components-checkbox-control__input taxonomy-filter__checkbox"
                                        onchange="this.form.submit()"
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