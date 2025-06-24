<?php

namespace Bcgov\WordpressSearch\SearchResultsPostMetadataDisplay;

// Get the current post in the query loop context.
global $post;

if ( ! $post ) {
    return;
}

// Get all metadata for the current post.
$metadata = get_post_meta( $post->ID );

// Filter out empty values and WordPress internal meta keys (starting with _)
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

?>
<div class="wp-block-wordpress-search-search-results-post-metadata-display">
    <?php if ( ! empty( $filtered_metadata ) ) : ?>
        <div class="post-metadata">
            <h4 class="metadata-title">Post Metadata</h4>
            <div class="metadata-list">
                <?php foreach ( $filtered_metadata as $key => $values ) : ?>
                    <div class="metadata-item">
                        <span class="metadata-key"><?php echo esc_html( ucwords( str_replace( [ '_', '-' ], ' ', $key ) ) ); ?>:</span>
                        <span class="metadata-value">
                            <?php
                            // Handle multiple values.
                            if ( is_array( $values ) ) {
                                $display_values = array();
                                foreach ( $values as $value ) {
                                    if ( is_string( $value ) && ! empty( trim( $value ) ) ) {
                                        $display_values[] = esc_html( $value );
                                    }
                                }
                                                                 echo esc_html( implode( ', ', $display_values ) );
                            }
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else : ?>
        <div class="post-metadata no-metadata">
            <p>No custom metadata available for this post.</p>
        </div>
    <?php endif; ?>
</div>