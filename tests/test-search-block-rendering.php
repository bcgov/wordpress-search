<?php
/**
 * Class SearchBlockRenderingTest
 *
 * Tests the block rendering functionality of the Search block.
 * Focuses on testing the render.php file that generates HTML output.
 *
 * @package WordPress_Search
 */
class SearchBlockRenderingTest extends WP_UnitTestCase {

    /**
     * Array of test data for cleanup.
     *
     * @var array
     */
    private $test_data = array();

    /**
     * Set up test environment before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Clear any existing search query.
        set_query_var( 's', '' );
        global $wp_query;
        if ( isset( $wp_query ) ) {
            $wp_query->set( 's', '' );
        }
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        // Clean up test data.
        foreach ( $this->test_data as $data ) {
            if ( isset( $data['type'] ) && 'post' === $data['type'] ) {
                wp_delete_post( $data['id'], true );
            }
        }
        $this->test_data = array();

        // Clean up globals.
        unset( $_GET );
        $_GET = array();
        set_query_var( 's', '' );

        parent::tearDown();
    }

    /**
     * Test search block renders correctly with default attributes.
     *
     * What this tests:
     * - Block renders HTML output with empty attributes
     * - Contains expected form elements and structure
     * - Uses proper WordPress search functionality
     */
    public function test_search_block_renders_with_default_attributes() {
        // Render the block with empty attributes.
        $output = $this->render_search_block( array() );

        // Should contain the main wrapper.
        $this->assertStringContainsString( 'wp-block-wordpress-search-search-bar', $output, 'Should contain main block wrapper class' );

        // Should contain container.
        $this->assertStringContainsString( 'dswp-search-bar__container', $output, 'Should contain search bar container' );

        // Should contain form element with proper attributes.
        $this->assertStringContainsString( '<form role="search-bar" method="get"', $output, 'Should contain form element with GET method' );
        $this->assertStringContainsString( 'class="dswp-search-bar__form"', $output, 'Should contain form class' );

        // Should contain input field with proper attributes.
        $this->assertStringContainsString( 'type="search"', $output, 'Should contain search input type' );
        $this->assertStringContainsString( 'name="s"', $output, 'Should use WordPress standard search parameter' );
        $this->assertStringContainsString( 'placeholder="Search term"', $output, 'Should contain placeholder text' );
        $this->assertStringContainsString( 'class="dswp-search-bar__input"', $output, 'Should contain input class' );
        $this->assertStringContainsString( 'required', $output, 'Should have required attribute' );

        // Should contain submit button.
        $this->assertStringContainsString( 'type="submit"', $output, 'Should contain submit button' );
        $this->assertStringContainsString( 'Search', $output, 'Should contain Search button text' );

        // Should contain clear button.
        $this->assertStringContainsString( 'dswp-search-bar__clear-button', $output, 'Should contain clear button' );

        // Should contain search icon.
        $this->assertStringContainsString( 'dswp-search-bar__search-icon', $output, 'Should contain search icon' );
        $this->assertStringContainsString( '<svg', $output, 'Should contain SVG icons' );

        // Should properly escape HTML.
        $this->assertStringNotContainsString( '<script>', $output, 'Should not contain unescaped script tags' );
    }

    /**
     * Test search block preserves existing search query.
     *
     * What this tests:
     * - Block shows current search query in input field
     * - Proper escaping of search query values
     * - Integration with WordPress search functionality
     */
    public function test_search_block_preserves_search_query() {
        // Set up a search query.
        $search_query = 'test search query';
        set_query_var( 's', $search_query );

        // Mock get_search_query() behavior.
        global $wp_query;
        if ( ! isset( $wp_query ) ) {
            $wp_query = new WP_Query();
        }
        $wp_query->set( 's', $search_query );

        // Render the block.
        $output = $this->render_search_block( array() );

        // Should contain the search query as the input value.
        $escaped_query = esc_attr( $search_query );
        $this->assertStringContainsString( 'value="' . $escaped_query . '"', $output, 'Should preserve current search query' );
    }

    /**
     * Test search block handles special characters safely.
     *
     * What this tests:
     * - Proper escaping of search queries with special characters
     * - XSS protection in search input values
     * - HTML entity encoding
     */
    public function test_search_block_handles_special_characters() {
        // Set up a search query with special characters.
        $search_query = 'test & search "quotes" <script>alert("xss")</script>';
        set_query_var( 's', $search_query );

        global $wp_query;
        if ( ! isset( $wp_query ) ) {
            $wp_query = new WP_Query();
        }
        $wp_query->set( 's', $search_query );

        // Render the block.
        $output = $this->render_search_block( array() );

        // Should properly escape the search query.
        $this->assertStringNotContainsString( '<script>alert("xss")</script>', $output, 'Should escape script tags' );
        $this->assertStringContainsString( 'test &amp; search', $output, 'Should escape ampersands' );
        $this->assertStringContainsString( '&quot;quotes&quot;', $output, 'Should escape quotes' );
        $this->assertStringContainsString( '&lt;script&gt;', $output, 'Should escape angle brackets' );
    }

