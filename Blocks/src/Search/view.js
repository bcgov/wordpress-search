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
 * - Clear button that clears text and searches with empty string
 * - Responsive clear button visibility
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

			// Function to clear input and search with empty string
			const clearAndSearch = () => {
				input.value = '';
				toggleClearButton();
				form.submit();
			};

			// Initial state
			toggleClearButton();

			// Handle input changes
			input.addEventListener('input', toggleClearButton);

			// Handle clear button click - clear text and search with empty string
			clearButton.addEventListener('click', clearAndSearch);
		}
	});
});
