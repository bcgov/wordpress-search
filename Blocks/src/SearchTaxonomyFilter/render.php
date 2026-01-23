<?php
/**
 * Search Taxonomy Filter Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchTaxonomyFilter;

// Used to import TAXONOMY_PREFIX variable.
use Bcgov\WordpressSearch\TaxonomyFilter;

// Get the selected taxonomies from block attributes.
// Support both singular and plural attribute names for backward compatibility.
$selected_taxonomies = $attributes['selectedTaxonomies'] ?? $attributes['selectedTaxonomy'] ?? [];

// Convert single taxonomy to array format if needed.
if ( ! is_array( $selected_taxonomies ) && ! empty( $selected_taxonomies ) ) {
    $selected_taxonomies = array( $selected_taxonomies );
}

// If no taxonomies are selected, don't render anything.
if ( empty( $selected_taxonomies ) || ! is_array( $selected_taxonomies ) ) {
    return;
}


// Get current URL parameters using WordPress native functions.
$all_query_params_raw = array();

// Get all registered query vars from WordPress.
global $wp_query;
$query_vars = $wp_query->query_vars;

if ( ! empty( $query_vars ) ) {
    foreach ( $query_vars as $key => $value ) {
        if ( ! empty( $value ) ) {
            $sanitized_key                          = sanitize_key( $key );
            $sanitized_value                        = is_array( $value )
                ? array_map( 'sanitize_text_field', $value )
                : sanitize_text_field( $value );
            $all_query_params_raw[ $sanitized_key ] = $sanitized_value;
        }
    }
}

// Filter to get only non-taxonomy parameters for hidden inputs.
$taxonomy_param_keys = array();
foreach ( $selected_taxonomies as $selected_taxonomy ) {
	$taxonomy_parts = explode( ':', $selected_taxonomy );
	if ( count( $taxonomy_parts ) === 2 ) {
		$document_post_type = $taxonomy_parts[0];
		$taxonomy_name      = $taxonomy_parts[1];

		$actual_taxonomy = TaxonomyFilter::resolve_taxonomy_name( $document_post_type, $taxonomy_name );

		if ( $actual_taxonomy && taxonomy_exists( $actual_taxonomy ) ) {
			$taxonomy_param_keys[] = TaxonomyFilter::TAXONOMY_PREFIX . $actual_taxonomy;
		}
	}
}

// If no valid taxonomies found, don't render anything.
if ( empty( $taxonomy_param_keys ) ) {
    return;
}

$hidden_params = array_filter(
    $all_query_params_raw,
    function ( $key ) use ( $taxonomy_param_keys ) {
        foreach ( $taxonomy_param_keys as $taxonomy_param_key ) {
            if ( strpos( $key, $taxonomy_param_key ) === 0 ) {
                return false; // Exclude taxonomy parameters.
            }
        }
        return true;
    },
    ARRAY_FILTER_USE_KEY
);

?>

<form class="taxonomy-filter-form" method="get">
<div class="wp-block-wordpress-search-taxonomy-filter">
    <div class="search-taxonomy-filter__container">
        <?php foreach ( $selected_taxonomies as $selected_taxonomy ) : ?>
            <?php
            // Parse the taxonomy (format: "posttype:taxonomy").
            $taxonomy_parts = explode( ':', $selected_taxonomy );
            if ( count( $taxonomy_parts ) !== 2 ) {
                continue;
            }

            $document_post_type     = $taxonomy_parts[0];
            $selected_taxonomy_name = $taxonomy_parts[1];

            $actual_taxonomy = TaxonomyFilter::resolve_taxonomy_name( $document_post_type, $selected_taxonomy_name );

            if ( ! $actual_taxonomy || ! taxonomy_exists( $actual_taxonomy ) ) {
                continue;
            }

            // Get currently selected terms (expecting term IDs).
            $current_terms      = array();
            $taxonomy_param_key = TaxonomyFilter::TAXONOMY_PREFIX . $actual_taxonomy;
            if ( isset( $all_query_params_raw[ $taxonomy_param_key ] ) ) {
                $current_terms_raw = $all_query_params_raw[ $taxonomy_param_key ];
                // Handle comma-separated values.
                if ( is_string( $current_terms_raw ) ) {
                    $current_terms = array_filter( array_map( 'trim', explode( ',', $current_terms_raw ) ) );
                } elseif ( is_array( $current_terms_raw ) ) {
                    // Fallback for array format (backward compatibility).
                    $current_terms = $current_terms_raw;
                } else {
                    $current_terms = array( $current_terms_raw );
                }
            }

            // Convert to strings for comparison.
            $current_terms = array_map( 'strval', $current_terms );

            // Get possible terms.
            $terms = get_terms(
                array(
                    'taxonomy'   => $actual_taxonomy,
                    'hide_empty' => false,
                )
            );

            // Get taxonomy label.
            $taxonomy_object = get_taxonomy( $actual_taxonomy );
            $taxonomy_label  = $taxonomy_object ? $taxonomy_object->labels->singular_name : ucwords( str_replace( '_', ' ', $actual_taxonomy ) );
            ?>

            <?php if ( is_wp_error( $terms ) ) : ?>
                <div class="taxonomy-filter-error">
                    <?php echo esc_html__( 'Error loading taxonomy terms.', 'wordpress-search' ); ?>
                </div>
            <?php elseif ( empty( $terms ) ) : ?>
                <div class="taxonomy-filter-empty">
                    <?php echo esc_html__( 'No terms available in this taxonomy.', 'wordpress-search' ); ?>
                </div>
            <?php else : ?>
                <div class="taxonomy-filter-section" data-taxonomy="<?php echo esc_attr( $actual_taxonomy ); ?>">
                    <fieldset class="taxonomy-filter">
                        <div class="taxonomy-filter__header" onclick="toggleTaxonomyFilter(this)">
                            <legend class="taxonomy-filter__label"><?php echo esc_html( $taxonomy_label ); ?></legend>
                            <div class="taxonomy-filter__toggle"></div>
                        </div>
                        
                        <div class="taxonomy-filter__content">
                            <div class="taxonomy-filter__options" id="taxonomy-filter-<?php echo esc_attr( $actual_taxonomy ); ?>-options">
                                <?php
                                $terms_count   = count( $terms );
                                $show_view_all = $terms_count > 5;
                                $display_limit = 5;
                                $term_index    = 0;
                                foreach ( $terms as $taxonomy_term ) :
                                    ++$term_index;
                                    $checkbox_id  = TaxonomyFilter::TAXONOMY_PREFIX . $actual_taxonomy . '_' . $taxonomy_term->term_id;
                                    $is_checked   = in_array( strval( $taxonomy_term->term_id ), $current_terms, true );
                                    $is_hidden    = $show_view_all && $term_index > $display_limit;
                                    $option_class = 'components-checkbox-control taxonomy-filter__option';
                                    if ( $is_hidden ) {
                                        $option_class .= ' taxonomy-filter__option--hidden';
                                    }
                                    ?>
                                    <div class="<?php echo esc_attr( $option_class ); ?>">
                                        <input 
                                            type="checkbox" 
                                            id="<?php echo esc_attr( $checkbox_id ); ?>"
                                            name="<?php echo esc_attr( TaxonomyFilter::TAXONOMY_PREFIX . $actual_taxonomy ); ?>[]" 
                                            value="<?php echo esc_attr( $taxonomy_term->term_id ); ?>"
                                            <?php checked( $is_checked ); ?>
                                            class="components-checkbox-control__input taxonomy-filter__checkbox"
                                        >
                                        <label class="components-checkbox-control__label taxonomy-filter__option-label" for="<?php echo esc_attr( $checkbox_id ); ?>">
                                            <?php echo esc_html( $taxonomy_term->name ); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ( $show_view_all ) : ?>
                                    <div class="taxonomy-filter__view-all-wrapper">
                                        <button 
                                            type="button"
                                            class="taxonomy-filter__view-all-link"
                                            aria-expanded="false"
                                            aria-controls="taxonomy-filter-<?php echo esc_attr( $actual_taxonomy ); ?>-options"
                                            <?php /* translators: 1: number of terms, 2: taxonomy label */ ?>
                                            aria-label="<?php echo esc_attr( sprintf( __( 'View all %1$d %2$s options', 'wordpress-search' ), $terms_count, $taxonomy_label ) ); ?>"
                                            data-taxonomy="<?php echo esc_attr( $actual_taxonomy ); ?>"
                                        >
                                            <span class="taxonomy-filter__view-all-text"><?php echo esc_html__( 'View all', 'wordpress-search' ); ?></span>
                                            <span class="taxonomy-filter__view-all-count" aria-hidden="true">(<?php echo esc_html( $terms_count - $display_limit ); ?>)</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </fieldset>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Apply Button -->
        <div class="taxonomy-filter-apply">
            <button type="button" class="taxonomy-filter-apply__button" onclick="applyTaxonomyFilters()">
                <?php echo esc_html__( 'Apply Filters', 'wordpress-search' ); ?>
            </button>
        </div>
    </div>
</div>
</form> 