/* global sessionStorage, requestAnimationFrame, history */

import './view.scss';

// Toggle function for taxonomy filter.
window.toggleTaxonomyFilter = function (header) {
	const content = header.nextElementSibling;
	const toggle = header.querySelector('.taxonomy-filter__toggle');

	if (content.classList.contains('collapsed')) {
		content.classList.remove('collapsed');
		toggle.classList.remove('collapsed');
	} else {
		content.classList.add('collapsed');
		toggle.classList.add('collapsed');
	}
};

// Apply taxonomy filters function.
window.applyTaxonomyFilters = function () {
	// Store scroll position before form submission.
	sessionStorage.setItem('filterScrollPosition', window.scrollY);

	// Get current URL
	const currentUrl = new URL(window.location.href);
	const params = currentUrl.searchParams;

	// Remove ALL existing taxonomy parameters first.
	Array.from(params.keys())
		.filter((key) => key.startsWith('taxonomy_'))
		.forEach((key) => params.delete(key));

	// Now collect ALL currently checked checkboxes and add them as new parameters
	document
		.querySelectorAll('.taxonomy-filter__checkbox:checked')
		.forEach((checkbox) => {
			const name = checkbox.getAttribute('name');
			const value = checkbox.value;

			if (name && value) {
				// Remove the [] suffix for the URL parameter name
				const paramName = name.endsWith('[]')
					? name.slice(0, -2)
					: name;

				// If this taxonomy already has values, append to it (comma-separated)
				if (params.has(paramName)) {
					const existingValue = params.get(paramName);
					params.set(paramName, existingValue + ',' + value);
				} else {
					// First value for this taxonomy
					params.set(paramName, value);
				}
			}
		});

	// Ensure all other parameters (including search query) are preserved
	// This is already handled by the URL manipulation above, but let's be explicit
	// The search query (s) and other filter parameters will remain intact

	// Navigate to the new URL with all the new filters and preserved parameters
	window.location.href = currentUrl.toString();
};

// Preserve scroll position on filter changes
(function () {
	if ('scrollRestoration' in history) {
		history.scrollRestoration = 'manual';
	}

	const scrollPos = sessionStorage.getItem('filterScrollPosition');
	if (scrollPos) {
		requestAnimationFrame(() => {
			window.scrollTo(0, parseInt(scrollPos));
			sessionStorage.removeItem('filterScrollPosition');
		});
	}
})();

document.addEventListener('DOMContentLoaded', function () {
	// Initialize any collapsed taxonomy filters
	const taxonomyFilters = document.querySelectorAll(
		'.taxonomy-filter__content'
	);
	taxonomyFilters.forEach((filter) => {
		// Check if there are any checked checkboxes in this filter
		const checkedBoxes = filter.querySelectorAll(
			'.taxonomy-filter__checkbox:checked'
		);
		if (checkedBoxes.length > 0) {
			// If there are checked boxes, ensure the filter is expanded
			const header = filter.previousElementSibling;
			const toggle = header.querySelector('.taxonomy-filter__toggle');
			filter.classList.remove('collapsed');
			toggle.classList.remove('collapsed');
		}
	});
});
