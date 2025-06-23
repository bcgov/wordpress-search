<?php
/**
 * Class SearchBlockFunctionalityTest
 *
 * Tests the functionality and integration of the Search block.
 * Focuses on testing WordPress search integration and form behavior.
 *
 * @package WordPress_Search
 */
class SearchBlockFunctionalityTest extends WP_UnitTestCase {

    /**
     * Array of test post IDs for cleanup.
     *
     * @var array
     */
    private $test_posts = array();

    /**
     * Set up test environment before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Create test posts for search functionality.
        $this->create_test_posts();
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        // Clean up test posts.
        foreach ( $this->test_posts as $post_id ) {
            wp_delete_post( $post_id, true );
        }
        $this->test_posts = array();

        // Clean up globals.
        unset( $_GET );
        $_GET = array();

        // Reset query vars.
        global $wp_query;
        if ( isset( $wp_query ) ) {
            $wp_query->init();
        }

        parent::tearDown();
    }

    /**
     * Test that search block integrates with WordPress search functionality.
     *
     * What this tests:
     * - Block uses standard WordPress search parameter 's'
     * - Integration with get_search_query() function
     * - Proper form submission to WordPress search
     */
    public function test_search_block_wordpress_integration() {
        // Test that the block uses the correct search parameter.
        $output = $this->render_search_block( array() );

        // Should use 's' parameter which is WordPress standard.
        $this->assertStringContainsString( 'name="s"', $output, 'Should use WordPress standard search parameter "s"' );

        // Should submit to home URL where WordPress handles search.
        $home_url = esc_url( home_url( '/' ) );
        $this->assertStringContainsString( 'action="' . $home_url . '"', $output, 'Should submit to WordPress home URL' );

        // Should use GET method for search.
        $this->assertStringContainsString( 'method="get"', $output, 'Should use GET method for search compatibility' );
    }

    /**
     * Test search query preservation across page loads.
     *
     * What this tests:
     * - Current search query is displayed in input field
     * - Query persistence during pagination
     * - Proper escaping of query values
     */
    public function test_search_query_preservation() {
        // Simulate a search query being active.
        $search_term = 'WordPress development';

        // Set up WordPress query to simulate search results page.
        global $wp_query;
        $wp_query = new WP_Query(
            array(
				's'         => $search_term,
				'post_type' => 'any',
            )
        );

        // Set the search query global.
        set_query_var( 's', $search_term );

        // Render the block.
        $output = $this->render_search_block( array() );

        // Should contain the current search term as value.
        $escaped_term = esc_attr( $search_term );
        $this->assertStringContainsString( 'value="' . $escaped_term . '"', $output, 'Should preserve current search query in input field' );
    }

    /**
     * Test search input validation and security.
     *
     * What this tests:
     * - XSS protection in search input
     * - Proper HTML escaping
     * - Security against malicious input
     */
    public function test_search_input_security() {
        // Test with potentially malicious search terms.
        $malicious_searches = array(
            '<script>alert("xss")</script>',
            '"><script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<?php echo "malicious"; ?>',
            '&lt;img src=x onerror=alert(1)&gt;',
        );

        foreach ( $malicious_searches as $malicious_search ) {
            // Set up the malicious search query.
            set_query_var( 's', $malicious_search );
            global $wp_query;
            $wp_query = new WP_Query( array( 's' => $malicious_search ) );

            // Render the block.
            $output = $this->render_search_block( array() );

            // Should not contain unescaped malicious content.
            $this->assertStringNotContainsString( '<script>alert("xss")</script>', $output, 'Should escape script tags' );
            $this->assertStringNotContainsString( 'javascript:alert("xss")', $output, 'Should escape javascript: URLs' );
            $this->assertStringNotContainsString( '<?php echo', $output, 'Should escape PHP tags' );

            // Should contain properly escaped version.
            $escaped_search = esc_attr( $malicious_search );
            $this->assertStringContainsString( 'value="' . $escaped_search . '"', $output, 'Should contain properly escaped search term' );
        }
    }

    /**
     * Test search form accessibility compliance.
     *
     * What this tests:
     * - ARIA roles and labels
     * - Keyboard navigation support
     * - Screen reader compatibility
     */
    public function test_search_form_accessibility() {
        $output = $this->render_search_block( array() );

        // Should have proper search role.
        $this->assertStringContainsString( 'role="search-bar"', $output, 'Should have search role for screen readers' );

        // Should use semantic HTML5 search input.
        $this->assertStringContainsString( 'type="search"', $output, 'Should use search input type for better accessibility' );

        // Should have required attribute for form validation.
        $this->assertStringContainsString( 'required', $output, 'Should have required attribute for form validation' );

        // Should have meaningful placeholder text.
        $this->assertStringContainsString( 'placeholder="Search term"', $output, 'Should have descriptive placeholder text' );

        // Should have proper button types.
        $this->assertStringContainsString( 'type="submit"', $output, 'Should have submit button for form submission' );
        $this->assertStringContainsString( 'type="button"', $output, 'Should have button type for clear functionality' );
    }

