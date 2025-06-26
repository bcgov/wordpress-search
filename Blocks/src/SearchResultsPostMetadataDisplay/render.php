<?php

namespace Bcgov\WordpressSearch\SearchResultsPostMetadataDisplay;

// Get the current post using the most reliable method based on context
// For search results in a loop context, we prioritize the loop post
// For single posts/pages, we use the queried object.
$current_post = null;

// First, try to get the post from the current loop context if we're in a loop.
if ( in_the_loop() ) {
    // We're in a loop, so get_post() is more appropriate for the current loop item.
    $current_post = get_post();
} else {
    // We're not in a loop, so get the main queried object.
    // Use the most reliable method from wp_the_query global.
    $queried_object = $GLOBALS['wp_the_query']->get_queried_object();

    // Ensure it's a post object (could be a term, user, etc.).
    if ( $queried_object instanceof \WP_Post ) {
        $current_post = $queried_object;
    }
}

// Fallback: if still no post, try get_post() as last resort.
if ( ! $current_post ) {
    $current_post = get_post();
}

if ( ! $current_post ) {
    return;
}

// Get all metadata for the current post.
$metadata = get_post_meta( $current_post->ID );

// Filter out empty values and WordPress internal meta keys (starting with _).
// Also exclude specific document file metadata fields.
$excluded_fields = [
    'document_file_id',
    'document_file_url',
    'document_file_name',
    'document_file_size',
    'document_file_type',
];

$filtered_metadata = array();
foreach ( $metadata as $key => $values ) {
    // Skip internal WordPress meta keys, empty values, and excluded document fields.
	if ( ! str_starts_with( $key, '_' ) && ! empty( $values ) && ! in_array( $key, $excluded_fields, true ) ) {
        $filtered_metadata[ $key ] = $values;
    }
}

// Get block attributes.
$font_size = $attributes['fontSize'] ?? 'medium';

// Build inline styles for font size.
$style_attr = '';
if ( $font_size ) {
    // Check if it's a preset size (no units) or custom value (has px, em, rem, etc.).
    if ( preg_match( '/\d+(px|em|rem|%|vh|vw)/', $font_size ) ) {
        // Custom value with units - use directly.
        $style_attr = sprintf( 'style="font-size: %s;"', esc_attr( $font_size ) );
    } else {
        // Preset size - use CSS custom property.
        $style_attr = sprintf( 'style="font-size: var(--wp--preset--font-size--%s);"', esc_attr( $font_size ) );
    }
}

?>
<div class="wp-block-wordpress-search-search-results-post-metadata-display" <?php echo wp_kses_post( $style_attr ); ?>>
    <?php if ( ! empty( $filtered_metadata ) ) : ?>
        <div class="post-metadata">
            <?php
            $metadata_items = array();
            foreach ( $filtered_metadata as $key => $values ) {
                $formatted_key = ucwords( str_replace( [ '_', '-' ], ' ', $key ) );

                // Handle multiple values.
                if ( is_array( $values ) ) {
                    $display_values = array();
                    foreach ( $values as $value ) {
                        if ( is_string( $value ) && ! empty( trim( $value ) ) ) {
                            $display_values[] = esc_html( $value );
                        }
                    }
                    $formatted_value = implode( ', ', $display_values );
                } else {
                    $formatted_value = esc_html( $values );
                }

                if ( ! empty( $formatted_value ) ) {
                    $metadata_items[] = esc_html( $formatted_key ) . ': ' . $formatted_value;
                }
            }

            // Display all metadata items inline, separated by spaces.
            echo wp_kses_post( implode( ' &nbsp;&nbsp; ', $metadata_items ) );
            ?>
        </div>
    <?php else : ?>
        <div class="post-metadata no-metadata">
            <p>No custom metadata available for this post.</p>
        </div>
    <?php endif; ?>
</div>