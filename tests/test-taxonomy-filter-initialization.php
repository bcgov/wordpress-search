<?php
/**
 * Class BasicTaxonomyFilterTest
 *
 * This test class contains basic tests to verify that the TaxonomyFilter
 * class can be properly instantiated and initialized. This is our starting point
 * for testing the SearchTaxonomyFilter block PHP functionality.
 *
 * @package WordPress_Search
 */

use Bcgov\WordpressSearch\TaxonomyFilter;

/**
 * Class BasicTaxonomyFilterTest
 *
 * This test class contains basic tests to verify that the TaxonomyFilter
 * class can be properly instantiated and initialized. This is our starting point
 * for testing the SearchTaxonomyFilter block PHP functionality.
 *
 * @package WordPress_Search
 */
class BasicTaxonomyFilterTest extends WP_UnitTestCase {

    /**
     * Test that the TaxonomyFilter class can be instantiated and initialized
     *
     * What this test does:
     * 1. Creates a new instance of the TaxonomyFilter class
     * 2. Calls the init() method to register WordPress hooks
     * 3. Verifies that the required WordPress hooks are properly registered
     *
     * Why this is important:
     * - This is the foundation of all functionality - the class must load and initialize
     * - It tests that our autoloading is working correctly
     * - It verifies that WordPress hooks are registered, which is how the filtering works
     */
    public function test_taxonomy_filter_initialization() {
        // Create and initialize the TaxonomyFilter.
        $taxonomy_filter = new TaxonomyFilter();
        $this->assertInstanceOf( TaxonomyFilter::class, $taxonomy_filter );

        $taxonomy_filter->init();

        // Verify WordPress hooks are registered.
        $this->assertNotFalse(
            has_filter( 'query_vars', array( $taxonomy_filter, 'add_query_vars' ) ),
            'The query_vars filter was not registered. URL parameters like ?taxonomy_category=5 will not work. Check that add_filter() is called in init() method.'
        );

        $this->assertNotFalse(
            has_action( 'pre_get_posts', array( $taxonomy_filter, 'handle_taxonomy_filtering' ) ),
            'The pre_get_posts action was not registered. Search filtering will not work. Check that add_action() is called in init() method.'
        );
    }

    /**
     * Test parsing of selectedTaxonomy format (Pure PHP test)
     *
     * What this tests:
     * - Parsing "posttype:taxonomy" format used throughout the block
     * - Edge cases like empty strings, malformed input, extra colons
     * - This is pure PHP logic - no WordPress dependencies
     */
    public function test_selected_taxonomy_parsing() {
        // Test valid format.
        $selected_taxonomy = 'document:document_category';
        $parts             = explode( ':', $selected_taxonomy );

        $this->assertCount( 2, $parts, 'Valid selectedTaxonomy should split into exactly 2 parts' );
        $this->assertEquals( 'document', $parts[0], 'First part should be the post type' );
        $this->assertEquals( 'document_category', $parts[1], 'Second part should be the taxonomy name' );

        // Test empty string.
        $selected_taxonomy = '';
        $parts             = explode( ':', $selected_taxonomy );
        $this->assertCount( 1, $parts, 'Empty string should result in 1 part (empty string)' );
        $this->assertEquals( '', $parts[0], 'Empty string should remain empty' );

        // Test missing colon.
        $selected_taxonomy = 'document_category';
        $parts             = explode( ':', $selected_taxonomy );
        $this->assertCount( 1, $parts, 'String without colon should result in 1 part' );
        $this->assertEquals( 'document_category', $parts[0], 'String without colon should be unchanged' );

        // Test too many colons.
        $selected_taxonomy = 'document:document_category:extra';
        $parts             = explode( ':', $selected_taxonomy );
        $this->assertCount( 3, $parts, 'String with extra colons should split into 3 parts' );
        $this->assertEquals( 'document', $parts[0] );
        $this->assertEquals( 'document_category', $parts[1] );
        $this->assertEquals( 'extra', $parts[2] );

        // Test validation logic used in the actual code.
        $valid_cases = array(
            'document:document_category' => true,
            'post:category'              => true,
            'page:page_taxonomy'         => true,
        );

        $invalid_cases = array(
            ''                                 => false,
            'document'                         => false,
            'document:'                        => false,
            ':document_category'               => false,
            'document:document_category:extra' => false,
        );

        foreach ( $valid_cases as $input => $expected ) {
            $parts    = explode( ':', $input );
            $is_valid = ( count( $parts ) === 2 && ! empty( $parts[0] ) && ! empty( $parts[1] ) );
            $this->assertTrue( $is_valid, "'{$input}' should be valid selectedTaxonomy format" );
        }

        foreach ( $invalid_cases as $input => $expected ) {
            $parts    = explode( ':', $input );
            $is_valid = ( count( $parts ) === 2 && ! empty( $parts[0] ) && ! empty( $parts[1] ) );
            $this->assertFalse( $is_valid, "'{$input}' should be invalid selectedTaxonomy format" );
        }
    }

