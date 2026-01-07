import './view.scss';

document.addEventListener('DOMContentLoaded', function () {
	// Handle sort dropdown changes
	const sortSelects = document.querySelectorAll(
		'.search-results-sort__sort-select'
	);

	sortSelects.forEach(function (select) {
		select.addEventListener('change', function () {
			const currentUrl = this.dataset.currentUrl;
			const sortValue = this.value;

			// Build new URL with sort parameters while preserving all other filters
			let newUrl = currentUrl;
			const url = new URL(newUrl, window.location.origin);
			const searchParams = new URLSearchParams(url.search);

			if (sortValue === 'relevance') {
				// Handle relevance sorting (default behavior)
				// Remove all sort parameters to use default relevance ranking
				searchParams.delete('sort');
				searchParams.delete('meta_sort');
				searchParams.delete('meta_field');

				newUrl = url.pathname + '?' + searchParams.toString();
			} else if (sortValue.startsWith('title_')) {
				// Handle title sorting
				// Remove existing sort parameters
				searchParams.delete('sort');
				searchParams.delete('meta_sort');
				searchParams.delete('meta_field');

				// Add title sort parameter
				searchParams.set('sort', sortValue);

				newUrl = url.pathname + '?' + searchParams.toString();
			} else if (sortValue.startsWith('meta_')) {
				// Handle metadata sorting
				const direction = sortValue.replace('meta_', '');

				// Remove existing sort parameters
				searchParams.delete('sort');
				searchParams.delete('meta_sort');
				searchParams.delete('meta_field');

				// Add metadata sort parameter
				searchParams.set('meta_sort', direction);

				// Get the metadata field from the block data attribute
				const blockElement = this.closest(
					'.wp-block-wordpress-search-searchresultssort'
				);
				if (blockElement && blockElement.dataset.metaField) {
					searchParams.set(
						'meta_field',
						blockElement.dataset.metaField
					);
				}

				newUrl = url.pathname + '?' + searchParams.toString();
			}

			// Navigate to new URL with preserved filters and new sort
			window.location.href = newUrl;
		});
	});
});
