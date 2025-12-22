<?php
/**
 * Enhanced Search Highlight Class
 *
 * Provides intelligent search term highlighting across WordPress content.
 * Features include metadata highlighting, customizable selectors, and
 * performance optimizations.
 *
 * @package WordPressSearch
 * @since 2.0.0
 * @author BC Gov
 */

namespace Bcgov\WordpressSearch;

/**
 * SearchHighlight class
 */
class SearchHighlight {
    /**
     * Search terms cache
     *
     * @var array|null $terms Cache for search terms
     */
    private static $terms = null;

    /**
     * Version for cache busting
     */
    const VERSION = '2.0.0';

    /**
     * Default selectors for highlighting (same as highlight-search-terms plugin)
     */
    const DEFAULT_SELECTORS = array(
        '#groups-dir-list', // BuddyPress.
        '#members-dir-list', // BuddyPress.
        'div.bbp-topic-content,div.bbp-reply-content,li.bbp-forum-info,.bbp-topic-title,.bbp-reply-title', // bbPress.
        'article',
        'div.hentry',
        'div.post',
        'div.post-content',
        'div.content',
        'div.page-content',
        'div.page',
        'div.wp-block-query', // Gutenberg query block.
        '.wp-block-wordpress-search-search-results-post-metadata-display', // Metadata block.
        '.post-metadata', // Metadata sections.
        '.metadata-list',
        '.metadata-item',
        '.metadata-label',
        '.metadata-value',
        'main',
        '#content',
        '#main',
        '#middle',
        '#container',
        'div.container',
        '#wrapper',
        'body', // Last, but not least.
    );

    /**
     * Default events to listen for.
     */
    const DEFAULT_EVENTS = array(
        'DOMContentLoaded',
        'load',
    );

    /**
     * Initialize the search highlight functionality.
     *
     * Sets up WordPress hooks for asset enqueuing and content filtering.
     * Called automatically when the class is instantiated.
     *
     * @since 2.0.0
     * @return void
     */
    public function init() {
        // Only run on frontend and when search terms exist.
        if ( ! is_admin() && self::get_search_terms() ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
            add_filter( 'the_title', array( $this, 'highlight_search_terms_in_title' ), 10, 2 );
            add_filter( 'the_excerpt', array( $this, 'highlight_search_terms_in_excerpt' ) );
            add_filter( 'get_the_excerpt', array( $this, 'highlight_search_terms_in_excerpt' ) );
            add_filter( 'the_content', array( $this, 'highlight_search_terms_in_content' ) );
            add_filter( 'wp_trim_excerpt', array( $this, 'highlight_search_terms_in_excerpt' ) );

            // Add analytics tracking.
            add_action( 'wp_footer', array( $this, 'add_analytics' ) );
        }
    }

    /**
     * Get search terms from various sources.
     *
     * Retrieves search terms from WordPress search query, GET parameters,
     * or referrer. Results are cached for performance.
     *
     * @since 2.0.0
     * @return array Array of search terms
     */
    public static function get_search_terms() {
        // Return cached terms if available.
        if ( null !== self::$terms ) {
            return self::$terms;
        }

        $terms = array();

        // Try WordPress search query first.
        $search_query = get_search_query();
        if ( ! empty( $search_query ) ) {
            $terms = self::parse_search_terms( $search_query );
        }

        // Try GET parameters as fallback.
        if ( empty( $terms ) ) {
            $get_terms = array( 's', 'q', 'search', 'query' );
            foreach ( $get_terms as $param ) {
                $value = filter_input( INPUT_GET, $param, FILTER_SANITIZE_STRING );
                if ( ! empty( $value ) ) {
                    $terms = self::parse_search_terms( $value );
                    break;
                }
            }
        }

        // Try referrer search terms as last resort.
        if ( empty( $terms ) ) {
            $terms = self::get_referrer_terms();
        }

        // Cache and return terms.
        self::$terms = $terms;
        return $terms;
    }

    /**
     * Parse search terms intelligently.
     *
     * @param string $search_string The search string to parse.
     * @return array Array of parsed search terms
     */
    private static function parse_search_terms( $search_string ) {
        $terms = array();

        // Handle quoted phrases and individual terms.
        if ( preg_match_all( '/([^\s",\+]+)|"([^"]*)"|\'([^\']*)\'/', stripslashes( urldecode( $search_string ) ), $matches ) ) {
            foreach ( $matches[0] as $term ) {
                $term = trim( str_replace( array( '"', '%22', '%27' ), '', $term ) );
                if ( ! empty( $term ) && strlen( $term ) >= 1 ) {
                    $terms[] = $term;
                }
            }
        }

        return array_unique( $terms );
    }

    /**
     * Get search terms from referrer.
     */
    private static function get_referrer_terms() {
        if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
            return array();
        }

        $referrer = wp_parse_url( $_SERVER['HTTP_REFERER'] );
        if ( ! isset( $referrer['query'] ) ) {
            return array();
        }

        parse_str( $referrer['query'], $params );

        $search_params = array( 'q', 'p', 'keywords', 'searchfor', 'wd' );
        foreach ( $search_params as $param ) {
            if ( isset( $params[ $param ] ) && ! empty( $params[ $param ] ) ) {
                return self::parse_search_terms( $params[ $param ] );
            }
        }

