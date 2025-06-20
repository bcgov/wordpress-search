<?php
/**
 * Class BasicMetadataFilterTest
 *
 * This test class contains a single, basic test to verify that the MetadataFilter
 * class can be properly instantiated and initialized. This is our starting point
 * for testing the SearchMetadataFilter block PHP functionality.
 *
 * @package WordPress_Search
 */
class BasicMetadataFilterTest extends WP_UnitTestCase {

    /**
     * Test that the MetadataFilter class can be instantiated and initialized
     *
     * What this test does:
     * 1. Creates a new instance of the MetadataFilter class
     * 2. Calls the init() method to register WordPress hooks
     * 3. Verifies that the required WordPress hooks are properly registered
     *
     * Why this is important:
     * - This is the foundation of all functionality - the class must load and initialize
     * - It tests that our autoloading is working correctly
     * - It verifies that WordPress hooks are registered, which is how the filtering works
     */
    public function test_metadata_filter_initialization() {
        // Create and initialize the MetadataFilter.
        $metadata_filter = new \Bcgov\WordpressSearch\MetadataFilter();
        $this->assertInstanceOf( '\Bcgov\WordpressSearch\MetadataFilter', $metadata_filter );

        $metadata_filter->init();

        // Verify WordPress hooks are registered.
        $this->assertNotFalse(
            has_filter( 'query_vars', array( $metadata_filter, 'add_query_vars' ) ),
            'The query_vars filter was not registered. URL parameters like ?metadata_department=IT will not work. Check that add_filter() is called in init() method.'
        );

        $this->assertNotFalse(
            has_action( 'pre_get_posts', array( $metadata_filter, 'handle_metadata_filtering' ) ),
            'The pre_get_posts action was not registered. Search filtering will not work. Check that add_action() is called in init() method.'
        );
    }

    /**
     * Test parsing of selectedMetadata format (Pure PHP test)
     *
     * What this tests:
     * - Parsing "posttype:fieldname" format used throughout the block
     * - Edge cases like empty strings, malformed input, extra colons
     * - This is pure PHP logic - no WordPress dependencies
     */
    public function test_selected_metadata_parsing() {
        // Test valid format.
        $selected_metadata = 'document:department';
        $parts             = explode( ':', $selected_metadata );

        $this->assertCount( 2, $parts, 'Valid selectedMetadata should split into exactly 2 parts' );
        $this->assertEquals( 'document', $parts[0], 'First part should be the post type' );
        $this->assertEquals( 'department', $parts[1], 'Second part should be the field name' );

        // Test empty string.
        $selected_metadata = '';
        $parts             = explode( ':', $selected_metadata );
        $this->assertCount( 1, $parts, 'Empty string should result in 1 part (empty string)' );
        $this->assertEquals( '', $parts[0], 'Empty string should remain empty' );

        // Test missing colon.
        $selected_metadata = 'document_department';
        $parts             = explode( ':', $selected_metadata );
        $this->assertCount( 1, $parts, 'String without colon should result in 1 part' );
        $this->assertEquals( 'document_department', $parts[0], 'String without colon should be unchanged' );

        // Test too many colons.
        $selected_metadata = 'document:department:extra';
        $parts             = explode( ':', $selected_metadata );
        $this->assertCount( 3, $parts, 'String with extra colons should split into 3 parts' );
        $this->assertEquals( 'document', $parts[0] );
        $this->assertEquals( 'department', $parts[1] );
        $this->assertEquals( 'extra', $parts[2] );

        // Test validation logic used in the actual code.
        $valid_cases = array(
            'document:department' => true,
            'post:category'       => true,
            'page:meta_field'     => true,
        );

        $invalid_cases = array(
            ''                          => false,
            'document'                  => false,
            'document:'                 => false,
            ':department'               => false,
            'document:department:extra' => false,
        );

        foreach ( $valid_cases as $input => $expected ) {
            $parts    = explode( ':', $input );
            $is_valid = ( count( $parts ) === 2 && ! empty( $parts[0] ) && ! empty( $parts[1] ) );
            $this->assertTrue( $is_valid, "'{$input}' should be valid selectedMetadata format" );
        }

        foreach ( $invalid_cases as $input => $expected ) {
            $parts    = explode( ':', $input );
            $is_valid = ( count( $parts ) === 2 && ! empty( $parts[0] ) && ! empty( $parts[1] ) );
            $this->assertFalse( $is_valid, "'{$input}' should be invalid selectedMetadata format" );
        }
    }

    /**
     * Test add_query_vars method (PHP + WordPress integration test)
     *
     * What this tests:
     * - The add_query_vars method correctly adds metadata fields to WordPress query vars
     * - Database queries work to find existing metadata fields
     * - Array manipulation adds proper "metadata_" prefixes
     * - WordPress query var system integration
     */
    public function test_add_query_vars() {
        $metadata_filter = new \Bcgov\WordpressSearch\MetadataFilter();

        // Create test posts with metadata fields.
        $test_posts = array();

        $post1_id = wp_insert_post(
            array(
				'post_title'   => 'Test Document 1',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_type'    => 'document',
            )
        );
        update_post_meta( $post1_id, 'department', 'IT' );
        update_post_meta( $post1_id, 'priority', 'high' );
        $test_posts[] = $post1_id;

        $post2_id = wp_insert_post(
            array(
				'post_title'   => 'Test Document 2',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_type'    => 'document',
            )
        );
        update_post_meta( $post2_id, 'department', 'HR' );
        update_post_meta( $post2_id, 'category', 'Policy' );
        $test_posts[] = $post2_id;

        // Test the add_query_vars method.
        $initial_vars = array( 's', 'p', 'page' );
        $result       = $metadata_filter->add_query_vars( $initial_vars );

        // Should preserve original query vars.
        $this->assertContains( 's', $result, 'Original query vars should be preserved' );
        $this->assertContains( 'p', $result, 'Original query vars should be preserved' );
        $this->assertContains( 'page', $result, 'Original query vars should be preserved' );

        // Should add metadata query vars with proper prefix.
        $this->assertContains( 'metadata_department', $result, 'Should add metadata_department query var' );
        $this->assertContains( 'metadata_priority', $result, 'Should add metadata_priority query var' );
        $this->assertContains( 'metadata_category', $result, 'Should add metadata_category query var' );

        // Should not add vars for non-existent metadata fields.
        $this->assertNotContains( 'metadata_nonexistent', $result, 'Should not add query vars for non-existent fields' );

        // Result should be an array.
        $this->assertIsArray( $result, 'add_query_vars should return an array' );

        // Should have more vars than we started with.
        $this->assertGreaterThan( count( $initial_vars ), count( $result ), 'Should add new query vars to the array' );

        // Clean up test posts.
        foreach ( $test_posts as $post_id ) {
            wp_delete_post( $post_id, true );
        }
    }
}
