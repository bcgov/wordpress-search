<?php
/**
 * Applied Filters Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchActiveFilters;

// Extract block attributes
$show_count = $attributes['showCount'] ?? true;
$show_clear_all = $attributes['showClearAll'] ?? true;

// Get current URL and query params
$current_url  = add_query_arg(null, null);
$query_params = $_GET;
$search_param = $_GET['s'] ?? '';
unset($query_params['s']); // don't treat search as a filter

$applied_filters = [];
$filter_count    = 0;

// Define how to resolve custom filters
$custom_filters = [
    'category'   => fn($v) => get_term_name(get_category($v)),
    'tag'        => fn($v) => get_term_name(get_tag($v)),
    'post_type'  => fn($v) => get_post_type_label($v),
    'author'     => fn($v) => get_user_name($v),
];

// 🛠 Helpers
function get_term_name($term) {
    return ($term && !is_wp_error($term)) ? $term->name : '';
}

function get_post_type_label($post_type) {
    $obj = get_post_type_object($post_type);
    return $obj ? $obj->labels->singular_name : '';
}

function get_user_name($id) {
    $user = get_user_by('ID', $id);
    return $user ? $user->display_name : '';
}

// 🔍 Process taxonomy_ filters
foreach ($query_params as $param => $values) {
    if (strpos($param, 'taxonomy_') !== 0) continue;

    $taxonomy = substr($param, 9);
    if (!get_taxonomy($taxonomy)) continue;

    foreach ((array) $values as $value) {
        $term = get_term($value, $taxonomy);
        if ($term && !is_wp_error($term)) {
            $applied_filters[] = [
                'type'     => 'taxonomy',
                'param'    => $param,
                'value'    => $value,
                'label'    => $term->name,
                'taxonomy' => $taxonomy,
                'term'     => $term,
            ];
            $filter_count++;
        }
    }
}

// 🔍 Process custom filters
foreach ($custom_filters as $param => $resolver) {
    if (!isset($query_params[$param])) continue;

    foreach ((array) $query_params[$param] as $value) {
        if (empty($value)) continue;

        $label = $resolver($value);
        $applied_filters[] = [
            'type'  => 'custom',
            'param' => $param,
            'value' => $value,
            'label' => $label ?: $value,
        ];
        $filter_count++;
    }
}

//  Build "clear all" URL
$clear_all_url = $search_param ? add_query_arg('s', $search_param, $current_url) : remove_query_arg(array_keys($query_params), $current_url);
?>

<div class="wp-block-wordpress-search-search-active-filters">
    <div class="search-active-filters">
        <?php if ($show_count): ?>
            <div class="search-active-filters__header">
                <span class="search-active-filters__count">
                    <?php printf(
                        _n('%d filter applied', '%d filters applied', $filter_count, 'wordpress-search'),
                        $filter_count
                    ); ?>
                </span>
                <?php if ($show_clear_all && $filter_count > 1): ?>
                    <a href="<?php echo esc_url($clear_all_url); ?>" class="search-active-filters__clear-all">
                        <?php esc_html_e('Clear all', 'wordpress-search'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="search-active-filters__chips">
            <?php foreach ($applied_filters as $filter): ?>
                <?php
                $remove_url = remove_query_arg($filter['param'], $current_url);
                $param_values = $query_params[$filter['param']] ?? [];

                // If multiple values, remove just one
                if (is_array($param_values)) {
                    $new_values = array_diff($param_values, [$filter['value']]);
                    if (!empty($new_values)) {
                        $remove_url = add_query_arg($filter['param'], $new_values, $remove_url);
                    }
                }

                if (!empty($search_param)) {
                    $remove_url = add_query_arg('s', $search_param, $remove_url);
                }
                ?>
                <div class="search-active-filters__chip"
                     data-filter-type="<?php echo esc_attr($filter['type']); ?>"
                     data-filter-param="<?php echo esc_attr($filter['param']); ?>"
                     data-filter-value="<?php echo esc_attr($filter['value']); ?>">
                    <span class="search-active-filters__chip-label"><?php echo esc_html($filter['label']); ?></span>
                    <button class="search-active-filters__chip-remove"
                            data-remove-url="<?php echo esc_url($remove_url); ?>"
                            aria-label="<?php esc_attr_e('Remove filter', 'wordpress-search'); ?>">
                        ×
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