        return array();
    }

    /**
     * Enqueue highlighting assets.
     */
    public function enqueue_assets() {
        $terms = self::get_search_terms();

        if ( empty( $terms ) ) {
            return;
        }

        // Use the main plugin file to get the correct plugin URL.
        $plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/wordpress-search.php' );
        $css_url    = $plugin_url . 'build/index.css';
        $js_url     = $plugin_url . 'build/index.js';

        // Enqueue CSS.
        wp_enqueue_style(
            'wordpress-search-highlight',
            $css_url,
            array(),
            self::VERSION
        );

        // Enqueue our highlight script.
        wp_enqueue_script(
            'wordpress-search-highlight',
            $js_url,
            array( 'jquery' ),
            self::VERSION,
            true
        );

        // Process terms like highlight-search-terms plugin.
        $processed_terms = array();
        foreach ( $terms as $term ) {
            $processed_terms[] = html_entity_decode( wptexturize( $term ) );
        }

        // Add search terms data with security nonce.
        wp_localize_script(
            'wordpress-search-highlight',
            'searchHighlightData',
            array(
                'terms'     => $processed_terms,
                'selectors' => apply_filters( 'wordpress_search_highlight_selectors', self::DEFAULT_SELECTORS ),
                'events'    => apply_filters( 'wordpress_search_highlight_events', self::DEFAULT_EVENTS ),
                'options'   => apply_filters(
                    'wordpress_search_highlight_options',
                    array(
						'separateWordSearch' => false,
						'caseSensitive'      => false,
						'wildcards'          => 'disabled',
						'debug'              => WP_DEBUG,
                    )
                ),
                'nonce'     => wp_create_nonce( 'search_highlight_nonce' ),
            )
        );
    }

    /**
     * Highlight search terms in titles.
     *
     * @param string $title The post title.
     * @return string The title with highlighted search terms
     */
    public function highlight_search_terms_in_title( $title ) {
        if ( ! is_search() ) {
            return $title;
        }

        $terms = self::get_search_terms();
        if ( empty( $terms ) ) {
            return $title;
        }

        return $this->highlight_terms( $title, $terms );
    }

    /**
     * Highlight search terms in excerpts.
     *
     * @param string $excerpt The post excerpt.
     * @return string The excerpt with highlighted search terms
     */
    public function highlight_search_terms_in_excerpt( $excerpt ) {
        if ( ! is_search() ) {
            return $excerpt;
        }

        $terms = self::get_search_terms();
        if ( empty( $terms ) ) {
            return $excerpt;
        }

        return $this->highlight_terms( $excerpt, $terms );
    }

    /**
     * Highlight search terms in content.
     *
     * @param string $content The post content.
     * @return string The content with highlighted search terms
     */
    public function highlight_search_terms_in_content( $content ) {
        if ( ! is_search() ) {
            return $content;
        }

        $terms = self::get_search_terms();
        if ( empty( $terms ) ) {
            return $content;
        }

        return $this->highlight_terms( $content, $terms );
    }

    /**
     * Highlight terms in text.
     *
     * @param string $text The text to highlight terms in.
     * @param array  $terms Array of search terms to highlight.
     * @return string The text with highlighted terms
     */
    private function highlight_terms( $text, $terms ) {
        if ( empty( $terms ) ) {
            return $text;
        }

        // Build regex pattern.
        $escaped_terms = array();
        foreach ( $terms as $term ) {
            $escaped_terms[] = preg_quote( $term, '/' );
        }

        // Use word boundaries for multi-character terms.
        $has_single_letters = false;
        foreach ( $terms as $term ) {
            if ( strlen( $term ) === 1 ) {
                $has_single_letters = true;
                break;
            }
        }

        if ( $has_single_letters ) {
            $pattern = '/(' . implode( '|', $escaped_terms ) . ')/i';
        } else {
            $pattern = '/\b(' . implode( '|', $escaped_terms ) . ')\b/i';
        }

        // Replace terms with highlighted versions.
        $highlighted_text = preg_replace( $pattern, '<mark class="search-highlight">$1</mark>', $text );

        return $highlighted_text;
    }

    /**
     * Add analytics tracking.
     */
    public function add_analytics() {
        if ( ! is_search() || empty( self::get_search_terms() ) ) {
            return;
        }

        ?>
        <script>
        // Track search highlighting effectiveness.
        document.addEventListener('DOMContentLoaded', function() {
            var highlights = document.querySelectorAll('.search-highlight');
            if (highlights.length > 0) {
                // Send analytics data.
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'search_highlight', {
                        'event_category': 'search',
                        'event_label': '<?php echo esc_js( implode( ',', self::get_search_terms() ) ); ?>',
                        'value': highlights.length
                    });
                }
            }
        });
        </script>
        <?php
    }

    /**
     * Get highlighting statistics.
     */
    public static function get_stats() {
        $terms = self::get_search_terms();
        return array(
            'terms'      => $terms,
            'term_count' => count( $terms ),
            'is_search'  => is_search(),
            'has_terms'  => ! empty( $terms ),
        );
    }
}
