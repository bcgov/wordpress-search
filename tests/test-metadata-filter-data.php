<?php
/**
 * Class DataRetrievalTest
 *
 * Tests the data retrieval functionality of the MetadataFilter class.
 * Focuses on testing how the class gets metadata values for filter dropdowns.
 *
 * @package WordPress_Search
 */
class DataRetrievalTest extends WP_UnitTestCase {

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
        
        // Create test posts with metadata for data retrieval
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
        
        parent::tearDown();
    }

    /**
     * Test get_metadata_values returns correct values for valid field
     * 
     * What this tests:
     * - Basic functionality of getting metadata values
     * - Returns unique values in alphabetical order
     * - Security validation passes for existing fields
     */
    public function test_get_metadata_values_returns_correct_values() {
        // Test getting department values
        $department_values = $this->metadata_filter->get_metadata_values( 'document', 'department' );
        
        $this->assertIsArray( $department_values, 'get_metadata_values should return array' );
        $this->assertNotEmpty( $department_values, 'Should return values when field exists' );
        
        // Should contain our test values in alphabetical order
        $expected_departments = array( 'Finance', 'HR', 'IT' );
        $this->assertEquals( $expected_departments, $department_values, 'Should return unique values in alphabetical order' );
        
        // Test getting priority values  
        $priority_values = $this->metadata_filter->get_metadata_values( 'document', 'priority' );
        
        $expected_priorities = array( 'high', 'low', 'medium' );
        $this->assertEquals( $expected_priorities, $priority_values, 'Should return priority values correctly' );
    }

    /**
     * Test get_metadata_values rejects invalid inputs
     * 
     * What this tests:
     * - Input validation and sanitization
     * - Security against arbitrary field enumeration
     * - Empty/invalid parameter handling
     */
    public function test_get_metadata_values_validates_inputs() {
        // Test empty post type
        $result = $this->metadata_filter->get_metadata_values( '', 'department' );
        $this->assertEmpty( $result, 'Empty post type should return empty array' );
        
        // Test empty field name
        $result = $this->metadata_filter->get_metadata_values( 'document', '' );
        $this->assertEmpty( $result, 'Empty field name should return empty array' );
        
        // Test non-existent post type
        $result = $this->metadata_filter->get_metadata_values( 'nonexistent', 'department' );
        $this->assertEmpty( $result, 'Non-existent post type should return empty array' );
        
        // Test non-existent field (security test - this is important!)
        $result = $this->metadata_filter->get_metadata_values( 'document', 'nonexistent_field' );
        $this->assertEmpty( $result, 'Non-existent field should return empty array for security' );
        
        // Test field that exists for different post type
        $result = $this->metadata_filter->get_metadata_values( 'page', 'department' );
        $this->assertEmpty( $result, 'Field that does not exist for post type should return empty array' );
    }

    /**
     * Test get_metadata_values handles edge cases
     * 
     * What this tests:
     * - Empty metadata values are filtered out
     * - Duplicate values are removed
     * - Values are properly sanitized
     */
    public function test_get_metadata_values_handles_edge_cases() {
        // Create a document with empty metadata value
        $post_id = wp_insert_post( array(
            'post_title' => 'Document with Empty Meta',
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'document'
        ));
        update_post_meta( $post_id, 'department', '' ); // Empty value
        update_post_meta( $post_id, 'test_field', 'duplicate' );
        $this->test_posts[] = $post_id;
        
        // Create another document with same value (to test deduplication)
        $post_id2 = wp_insert_post( array(
            'post_title' => 'Document with Duplicate Meta',
            'post_content' => 'Test content',
            'post_status' => 'publish', 
            'post_type' => 'document'
        ));
        update_post_meta( $post_id2, 'test_field', 'duplicate' ); // Same value
        $this->test_posts[] = $post_id2;
        
        // Test that empty values are filtered out
        $department_values = $this->metadata_filter->get_metadata_values( 'document', 'department' );
        $this->assertNotContains( '', $department_values, 'Empty values should be filtered out' );
        
        // Test that duplicates are removed
        $test_values = $this->metadata_filter->get_metadata_values( 'document', 'test_field' );
        $this->assertEquals( array( 'duplicate' ), $test_values, 'Duplicate values should be removed' );
    }

    /**
     * Test that only published posts are included
     * 
     * What this tests:
     * - Draft/private posts don't contribute to metadata values
     * - Only published content is searchable
     * - Security: private data doesn't leak
     */
    public function test_only_published_posts_included() {
        // Create a draft document with department metadata
        $draft_id = wp_insert_post( array(
            'post_title' => 'Draft Document',
            'post_content' => 'Draft content',
            'post_status' => 'draft', // Not published
            'post_type' => 'document'
        ));
        update_post_meta( $draft_id, 'department', 'Secret' );
        $this->test_posts[] = $draft_id;
        
        // Create a private document with department metadata
        $private_id = wp_insert_post( array(
            'post_title' => 'Private Document',
            'post_content' => 'Private content',
            'post_status' => 'private', // Not public
            'post_type' => 'document'
        ));
        update_post_meta( $private_id, 'department', 'Confidential' );
        $this->test_posts[] = $private_id;
        
        // Get department values
        $department_values = $this->metadata_filter->get_metadata_values( 'document', 'department' );
        
        // Should not contain values from draft or private posts
        $this->assertNotContains( 'Secret', $department_values, 'Draft post metadata should not be included' );
        $this->assertNotContains( 'Confidential', $department_values, 'Private post metadata should not be included' );
        
        // Should still contain our original test values
        $this->assertContains( 'IT', $department_values, 'Published post metadata should be included' );
        $this->assertContains( 'HR', $department_values, 'Published post metadata should be included' );
    }

    /**
     * Test field existence validation
     * 
     * What this tests:
     * - field_exists_for_post_type method through reflection
     * - Security validation logic
     * - Database query correctness
     */
    public function test_field_existence_validation() {
        // Use reflection to test the private method
        $reflection = new ReflectionClass( $this->metadata_filter );
        $method = $reflection->getMethod( 'field_exists_for_post_type' );
        $method->setAccessible( true );
        
        // Test field that exists
        $exists = $method->invoke( $this->metadata_filter, 'document', 'department' );
        $this->assertTrue( $exists, 'field_exists_for_post_type should return true for existing field' );
        
        // Test field that doesn't exist
        $exists = $method->invoke( $this->metadata_filter, 'document', 'nonexistent_field' );
        $this->assertFalse( $exists, 'field_exists_for_post_type should return false for non-existent field' );
        
        // Test field that exists for different post type
        $exists = $method->invoke( $this->metadata_filter, 'page', 'department' );
        $this->assertFalse( $exists, 'field_exists_for_post_type should return false for wrong post type' );
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
                'title' => 'IT Document 2',
                'meta' => array( 'department' => 'IT', 'priority' => 'medium' )
            ),
            array(
                'title' => 'HR Document 1', 
                'meta' => array( 'department' => 'HR', 'priority' => 'low' )
            ),
            array(
                'title' => 'Finance Document 1',
                'meta' => array( 'department' => 'Finance', 'priority' => 'high' )
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