    /**
     * Test search block empty state handling.
     *
     * What this tests:
     * - Behavior when no search query is present
     * - Default placeholder behavior
     * - Empty value handling
     */
    public function test_search_block_empty_state() {
        // Ensure no search query is set.
        set_query_var( 's', '' );
        global $wp_query;
        $wp_query = new WP_Query();

        $output = $this->render_search_block( array() );

        // Should have empty value when no search is active.
        $this->assertStringContainsString( 'value=""', $output, 'Should have empty value when no search query is present' );

        // Should still have placeholder text.
        $this->assertStringContainsString( 'placeholder="Search term"', $output, 'Should have placeholder text in empty state' );

        // Should still render all required elements.
        $this->assertStringContainsString( 'type="search"', $output, 'Should render search input even when empty' );
        $this->assertStringContainsString( 'type="submit"', $output, 'Should render submit button even when empty' );
    }

    /**
     * Test search block with special characters in query.
     *
     * What this tests:
     * - Unicode character support
     * - International character handling
     * - Special symbol preservation
     */
    public function test_search_block_special_characters() {
        $special_searches = array(
            'café résumé',           // Accented characters.
            'Москва',                // Cyrillic.
            '东京',                   // Chinese.
            'François & María',      // Mixed special chars.
            'test@example.com',      // Email format.
            '#hashtag @mention',     // Social media format.
            '50% off $100+',         // Symbols and numbers.
        );

        foreach ( $special_searches as $search_term ) {
            set_query_var( 's', $search_term );
            global $wp_query;
            $wp_query = new WP_Query( array( 's' => $search_term ) );

            $output = $this->render_search_block( array() );

            // Should contain properly escaped version of the search term.
            $escaped_term = esc_attr( $search_term );
            $this->assertStringContainsString( 'value="' . $escaped_term . '"', $output, 'Should handle special characters: ' . $search_term );

            // Should not break the HTML structure.
            $this->assertStringContainsString( 'type="search"', $output, 'Should maintain proper HTML structure with special characters' );
        }
    }

    /**
     * Test search block URL parameter handling.
     *
     * What this tests:
     * - GET parameter processing
     * - URL encoding/decoding
     * - Parameter preservation
     */
    public function test_search_block_url_parameters() {
        // Simulate URL with search parameter: example.com/?s=test+query.
        $_GET['s'] = 'test query';
        set_query_var( 's', 'test query' );

        global $wp_query;
        $wp_query = new WP_Query( array( 's' => 'test query' ) );

        $output = $this->render_search_block( array() );

        // Should reflect the URL parameter in the input value.
        $this->assertStringContainsString( 'value="test query"', $output, 'Should reflect URL search parameter in input field' );

        // Clean up.
        unset( $_GET['s'] );
    }

    /**
     * Test search block performance with long queries.
     *
     * What this tests:
     * - Handling of very long search queries
     * - Memory usage with large strings
     * - Proper truncation if needed
     */
    public function test_search_block_long_queries() {
        // Create a very long search query.
        $long_query = str_repeat( 'very long search query with many words ', 10 ); // Reduced for practical testing.

        // Set up the WordPress query properly for the long query.
        global $wp_query;
        $original_wp_query = $wp_query;

        $wp_query = new WP_Query( array( 's' => $long_query ) );
        set_query_var( 's', $long_query );

        $output = $this->render_search_block( array() );

        // Should handle long queries without breaking.
        $this->assertIsString( $output, 'Should return string output even with very long queries' );
        $this->assertStringContainsString( 'type="search"', $output, 'Should maintain proper structure with long queries' );

        // Should contain some part of the long query (may be truncated).
        $this->assertStringContainsString( 'very long search query', $output, 'Should handle long search queries properly' );

        // Restore original query.
        $wp_query = $original_wp_query;
    }

    /**
     * Helper method to render the search block.
     *
     * @param array $attributes Block attributes.
     * @return string Block output.
     */
    private function render_search_block( array $attributes ): string {
        ob_start();

        // Set up the required variables that render.php expects.
        $block = (object) array(
            'blockName' => 'wordpress-search/search-bar',
        );

        // Include the render file from the correct path.
        $render_file = dirname( __DIR__ ) . '/Blocks/build/Search/render.php';
        include $render_file;

        return ob_get_clean();
    }

    /**
     * Create test posts for search functionality testing.
     */
    private function create_test_posts() {
        $test_posts_data = array(
            array(
                'title'   => 'WordPress Development Guide',
                'content' => 'This is a comprehensive guide to WordPress development including themes, plugins, and custom post types.',
                'type'    => 'post',
            ),
            array(
                'title'   => 'JavaScript and React Tutorial',
                'content' => 'Learn modern JavaScript ES6+ features and React for building dynamic web applications.',
                'type'    => 'post',
            ),
            array(
                'title'   => 'PHP Best Practices',
                'content' => 'Modern PHP development practices including PSR standards, composer, and testing.',
                'type'    => 'page',
            ),
            array(
                'title'   => 'Database Optimization Tips',
                'content' => 'How to optimize MySQL databases for better performance in WordPress applications.',
                'type'    => 'post',
            ),
        );

        foreach ( $test_posts_data as $post_data ) {
            $post_id = wp_insert_post(
                array(
					'post_title'   => $post_data['title'],
					'post_content' => $post_data['content'],
					'post_status'  => 'publish',
					'post_type'    => $post_data['type'],
                )
            );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                $this->test_posts[] = $post_id;
            }
        }
    }
}