    /**
     * Test add_query_vars method (PHP + WordPress integration test)
     *
     * What this tests:
     * - The add_query_vars method correctly adds taxonomy fields to WordPress query vars
     * - All registered taxonomies get proper "taxonomy_" prefixes
     * - WordPress query var system integration
     */
    public function test_add_query_vars() {
        $taxonomy_filter = new TaxonomyFilter();

        // Create a custom taxonomy for testing.
        register_taxonomy(
            'test_taxonomy',
            array( 'document' ),
            array(
                'public' => true,
                'label'  => 'Test Taxonomy',
            )
        );

        // Test the add_query_vars method.
        $initial_vars = array( 's', 'p', 'page' );
        $result       = $taxonomy_filter->add_query_vars( $initial_vars );

        // Should preserve original query vars.
        $this->assertContains( 's', $result, 'Original query vars should be preserved' );
        $this->assertContains( 'p', $result, 'Original query vars should be preserved' );
        $this->assertContains( 'page', $result, 'Original query vars should be preserved' );

        // Should add taxonomy query vars with proper prefix.
        $this->assertContains( 'taxonomy_category', $result, 'Should add taxonomy_category query var' );
        $this->assertContains( 'taxonomy_post_tag', $result, 'Should add taxonomy_post_tag query var' );
        $this->assertContains( 'taxonomy_test_taxonomy', $result, 'Should add taxonomy_test_taxonomy query var' );

        // Result should be an array.
        $this->assertIsArray( $result, 'add_query_vars should return an array' );

        // Should have more vars than we started with.
        $this->assertGreaterThan( count( $initial_vars ), count( $result ), 'Should add new query vars to the array' );

        // Clean up test taxonomy.
        unregister_taxonomy( 'test_taxonomy' );
    }

    /**
     * Test taxonomy existence validation
     *
     * What this tests:
     * - taxonomy_exists() function integration
     * - Validation logic for registered taxonomies
     * - Security validation against non-existent taxonomies
     */
    public function test_taxonomy_existence_validation() {
        // Test with built-in WordPress taxonomies.
        $this->assertTrue( taxonomy_exists( 'category' ), 'Category taxonomy should exist' );
        $this->assertTrue( taxonomy_exists( 'post_tag' ), 'Post tag taxonomy should exist' );

        // Test with non-existent taxonomy.
        $this->assertFalse( taxonomy_exists( 'nonexistent_taxonomy' ), 'Non-existent taxonomy should return false' );

        // Test with empty string.
        $this->assertFalse( taxonomy_exists( '' ), 'Empty taxonomy name should return false' );

        // Test with custom taxonomy.
        register_taxonomy(
            'test_custom_taxonomy',
            array( 'document' ),
            array(
                'public' => true,
                'label'  => 'Test Custom Taxonomy',
            )
        );

        $this->assertTrue( taxonomy_exists( 'test_custom_taxonomy' ), 'Custom taxonomy should exist after registration' );

        // Clean up.
        unregister_taxonomy( 'test_custom_taxonomy' );
        $this->assertFalse( taxonomy_exists( 'test_custom_taxonomy' ), 'Custom taxonomy should not exist after unregistration' );
    }
}
