<?php
/**
 * Class TaxonomyBlockRenderingTest
 *
 * Tests the block rendering functionality of the SearchTaxonomyFilter block.
 * Focuses on testing the render.php file that generates HTML output.
 *
 * @package WordPress_Search
 */
class TaxonomyBlockRenderingTest extends WP_UnitTestCase {

    /**
     * The taxonomy filter instance for testing.
     *
     * @var \Bcgov\WordpressSearch\TaxonomyFilter
     */
    private $taxonomy_filter;

    /**
     * Array of test post IDs for cleanup.
     *
     * @var array
     */
    private $test_posts = array();

    /**
     * Array of test term IDs for cleanup.
     *
     * @var array
     */
    private $test_terms = array();

    /**
     * Array of test taxonomies for cleanup.
     *
     * @var array
     */
    private $test_taxonomies = array();

    /**
     * Set up test environment before each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Create our TaxonomyFilter instance.
        $this->taxonomy_filter = new \Bcgov\WordpressSearch\TaxonomyFilter();
        $this->taxonomy_filter->init();

        // Create test taxonomies and terms for rendering.
        $this->create_test_taxonomies_and_terms();
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

        // Clean up test terms.
        foreach ( $this->test_terms as $term_data ) {
            wp_delete_term( $term_data['id'], $term_data['taxonomy'] );
        }
        $this->test_terms = array();

        // Clean up test taxonomies.
        foreach ( $this->test_taxonomies as $taxonomy ) {
            unregister_taxonomy( $taxonomy );
        }
        $this->test_taxonomies = array();

        // Clean up globals.
        unset( $_GET );
        $_GET = array();

        parent::tearDown();
    }

    /**
     * Test block renders correctly with valid attributes.
     *
     * What this tests:
     * - Block renders HTML output when given valid selectedTaxonomy
     * - Contains expected form elements and structure
     * - Uses taxonomy terms from WordPress
     */
    public function test_block_renders_with_valid_attributes() {
        // Set up block attributes.
        $attributes = array(
            'selectedTaxonomy' => 'document:document_category',
        );

        // Render the block.
        $output = $this->render_block( $attributes );

        // Should contain the main wrapper.
        $this->assertStringContainsString( 'wp-block-wordpress-search-taxonomy-filter', $output, 'Should contain main block wrapper class' );

        // Should contain form element.
        $this->assertStringContainsString( '<form class="taxonomy-filter-form" method="get"', $output, 'Should contain form element' );

        // Should contain fieldset with legend.
        $this->assertStringContainsString( '<fieldset class="taxonomy-filter">', $output, 'Should contain fieldset' );
        $this->assertStringContainsString( 'Document Category', $output, 'Should contain taxonomy label' );

        // Should contain checkboxes for our test terms.
        $this->assertStringContainsString( 'name="taxonomy_document_category[]"', $output, 'Should contain taxonomy checkboxes' );
        $this->assertStringContainsString( 'Policies', $output, 'Should contain Policies term' );
        $this->assertStringContainsString( 'Procedures', $output, 'Should contain Procedures term' );
        $this->assertStringContainsString( 'Guidelines', $output, 'Should contain Guidelines term' );

        // Should properly escape HTML.
        $this->assertStringNotContainsString( '<script>', $output, 'Should not contain unescaped script tags' );
    }

    /**
     * Test block returns empty output for invalid attributes.
     *
     * What this tests:
     * - Block handles empty selectedTaxonomy gracefully
     * - Block handles malformed selectedTaxonomy format
     * - No HTML output when attributes are invalid
     */
    public function test_block_handles_invalid_attributes() {
        // Test with empty selectedTaxonomy.
        $attributes = array(
            'selectedTaxonomy' => '',
        );
        $output     = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Empty selectedTaxonomy should return no output' );

        // Test with malformed selectedTaxonomy (missing colon).
        $attributes = array(
            'selectedTaxonomy' => 'document_category',
        );
        $output     = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Malformed selectedTaxonomy should return no output' );

        // Test with malformed selectedTaxonomy (too many parts).
        $attributes = array(
            'selectedTaxonomy' => 'document:document_category:extra',
        );
        $output     = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Malformed selectedTaxonomy should return no output' );

        // Test with non-existent taxonomy.
        $attributes = array(
            'selectedTaxonomy' => 'document:nonexistent_taxonomy',
        );
        $output     = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Non-existent taxonomy should return no output' );
    }

