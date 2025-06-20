<?php
/**
 * Class BlockRenderingTest
 *
 * Tests the block rendering functionality of the SearchMetadataFilter block.
 * Focuses on testing the render.php file that generates HTML output.
 *
 * @package WordPress_Search
 */
class BlockRenderingTest extends WP_UnitTestCase {

    /**
     * @var \Bcgov\WordpressSearch\MetadataFilter
     */
    private $metadata_filter;

    /**
     * @var array
     */
    private $test_posts = array();

    /**
     * Set up test environment before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create our MetadataFilter instance
        $this->metadata_filter = new \Bcgov\WordpressSearch\MetadataFilter();
        $this->metadata_filter->init();
        
        // Create test posts with metadata for rendering
        $this->create_test_posts_with_metadata();
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        // Clean up test posts
        foreach ( $this->test_posts as $post_id ) {
            wp_delete_post( $post_id, true );
        }
        $this->test_posts = array();
        
        // Clean up globals
        unset( $_GET );
        $_GET = array();
        
        parent::tearDown();
    }

    /**
     * Test block renders correctly with valid attributes
     * 
     * What this tests:
     * - Block renders HTML output when given valid selectedMetadata
     * - Contains expected form elements and structure
     * - Uses metadata values from our MetadataFilter class
     */
    public function test_block_renders_with_valid_attributes() {
        // Set up block attributes
        $attributes = array(
            'selectedMetadata' => 'document:department'
        );
        
        // Render the block
        $output = $this->render_block( $attributes );
        
        // Should contain the main wrapper
        $this->assertStringContainsString( 'wp-block-wordpress-search-metadata-filter', $output, 'Should contain main block wrapper class' );
        
        // Should contain form element
        $this->assertStringContainsString( '<form class="metadata-filter-form" method="get">', $output, 'Should contain form element' );
        
        // Should contain fieldset with legend
        $this->assertStringContainsString( '<fieldset class="metadata-filter">', $output, 'Should contain fieldset' );
        $this->assertStringContainsString( 'Filter by Department:', $output, 'Should contain field label' );
        
        // Should contain checkboxes for our test data
        $this->assertStringContainsString( 'name="metadata_department[]"', $output, 'Should contain metadata checkboxes' );
        $this->assertStringContainsString( 'value="IT"', $output, 'Should contain IT option' );
        $this->assertStringContainsString( 'value="HR"', $output, 'Should contain HR option' );
        $this->assertStringContainsString( 'value="Finance"', $output, 'Should contain Finance option' );
        
        // Should properly escape HTML
        $this->assertStringNotContainsString( '<script>', $output, 'Should not contain unescaped script tags' );
    }

    /**
     * Test block returns empty output for invalid attributes
     * 
     * What this tests:
     * - Block handles empty selectedMetadata gracefully
     * - Block handles malformed selectedMetadata format
     * - No HTML output when attributes are invalid
     */
    public function test_block_handles_invalid_attributes() {
        // Test with empty selectedMetadata
        $attributes = array(
            'selectedMetadata' => ''
        );
        $output = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Empty selectedMetadata should return no output' );
        
        // Test with malformed selectedMetadata (missing colon)
        $attributes = array(
            'selectedMetadata' => 'document_department'
        );
        $output = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Malformed selectedMetadata should return no output' );
        
        // Test with malformed selectedMetadata (too many parts)
        $attributes = array(
            'selectedMetadata' => 'document:department:extra'
        );
        $output = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Malformed selectedMetadata should return no output' );
        
        // Test with non-existent field
        $attributes = array(
            'selectedMetadata' => 'document:nonexistent_field'
        );
        $output = $this->render_block( $attributes );
        $this->assertEmpty( $output, 'Non-existent field should return no output' );
    }

    /**
     * Test block handles form structure correctly
     * 
     * What this tests:
     * - Form method and structure are correct
     * - All metadata values appear as checkboxes
     * - Apply button is present
     * 
     * Note: Testing actual URL parameter handling would require complex WordPress
     * environment mocking, so we focus on the basic form structure here.
     */
    public function test_block_handles_form_structure() {
        $attributes = array(
            'selectedMetadata' => 'document:department'
        );
        
        $output = $this->render_block( $attributes );
        
        // Should contain the form structure
        $this->assertStringContainsString( '<form class="metadata-filter-form" method="get">', $output, 'Should contain form element with GET method' );
        
        // Should contain checkboxes for all values
        $this->assertStringContainsString( 'value="IT"', $output, 'Should contain IT option' );
        $this->assertStringContainsString( 'value="HR"', $output, 'Should contain HR option' );
        $this->assertStringContainsString( 'value="Finance"', $output, 'Should contain Finance option' );
        
        // Should contain Apply button
        $this->assertStringContainsString( 'Apply Filters', $output, 'Should contain Apply Filters button' );
        $this->assertStringContainsString( 'type="submit"', $output, 'Should contain submit button' );
        
        // Should use proper checkbox naming for arrays
        $this->assertStringContainsString( 'name="metadata_department[]"', $output, 'Should use array notation for multiple values' );
    }

