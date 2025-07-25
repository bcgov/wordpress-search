<?php
/**
 * Search Result Count Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchResultCount;

global $wp_query;

$total_results   = $wp_query->found_posts;
$current_results = $wp_query->post_count;
$search_query    = get_search_query();

// Determine message.
if ( 0 === $total_results ) {
	$message = ! empty( $search_query )
		? sprintf(
			/* translators: %s: search query */
			__( 'No results found for "%s"', 'wordpress-search' ),
			esc_html( $search_query )
		)
		: __( 'No results found', 'wordpress-search' );
} else {
	$result_word = ( 1 === $total_results ) ? __( 'result', 'wordpress-search' ) : __( 'results', 'wordpress-search' );
	$message     = ! empty( $search_query )
		? sprintf(
			/* translators: 1: result word (result/results), 2: search query */
			__( '%1$s found for "%2$s"', 'wordpress-search' ),
			$result_word,
			esc_html( $search_query )
		)
		: sprintf(
			/* translators: %s: result word (result/results) */
			__( '%s found', 'wordpress-search' ),
			$result_word
		);
}

?>

<div class="search-result-count">
	<div class="search-result-count__content">
		<span class="search-result-count__number"><?php echo esc_html( $total_results ); ?></span>
		<span class="search-result-count__message"><?php echo esc_html( $message ); ?></span>
	</div>
</div>
