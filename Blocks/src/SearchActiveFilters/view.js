import './view.scss';

document.addEventListener('DOMContentLoaded', function () {
	// Handle individual filter removal
	document.addEventListener('click', function (event) {
		// Check if the clicked element is a filter chip remove button
		if (event.target.matches('.search-active-filters__chip-remove')) {
			event.preventDefault();

			// Get the remove URL from the data attribute
			const removeUrl = event.target.getAttribute('data-remove-url');
			if (removeUrl) {
				window.location.href = removeUrl;
			}
		}
	});

	// Handle clear all functionality
	document.addEventListener('click', function (event) {
		// Check if the clicked element is the clear all link
		if (event.target.matches('.search-active-filters__clear-all')) {
			// Clear all functionality - no scroll position preservation needed
		}
	});
});
