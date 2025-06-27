/**
 * Frontend Styles
 * Import the styles that will be applied to the search Metadata filter block on the frontend
 */

import './view.scss';

/**
 * Search Metadata Filter Block Frontend JavaScript
 *
 * This script runs on the frontend when the search Metadata filter block is rendered.
 *
 * Current features:
 * - Imports and applies frontend styles
 * - Provides toggle functionality for collapsible filter sections
 *
 */

// Toggle function for metadata filter
window.toggleMetadataFilter = function (header) {
	const content = header.nextElementSibling;
	const toggle = header.querySelector('.metadata-filter__toggle');

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
	// Prevent browser's default scroll restoration
	if ('scrollRestoration' in history) {
		history.scrollRestoration = 'manual';
	}

	// Restore scroll position immediately on page load (before DOM ready)
	const scrollPos = sessionStorage.getItem('filterScrollPosition');
	if (scrollPos) {
		// Use requestAnimationFrame for smoother scrolling
		requestAnimationFrame(() => {
			window.scrollTo(0, parseInt(scrollPos));
			sessionStorage.removeItem('filterScrollPosition');
		});
	}
})();

document.addEventListener('DOMContentLoaded', function () {
	// Store scroll position before form submission
	const filterCheckboxes = document.querySelectorAll(
		'.metadata-filter__checkbox'
	);
	filterCheckboxes.forEach((checkbox) => {
		checkbox.addEventListener('change', function () {
			sessionStorage.setItem('filterScrollPosition', window.scrollY);
		});
	});
});
