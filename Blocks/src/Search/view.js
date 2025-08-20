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
 * - Clear button that clears text and submits form with empty search term
 * - Responsive clear button visibility
 * - Filter persistence handled by PHP backend
 */

// Wait for the DOM to be fully loaded
document.addEventListener( 'DOMContentLoaded', function () {
	// Find all search forms
	const searchForms = document.querySelectorAll( '.dswp-search-bar__form' );

	searchForms.forEach( ( form ) => {
		const input = form.querySelector( '.dswp-search-bar__input' );
		const clearButton = form.querySelector(
			'.dswp-search-bar__clear-button'
		);

		if ( input && clearButton ) {
			// Show/hide clear button based on input content
			const toggleClearButton = () => {
				clearButton.style.display = input.value ? 'flex' : 'none';
			};

			// Function to clear input and submit form with empty search term
			// Filters are automatically preserved by the PHP backend hidden inputs
			const clearAndSearch = () => {
				// Clear the search input
				input.value = '';
				
				// Hide the clear button
				toggleClearButton();
				
				// Submit the form - PHP backend handles filter persistence
				form.submit();
			};

			// Initial state
			toggleClearButton();

			// Handle input changes
			input.addEventListener( 'input', toggleClearButton );

			// Handle clear button click - clear text and submit form
			clearButton.addEventListener( 'click', clearAndSearch );
		}
	} );
} );
