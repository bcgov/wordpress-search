<?php
/**
 * Search Result Count Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchResultCount;

global $wp_query;

$total_results = $wp_query->found_posts;
$result_word   = ( 1 === $total_results ) ? __( 'result', 'wordpress-search' ) : __( 'results', 'wordpress-search' );
$message       = sprintf(
	/* translators: 1: number of results, 2: result word (result/results) */
	__( '%1$d %2$s found', 'wordpress-search' ),
	$total_results,
	$result_word
);

?>

<div class="search-result-count">
	<div class="search-result-count__content">
		<span class="search-result-count__message"><?php echo esc_html( $message ); ?></span>
	</div>
</div>
