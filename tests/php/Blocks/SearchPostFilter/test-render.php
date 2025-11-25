<?php

namespace Bcgov\WordpressSearch\Test\SearchPostFilter;

use Bcgov\WordpressSearch\Test\BlockUnitTestCase;

/**
 * Tests the rendering of the SearchPostFilter block on the front end.
 *
 * @package WordPress_Search
 */
class RenderTest extends BlockUnitTestCase {

    /**
     * Set block name and class for parent class.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->block_name  = 'wordpress-search/search-post-filter';
        $this->block_class = 'SearchPostFilter';
    }

    /**
     * Snapshot test for block providing no attributes.
     *
     * @return void
     */
    public function test_no_attributes() {
        $output = $this->render_block();

        $this->assert_equals_snapshot( 'no-attributes.html', $output );
    }

    /**
     * Snapshot test for block providing custom post type.
     *
     * @return void
     */
    public function test_post_types() {
        register_post_type(
            'test_post_type',
            [
				'public' => true,
				'label'  => 'Test Post Type',
			]
        );

        $output = $this->render_block(
            [
				'selectedPostTypes' => [ 'post', 'page', 'test_post_type' ],
			]
        );

        $this->assert_equals_snapshot( 'with-post-types.html', $output );
    }
}
