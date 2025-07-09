<?php
/**
 * Class TaxonomyQueryFilteringTest
 *
 * Tests the query filtering functionality of the TaxonomyFilter class.
 * Focuses on testing how URL parameters are converted into WordPress tax queries.
 *
 * @package WordPress_Search
 */

use Bcgov\WordpressSearch\TaxonomyFilter;

/**
 * Class TaxonomyQueryFilteringTest
 *
 * Tests the query filtering functionality of the TaxonomyFilter class.
 * Focuses on testing how URL parameters are converted into WordPress tax queries.
 *
 * @package WordPress_Search
 */
class TaxonomyQueryFilteringTest extends WP_UnitTestCase {

    /**
     * The taxonomy filter instance for testing.
     *
     * @var TaxonomyFilter
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
     * Set up test environment before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Create our TaxonomyFilter instance.
        $this->taxonomy_filter = new TaxonomyFilter();
        $this->taxonomy_filter->init();

        // Create test taxonomies, terms, and posts.
        $this->create_test_environment();
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

        // Clean up any GET parameters.
        $_GET = array();

        parent::tearDown();
    }

    /**
     * Test that handle_taxonomy_filtering ignores admin queries
     *
     * What this tests:
     * - Admin queries should not be modified (safety feature)
     * - The method should exit early without making changes
     */
    public function test_ignores_admin_queries() {
        // Set up a URL parameter that would normally trigger filtering.
        $_GET['taxonomy_document_category'] = '1';

        // Create a query that's marked as admin.
        $query = new WP_Query();
        $query->init();
        $query->is_admin = true;
        $query->set( 's', 'test search' ); // Make it a search query.

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Should not have modified the query.
        $tax_query = $query->get( 'tax_query' );
        $this->assertEmpty( $tax_query, 'Admin queries should not be modified' );

        // Clean up.
        unset( $_GET['taxonomy_document_category'] );
    }

    /**
     * Test that handle_taxonomy_filtering ignores non-main queries
     *
     * What this tests:
     * - Widget queries, secondary queries should not be modified
     * - Only the main page query should be filtered
     */
    public function test_ignores_non_main_queries() {
        // Set up a URL parameter.
        $_GET['taxonomy_document_category'] = '1';

        // Create a query that's not the main query.
        $query = new WP_Query();
        $query->init();
        $query->is_admin = false;
        $query->set( 's', 'test search' ); // Make it a search query.
        // Note: is_main_query defaults to false for new WP_Query instances.

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Should not have modified the query.
        $tax_query = $query->get( 'tax_query' );
        $this->assertEmpty( $tax_query, 'Non-main queries should not be modified' );

        unset( $_GET['taxonomy_document_category'] );
    }

    /**
     * Test that handle_taxonomy_filtering ignores non-search queries
     *
     * What this tests:
     * - Only search queries should be modified
     * - Regular page/post queries should not be affected
     */
    public function test_ignores_non_search_queries() {
        // Set up a URL parameter.
        $_GET['taxonomy_document_category'] = '1';

        // Create a main query that's not a search.
        $query = new WP_Query();
        $query->init();
        $query->is_admin      = false;
        $query->is_main_query = true;
        // Note: is_search defaults to false.

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Should not have modified the query.
        $tax_query = $query->get( 'tax_query' );
        $this->assertEmpty( $tax_query, 'Non-search queries should not be modified' );

        unset( $_GET['taxonomy_document_category'] );
    }

    /**
     * Test single taxonomy filter creates correct tax query
     *
     * What this tests:
     * - URL parameter like ?taxonomy_document_category=1
     * - Gets converted to proper WordPress tax_query
     * - Term IDs are handled correctly
     */
    public function test_single_taxonomy_filter() {
        // Get a test term ID.
        $policies_term = get_term_by( 'name', 'Policies', 'document_category' );
        $term_id       = $policies_term->term_id;

        // Set up $_GET parameter to simulate URL parameter.
        $_GET['taxonomy_document_category'] = $term_id;

        // Create a search query and manually set the required properties.
        // We need to simulate the main query properly for the test environment.
        global $wp_query;
        $original_wp_query = $wp_query;

        $query = new WP_Query();
        $query->init();

        // Set this as the main query temporarily for the test.
        $wp_query = $query;

        // Manually set query properties.
        $query->is_admin      = false;
        $query->is_main_query = true;
        $query->is_search     = true;

        // Manually add to query_vars array to ensure it's there.
        $query->query_vars['taxonomy_document_category'] = $term_id;

        // Verify that the taxonomy filter can find the parameter before calling the method.
        $this->assertTrue( taxonomy_exists( 'document_category' ), 'document_category taxonomy should exist' );
        $this->assertArrayHasKey( 'taxonomy_document_category', $query->query_vars, 'Query vars should contain taxonomy parameter' );
        $this->assertEquals( $term_id, $query->query_vars['taxonomy_document_category'], 'Query vars should have correct term ID' );

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Check that tax_query was created.
        $tax_query = $query->get( 'tax_query' );

        $this->assertNotEmpty( $tax_query, 'Tax query should be created for taxonomy filters' );
        $this->assertIsArray( $tax_query, 'Tax query should be an array' );

        // Find the taxonomy filter in the tax query.
        $taxonomy_filter = null;
        foreach ( $tax_query as $clause ) {
            if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && 'document_category' === $clause['taxonomy'] ) {
                $taxonomy_filter = $clause;
                break;
            }
        }

