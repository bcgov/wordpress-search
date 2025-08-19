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
$selected_taxonomies = $attributes['selectedTaxonomies'] ?? [];

// If no taxonomies are selected, don't render anything.
if ( empty( $selected_taxonomies ) || ! is_array( $selected_taxonomies ) ) {
    return;
}

/**
 * Helper function to resolve taxonomy name with fallbacks
 *
 * @param string $document_post_type The post type.
 * @param string $taxonomy_name The taxonomy name to resolve.
 * @return string|null The resolved taxonomy name or null if not found.
 */
function resolve_taxonomy_name( $document_post_type, $taxonomy_name ) {
	// Optimized taxonomy name resolution.
	$registered_taxonomies = get_object_taxonomies( $document_post_type, 'names' );

	// If no taxonomies found for the exact post type, try case-insensitive post type matching.
	if ( empty( $registered_taxonomies ) ) {
		$all_post_types = get_post_types( array(), 'names' );
		foreach ( $all_post_types as $matched_post_type ) {
			if ( strcasecmp( $matched_post_type, $document_post_type ) === 0 ) {
				$registered_taxonomies = get_object_taxonomies( $matched_post_type, 'names' );
				if ( ! empty( $registered_taxonomies ) ) {
					$document_post_type = $matched_post_type; // Use the correctly cased post type.
					break;
				}
			}
		}
	}

	// Create a mapping for efficient lookup.
	$taxonomy_map = array_flip( $registered_taxonomies );

	// Direct validation - check exact match first.
	if ( isset( $taxonomy_map[ $taxonomy_name ] ) ) {
		return $taxonomy_name;
	}

	// If no exact match, check for case-insensitive match first.
	foreach ( $registered_taxonomies as $tax_name ) {
		if ( strcasecmp( $tax_name, $taxonomy_name ) === 0 ) {
			return $tax_name;
		}
	}

	// If still no match, check for partial matches (for backward compatibility).
	foreach ( $registered_taxonomies as $tax_name ) {
		if ( stripos( $tax_name, $taxonomy_name ) !== false ) {
			return $tax_name;
		}
	}

	return null;
}

// Get current URL parameters and filter relevant ones upfront.
$current_url = home_url( add_query_arg( null, null ) );
$url_parts   = wp_parse_url( $current_url );
parse_str( $url_parts['query'] ?? '', $all_query_params_raw );

// Fallback to $_GET if URL parsing doesn't work (e.g., in test environments).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe read-only access to $_GET for search filtering context
if ( empty( $all_query_params_raw ) && ! empty( $_GET ) ) {
    // Sanitize $_GET parameters before using them.
    $all_query_params_raw = array();
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe read-only access to $_GET for preserving search parameters, all values are sanitized
    foreach ( $_GET as $key => $value ) {
        $sanitized_key                          = sanitize_key( $key );
        $sanitized_value                        = is_array( $value )
            ? array_map( 'sanitize_text_field', $value )
            : sanitize_text_field( $value );
        $all_query_params_raw[ $sanitized_key ] = $sanitized_value;
    }
}

// Filter to get only non-taxonomy parameters for hidden inputs.
$taxonomy_param_keys = array();
foreach ( $selected_taxonomies as $selected_taxonomy ) {
	$taxonomy_parts = explode( ':', $selected_taxonomy );
	if ( count( $taxonomy_parts ) === 2 ) {
		$document_post_type = $taxonomy_parts[0];
		$taxonomy_name = $taxonomy_parts[1];
		
		$actual_taxonomy = resolve_taxonomy_name( $document_post_type, $taxonomy_name );
		
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
                return false; // Exclude taxonomy parameters
            }
        }
        return true;
    },
    ARRAY_FILTER_USE_KEY
);

?>

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

            $actual_taxonomy = resolve_taxonomy_name( $document_post_type, $selected_taxonomy_name );

            if ( ! $actual_taxonomy || ! taxonomy_exists( $actual_taxonomy ) ) {
                continue;
            }

            // Get currently selected terms (expecting term IDs).
            $current_terms = array();
            $taxonomy_param_key = TaxonomyFilter::TAXONOMY_PREFIX . $actual_taxonomy;
            if ( isset( $all_query_params_raw[ $taxonomy_param_key ] ) ) {
                $current_terms_raw = $all_query_params_raw[ $taxonomy_param_key ];
                // Handle comma-separated values
                if ( is_string( $current_terms_raw ) ) {
                    $current_terms = array_filter( array_map( 'trim', explode( ',', $current_terms_raw ) ) );
                } elseif ( is_array( $current_terms_raw ) ) {
                    // Fallback for array format (backward compatibility)
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
                            <div class="taxonomy-filter__options">
                                <?php foreach ( $terms as $taxonomy_term ) : ?>
                                    <?php
                                    $checkbox_id = TaxonomyFilter::TAXONOMY_PREFIX . $actual_taxonomy . '_' . $taxonomy_term->term_id;
                                    $is_checked  = in_array( strval( $taxonomy_term->term_id ), $current_terms, true );
                                    ?>
                                    <div class="components-checkbox-control taxonomy-filter__option">
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