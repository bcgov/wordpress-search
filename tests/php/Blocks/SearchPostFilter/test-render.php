<?php

namespace Bcgov\WordpressSearch\Test\SearchPostFilter;

use Bcgov\WordpressSearch\Test\BlockUnitTestCase;

/**
 * Class SearchBlockFunctionalityTest
 *
 * Tests the functionality and integration of the Search block.
 * Focuses on testing WordPress search integration and form behavior.
 *
 * @package WordPress_Search
 */
class RenderTest extends BlockUnitTestCase {

    public function setUp(): void {
        parent::setUp();
        $this->block_name = 'wordpress-search/search-post-filter';
        $this->block_path = 'Blocks/src/SearchPostFilter/';
    }

    public function test_no_attributes() {
        $output = $this->render_block();

        $this->assertEquals(file_get_contents('tests/php/Blocks/SearchPostFilter/__snapshots__/no-attributes.html'), $output);
    }

    public function test_post_types() {
        register_post_type('test_post_type', ['public' => true, 'label' => 'Test Post Type']);

        $output = $this->render_block([
            'selectedPostTypes' => ['post', 'page', 'test_post_type']
        ]);

        $this->assertEquals(file_get_contents('tests/php/Blocks/SearchPostFilter/__snapshots__/with-post-types.html'), $output);
    }
}
