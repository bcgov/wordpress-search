<?php
/**
 * Applied Filters Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchActiveFilters;

// Get current URL using WordPress functions.
$current_url = add_query_arg( null, null );

// Parse the current URL to get all parameters.
$parsed_url   = wp_parse_url( $current_url );
$query_params = array();
$search_param = '';

if ( isset( $parsed_url['query'] ) ) {
    parse_str( $parsed_url['query'], $url_params );

    // Extract search parameter.
    if ( isset( $url_params['s'] ) ) {
        $search_param = sanitize_text_field( $url_params['s'] );
        unset( $url_params['s'] );
    }

    // Remove pagination parameters.
    unset( $url_params['paged'] );
    unset( $url_params['posts_per_page'] );

    // Store all other parameters.
    $query_params = $url_params;
}

$applied_filters = [];
$filter_count    = 0;

/**
 * Get term name safely.
 *
 * @param object|WP_Error|null $term Term object or error.
 * @return string Term name or empty string.
 */
function get_term_name_safe( $term ) {
    return ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
}

/**
 * Get post type label.
 *
 * @param string $post_type Post type slug.
 * @return string Post type label or empty string.
 */
function get_post_type_label_safe( $post_type ) {
    $obj = get_post_type_object( $post_type );
    return $obj ? $obj->labels->singular_name : '';
}

/**
 * Get user display name.
 *
 * @param int $user_id User ID.
 * @return string User display name or empty string.
 */
function get_user_name_safe( $user_id ) {
    $user = get_user_by( 'ID', $user_id );
    return $user ? $user->display_name : '';
}

/**
 * Get category name safely.
 *
 * @param int $category_id Category ID.
 * @return string Category name or empty string.
 */
function get_category_name_safe( $category_id ) {
    return get_term_name_safe( get_category( $category_id ) );
}

/**
 * Get tag name safely.
 *
 * @param int $tag_id Tag ID.
 * @return string Tag name or empty string.
 */
function get_tag_name_safe( $tag_id ) {
    return get_term_name_safe( get_tag( $tag_id ) );
}

// Custom filters and their resolvers.
$custom_filters = [
    'category' => __NAMESPACE__ . '\\get_category_name_safe',
    'tag'      => __NAMESPACE__ . '\\get_tag_name_safe',
    'author'   => __NAMESPACE__ . '\\get_user_name_safe',
];

// Taxonomy filters.
foreach ( $query_params as $param => $values ) {
    if ( strpos( $param, 'taxonomy_' ) !== 0 ) {
        continue;
    }

    $taxonomy_name = substr( $param, 9 );
    if ( ! get_taxonomy( $taxonomy_name ) ) {
        continue;
    }

    // Handle comma-separated values for taxonomy filters.
    if ( is_string( $values ) ) {
        $term_values = array_filter( array_map( 'trim', explode( ',', $values ) ) );
    } else {
        $term_values = (array) $values;
    }

    foreach ( $term_values as $value ) {
        if ( ! $value ) {
            continue;
        }
        $term_obj = get_term( $value, $taxonomy_name );
        if ( is_wp_error( $term_obj ) || ! $term_obj ) {
            continue;
        }

        $applied_filters[] = [
            'type'     => 'taxonomy',
            'param'    => $param,
            'value'    => $value,
            'label'    => $term_obj->name,
            'taxonomy' => $taxonomy_name,
            'term'     => $term_obj,
        ];
        ++$filter_count;
    }
}

// Custom filters.
foreach ( $custom_filters as $param => $resolver ) {
    if ( empty( $query_params[ $param ] ) ) {
        continue;
    }

    foreach ( (array) $query_params[ $param ] as $value ) {
        if ( ! $value ) {
            continue;
        }

        $label             = $resolver( $value );
        $applied_filters[] = [
            'type'  => 'custom',
            'param' => $param,
            'value' => $value,
            'label' => $label ? $label : $value,
        ];
        ++$filter_count;
    }
}

// Build clear all URL - preserve only search parameter if it exists.
$clear_all_url = home_url( '/' );
if ( $search_param ) {
    $clear_all_url = add_query_arg( 's', $search_param, $clear_all_url );
} else {
    // If no search term, redirect to empty search results instead of homepage.
    $clear_all_url = add_query_arg( 's', '', $clear_all_url );
}

?>
<div class="wp-block-wordpress-search-search-active-filters">
    <div class="search-active-filters">
            <div class="search-active-filters__header">
                <span class="search-active-filters__count">
                    <?php
                    printf(
                        /* translators: %d: number of filters applied */
                        esc_html( _n( '%d filter applied', '%d filters applied', $filter_count, 'wordpress-search' ) ),
                        esc_html( $filter_count )
                    );
                    ?>
                </span>
                <?php if ( $filter_count > 1 ) : ?>
                    <a href="<?php echo esc_url( $clear_all_url ); ?>" class="search-active-filters__clear-all">
                        <?php esc_html_e( 'Clear all', 'wordpress-search' ); ?>
                    </a>
                <?php endif; ?>
            </div>

        <div class="search-active-filters__chips">
            <?php foreach ( $applied_filters as $filter ) : ?>
                <?php
                $param      = $filter['param'];
                $value      = $filter['value'];
                $param_vals = $query_params[ $param ] ?? '';

                // Handle comma-separated values for taxonomy filters.
                if ( strpos( $param, 'taxonomy_' ) === 0 && is_string( $param_vals ) ) {
                    $param_vals = array_filter( array_map( 'trim', explode( ',', $param_vals ) ) );
                } else {
                    $param_vals = (array) $param_vals;
                }

                $remove_url = home_url( '/' );

                if ( count( $param_vals ) > 1 ) {
                    $new_vals = array_diff( $param_vals, [ $value ] );
                    // For taxonomy filters, join back as comma-separated.
                    if ( strpos( $param, 'taxonomy_' ) === 0 ) {
                        $remove_url = add_query_arg( $param, implode( ',', $new_vals ), $remove_url );
                    } else {
                        $remove_url = add_query_arg( $param, $new_vals, $remove_url );
                    }
                }

                // Add back all other filter parameters.
                foreach ( $query_params as $other_param => $other_value ) {
                    if ( $other_param !== $param ) {
                        if ( is_array( $other_value ) ) {
                            $remove_url = add_query_arg( $other_param, $other_value, $remove_url );
                        } else {
                            $remove_url = add_query_arg( $other_param, $other_value, $remove_url );
                        }
                    }
                }

                // Always add search parameter to stay on search results page.
                if ( $search_param ) {
                    $remove_url = add_query_arg( 's', $search_param, $remove_url );
                } else {
                    // If no search term, add empty search to stay on search results instead of homepage.
                    $remove_url = add_query_arg( 's', '', $remove_url );
                }
                ?>
                <div class="search-active-filters__chip"
                     data-filter-type="<?php echo esc_attr( $filter['type'] ); ?>"
                     data-filter-param="<?php echo esc_attr( $param ); ?>"
                     data-filter-value="<?php echo esc_attr( $value ); ?>">
                    <span class="search-active-filters__chip-label"><?php echo esc_html( $filter['label'] ); ?></span>
                    <button class="search-active-filters__chip-remove"
                            data-remove-url="<?php echo esc_url( $remove_url ); ?>"
                            aria-label="<?php esc_attr_e( 'Remove filter', 'wordpress-search' ); ?>">
                        ×
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