    /**
     * Test block generates proper field labels
     * 
     * What this tests:
     * - Field names are converted to human-readable labels
     * - Underscores are replaced with spaces
     * - Proper capitalization is applied
     */
    public function test_block_generates_proper_field_labels() {
        // Test with underscore field name
        $attributes = array(
            'selectedMetadata' => 'document:priority_level'
        );
        
        // Create a test post with this field so it renders
        $post_id = wp_insert_post( array(
            'post_title' => 'Test Document',
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'document'
        ));
        update_post_meta( $post_id, 'priority_level', 'high' );
        $this->test_posts[] = $post_id;
        
        $output = $this->render_block( $attributes );
        
        // Should convert priority_level to "Priority Level"
        $this->assertStringContainsString( 'Filter by Priority Level:', $output, 'Should convert underscores to spaces and capitalize' );
        
        // Clean up
        wp_delete_post( $post_id, true );
    }

    /**
     * Test block accessibility features
     * 
     * What this tests:
     * - Proper form labels and fieldset structure
     * - Checkbox IDs match label for attributes
     * - Semantic HTML structure for screen readers
     */
    public function test_block_accessibility_features() {
        $attributes = array(
            'selectedMetadata' => 'document:department'
        );
        
        $output = $this->render_block( $attributes );
        
        // Should have proper fieldset/legend structure
        $this->assertStringContainsString( '<fieldset class="metadata-filter">', $output, 'Should use fieldset for grouping' );
        $this->assertStringContainsString( '<legend class="metadata-filter__label">', $output, 'Should use legend for group label' );
        
        // Should have proper label/input associations
        $this->assertStringContainsString( 'for="metadata_department_it"', $output, 'Should have label for IT' );
        $this->assertStringContainsString( 'id="metadata_department_it"', $output, 'Should have input id for IT' );
        $this->assertStringContainsString( 'for="metadata_department_hr"', $output, 'Should have label for HR' );
        $this->assertStringContainsString( 'id="metadata_department_hr"', $output, 'Should have input id for HR' );
        
        // Should have proper input types and names
        $this->assertStringContainsString( 'type="checkbox"', $output, 'Should use checkbox input type' );
        $this->assertStringContainsString( 'name="metadata_department[]"', $output, 'Should use array notation for multiple values' );
    }

    /**
     * Test block security features
     * 
     * What this tests:
     * - All output is properly escaped
     * - No XSS vulnerabilities in dynamic content
     * - Proper sanitization of user input
     */
    public function test_block_security_features() {
        // Create a post with potentially dangerous metadata value
        $post_id = wp_insert_post( array(
            'post_title' => 'Security Test Document',
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'document'
        ));
        update_post_meta( $post_id, 'department', '<script>alert("xss")</script>' );
        $this->test_posts[] = $post_id;
        
        $attributes = array(
            'selectedMetadata' => 'document:department'
        );
        
        $output = $this->render_block( $attributes );
        
        // Should escape HTML in metadata values
        $this->assertStringNotContainsString( '<script>alert("xss")</script>', $output, 'Should not contain unescaped script tags' );
        $this->assertStringContainsString( '&lt;script&gt;', $output, 'Should contain escaped HTML entities' );
        
        // Should escape attributes properly
        $this->assertStringContainsString( 'value="&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"', $output, 'Should escape attribute values' );
    }

    /**
     * Helper method to render the SearchMetadataFilter block
     */
    private function render_block( array $attributes ): string {
        // Start output buffering
        ob_start();
        
        // Include the render template (same way WordPress does it)
        include plugin_dir_path( dirname( __FILE__ ) ) . 'Blocks/build/SearchMetadataFilter/render.php';
        
        // Return the buffered output
        return ob_get_clean();
    }

    /**
     * Helper method to create test posts with metadata
     */
    private function create_test_posts_with_metadata() {
        $test_data = array(
            array(
                'title' => 'IT Document 1',
                'meta' => array( 'department' => 'IT', 'priority' => 'high' )
            ),
            array(
                'title' => 'HR Document 1', 
                'meta' => array( 'department' => 'HR', 'priority' => 'medium' )
            ),
            array(
                'title' => 'Finance Document 1',
                'meta' => array( 'department' => 'Finance', 'priority' => 'low' )
            )
        );

        foreach ( $test_data as $data ) {
            $post_id = wp_insert_post( array(
                'post_title' => $data['title'],
                'post_content' => 'Test content for ' . $data['title'],
                'post_status' => 'publish',
                'post_type' => 'document'
            ));

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                foreach ( $data['meta'] as $key => $value ) {
                    update_post_meta( $post_id, $key, $value );
                }
                $this->test_posts[] = $post_id;
            }
        }
    }
} 