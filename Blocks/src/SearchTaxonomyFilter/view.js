/* global sessionStorage, requestAnimationFrame, history */

import './view.scss';

// Toggle function for taxonomy filter
window.toggleTaxonomyFilter = function ( header ) {
	const content = header.nextElementSibling;
	const toggle = header.querySelector( '.taxonomy-filter__toggle' );

	if ( content.classList.contains( 'collapsed' ) ) {
		content.classList.remove( 'collapsed' );
		toggle.classList.remove( 'collapsed' );
	} else {
		content.classList.add( 'collapsed' );
		toggle.classList.add( 'collapsed' );
	}
};

// Apply taxonomy filters function
window.applyTaxonomyFilters = function () {
	// Store scroll position before form submission
	sessionStorage.setItem( 'filterScrollPosition', window.scrollY );

	// Get current URL
	const currentUrl = new URL( window.location.href );
	
	// Clear existing taxonomy parameters
	const params = currentUrl.searchParams;
	const keysToRemove = [];
	for ( const key of params.keys() ) {
		if ( key.startsWith( 'taxonomy_' ) ) {
			keysToRemove.push( key );
		}
	}
	
	// Remove the taxonomy parameters
	keysToRemove.forEach( key => params.delete( key ) );

	// Collect all checked checkboxes and group them by taxonomy
	const taxonomyGroups = {};
	document.querySelectorAll( '.taxonomy-filter__checkbox:checked' ).forEach( ( checkbox ) => {
		const name = checkbox.getAttribute( 'name' );
		const value = checkbox.value;
		
		if ( name && value ) {
			// Handle array parameters (remove the [] suffix for the URL)
			if ( name.endsWith( '[]' ) ) {
				const baseName = name.slice( 0, -2 );
				if ( ! taxonomyGroups[ baseName ] ) {
					taxonomyGroups[ baseName ] = [];
				}
				taxonomyGroups[ baseName ].push( value );
			} else {
				if ( ! taxonomyGroups[ name ] ) {
					taxonomyGroups[ name ] = [];
				}
				taxonomyGroups[ name ].push( value );
			}
		}
	} );

	// Add taxonomy parameters as comma-separated values
	Object.keys( taxonomyGroups ).forEach( taxonomyKey => {
		if ( taxonomyGroups[ taxonomyKey ].length > 0 ) {
			params.set( taxonomyKey, taxonomyGroups[ taxonomyKey ].join( ',' ) );
		}
	} );

	// Navigate to the new URL
	window.location.href = currentUrl.toString();
};

// Remove individual filter function
window.removeTaxonomyFilter = function ( taxonomyName, termValue ) {
	// Store scroll position
	sessionStorage.setItem( 'filterScrollPosition', window.scrollY );

	// Get current URL
	const currentUrl = new URL( window.location.href );
	const params = currentUrl.searchParams;
	
	// Remove the specific taxonomy term
	const taxonomyKey = 'taxonomy_' + taxonomyName;
	if ( params.has( taxonomyKey ) ) {
		const currentValue = params.get( taxonomyKey );
		const values = currentValue.split( ',' ).filter( val => val !== termValue );
		
		// Remove the parameter completely if no values left
		if ( values.length === 0 ) {
			params.delete( taxonomyKey );
		} else {
			// Update with remaining values as comma-separated
			params.set( taxonomyKey, values.join( ',' ) );
		}
	}

	// Navigate to the new URL
	window.location.href = currentUrl.toString();
};

// Preserve scroll position on filter changes
( function () {
	if ( 'scrollRestoration' in history ) {
		history.scrollRestoration = 'manual';
	}

	const scrollPos = sessionStorage.getItem( 'filterScrollPosition' );
	if ( scrollPos ) {
		requestAnimationFrame( () => {
			window.scrollTo( 0, parseInt( scrollPos ) );
			sessionStorage.removeItem( 'filterScrollPosition' );
		} );
	}
} )();

// Function to sync checkbox states with URL parameters
function syncCheckboxStates() {
	const urlParams = new URLSearchParams( window.location.search );
	
	// Reset all checkboxes to unchecked first
	document.querySelectorAll( '.taxonomy-filter__checkbox' ).forEach( checkbox => {
		checkbox.checked = false;
	} );
	
	// Check boxes based on URL parameters
	urlParams.forEach( ( value, key ) => {
		if ( key.startsWith( 'taxonomy_' ) ) {
			const taxonomyName = key.replace( 'taxonomy_', '' );
			// Handle comma-separated values
			const termValues = value.split( ',' );
			
			termValues.forEach( termValue => {
				const checkboxes = document.querySelectorAll( `input[name="taxonomy_${taxonomyName}[]"][value="${termValue.trim()}"]` );
				checkboxes.forEach( checkbox => {
					checkbox.checked = true;
				} );
			} );
		}
	} );
}

document.addEventListener( 'DOMContentLoaded', function () {
	// Sync checkbox states with URL parameters
	syncCheckboxStates();
	
	// Initialize any collapsed taxonomy filters
	const taxonomyFilters = document.querySelectorAll( '.taxonomy-filter__content' );
	taxonomyFilters.forEach( ( filter ) => {
		// Check if there are any checked checkboxes in this filter
		const checkedBoxes = filter.querySelectorAll( '.taxonomy-filter__checkbox:checked' );
		if ( checkedBoxes.length > 0 ) {
			// If there are checked boxes, ensure the filter is expanded
			const header = filter.previousElementSibling;
			const toggle = header.querySelector( '.taxonomy-filter__toggle' );
			filter.classList.remove( 'collapsed' );
			toggle.classList.remove( 'collapsed' );
		}
	} );
} );
