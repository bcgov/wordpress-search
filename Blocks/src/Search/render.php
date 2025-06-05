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

namespace Bcgov\WordpressSearch\Searchbar

?>
<div class="wp-block-wordpress-search-search-bar">
    <div class="dswp-search-bar__container">
        <?php
        /**
         * Search Bar Form.
         *
         * @param string role    Accessibility role attribute for screen readers
         * @param string method  GET method ensures search-bar query appears in URL
         * @param string action  Redirects to site root where WordPress handles the search-bar
         */
        ?>
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
                        required
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
        </form>
    </div>
</div>