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
        <form role="search" method="get" class="dswp-search-bar__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <div class="dswp-search-bar__input-container">
                <div class="dswp-search-bar__search-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.514 16.506L21 21ZM19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="dswp-search-bar__input-wrapper">
                    <input
                        type="search"
                        name="s"
                        placeholder="Search term"
                        class="dswp-search-bar__input"
                        value="<?php echo esc_attr( get_search_query() ); ?>"
                        required
                    />
                    <button type="button" class="dswp-search-bar__clear-button" aria-label="Clear search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
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