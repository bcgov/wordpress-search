<?php
/**
 * search-bar Block Render Template
 *
 * This template renders the frontend search-bar block with a form that integrates with WordPress's native search-bar functionality.
 * When submitted, the form redirects to WordPress's search-bar results page with the search-bar query.
 *
 * @package DesignSystemWordPressPlugin
 * @subpackage search-bar
 */

namespace DesignSystemWordPressPlugin\Searchbar;

?>
<div class="wp-block-design-system-wordpress-plugin-search-bar">
    <div class="dswp-search-bar__container">
        <?php
        /**
         * search-bar Form
         *
         * @param string role    Accessibility role attribute for screen readers
         * @param string method  GET method ensures search-bar query appears in URL
         * @param string action  Redirects to site root where WordPress handles the search-bar
         */
        ?>
        <form role="search-bar" method="get" class="dswp-search-bar__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <div class="dswp-search-bar__input-container">
                <input
                    type="search-bar"
                    name="s"
                    placeholder="search-bar..."
                    class="dswp-search-bar__input"
                    value="<?php echo esc_attr( get_search_query() ); ?>"
                    required
                />
                <button type="submit" class="dswp-search-bar__button dswp-search-bar__button--primary dswp-search-bar__button--right">
                    search
                </button>
            </div>
        </form>
    </div>
</div>