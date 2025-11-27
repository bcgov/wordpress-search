<?php

namespace Bcgov\WordpressSearch\Test;

/**
 * Test case class mainly for testing a custom WP block's render function
 * for blocks that use server-side rendering.
 */
class BlockUnitTestCase extends \WP_UnitTestCase {

    /**
     * Block name, eg. "wordpress-search/search-post-filter".
     *
     * @var string
     */
    protected $block_name;

    /**
     * Block class, eg. "SearchPostFilter".
     *
     * @var string
     */
    protected $block_class;

    /**
     * Helper method to render blocks.
     *
     * @param array $attributes Block attributes.
     * @return string Block output.
     */
    protected function render_block( array $attributes = [] ): string {
        // Include the render file and capture output.
        ob_start();

        // Set up the required variables that render.php expects.
        $block = (object) array(
            'blockName' => $this->block_name,
        );

        // Include the render file from the correct path.
        $render_file = dirname( __DIR__, 2 ) . '/Blocks/src/' . $this->block_class . '/render.php';
        include $render_file;

        return ob_get_clean();
    }

    /**
     * Asserts that the HTML output of a block render function and a given snapshot are equal.
     *
     * @param string $expected_snapshot_filename The filename of a file in the Block's __snapshots__ directory.
     * @param string $actual                     The actual output of the block render function.
     * @return void
     */
    public function assert_equals_snapshot( string $expected_snapshot_filename, string $actual ) {
        // phpcs:ignore
        $expected = file_get_contents( dirname( __DIR__, 2 ) . '/tests/php/Blocks/' . $this->block_class . '/__snapshots__/' . $expected_snapshot_filename );
        $this->assertEquals( $expected, $actual );
    }
}
