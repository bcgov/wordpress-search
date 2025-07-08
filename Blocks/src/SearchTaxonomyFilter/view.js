/* global sessionStorage, requestAnimationFrame, history */

import './view.scss';

// Toggle function for taxonomy filter
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
	// Use event delegation for better performance with many checkboxes
	document.addEventListener('change', function (event) {
		// Check if the changed element is a taxonomy filter checkbox
		if (event.target.matches('.taxonomy-filter__checkbox')) {
			// Store scroll position before form submission
			sessionStorage.setItem('filterScrollPosition', window.scrollY);

			// Find the parent form and submit it
			const form = event.target.closest('.taxonomy-filter-form');
			if (form) {
				form.submit();
			}
		}
	});
});
