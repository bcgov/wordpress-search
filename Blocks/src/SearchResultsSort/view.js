import './view.scss';

document.addEventListener( 'DOMContentLoaded', function () {
	const sortBlocks = document.querySelectorAll(
		'.wp-block-wordpress-search-searchresultssort'
	);

	sortBlocks.forEach( function ( block ) {
		const header = block.querySelector( '.search-results-sort__header' );
		const content = block.querySelector( '.search-results-sort__content' );
		const optionInputs = block.querySelectorAll(
			'.search-results-sort__option-input'
		);

		if ( header && content ) {
			header.addEventListener( 'click', function () {
				const isExpanded =
					header.getAttribute( 'aria-expanded' ) === 'true';
				header.setAttribute( 'aria-expanded', String( ! isExpanded ) );
				content.classList.toggle( 'collapsed', isExpanded );
				const toggle = header.querySelector(
					'.search-results-sort__toggle'
				);
				if ( toggle ) {
					toggle.classList.toggle( 'collapsed', isExpanded );
				}
			} );
		}

		optionInputs.forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				if ( ! this.checked ) {
					return;
				}

				const sortValue = this.value;
				const currentUrl =
					block.dataset.currentUrl || window.location.href;
				const url = new URL( currentUrl, window.location.origin );
				const searchParams = new URLSearchParams( url.search );

				// Reset existing sort state before applying the new sort option.
				searchParams.delete( 'sort' );
				searchParams.delete( 'meta_sort' );
				searchParams.delete( 'meta_field' );

				if ( sortValue.startsWith( 'title_' ) ) {
					searchParams.set( 'sort', sortValue );
				} else if ( sortValue.startsWith( 'meta_' ) ) {
					const direction = sortValue.replace( 'meta_', '' );
					searchParams.set( 'meta_sort', direction );

					if ( block.dataset.metaField ) {
						searchParams.set(
							'meta_field',
							block.dataset.metaField
						);
					}
				}

				const queryString = searchParams.toString();
				window.location.href = queryString
					? `${ url.pathname }?${ queryString }`
					: url.pathname;
			} );
		} );
	} );
} );