    /**
     * Test block handles form structure correctly.
     *
     * What this tests:
     * - Form method and structure are correct
     * - All taxonomy terms appear as checkboxes
     * - Proper HTML structure for accessibility
     */
    public function test_block_handles_form_structure() {
        $attributes = array(
            'selectedTaxonomy' => 'document:document_category',
        );

        $output = $this->render_block( $attributes );

        // Should contain the form structure.
        $this->assertStringContainsString( '<form class="taxonomy-filter-form" method="get"', $output, 'Should contain form element with GET method' );

        // Should contain data attribute for taxonomy.
        $this->assertStringContainsString( 'data-taxonomy="document_category"', $output, 'Should contain data-taxonomy attribute' );

        // Should contain checkboxes for all terms.
        $this->assertStringContainsString( 'type="checkbox"', $output, 'Should contain checkbox inputs' );
        $this->assertStringContainsString( 'Policies', $output, 'Should contain Policies term' );
        $this->assertStringContainsString( 'Procedures', $output, 'Should contain Procedures term' );
        $this->assertStringContainsString( 'Guidelines', $output, 'Should contain Guidelines term' );

        // Should use proper checkbox naming for arrays.
        $this->assertStringContainsString( 'name="taxonomy_document_category[]"', $output, 'Should use array notation for multiple values' );

        // Should have toggle functionality.
        $this->assertStringContainsString( 'taxonomy-filter__toggle', $output, 'Should contain toggle element' );
        $this->assertStringContainsString( 'onclick="toggleTaxonomyFilter(this)"', $output, 'Should contain toggle functionality' );
    }

    /**
     * Test block accessibility features.
     *
     * What this tests:
     * - Proper form labels and fieldset structure
     * - Checkbox IDs match label for attributes
     * - Semantic HTML structure for screen readers
     */
    public function test_block_accessibility_features() {
        $attributes = array(
            'selectedTaxonomy' => 'document:document_category',
        );

        $output = $this->render_block( $attributes );

        // Should have proper fieldset/legend structure.
        $this->assertStringContainsString( '<fieldset class="taxonomy-filter">', $output, 'Should use fieldset for grouping' );
        $this->assertStringContainsString( '<legend class="taxonomy-filter__label">', $output, 'Should use legend for group label' );

        // Should have proper label/input associations.
        $policies_term = get_term_by( 'name', 'Policies', 'document_category' );
        if ( $policies_term ) {
            $checkbox_id = 'taxonomy_document_category_' . $policies_term->term_id;
            $this->assertStringContainsString( 'for="' . $checkbox_id . '"', $output, 'Should have label for Policies' );
            $this->assertStringContainsString( 'id="' . $checkbox_id . '"', $output, 'Should have input id for Policies' );
        }

        // Should have proper input types and names.
        $this->assertStringContainsString( 'type="checkbox"', $output, 'Should use checkbox input type' );
        $this->assertStringContainsString( 'name="taxonomy_document_category[]"', $output, 'Should use array notation for multiple values' );

        // Should have CSS classes for styling.
        $this->assertStringContainsString( 'class="components-checkbox-control taxonomy-filter__option"', $output, 'Should have proper CSS classes' );
    }

    /**
     * Test block security features
     *
     * What this tests:
     * - All output is properly escaped
     * - No XSS vulnerabilities in dynamic content
     * - Proper sanitization of term data
     */
    public function test_block_security_features() {
        // Create a term with potentially dangerous content.
        // Note: WordPress itself sanitizes term names, so we test with content that survives insertion.
        $dangerous_name = 'Test <b>Bold</b> & "Quotes" Term';
        $dangerous_term = wp_insert_term( $dangerous_name, 'document_category' );
        if ( ! is_wp_error( $dangerous_term ) ) {
            $this->test_terms[] = array(
                'id'       => $dangerous_term['term_id'],
                'taxonomy' => 'document_category',
            );
        }

        $attributes = array(
            'selectedTaxonomy' => 'document:document_category',
        );

        $output = $this->render_block( $attributes );

        // Check what the term name actually became after WordPress processing.
        $stored_term        = get_term( $dangerous_term['term_id'] );
        $actual_stored_name = $stored_term->name;

        // Should not contain unescaped HTML tags.
        $this->assertStringNotContainsString( '<b>', $output, 'Should not contain unescaped HTML tags' );

        // If the stored name contains HTML entities, they should be properly escaped in output.
        if ( strpos( $actual_stored_name, '<' ) !== false || strpos( $actual_stored_name, '&' ) !== false ) {
            // Should contain escaped HTML entities when they exist in the term name.
            $this->assertStringContainsString( esc_html( $actual_stored_name ), $output, 'Should contain properly escaped term name' );
        }

        // Should escape attributes properly - test with term ID which should be numeric.
        $this->assertStringContainsString( 'value="' . $dangerous_term['term_id'] . '"', $output, 'Should escape attribute values' );

        // Term name should be properly escaped in labels.
        $this->assertStringContainsString( esc_html( $actual_stored_name ), $output, 'Term name should be escaped in labels' );
    }