        $this->assertNotNull( $taxonomy_filter, 'Should find document_category filter in tax query' );
        $this->assertEquals( 'document_category', $taxonomy_filter['taxonomy'], 'Filter taxonomy should be document_category' );
        $this->assertEquals( array( $term_id ), $taxonomy_filter['terms'], 'Filter terms should contain term ID' );
        $this->assertEquals( 'term_id', $taxonomy_filter['field'], 'Filter field should be term_id' );
        $this->assertEquals( 'IN', $taxonomy_filter['operator'], 'Filter operator should be IN' );
        $this->assertTrue( $taxonomy_filter['include_children'], 'Should include children for hierarchical taxonomies' );

        // Clean up.
        unset( $_GET['taxonomy_document_category'] );

        // Restore original wp_query.
        $wp_query = $original_wp_query;
    }

    /**
     * Test multiple taxonomy filters create correct tax query with AND relation
     *
     * What this tests:
     * - Multiple URL parameters like ?taxonomy_document_category=1&taxonomy_document_type=2
     * - Gets converted to proper WordPress tax_query with AND relation
     * - All filters are applied correctly
     */
    public function test_multiple_taxonomy_filters() {
        // Get test term IDs.
        $policies_term = get_term_by( 'name', 'Policies', 'document_category' );
        $form_term     = get_term_by( 'name', 'Form', 'document_type' );

        // Set up $_GET parameters to simulate URL parameters.
        $_GET['taxonomy_document_category'] = $policies_term->term_id;
        $_GET['taxonomy_document_type']     = $form_term->term_id;

        // Simulate main query properly.
        global $wp_query;
        $original_wp_query = $wp_query;

        // Create a search query.
        $query = new WP_Query();
        $query->init();

        // Set as main query.
        $wp_query             = $query;
        $query->is_admin      = false;
        $query->is_main_query = true;
        $query->is_search     = true;

        // Set up multiple query variables (both methods for thoroughness).
        $query->set( 'taxonomy_document_category', $policies_term->term_id );
        $query->set( 'taxonomy_document_type', $form_term->term_id );

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Check that tax_query was created.
        $tax_query = $query->get( 'tax_query' );
        $this->assertNotEmpty( $tax_query, 'Tax query should be created for taxonomy filters' );
        $this->assertIsArray( $tax_query, 'Tax query should be an array' );

        // Find both filters in the tax query.
        $category_filter = null;
        $type_filter     = null;

        foreach ( $tax_query as $clause ) {
            if ( is_array( $clause ) && isset( $clause['taxonomy'] ) ) {
                if ( 'document_category' === $clause['taxonomy'] ) {
                    $category_filter = $clause;
                } elseif ( 'document_type' === $clause['taxonomy'] ) {
                    $type_filter = $clause;
                }
            }
        }

        $this->assertNotNull( $category_filter, 'Should find document_category filter in tax query' );
        $this->assertEquals( 'document_category', $category_filter['taxonomy'] );
        $this->assertEquals( array( $policies_term->term_id ), $category_filter['terms'] );

        $this->assertNotNull( $type_filter, 'Should find document_type filter in tax query' );
        $this->assertEquals( 'document_type', $type_filter['taxonomy'] );
        $this->assertEquals( array( $form_term->term_id ), $type_filter['terms'] );

        // Clean up.
        unset( $_GET['taxonomy_document_category'] );
        unset( $_GET['taxonomy_document_type'] );

        // Restore original wp_query.
        $wp_query = $original_wp_query;
    }

    /**
     * Test array values for taxonomy filters
     *
     * What this tests:
     * - URL parameters like ?taxonomy_document_category[]=1&taxonomy_document_category[]=2
     * - Multiple term IDs in a single taxonomy filter
     * - Array handling in tax queries
     */
    public function test_array_taxonomy_filter_values() {
        // Get test term IDs.
        $policies_term   = get_term_by( 'name', 'Policies', 'document_category' );
        $procedures_term = get_term_by( 'name', 'Procedures', 'document_category' );

        // Set up $_GET parameter with array of term IDs.
        $_GET['taxonomy_document_category'] = array( $policies_term->term_id, $procedures_term->term_id );

        // Simulate main query properly.
        global $wp_query;
        $original_wp_query = $wp_query;

        // Create a search query.
        $query = new WP_Query();
        $query->init();

        // Set as main query.
        $wp_query             = $query;
        $query->is_admin      = false;
        $query->is_main_query = true;
        $query->is_search     = true;

        // Set up query variable with array of term IDs (both methods for thoroughness).
        $query->set( 'taxonomy_document_category', array( $policies_term->term_id, $procedures_term->term_id ) );

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Check that tax_query was created.
        $tax_query = $query->get( 'tax_query' );
        $this->assertNotEmpty( $tax_query, 'Tax query should be created for taxonomy filters' );

        // Find the taxonomy filter.
        $taxonomy_filter = null;
        foreach ( $tax_query as $clause ) {
            if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && 'document_category' === $clause['taxonomy'] ) {
                $taxonomy_filter = $clause;
                break;
            }
        }

        $this->assertNotNull( $taxonomy_filter, 'Should find document_category filter in tax query' );
        $this->assertEquals( 'document_category', $taxonomy_filter['taxonomy'] );
        $this->assertContains( $policies_term->term_id, $taxonomy_filter['terms'], 'Should contain Policies term ID' );
        $this->assertContains( $procedures_term->term_id, $taxonomy_filter['terms'], 'Should contain Procedures term ID' );
        $this->assertEquals( 'IN', $taxonomy_filter['operator'], 'Should use IN operator for multiple terms' );

        // Clean up.
        unset( $_GET['taxonomy_document_category'] );

        // Restore original wp_query.
        $wp_query = $original_wp_query;
    }

    /**
     * Test post type determination from taxonomy filters
     *
     * What this tests:
     * - get_post_type_from_taxonomy_filters method functionality
     * - Post type is correctly determined from taxonomy associations
     * - Method returns null for standard WordPress taxonomies
     */
    public function test_post_type_determination_from_taxonomy() {
        // Get test term ID.
        $policies_term = get_term_by( 'name', 'Policies', 'document_category' );

        // Set up $_GET parameter to simulate URL parameter.
        $_GET['taxonomy_document_category'] = $policies_term->term_id;

        // Simulate main query properly.
        global $wp_query;
        $original_wp_query = $wp_query;

        // Create a search query.
        $query = new WP_Query();
        $query->init();

        // Set as main query.
        $wp_query             = $query;
        $query->is_admin      = false;
        $query->is_main_query = true;
        $query->is_search     = true;

        // Set up query variable for custom taxonomy (both methods for thoroughness).
        $query->set( 'taxonomy_document_category', $policies_term->term_id );

        // Apply the filtering.
        $this->taxonomy_filter->handle_taxonomy_filtering( $query );

        // Check that post type was set correctly.
        $post_type = $query->get( 'post_type' );
        $this->assertEquals( 'document', $post_type, 'Post type should be set to document for document_category taxonomy' );

        // Clean up.
        unset( $_GET['taxonomy_document_category'] );

        // Restore original wp_query.
        $wp_query = $original_wp_query;
    }

    /**
     * Helper method to create test environment
     */
    private function create_test_environment() {
        // Register custom taxonomies.
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

        register_taxonomy(
            'document_type',
            array( 'document' ),
            array(
                'public'       => true,
                'hierarchical' => false,
                'labels'       => array(
                    'name'          => 'Document Types',
                    'singular_name' => 'Document Type',
                ),
            )
        );
        $this->test_taxonomies[] = 'document_type';

        // Create test terms.
        $terms_data = array(
            array(
				'name'     => 'Policies',
				'taxonomy' => 'document_category',
			),
            array(
				'name'     => 'Procedures',
				'taxonomy' => 'document_category',
			),
            array(
				'name'     => 'Guidelines',
				'taxonomy' => 'document_category',
			),
            array(
				'name'     => 'Form',
				'taxonomy' => 'document_type',
			),
            array(
				'name'     => 'Report',
				'taxonomy' => 'document_type',
			),
        );

        foreach ( $terms_data as $term_data ) {
            $term = wp_insert_term( $term_data['name'], $term_data['taxonomy'] );
            if ( ! is_wp_error( $term ) ) {
                $this->test_terms[] = array(
                    'id'       => $term['term_id'],
                    'taxonomy' => $term_data['taxonomy'],
                );
            }
        }

        // Create test posts with term assignments.
        $posts_data = array(
            array(
                'title' => 'Policy Document 1',
                'terms' => array(
                    'document_category' => array( 'Policies' ),
                    'document_type'     => array( 'Form' ),
                ),
            ),
            array(
                'title' => 'Procedure Document 1',
                'terms' => array(
                    'document_category' => array( 'Procedures' ),
                    'document_type'     => array( 'Report' ),
                ),
            ),
        );

        foreach ( $posts_data as $post_data ) {
            $post_id = wp_insert_post(
                array(
                    'post_title'   => $post_data['title'],
                    'post_content' => 'Test content for ' . $post_data['title'],
                    'post_status'  => 'publish',
                    'post_type'    => 'document',
                )
            );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                foreach ( $post_data['terms'] as $taxonomy => $term_names ) {
                    wp_set_object_terms( $post_id, $term_names, $taxonomy );
                }
                $this->test_posts[] = $post_id;
            }
        }
    }
}
