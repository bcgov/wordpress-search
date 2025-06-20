<?php
/**
 * Class QueryFilteringTest
 *
 * Tests the query filtering functionality of the MetadataFilter class.
 * Focuses on testing how URL parameters are converted into WordPress meta queries.
 *
 * @package WordPress_Search
 */
class QueryFilteringTest extends WP_UnitTestCase {

    /**
     * The metadata filter instance for testing.
     *
     * @var \Bcgov\WordpressSearch\MetadataFilter
     */
    private $metadata_filter;

    /**
     * Array of test post IDs for cleanup.
     *
     * @var array
     */
    private $test_posts = array();

    /**
     * Set up test environment before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Create our MetadataFilter instance.
        $this->metadata_filter = new \Bcgov\WordpressSearch\MetadataFilter();
        $this->metadata_filter->init();

        // Create test posts with metadata for filtering.
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

        // Clean up any GET parameters.
        $_GET = array();

        parent::tearDown();
    }

    /**
     * Test that handle_metadata_filtering ignores admin queries
     *
     * What this tests:
     * - Admin queries should not be modified (safety feature)
     * - The method should exit early without making changes
     */
    public function test_ignores_admin_queries() {
        // Set up a URL parameter that would normally trigger filtering.
        $_GET['metadata_department'] = 'IT';

        // Create a query that's marked as admin.
        $query = new WP_Query();
        $query->init();
        $query->is_admin      = true;
        $query->is_main_query = true;

        // Apply the filtering.
        $this->metadata_filter->handle_metadata_filtering( $query );

        // Should not have modified the query.
        $meta_query = $query->get( 'meta_query' );
        $this->assertEmpty( $meta_query, 'Admin queries should not be modified' );

        // Clean up.
        unset( $_GET['metadata_department'] );
    }

    /**
     * Test that handle_metadata_filtering ignores non-main queries
     *
     * What this tests:
     * - Widget queries, secondary queries should not be modified
     * - Only the main page query should be filtered
     */
    public function test_ignores_non_main_queries() {
        // Set up a URL parameter.
        $_GET['metadata_department'] = 'IT';

        // Create a query that's not the main query.
        $query = new WP_Query();
        $query->init();
        $query->is_admin      = false;
        $query->is_main_query = false; // This is the key difference.

        // Apply the filtering.
        $this->metadata_filter->handle_metadata_filtering( $query );

        // Should not have modified the query.
        $meta_query = $query->get( 'meta_query' );
        $this->assertEmpty( $meta_query, 'Non-main queries should not be modified' );

        unset( $_GET['metadata_department'] );
    }

    /**
     * Test single metadata filter creates correct meta query
     *
     * What this tests:
     * - URL parameter like ?metadata_department=IT
     * - Gets converted to proper WordPress meta_query
     * - Post type is set correctly
     * - Requires proper setup of global $post with SearchMetadataFilter block
     */
    public function test_single_metadata_filter() {
        global $post, $wp_query;

        // Create a page with SearchMetadataFilter block content.
        // This simulates a page that has the search block with selectedMetadata="document:department".
        $block_content = '<!-- wp:wordpress-search/search-metadata-filter {"selectedMetadata":"document:department"} /-->';

        $page_id = wp_insert_post(
            array(
				'post_title'   => 'Search Page',
				'post_content' => $block_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
            )
        );

        // Set up global $post (this is what WordPress does when loading a page).
        $post = get_post( $page_id );

        // IMPORTANT: Create posts with metadata so get_all_metadata_fields() finds the "department" field.
        // The method searches the database for existing metadata keys.
        $metadata_post_id = wp_insert_post(
            array(
				'post_title'   => 'Document with Department Metadata',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_type'    => 'document',
            )
        );
        update_post_meta( $metadata_post_id, 'department', 'IT' );

        // Create a main query (like WordPress does for page requests).
        $query = new WP_Query();
        $query->init();
        $query->is_admin = false;

        // Properly set as main query - this is how WordPress does it internally.
        global $wp_the_query;
        $original_wp_the_query = $wp_the_query;
        $wp_the_query          = $query;

        // Set up query variable on the query itself (this is what WordPress does when processing ?metadata_department=IT).
        $query->set( 'metadata_department', 'IT' );

        // Make this query the global query temporarily (needed for get_query_var to work).
        $original_wp_query = $wp_query;
        $wp_query          = $query;

        // Apply the filtering.
        $this->metadata_filter->handle_metadata_filtering( $query );

        // Check that meta_query was created.
        $meta_query = $query->get( 'meta_query' );
        $this->assertNotEmpty( $meta_query, 'Meta query should be created for metadata filters' );
        $this->assertIsArray( $meta_query, 'Meta query should be an array' );

        // Check that post type was set to 'document' (from the block's selectedMetadata).
        $post_type = $query->get( 'post_type' );
        $this->assertEquals( 'document', $post_type, 'Post type should be set to document' );

        // Find the department filter in the meta query.
        $department_filter = null;
        foreach ( $meta_query as $clause ) {
            if ( is_array( $clause ) && isset( $clause['key'] ) && 'department' === $clause['key'] ) {
                $department_filter = $clause;
                break;
            }
        }

        $this->assertNotNull( $department_filter, 'Should find department filter in meta query' );
        $this->assertEquals( 'department', $department_filter['key'], 'Filter key should be department' );
        $this->assertEquals( array( 'IT' ), $department_filter['value'], 'Filter value should be IT in array' );
        $this->assertEquals( 'IN', $department_filter['compare'], 'Filter comparison should be IN' );

        // Clean up.
        $wp_query     = $original_wp_query;
        $wp_the_query = $original_wp_the_query;
        wp_delete_post( $page_id, true );
        wp_delete_post( $metadata_post_id, true );
        $post = null;
    }

