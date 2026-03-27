<?php
/**
 * Search Bar Block Render Template.
 *
 * This template renders the frontend search-bar block with a form that integrates with WordPress's native search-bar functionality.
 * When submitted, the form redirects to WordPress's native search results with the query.
 *
 * @package WordpressSearchWordPressPlugin
 * @subpackage search-bar
 */

namespace Bcgov\WordpressSearch\Searchbar;

use Bcgov\WordpressSearch\SearchBarBlock;

$attrs               = SearchBarBlock::normalized_attributes( $attributes ?? array(), $block ?? null );
$submit_button_class = SearchBarBlock::submit_button_class( $attrs );
$current_url_params  = SearchBarBlock::preserved_query_params();

if ( isset( $block ) && $block instanceof \WP_Block ) {
	$wrapper_attributes = get_block_wrapper_attributes( array(), $block );
} else {
	$class_name         = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';
	$wrapper_classes    = trim( 'wp-block-wordpress-search-search-bar ' . $class_name );
	$wrapper_attributes = 'class="' . esc_attr( preg_replace( '/\s+/', ' ', $wrapper_classes ) ) . '"';
}

?>
<?php
// PHPCS: wrap opening tag with wp_kses_post(); raw `echo $wrapper_attributes` matches core examples but fails EscapeOutput here. Never esc_attr() the blob from get_block_wrapper_attributes().
echo wp_kses_post( sprintf( '<div %s>', $wrapper_attributes ) );
?>
    <div class="dswp-search-bar__container">
        <form role="search" method="get" class="dswp-search-bar__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
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
                        placeholder="<?php echo esc_attr__( 'Search term', 'wordpress-search' ); ?>"
                        class="dswp-search-bar__input"
                        value="<?php echo esc_attr( get_search_query() ); ?>"
                    />
                    <button type="button" class="dswp-search-bar__clear-button" aria-label="<?php echo esc_attr__( 'Clear search', 'wordpress-search' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <button type="submit" class="<?php echo esc_attr( $submit_button_class ); ?>">
                    <?php echo esc_html__( 'Search', 'wordpress-search' ); ?>
                </button>
            </div>
            <?php if ( ! empty( $current_url_params ) ) : ?>
                <?php foreach ( $current_url_params as $param_key => $param_value ) : ?>
                    <?php if ( is_array( $param_value ) ) : ?>
                        <?php foreach ( $param_value as $array_value ) : ?>
                            <input type="hidden" name="<?php echo esc_attr( $param_key ); ?>[]" value="<?php echo esc_attr( $array_value ); ?>" />
                        <?php endforeach; ?>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr( $param_key ); ?>" value="<?php echo esc_attr( $param_value ); ?>" />
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </form>
    </div>
</div>
