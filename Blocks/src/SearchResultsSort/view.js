/* global sessionStorage, requestAnimationFrame, history */

import './view.scss';

document.addEventListener('DOMContentLoaded', function () {
	// Handle meta field dropdown changes
	const fieldSelects = document.querySelectorAll(
		'.search-results-sort__field-select'
	);
	const orderSelects = document.querySelectorAll(
		'.search-results-sort__order-select'
	);

	fieldSelects.forEach(function (select) {
		select.addEventListener('change', function () {
			const currentUrl = this.dataset.currentUrl;
			const fieldValue = this.value;

			// Store scroll position before navigation
			sessionStorage.setItem('sortScrollPosition', window.scrollY);

			// Build new URL with simplified parameter structure
			let newUrl = currentUrl;
			if (fieldValue) {
				// Extract just the field name from "posttype:fieldname" format
				let fieldName = fieldValue;
				if (fieldValue.includes(':')) {
					const parts = fieldValue.split(':');
					fieldName = parts[parts.length - 1];
				}

				const separator = currentUrl.includes('?') ? '&' : '?';
				// Use simplified format: field_name=direction
				newUrl = currentUrl + separator + fieldName + '=desc'; // Default to descending
			}

			// Navigate to new URL
			window.location.href = newUrl;
		});
	});

	orderSelects.forEach(function (select) {
		select.addEventListener('change', function () {
			const currentUrl = this.dataset.currentUrl;
			const currentField = this.dataset.currentField;
			const orderValue = this.value;

			// Store scroll position before navigation
			sessionStorage.setItem('sortScrollPosition', window.scrollY);

			// Build new URL with simplified parameter structure
			let newUrl = currentUrl;
			if (currentField && orderValue && orderValue !== 'off') {
				// Extract just the field name from "posttype:fieldname" format
				let fieldName = currentField;
				if (currentField.includes(':')) {
					const parts = currentField.split(':');
					fieldName = parts[parts.length - 1];
				}

				const separator = currentUrl.includes('?') ? '&' : '?';
				// Use simplified format: field_name=direction
				newUrl =
					currentUrl +
					separator +
					fieldName +
					'=' +
					encodeURIComponent(orderValue);
			}

			// Navigate to new URL
			window.location.href = newUrl;
		});
	});
});

// Preserve scroll position after sort changes
(function () {
	if ('scrollRestoration' in history) {
		history.scrollRestoration = 'manual';
	}

	const scrollPos = sessionStorage.getItem('sortScrollPosition');
	if (scrollPos) {
		requestAnimationFrame(() => {
			window.scrollTo(0, parseInt(scrollPos));
			sessionStorage.removeItem('sortScrollPosition');
		});
	}
})();