    /**
     * Test search block form action points to home URL.
     *
     * What this tests:
     * - Form action attribute uses WordPress home_url()
     * - Proper URL escaping
     * - Integration with WordPress site settings
     */
    public function test_search_block_form_action() {
        // Render the block.
        $output = $this->render_search_block( array() );

        // Should contain form action pointing to home URL.
        $home_url = esc_url( home_url( '/' ) );
        $this->assertStringContainsString( 'action="' . $home_url . '"', $output, 'Should use WordPress home URL as form action' );
    }

    /**
     * Test search block accessibility features.
     *
     * What this tests:
     * - Proper ARIA roles and attributes
     * - Screen reader compatibility
     * - Keyboard navigation support
     */
    public function test_search_block_accessibility_features() {
        // Render the block.
        $output = $this->render_search_block( array() );

        // Should contain proper ARIA role.
        $this->assertStringContainsString( 'role="search-bar"', $output, 'Should contain search role for accessibility' );

        // Should contain proper input attributes.
        $this->assertStringContainsString( 'type="search"', $output, 'Should use search input type for better accessibility' );
        $this->assertStringContainsString( 'placeholder="Search term"', $output, 'Should contain helpful placeholder text' );

        // Should have proper button types.
        $this->assertStringContainsString( 'type="submit"', $output, 'Should have submit button' );
        $this->assertStringContainsString( 'type="button"', $output, 'Should have clear button' );
    }

    /**
     * Test search block structure and CSS classes.
     *
     * What this tests:
     * - Proper nesting of HTML elements
     * - Correct CSS classes for styling
     * - Element structure for frontend JavaScript
     */
    public function test_search_block_structure_and_classes() {
        // Render the block.
        $output = $this->render_search_block( array() );

        // Test main structure.
        $this->assertStringContainsString( 'wp-block-wordpress-search-search-bar', $output, 'Should have main block class' );
        $this->assertStringContainsString( 'dswp-search-bar__container', $output, 'Should have container class' );
        $this->assertStringContainsString( 'dswp-search-bar__form', $output, 'Should have form class' );
        $this->assertStringContainsString( 'dswp-search-bar__input-container', $output, 'Should have input container class' );
        $this->assertStringContainsString( 'dswp-search-bar__input-wrapper', $output, 'Should have input wrapper class' );
        $this->assertStringContainsString( 'dswp-search-bar__input', $output, 'Should have input class' );
        $this->assertStringContainsString( 'dswp-search-bar__button', $output, 'Should have button class' );
        $this->assertStringContainsString( 'dswp-search-bar__button--primary', $output, 'Should have primary button modifier class' );
        $this->assertStringContainsString( 'dswp-search-bar__clear-button', $output, 'Should have clear button class' );
        $this->assertStringContainsString( 'dswp-search-bar__search-icon', $output, 'Should have search icon class' );
    }

    /**
     * Test search block SVG icons are properly included.
     *
     * What this tests:
     * - SVG icons are present in output
     * - Icons have proper structure and attributes
     * - No external dependencies for icons
     */
    public function test_search_block_svg_icons() {
        // Render the block.
        $output = $this->render_search_block( array() );

        // Should contain SVG elements.
        $this->assertStringContainsString( '<svg', $output, 'Should contain SVG elements' );
        $this->assertStringContainsString( 'xmlns="http://www.w3.org/2000/svg"', $output, 'Should have proper SVG namespace' );
        $this->assertStringContainsString( 'viewBox=', $output, 'Should have viewBox for scalability' );
        $this->assertStringContainsString( 'stroke="currentColor"', $output, 'Should use currentColor for theming' );

        // Should contain both search and clear icons.
        $this->assertGreaterThanOrEqual( 2, substr_count( $output, '<svg' ), 'Should contain at least 2 SVG icons' );

        // Should contain circle for search icon.
        $this->assertStringContainsString( '<circle', $output, 'Should contain search icon circle' );

        // Should contain lines for both icons.
        $this->assertStringContainsString( '<line', $output, 'Should contain icon lines' );
    }

    /**
     * Helper method to render the search block.
     *
     * @param array $attributes Block attributes.
     * @return string Block output.
     */
    private function render_search_block( array $attributes ): string {
        // Include the render file and capture output.
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
}
