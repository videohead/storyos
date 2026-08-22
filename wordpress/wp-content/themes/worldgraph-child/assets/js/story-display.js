( function () {
	'use strict';

	function pauseMedia( container, except ) {
		container.querySelectorAll( 'audio, video' ).forEach( function ( player ) {
			if ( player !== except && ! player.paused ) {
				player.pause();
			}
		} );
	}

	function setFlipState( card, flipped, moveFocus ) {
		var front = card.querySelector( '[data-wg-flip-face="front"]' );
		var back = card.querySelector( '[data-wg-flip-face="back"]' );
		var buttons = card.querySelectorAll( '[data-wg-flip-control]' );
		var activeFace = flipped ? back : front;

		card.classList.toggle( 'is-flipped', flipped );
		front.setAttribute( 'aria-hidden', flipped ? 'true' : 'false' );
		back.setAttribute( 'aria-hidden', flipped ? 'false' : 'true' );
		front.toggleAttribute( 'inert', flipped );
		back.toggleAttribute( 'inert', ! flipped );

		buttons.forEach( function ( button ) {
			var label = flipped ? button.dataset.backLabel : button.dataset.frontLabel;
			var controlledFace = document.getElementById( button.getAttribute( 'aria-controls' ) );
			button.setAttribute( 'aria-expanded', controlledFace === activeFace ? 'true' : 'false' );
			button.setAttribute( 'aria-label', label );
			var text = button.querySelector( 'span:first-child' );
			if ( text ) {
				text.textContent = label;
			}
		} );

		if ( moveFocus && activeFace ) {
			window.requestAnimationFrame( function () {
				var activeButton = activeFace.querySelector( '[data-wg-flip-control]' );
				if ( activeButton ) {
					activeButton.focus();
				}
			} );
		}
	}

	function initializeFlipCards() {
		document.querySelectorAll( '[data-wg-flip-card]' ).forEach( function ( card ) {
			setFlipState( card, false, false );

			card.addEventListener( 'click', function ( event ) {
				var control = event.target.closest( '[data-wg-flip-control]' );
				if ( ! control || ! card.contains( control ) ) {
					return;
				}

				setFlipState( card, ! card.classList.contains( 'is-flipped' ), true );
			} );

			card.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && card.classList.contains( 'is-flipped' ) ) {
					event.preventDefault();
					setFlipState( card, false, true );
				}
			} );
		} );
	}

	function initializeGalleries() {
		document.querySelectorAll( '[data-wg-gallery]' ).forEach( function ( gallery ) {
			var tabs = Array.prototype.slice.call( gallery.querySelectorAll( '[data-wg-gallery-trigger]' ) );
			var panels = Array.prototype.slice.call( gallery.querySelectorAll( '[data-wg-gallery-panel]' ) );
			var status = gallery.querySelector( '[data-wg-gallery-status]' );

			if ( tabs.length < 2 ) {
				return;
			}

			function activate( index, moveFocus, announce ) {
				if ( index < 0 ) {
					index = tabs.length - 1;
				}
				if ( index >= tabs.length ) {
					index = 0;
				}

				pauseMedia( gallery );
				tabs.forEach( function ( tab, tabIndex ) {
					var selected = tabIndex === index;
					tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
					tab.setAttribute( 'tabindex', selected ? '0' : '-1' );
				} );
				panels.forEach( function ( panel, panelIndex ) {
					panel.hidden = panelIndex !== index;
				} );

				if ( moveFocus ) {
					tabs[ index ].focus();
				}
				if ( announce && status ) {
					var text = tabs[ index ].querySelector( '.screen-reader-text' );
					status.textContent = text ? text.textContent : '';
				}
			}

			tabs.forEach( function ( tab, index ) {
				tab.addEventListener( 'click', function () {
					activate( index, false, true );
				} );
				tab.addEventListener( 'keydown', function ( event ) {
					var nextIndex = index;
					if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
						nextIndex = index + 1;
					} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
						nextIndex = index - 1;
					} else if ( 'Home' === event.key ) {
						nextIndex = 0;
					} else if ( 'End' === event.key ) {
						nextIndex = tabs.length - 1;
					} else {
						return;
					}

					event.preventDefault();
					activate( nextIndex, true, true );
				} );
			} );

			activate( 0, false, false );
		} );
	}

	function initializePlayerCoordination() {
		document.addEventListener( 'play', function ( event ) {
			if ( event.target.matches( '.wg-story-player' ) ) {
				pauseMedia( document, event.target );
			}
		}, true );
	}

	function initialize() {
		initializeFlipCards();
		initializeGalleries();
		initializePlayerCoordination();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
}() );