    /**
     * Test block handles selected terms correctly
     *
     * What this tests:
     * - Currently selected terms are marked as checked
     * - URL parameters are preserved in hidden inputs
     * - Selected state is properly reflected
     */
    public function test_block_handles_selected_terms() {
        // Get a test term.
        $policies_term = get_term_by( 'name', 'Policies', 'document_category' );

        // Simulate URL parameter for selected term.
        $_GET['taxonomy_document_category'] = array( $policies_term->term_id );
        $_GET['s']                          = 'test search'; // Add search parameter to preserve.

        $attributes = array(
            'selectedTaxonomy' => 'document:document_category',
        );

        $output = $this->render_block( $attributes );

        // Should contain hidden input for search parameter.
        $this->assertStringContainsString( 'name="s"', $output, 'Should preserve search parameter' );
        $this->assertStringContainsString( 'value="test search"', $output, 'Should contain search value' );

        // Should mark the selected term as checked.
        $checkbox_id = 'taxonomy_document_category_' . $policies_term->term_id;
        $this->assertTrue(
            strpos( $output, 'checked="checked"' ) !== false || strpos( $output, "checked='checked'" ) !== false,
            'Should mark selected terms as checked (either single or double quotes)'
        );

        // Clean up.
        unset( $_GET['taxonomy_document_category'] );
        unset( $_GET['s'] );
    }

    /**
     * Test block error handling
     *
     * What this tests:
     * - Graceful handling of taxonomy errors
     * - Proper error messages for users
     * - No fatal errors on edge cases
     */
    public function test_block_error_handling() {
        // Test with taxonomy that has no terms.
        register_taxonomy(
            'empty_taxonomy',
            array( 'document' ),
            array(
                'public' => true,
                'label'  => 'Empty Taxonomy',
            )
        );
        $this->test_taxonomies[] = 'empty_taxonomy';

        $attributes = array(
            'selectedTaxonomy' => 'document:empty_taxonomy',
        );

        $output = $this->render_block( $attributes );

        // Should contain empty message.
        $this->assertStringContainsString( 'No terms available in this taxonomy', $output, 'Should show empty message for taxonomies with no terms' );

        // Should still have proper structure.
        $this->assertStringContainsString( 'wp-block-wordpress-search-taxonomy-filter', $output, 'Should contain main wrapper even when empty' );
    }

    /**
     * Helper method to render the SearchTaxonomyFilter block.
     *
     * @param array $attributes Block attributes to pass to the render template.
     */
    private function render_block( array $attributes ): string {
        // Start output buffering.
        ob_start();

        // Include the render template (same way WordPress does it).
        include plugin_dir_path( __DIR__ ) . 'Blocks/src/SearchTaxonomyFilter/render.php';

        // Return the buffered output.
        return ob_get_clean();
    }

    /**
     * Helper method to create test taxonomies and terms
     */
    private function create_test_taxonomies_and_terms() {
        // Register custom taxonomy for documents.
        register_taxonomy(
            'document_category',
            array( 'document' ),
            array(
                'public'       => true,
                'hierarchical' => true,
                'labels'       => array(
                    'name'          => 'Document Categories',
                    'singular_name' => 'Document Category',
                ),
            )
        );
        $this->test_taxonomies[] = 'document_category';

        // Create test terms.
        $terms_data = array(
            'Policies',
            'Procedures',
            'Guidelines',
            'Reports',
        );

        foreach ( $terms_data as $term_name ) {
            $term = wp_insert_term( $term_name, 'document_category' );
            if ( ! is_wp_error( $term ) ) {
                $this->test_terms[] = array(
                    'id'       => $term['term_id'],
                    'taxonomy' => 'document_category',
                );
            }
        }
    }
}
