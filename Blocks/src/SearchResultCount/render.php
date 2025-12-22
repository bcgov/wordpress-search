<?php
/**
 * Search Result Count Block - Frontend Render
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch\SearchResultCount;

global $wp_query;

$total_results = $wp_query->found_posts;
$message       = sprintf(
	/* translators: %d: number of results */
	_n( '%d result found', '%d results found', $total_results, 'wordpress-search' ),
	$total_results
);

?>

<div class="search-result-count">
	<div class="search-result-count__content">
		<span class="search-result-count__message"><?php echo esc_html( $message ); ?></span>
	</div>
</div>