    /**
     * Test multiple metadata filters create correct meta query with AND relation
     *
     * What this tests:
     * - Multiple URL parameters like ?metadata_department=IT&metadata_priority=high
     * - Gets converted to proper WordPress meta_query with AND relation
     * - All filters are applied correctly
     */
    public function test_multiple_metadata_filters() {
        global $post, $wp_query, $wp_the_query;

        // Create a page with SearchMetadataFilter blocks for multiple fields.
        $block_content = '
        <!-- wp:wordpress-search/search-metadata-filter {"selectedMetadata":"document:department"} /-->
        <!-- wp:wordpress-search/search-metadata-filter {"selectedMetadata":"document:priority"} /-->';

        $page_id = wp_insert_post(
            array(
				'post_title'   => 'Search Page',
				'post_content' => $block_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
            )
        );

        // Set up global $post.
        $post = get_post( $page_id );

        // Create posts with metadata so get_all_metadata_fields() finds the fields.
        $metadata_post_id = wp_insert_post(
            array(
				'post_title'   => 'Document with Multiple Metadata',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_type'    => 'document',
            )
        );
        update_post_meta( $metadata_post_id, 'department', 'IT' );
        update_post_meta( $metadata_post_id, 'priority', 'high' );

        // Create a main query.
        $query = new WP_Query();
        $query->init();
        $query->is_admin = false;

        // Properly set as main query.
        $original_wp_the_query = $wp_the_query;
        $wp_the_query          = $query;

        // Set up multiple query variables.
        $query->set( 'metadata_department', 'IT' );
        $query->set( 'metadata_priority', 'high' );

        // Make this query the global query temporarily.
        $original_wp_query = $wp_query;
        $wp_query          = $query;

        // Apply the filtering.
        $this->metadata_filter->handle_metadata_filtering( $query );

        // Check that meta_query was created.
        $meta_query = $query->get( 'meta_query' );
        $this->assertNotEmpty( $meta_query, 'Meta query should be created for metadata filters' );
        $this->assertIsArray( $meta_query, 'Meta query should be an array' );

        // Check that relation is set to AND for multiple filters.
        $this->assertEquals( 'AND', $meta_query['relation'], 'Multiple filters should use AND relation' );

        // Check that post type was set to 'document'.
        $post_type = $query->get( 'post_type' );
        $this->assertEquals( 'document', $post_type, 'Post type should be set to document' );

        // Find both filters in the meta query.
        $department_filter = null;
        $priority_filter   = null;

        foreach ( $meta_query as $clause ) {
            if ( is_array( $clause ) && isset( $clause['key'] ) ) {
                if ( 'department' === $clause['key'] ) {
                    $department_filter = $clause;
                } elseif ( 'priority' === $clause['key'] ) {
                    $priority_filter = $clause;
                }
            }
        }

        $this->assertNotNull( $department_filter, 'Should find department filter in meta query' );
        $this->assertEquals( 'department', $department_filter['key'] );
        $this->assertEquals( array( 'IT' ), $department_filter['value'] );
        $this->assertEquals( 'IN', $department_filter['compare'] );

        $this->assertNotNull( $priority_filter, 'Should find priority filter in meta query' );
        $this->assertEquals( 'priority', $priority_filter['key'] );
        $this->assertEquals( array( 'high' ), $priority_filter['value'] );
        $this->assertEquals( 'IN', $priority_filter['compare'] );

        // Clean up.
        $wp_query     = $original_wp_query;
        $wp_the_query = $original_wp_the_query;
        wp_delete_post( $page_id, true );
        wp_delete_post( $metadata_post_id, true );
        $post = null;
    }

    /**
     * Helper method to create test posts with metadata
     */
    private function create_test_posts() {
        $test_data = array(
            array(
                'title' => 'IT Document 1',
                'meta'  => array(
					'department' => 'IT',
					'priority'   => 'high',
				),
            ),
            array(
                'title' => 'HR Document 1',
                'meta'  => array(
					'department' => 'HR',
					'priority'   => 'medium',
				),
            ),
            array(
                'title' => 'Finance Document 1',
                'meta'  => array(
					'department' => 'Finance',
					'priority'   => 'low',
				),
            ),
        );

        foreach ( $test_data as $data ) {
            $post_id = wp_insert_post(
                array(
					'post_title'   => $data['title'],
					'post_content' => 'Test content for ' . $data['title'],
					'post_status'  => 'publish',
					'post_type'    => 'document',
                )
            );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                foreach ( $data['meta'] as $key => $value ) {
                    update_post_meta( $post_id, $key, $value );
                }
                $this->test_posts[] = $post_id;
            }
        }
    }
}
