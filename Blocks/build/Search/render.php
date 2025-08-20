<?php
/**
 * Search Bar Block Render Template.
 *
 * This template renders the frontend search-bar block with a form that integrates with WordPress's native search-bar functionality.
 * When submitted, the form redirects to WordPress's search-bar results page with the search-bar query.
 *
 * @package WordpressSearchWordPressPlugin
 * @subpackage search-bar
 */

namespace Bcgov\WordpressSearch\Searchbar;

// Get current URL parameters to preserve filters using WordPress functions.
$current_url_params = array();

// Use WordPress query functions to get current parameters.
$current_url = add_query_arg( null, null );

// Parse the current URL to get parameters.
$parsed_url = wp_parse_url( $current_url );
if ( isset( $parsed_url['query'] ) ) {
    parse_str( $parsed_url['query'], $url_params );

    // Filter out search query and pagination parameters.
    $excluded_params = array( 's', 'paged', 'posts_per_page' );

    foreach ( $url_params as $key => $value ) {
        if ( in_array( $key, $excluded_params, true ) || empty( $value ) ) {
            continue;
        }

        // Sanitize the key.
        $sanitized_key = sanitize_key( $key );

        // Handle array and string values.
        if ( is_array( $value ) ) {
            $sanitized_values = array_filter( array_map( 'sanitize_text_field', $value ) );
            if ( ! empty( $sanitized_values ) ) {
                $current_url_params[ $sanitized_key ] = $sanitized_values;
            }
        } else {
            $current_url_params[ $sanitized_key ] = sanitize_text_field( $value );
        }
    }
}

// Allow other plugins/themes to add custom parameters.
$current_url_params = apply_filters( 'wordpress_search_filter_parameters', $current_url_params );

?>
<div class="wp-block-wordpress-search-search-bar">
    <div class="dswp-search-bar__container">
        <form role="search-bar" method="get" class="dswp-search-bar__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <div class="dswp-search-bar__input-container">
                <div class="dswp-search-bar__input-wrapper">
                    <div class="dswp-search-bar__search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <input
                        type="search"
                        name="s"
                        placeholder="Search term"
                        class="dswp-search-bar__input"
                        value="<?php echo esc_attr( get_search_query() ); ?>"
                    />
                    <button type="button" class="dswp-search-bar__clear-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <button type="submit" class="dswp-search-bar__button dswp-search-bar__button--primary">
                    Search
                </button>
            </div>
            
            <?php
            
            // Hidden inputs to preserve all current filter parameters.
            if ( ! empty( $current_url_params ) ) :
                foreach ( $current_url_params as $param_key => $param_value ) :
                    if ( is_array( $param_value ) ) :
                        foreach ( $param_value as $array_value ) :
                            ?>
                            <input type="hidden" name="<?php echo esc_attr( $param_key ); ?>[]" value="<?php echo esc_attr( $array_value ); ?>" />
                            <?php
                        endforeach;
                    else :
                        ?>
                        <input type="hidden" name="<?php echo esc_attr( $param_key ); ?>" value="<?php echo esc_attr( $param_value ); ?>" />
                        <?php
                    endif;
                endforeach;
            endif;
            ?>
        </form>
    </div>
</div>