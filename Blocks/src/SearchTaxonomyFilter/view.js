import './view.scss';

window.applyTaxonomyFilters = function () {
	const currentUrl = new URL( window.location.href );
	const params = currentUrl.searchParams;

	// Preserve sort parameters before clearing filters
	const sortParam = params.get( 'sort' );
	const metaSortParam = params.get( 'meta_sort' );
	const metaFieldParam = params.get( 'meta_field' );

	// Clear old taxonomy filters and pagination
	Array.from( params.keys() )
		.filter( ( key ) => key.startsWith( 'taxonomy_' ) || key === 'paged' )
		.forEach( ( key ) => params.delete( key ) );

	// Restore sort parameters if they existed
	if ( sortParam ) {
		params.set( 'sort', sortParam );
	}
	if ( metaSortParam ) {
		params.set( 'meta_sort', metaSortParam );
	}
	if ( metaFieldParam ) {
		params.set( 'meta_field', metaFieldParam );
	}

	// Remove /page/2/ from path (robust version)
	currentUrl.pathname =
		currentUrl.pathname
			.replace( /\/page\/\d+\/?/g, '/' )
			.replace( /\/+/g, '/' )
			.replace( /\/$/, '' ) || '/'; // Remove trailing slash except for root

	// If original URL had trailing slash on non-page paths, restore it?
	// Usually not needed — WordPress treats both as same

	// Rebuild taxonomy params
	document
		.querySelectorAll( '.taxonomy-filter__checkbox:checked' )
		.forEach( ( checkbox ) => {
			const name = checkbox.name.replace( '[]', '' );
			const value = checkbox.value;

			if ( params.has( name ) ) {
				params.set( name, params.get( name ) + ',' + value );
			} else {
				params.set( name, value );
			}
		} );

	// Use replaceState for instant feel (optional, if you want no reload)
	// Or just: window.location.href = currentUrl.toString();
	window.location.href = currentUrl.toString();
};

// Handle "View All" toggle for taxonomy filters
document.addEventListener( 'DOMContentLoaded', function () {
	const viewAllLinks = document.querySelectorAll(
		'.taxonomy-filter__view-all-link'
	);

	viewAllLinks.forEach( ( link ) => {
		// Cache hidden elements to avoid re-querying on each click
		const filterSection = link.closest( '.taxonomy-filter-section' );
		const optionsContainer = filterSection.querySelector(
			'.taxonomy-filter__options'
		);
		const allOptions = Array.from(
			optionsContainer.querySelectorAll( '.taxonomy-filter__option' )
		);
		const hiddenOptions = allOptions.slice( 5 ); // Get all options after index 4 (0-indexed, so 5th item onwards)
		const viewAllText = link.querySelector(
			'.taxonomy-filter__view-all-text'
		);

		// Toggle function
		const toggleViewAll = ( e ) => {
			if ( e ) {
				e.preventDefault();
			}
			const isExpanded = link.getAttribute( 'aria-expanded' ) === 'true';

			if ( isExpanded ) {
				// Collapse: hide the additional items by adding the hidden class and removing inline style
				hiddenOptions.forEach( ( option ) => {
					option.classList.add( 'taxonomy-filter__option--hidden' );
					// Remove inline display style so CSS rule can take effect
					option.style.removeProperty( 'display' );
				} );
				link.setAttribute( 'aria-expanded', 'false' );
				viewAllText.textContent =
					window.wp?.i18n?.__( 'View all', 'wordpress-search' ) ||
					'View all';
			} else {
				// Expand: show the additional items by removing the hidden class and setting display
				hiddenOptions.forEach( ( option ) => {
					option.classList.remove(
						'taxonomy-filter__option--hidden'
					);
					// Set display to flex to override any other rules
					option.style.setProperty( 'display', 'flex', 'important' );
				} );
				link.setAttribute( 'aria-expanded', 'true' );
				viewAllText.textContent =
					window.wp?.i18n?.__( 'View less', 'wordpress-search' ) ||
					'View less';
			}
		};

		// Click handler
		link.addEventListener( 'click', toggleViewAll );

		// Keyboard support for accessibility (Enter and Space)
		link.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				toggleViewAll( e );
			}
		} );
	} );
} );
