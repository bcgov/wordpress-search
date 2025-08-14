/* global sessionStorage, requestAnimationFrame, history */

import './view.scss';

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

document.addEventListener( 'DOMContentLoaded', function () {
	// Handle individual filter removal
	document.addEventListener( 'click', function ( event ) {
		// Check if the clicked element is a filter chip remove button
		if ( event.target.matches( '.search-active-filters__chip-remove' ) ) {
			event.preventDefault();

			// Store scroll position before navigation
			sessionStorage.setItem( 'filterScrollPosition', window.scrollY );

			// Get the remove URL from the data attribute
			const removeUrl = event.target.getAttribute( 'data-remove-url' );
			if ( removeUrl ) {
				window.location.href = removeUrl;
			}
		}
	} );

	// Handle clear all functionality
	document.addEventListener( 'click', function ( event ) {
		// Check if the clicked element is the clear all link
		if ( event.target.matches( '.search-active-filters__clear-all' ) ) {
			// Store scroll position before navigation
			sessionStorage.setItem( 'filterScrollPosition', window.scrollY );
		}
	} );
} );
