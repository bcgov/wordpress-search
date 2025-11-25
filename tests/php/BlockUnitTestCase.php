<?php

namespace Bcgov\WordpressSearch\Test;

class BlockUnitTestCase extends \WP_UnitTestCase {

    protected $block_name;
    protected $block_path;

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
        $render_file = dirname( __DIR__, 2 ) . '/' . $this->block_path . 'render.php';
        include $render_file;

        return ob_get_clean();
    }
}