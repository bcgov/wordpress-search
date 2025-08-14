/**
 * Search Modal Block Frontend JavaScript
 *
 * This creates the modal functionality by transforming the saved InnerBlocks
 * into a responsive modal interface.
 *
 * @package
 */

document.addEventListener( 'DOMContentLoaded', function () {
	'use strict';

	let modalCounter = 0;
	let activeModal = null;
	let previousFocus = null;

	/**
	 * Initialize all modal blocks
	 */
	function initModalBlocks() {
		const modalBlocks = document.querySelectorAll(
			'.wp-block-wordpress-search-search-modal'
		);

		modalBlocks.forEach( setupModalBlock );
		updateResponsiveStates();

		// Add global event listeners
		addEventListeners();
	}

	/**
	 * Setup a single modal block
	 * @param {HTMLElement} block - The modal block element
	 */
	function setupModalBlock( block ) {
		modalCounter++;
		const modalId = `search-modal-${ modalCounter }`;

		// Get attributes
		const buttonText = block.dataset.buttonText || 'Open Modal';
		const buttonStyle = block.dataset.buttonStyle || 'primary';

		// Get the InnerBlocks content
		const innerBlocks = block.querySelector(
			'.wp-block-wordpress-search-search-modal > div'
		);
		if ( ! innerBlocks ) {
			return;
		}

		// Clone the content for both inline and modal use
		const inlineContent = innerBlocks.cloneNode( true );
		const modalContent = innerBlocks.cloneNode( true );

		// Clear the block and rebuild structure
		block.innerHTML = '';

		// Create inline content (mobile/tablet)
		const inlineWrapper = document.createElement( 'div' );
		inlineWrapper.className = 'dswp-search-modal__inline-content';
		inlineWrapper.appendChild( inlineContent );

		// Create trigger button (desktop)
		const trigger = document.createElement( 'button' );
		trigger.type = 'button';
		trigger.className = `dswp-search-modal__trigger dswp-search-modal__trigger--${ buttonStyle }`;
		trigger.setAttribute( 'data-modal-target', modalId );
		trigger.setAttribute( 'aria-controls', modalId );
		trigger.setAttribute( 'aria-expanded', 'false' );
		trigger.textContent = buttonText;

		// Create modal overlay
		const overlay = document.createElement( 'div' );
		overlay.className = 'dswp-search-modal__overlay';
		overlay.id = modalId;
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-hidden', 'true' );

		// Create modal structure
		overlay.innerHTML = `
            <div class="dswp-search-modal__container">
                <div class="dswp-search-modal__content">
                    <div class="dswp-search-modal__header">
                        <button type="button" class="dswp-search-modal__close" data-modal-close="${ modalId }" aria-label="Close modal">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="dswp-search-modal__body"></div>
                </div>
            </div>
        `;

		// Insert modal content
		const modalBody = overlay.querySelector( '.dswp-search-modal__body' );
		modalBody.appendChild( modalContent );

		// Append all elements to block
		block.appendChild( inlineWrapper );
		block.appendChild( trigger );
		block.appendChild( overlay );
	}

	/**
	 * Add global event listeners
	 */
	function addEventListeners() {
		document.addEventListener( 'click', handleClick );
		document.addEventListener( 'keydown', handleKeydown );
		window.addEventListener( 'resize', handleResize );
	}

	/**
	 * Handle click events
	 * @param {Event} e - The click event
	 */
	function handleClick( e ) {
		// Open modal
		if ( e.target.matches( '.dswp-search-modal__trigger' ) ) {
			e.preventDefault();
			const modalId = e.target.getAttribute( 'data-modal-target' );
			openModal( modalId, e.target );
			return;
		}

		// Close modal
		if (
			e.target.matches( '[data-modal-close]' ) ||
			e.target.closest( '[data-modal-close]' )
		) {
			e.preventDefault();
			const closeBtn = e.target.matches( '[data-modal-close]' )
				? e.target
				: e.target.closest( '[data-modal-close]' );
			const modalId = closeBtn.getAttribute( 'data-modal-close' );
			closeModal( modalId );
		}
	}

	/**
	 * Handle keyboard events
	 * @param {KeyboardEvent} e - The keyboard event
	 */
	function handleKeydown( e ) {
		if ( e.key === 'Escape' && activeModal ) {
			closeModal( activeModal.id );
			return;
		}

		if ( activeModal && e.key === 'Tab' ) {
			trapFocus( e, activeModal );
		}
	}

	/**
	 * Handle window resize
	 */
	function handleResize() {
		updateResponsiveStates();

		// Close modal if switching to mobile
		if ( activeModal ) {
			const container = activeModal.closest(
				'.wp-block-wordpress-search-search-modal'
			);
			const breakpoint =
				parseInt( container.dataset.mobileBreakpoint ) || 768;
			const isMobile = window.innerWidth < breakpoint;

			if ( isMobile ) {
				closeModal( activeModal.id );
			}
		}
	}

	/**
	 * Open modal
	 * @param {string}      modalId - The ID of the modal to open
	 * @param {HTMLElement} trigger - The trigger element that opened the modal
	 */
	function openModal( modalId, trigger ) {
		const modal = document.getElementById( modalId );
		if ( ! modal ) {
			return;
		}

		previousFocus = trigger;
		activeModal = modal;

		modal.setAttribute( 'aria-hidden', 'false' );
		modal.classList.add( 'dswp-search-modal--open' );
		document.body.classList.add( 'dswp-search-modal-open' );

		trigger.setAttribute( 'aria-expanded', 'true' );

		// Focus first element
		const firstFocusable = getFocusableElements( modal )[ 0 ];
		if ( firstFocusable ) {
			firstFocusable.focus();
		}
	}

	/**
	 * Close modal
	 * @param {string} modalId - The ID of the modal to close
	 */
	function closeModal( modalId ) {
		const modal = document.getElementById( modalId );
		if ( ! modal ) {
			return;
		}

		modal.setAttribute( 'aria-hidden', 'true' );
		modal.classList.remove( 'dswp-search-modal--open' );
		document.body.classList.remove( 'dswp-search-modal-open' );

		const trigger = document.querySelector(
			`[data-modal-target="${ modalId }"]`
		);
		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'false' );
		}

		if ( previousFocus ) {
			previousFocus.focus();
			previousFocus = null;
		}

		activeModal = null;
	}

	/**
	 * Update responsive states
	 */
	function updateResponsiveStates() {
		const modalBlocks = document.querySelectorAll(
			'.wp-block-wordpress-search-search-modal'
		);

		modalBlocks.forEach( ( block ) => {
			const breakpoint =
				parseInt( block.dataset.mobileBreakpoint ) || 768;
			const isMobile = window.innerWidth < breakpoint;

			const inlineContent = block.querySelector(
				'.dswp-search-modal__inline-content'
			);
			const trigger = block.querySelector(
				'.dswp-search-modal__trigger'
			);

			if ( inlineContent && trigger ) {
				if ( isMobile ) {
					// Mobile: hide inline content, show trigger button
					inlineContent.style.display = 'none';
					trigger.style.display = 'block';
				} else {
					// Desktop: show content inline, hide trigger
					inlineContent.style.display = 'block';
					trigger.style.display = 'none';
				}
			}
		} );
	}

	/**
	 * Trap focus within modal
	 * @param {KeyboardEvent} e     - The keyboard event
	 * @param {HTMLElement}   modal - The modal element
	 */
	function trapFocus( e, modal ) {
		const focusableElements = getFocusableElements( modal );
		const firstElement = focusableElements[ 0 ];
		const lastElement = focusableElements[ focusableElements.length - 1 ];

		const activeElement = modal.ownerDocument.activeElement;

		if ( e.shiftKey ) {
			if ( activeElement === firstElement ) {
				e.preventDefault();
				lastElement.focus();
			}
		} else if ( activeElement === lastElement ) {
			e.preventDefault();
			firstElement.focus();
		}
	}

	/**
	 * Get focusable elements
	 * @param {HTMLElement} container - The container to search for focusable elements
	 * @return {HTMLElement[]} Array of focusable elements
	 */
	function getFocusableElements( container ) {
		const selector =
			'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select, [tabindex]:not([tabindex="-1"])';
		return Array.from( container.querySelectorAll( selector ) ).filter(
			( el ) => {
				return ! el.disabled && ! el.getAttribute( 'aria-hidden' );
			}
		);
	}

	// Initialize when DOM is ready
	initModalBlocks();
} );
