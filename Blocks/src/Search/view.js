/**
 * Frontend Styles
 * Import the styles that will be applied to the search-bar block on the frontend
 */
import './view.scss';

/**
 * search-bar Block Frontend JavaScript
 *
 * This script runs on the frontend when the search-bar block is rendered.
 *
 * Current features:
 * - Imports and applies frontend styles
 *
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function () {
	// Find all search forms
	const searchForms = document.querySelectorAll('.dswp-search-bar__form');

	searchForms.forEach((form) => {
		const input = form.querySelector('.dswp-search-bar__input');
		const clearButton = form.querySelector(
			'.dswp-search-bar__clear-button'
		);

		if (input && clearButton) {
			// Show/hide clear button based on input content
			const toggleClearButton = () => {
				clearButton.style.display = input.value ? 'flex' : 'none';
			};

			// Initial state
			toggleClearButton();

			// Handle input changes
			input.addEventListener('input', toggleClearButton);

			// Handle clear button click
			clearButton.addEventListener('click', () => {
				input.value = '';
				input.focus();
				toggleClearButton();
			});
		}
	});
});
