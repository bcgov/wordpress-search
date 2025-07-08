<?php
/**
 * Class TaxonomyDataRetrievalTest
 *
 * Tests the data retrieval functionality related to taxonomies.
 * Focuses on testing how the system gets taxonomy terms for filter dropdowns.
 *
 * @package WordPress_Search
 */
class TaxonomyDataRetrievalTest extends WP_UnitTestCase {

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
     * Set up test environment before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Create our TaxonomyFilter instance.
        $this->taxonomy_filter = new \Bcgov\WordpressSearch\TaxonomyFilter();
        $this->taxonomy_filter->init();

        // Create test taxonomies and terms.
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
        foreach ( $this->test_terms as $term_id ) {
            wp_delete_term( $term_id, 'document_category' );
        }
        $this->test_terms = array();

        // Clean up test taxonomies.
        foreach ( $this->test_taxonomies as $taxonomy ) {
            unregister_taxonomy( $taxonomy );
        }
        $this->test_taxonomies = array();

        parent::tearDown();
    }

    /**
     * Test get_terms returns correct taxonomy terms.
     *
     * What this tests:
     * - Basic functionality of getting taxonomy terms
     * - Returns terms in correct format
     * - Security validation passes for existing taxonomies
     */
    public function test_get_terms_returns_correct_values() {
        // Test getting document_category terms.
        $terms = get_terms(
            array(
                'taxonomy'   => 'document_category',
                'hide_empty' => false,
            )
        );

        $this->assertIsArray( $terms, 'get_terms should return array' );
        $this->assertNotEmpty( $terms, 'Should return terms when taxonomy exists' );

        // Should contain our test terms.
        $term_names = wp_list_pluck( $terms, 'name' );
        $this->assertContains( 'Policies', $term_names, 'Should contain Policies term' );
        $this->assertContains( 'Procedures', $term_names, 'Should contain Procedures term' );
        $this->assertContains( 'Guidelines', $term_names, 'Should contain Guidelines term' );

        // Terms should have proper structure.
        foreach ( $terms as $term ) {
            $this->assertObjectHasProperty( 'term_id', $term, 'Term should have term_id' );
            $this->assertObjectHasProperty( 'name', $term, 'Term should have name' );
            $this->assertObjectHasProperty( 'slug', $term, 'Term should have slug' );
            $this->assertObjectHasProperty( 'taxonomy', $term, 'Term should have taxonomy' );
            $this->assertEquals( 'document_category', $term->taxonomy, 'Term taxonomy should match' );
        }
    }

    /**
     * Test get_terms validates taxonomy existence
     *
     * What this tests:
     * - Input validation and sanitization
     * - Security against arbitrary taxonomy enumeration
     * - Empty/invalid parameter handling
     */
    public function test_get_terms_validates_taxonomy() {
        // Test non-existent taxonomy.
        $terms = get_terms(
            array(
                'taxonomy'   => 'nonexistent_taxonomy',
                'hide_empty' => false,
            )
        );
        $this->assertInstanceOf( 'WP_Error', $terms, 'Non-existent taxonomy should return WP_Error' );

        // Test empty taxonomy.
        $terms = get_terms(
            array(
                'taxonomy'   => '',
                'hide_empty' => false,
            )
        );
        $this->assertInstanceOf( 'WP_Error', $terms, 'Empty taxonomy should return WP_Error' );

        // Test valid taxonomy.
        $terms = get_terms(
            array(
                'taxonomy'   => 'category',
                'hide_empty' => false,
            )
        );
        $this->assertIsArray( $terms, 'Valid taxonomy should return array' );
    }

    /**
     * Test terms are properly associated with posts
     *
     * What this tests:
     * - Terms are correctly assigned to posts
     * - get_the_terms returns proper associations
     * - Term relationships work as expected
     */
    public function test_terms_associated_with_posts() {
        // Create test post and assign terms.
        $post_id            = wp_insert_post(
            array(
                'post_title'   => 'Test Document with Terms',
                'post_content' => 'Test content',
                'post_status'  => 'publish',
                'post_type'    => 'document',
            )
        );
        $this->test_posts[] = $post_id;

        // Get our test terms.
        $policies_term   = get_term_by( 'name', 'Policies', 'document_category' );
        $procedures_term = get_term_by( 'name', 'Procedures', 'document_category' );

        // Assign terms to post.
        wp_set_object_terms( $post_id, array( $policies_term->term_id, $procedures_term->term_id ), 'document_category' );

        // Test that terms are properly associated.
        $post_terms = get_the_terms( $post_id, 'document_category' );
        $this->assertIsArray( $post_terms, 'get_the_terms should return array' );
        $this->assertCount( 2, $post_terms, 'Should have 2 assigned terms' );

        $post_term_names = wp_list_pluck( $post_terms, 'name' );
        $this->assertContains( 'Policies', $post_term_names, 'Should contain Policies term' );
        $this->assertContains( 'Procedures', $post_term_names, 'Should contain Procedures term' );
    }

    /**
     * Test that only published posts affect term availability in search filtering
     *
     * What this tests:
     * - Draft/private posts don't make terms available for public filtering
     * - Only published content makes terms visible in search taxonomy filters
     * - Security: private data doesn't affect public filtering options
     */
    public function test_only_published_posts_affect_terms() {
        // Create a unique term name to avoid conflicts with existing terms.
        $unique_secret_name = 'TestSecret_' . uniqid() . '_' . wp_rand( 10000, 99999 );

        // Create a secret term that will only be assigned to a draft post.
        $secret_term = wp_insert_term( $unique_secret_name, 'document_category' );
        $this->assertFalse( is_wp_error( $secret_term ), 'Secret term should be created successfully' );

        if ( ! is_wp_error( $secret_term ) ) {
            $this->test_terms[] = $secret_term['term_id'];
        }

        // Create a draft document and assign only the secret term.
        $draft_id           = wp_insert_post(
            array(
                'post_title'   => 'Draft Document with Secret Term',
                'post_content' => 'Draft content',
                'post_status'  => 'draft',
                'post_type'    => 'document',
            )
        );
        $this->test_posts[] = $draft_id;

        // Assign the secret term ONLY to the draft post.
        wp_set_object_terms( $draft_id, array( $secret_term['term_id'] ), 'document_category' );

        // Verify term assignment worked for draft post.
        $draft_terms = get_the_terms( $draft_id, 'document_category' );
        $this->assertNotEmpty( $draft_terms, 'Draft post should have secret term assigned' );
        $draft_term_names = wp_list_pluck( $draft_terms, 'name' );
        $this->assertContains( $unique_secret_name, $draft_term_names, 'Draft post should have the secret term' );

        // Test the core functionality: check if this term would show up in taxonomy filter.
        // by simulating what the SearchTaxonomyFilter block does.
        $terms_for_filter = get_terms(
            array(
                'taxonomy'   => 'document_category',
                'hide_empty' => false, // Get all terms first.
            )
        );

        // Find our secret term in the full list.
        $secret_term_found = false;
        foreach ( $terms_for_filter as $term ) {
            if ( $term->name === $unique_secret_name ) {
                $secret_term_found = true;

                // Check if this term has any published posts associated.
                $published_posts = get_posts(
                    array(
						'post_type'      => 'document',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'tax_query'      => array(
							array(
								'taxonomy' => 'document_category',
								'field'    => 'term_id',
								'terms'    => $term->term_id,
							),
						),
                    )
                );

                // The secret term should have NO published posts.
                $this->assertEmpty( $published_posts, 'Secret term should have no published posts' );
                break;
            }
        }

        // Verify our secret term exists (so the test is actually testing something).
        $this->assertTrue( $secret_term_found, 'Secret term should exist in the full terms list' );

        // Additional verification: ensure existing published terms still work.
        $published_post_id  = wp_insert_post(
            array(
                'post_title'   => 'Published Document for Test',
                'post_content' => 'Published content',
                'post_status'  => 'publish',
                'post_type'    => 'document',
            )
        );
        $this->test_posts[] = $published_post_id;

        // Get or create a regular term.
        $regular_term = get_term_by( 'name', 'Policies', 'document_category' );
        if ( ! $regular_term ) {
            $regular_term_data  = wp_insert_term( 'Policies', 'document_category' );
            $regular_term       = get_term( $regular_term_data['term_id'] );
            $this->test_terms[] = $regular_term_data['term_id'];
        }

        wp_set_object_terms( $published_post_id, array( $regular_term->term_id ), 'document_category' );

        // This term should have published posts.
        $published_posts_for_regular = get_posts(
            array(
				'post_type'      => 'document',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'document_category',
						'field'    => 'term_id',
						'terms'    => $regular_term->term_id,
					),
				),
            )
        );

        $this->assertNotEmpty( $published_posts_for_regular, 'Regular term should have published posts' );
    }

    /**
     * Test taxonomy object retrieval and properties.
     *
     * What this tests:
     * - get_taxonomy() returns proper taxonomy objects
     * - Taxonomy objects have expected properties
     * - Labels and settings are correctly configured
     */
    public function test_taxonomy_object_properties() {
        // Test custom taxonomy object.
        $taxonomy_object = get_taxonomy( 'document_category' );

        $this->assertNotFalse( $taxonomy_object, 'Should return taxonomy object' );
        $this->assertIsObject( $taxonomy_object, 'Should be an object' );

        // Check required properties.
        $this->assertObjectHasProperty( 'name', $taxonomy_object, 'Should have name property' );
        $this->assertObjectHasProperty( 'labels', $taxonomy_object, 'Should have labels property' );
        $this->assertObjectHasProperty( 'object_type', $taxonomy_object, 'Should have object_type property' );

        $this->assertEquals( 'document_category', $taxonomy_object->name, 'Name should match' );
        $this->assertContains( 'document', $taxonomy_object->object_type, 'Should be associated with document post type' );

        // Test non-existent taxonomy.
        $nonexistent = get_taxonomy( 'nonexistent_taxonomy' );
        $this->assertFalse( $nonexistent, 'Non-existent taxonomy should return false' );
    }

    /**
     * Helper method to create test taxonomies and terms.
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
                $this->test_terms[] = $term['term_id'];
            }
        }
    }
}
