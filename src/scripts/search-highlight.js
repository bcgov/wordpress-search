/**
 * Search Highlight JavaScript - ES6 version
 *
 * Handles the client-side highlighting of search terms across WordPress content.
 * Integrates with the PHP SearchHighlight class to provide seamless highlighting.
 *
 * @since 2.0.0
 * @author BC Gov
 */

/* global jQuery, searchHighlightData, NodeFilter, Cufon */

jQuery( document ).ready( function () {
	'use strict';

	// Check if we have search data
	if ( typeof searchHighlightData === 'undefined' ) {
		return;
	}

	const {
		terms = [],
		selectors = [],
		events = [ 'DOMContentLoaded' ],
	} = searchHighlightData;

	// Don't proceed if no terms
	if ( terms.length === 0 ) {
		return;
	}

	/**
	 * Create a highlighter instance (factory function)
	 * @param {HTMLElement} element - The element to search within
	 * @return {Object} Highlighter object with methods
	 */
	const createHighlighter = ( element ) => {
		return {
			element,

			/**
			 * Highlight search terms in the element
			 * @param {string} searchTerm - The term to search for
			 * @param {Object} options    - Options object
			 */
			highlight( searchTerm, options = {} ) {
				const {
					className = 'search-highlight',
					exclude = [
						'script',
						'style',
						'title',
						'head',
						'html',
						'mark',
						'iframe',
						'input',
						'textarea',
					],
					excludeClasses = [],
				} = options;

				// Skip if element should be excluded
				if ( exclude.includes( this.element.tagName.toLowerCase() ) ) {
					return;
				}

				// Create regex pattern for search term (case-insensitive)
				const pattern = new RegExp(
					`(${ searchTerm.replace(
						/[.*+?^${}()|[\]\\]/g,
						'\\$&'
					) })`,
					'gi'
				);

				// Find all text nodes and highlight them
				this.highlightTextNodes(
					this.element,
					pattern,
					className,
					exclude,
					excludeClasses
				);
			},

			/**
			 * Find and highlight all text nodes in an element
			 * @param {HTMLElement} elementToProcess - Element to process
			 * @param {RegExp}      pattern          - Regex pattern to match
			 * @param {string}      className        - CSS class for highlights
			 * @param {Array}       exclude          - Tags to exclude
			 * @param {Array}       excludeClasses   - CSS classes to exclude
			 */
			highlightTextNodes(
				elementToProcess,
				pattern,
				className,
				exclude,
				excludeClasses
			) {
				// Get all text nodes using a simple recursive approach
				const textNodes = this.getTextNodes(
					elementToProcess,
					exclude,
					excludeClasses
				);

				for ( const textNode of textNodes ) {
					this.highlightTextNode( textNode, pattern, className );
				}
			},

			/**
			 * Get all text nodes in an element
			 * @param {HTMLElement} elementToSearch - Element to search
			 * @param {Array}       exclude         - Tags to exclude
			 * @param {Array}       excludeClasses  - CSS classes to exclude
			 * @return {Array} Array of text nodes
			 */
			getTextNodes( elementToSearch, exclude, excludeClasses ) {
				const textNodes = [];
				const walker = document.createTreeWalker(
					elementToSearch,
					NodeFilter.SHOW_TEXT,
					{
						acceptNode: ( node ) => {
							const parent = node.parentNode;

							// Skip if parent tag is excluded
							if (
								exclude.includes( parent.tagName.toLowerCase() )
							) {
								return NodeFilter.FILTER_REJECT;
							}

							// Skip if parent has excluded class
							if ( parent.className ) {
								const classes = parent.className.split( ' ' );
								if (
									classes.some( ( cls ) =>
										excludeClasses.includes( cls )
									)
								) {
									return NodeFilter.FILTER_REJECT;
								}
							}

							return NodeFilter.FILTER_ACCEPT;
						},
					}
				);

				let node;
				while ( ( node = walker.nextNode() ) ) {
					textNodes.push( node );
				}

				return textNodes;
			},

			/**
			 * Highlight text in a single text node
			 * @param {Text}   textNode  - Text node to process
			 * @param {RegExp} pattern   - Regex pattern to match
			 * @param {string} className - CSS class for highlights
			 */
			highlightTextNode( textNode, pattern, className ) {
				const text = textNode.textContent;
				const matches = text.match( pattern );

				if ( matches && matches.length > 0 ) {
					// Create a document fragment to hold the highlighted text
					const fragment = document.createDocumentFragment();
					let lastIndex = 0;

					// Reset regex for consistent matching
					pattern.lastIndex = 0;

					// Find all matches and create highlighted spans
					let match;
					while ( ( match = pattern.exec( text ) ) !== null ) {
						// Add text before the match
						if ( match.index > lastIndex ) {
							fragment.appendChild(
								document.createTextNode(
									text.substring( lastIndex, match.index )
								)
							);
						}

						// Create highlighted span for the match
						const highlightElement =
							document.createElement( 'mark' );
						highlightElement.className = className;
						highlightElement.textContent = match[ 0 ];
						fragment.appendChild( highlightElement );

						lastIndex = match.index + match[ 0 ].length;
					}

					// Add remaining text
					if ( lastIndex < text.length ) {
						fragment.appendChild(
							document.createTextNode(
								text.substring( lastIndex )
							)
						);
					}

					// Replace the text node with the fragment
					textNode.parentNode.replaceChild( fragment, textNode );
				}
			},
		};
	};

	/**
	 * Main highlighting function - exact same logic as highlight-search-terms plugin
	 */
	const highlightSearchTerms = () => {
		for ( const selector of selectors ) {
			const elements = document.querySelectorAll( selector );
			if ( ! elements.length ) {
				continue;
			}

			for ( const currentElement of elements ) {
				for ( const [ termIndex, term ] of terms.entries() ) {
					if ( term && term.length > 0 ) {
						// Use our highlighter with custom options
						const highlighter = createHighlighter( currentElement );
						highlighter.highlight( term, {
							className: `search-highlight term-${ termIndex }`,
							exclude: [
								'script',
								'style',
								'title',
								'head',
								'html',
								'mark',
								'iframe',
								'input',
								'textarea',
							],
							excludeClasses: [ 'metadata-label' ],
							separateWordSearch: false,
						} );
					}
				}
			}

			if ( elements.length ) {
				break; // Found elements, no need to try other selectors
			}
		}

		// Add Cufon refresh if available (same as highlight-search-terms plugin)
		if ( typeof Cufon === 'function' ) {
			Cufon.refresh();
		}
	};

	/**
	 * Initialize highlighting
	 */
	const init = () => {
		// Highlight on page load
		highlightSearchTerms();

		// Listen for events
		events.forEach( function ( event ) {
			document.addEventListener( event, highlightSearchTerms );
		} );
	};

	// Initialize highlighting
	init();
} );
