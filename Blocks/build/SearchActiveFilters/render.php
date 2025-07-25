<?php
/**
 * Applied Filters Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchActiveFilters;

// Note: Nonce verification would be handled at the form submission level.



$current_url = add_query_arg( null, null );

// Verify nonce for security.
if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'search_filters' ) ) {
    // Nonce verification failed, but we'll continue for display purposes.
    wp_die( esc_html__( 'Security check failed.', 'wordpress-search' ) );
}

$query_params = wp_unslash( $_GET );
$search_param = sanitize_text_field( $query_params['s'] ?? '' );
unset( $query_params['s'] );

$applied_filters = [];
$filter_count    = 0;

// Inline helper functions (used only once).
$get_term_name       = function ( $term ) {
    return ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
};
$get_post_type_label = function ( $pt ) {
    $obj = get_post_type_object( $pt );
    return $obj ? $obj->labels->singular_name : '';
};
$get_user_name       = function ( $id ) {
    $user = get_user_by( 'ID', $id );
    return $user ? $user->display_name : '';
};

// Custom filters and their resolvers.
$custom_filters = [
    'category'  => fn( $v ) => $get_term_name( get_category( $v ) ),
    'tag'       => fn( $v ) => $get_term_name( get_tag( $v ) ),
    'post_type' => $get_post_type_label,
    'author'    => $get_user_name,
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

    foreach ( (array) $values as $value ) {
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

//  Custom filters.
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

// Build clear all URL.
$clear_all_url = $search_param
    ? add_query_arg( 's', $search_param, $current_url )
    : remove_query_arg( array_keys( $query_params ), $current_url );
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
                $param_vals = (array) ( $query_params[ $param ] ?? [] );
                $remove_url = $current_url;

                if ( count( $param_vals ) > 1 ) {
                    $new_vals   = array_diff( $param_vals, [ $value ] );
                    $remove_url = add_query_arg( $param, $new_vals, remove_query_arg( $param, $remove_url ) );
                } else {
                    $remove_url = remove_query_arg( $param, $remove_url );
                }

                if ( $search_param ) {
                    $remove_url = add_query_arg( 's', $search_param, $remove_url );
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
