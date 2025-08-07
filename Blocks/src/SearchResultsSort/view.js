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

				// Clean URL by removing existing sort parameters first
				const url = new URL(newUrl, window.location.origin);
				const searchParams = new URLSearchParams(url.search);
				
				// Remove any existing sort parameters
				// Look for parameters with sort values (asc/desc) and alphanumeric names
				const keysToRemove = [];
				for (const [key, value] of searchParams) {
					if (['asc', 'desc'].includes(value) && 
						key.match(/^[a-zA-Z0-9_]+$/)) {
						keysToRemove.push(key);
					}
				}
				keysToRemove.forEach(key => searchParams.delete(key));
				
				// Add the new sort parameter
				searchParams.set(fieldName, 'desc');
				
				// Build final URL
				newUrl = url.pathname + '?' + searchParams.toString();
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

				// Clean URL by removing existing sort parameters first
				const url = new URL(newUrl, window.location.origin);
				const searchParams = new URLSearchParams(url.search);
				
				// Remove any existing sort parameters
				// Look for parameters with sort values (asc/desc) and alphanumeric names
				const keysToRemove = [];
				for (const [key, value] of searchParams) {
					if (['asc', 'desc'].includes(value) && 
						key.match(/^[a-zA-Z0-9_]+$/)) {
						keysToRemove.push(key);
					}
				}
				keysToRemove.forEach(key => searchParams.delete(key));
				
				// Add the new sort parameter
				searchParams.set(fieldName, orderValue);
				
				// Build final URL
				newUrl = url.pathname + '?' + searchParams.toString();
			} else if (orderValue === 'off') {
				// Remove all sort parameters when turning off sorting
				const url = new URL(newUrl, window.location.origin);
				const searchParams = new URLSearchParams(url.search);
				
				const keysToRemove = [];
				for (const [key, value] of searchParams) {
					if (['asc', 'desc'].includes(value) && 
						(key.match(/^(document_|new_|sort_|relevance_|file_|date|time|data_)/) ||
						 ['new_date', 'sort_relevance', 'relevance_date', 'data_date', 'test'].includes(key))) {
						keysToRemove.push(key);
					}
				}
				keysToRemove.forEach(key => searchParams.delete(key));
				
				newUrl = url.pathname + '?' + searchParams.toString();
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
