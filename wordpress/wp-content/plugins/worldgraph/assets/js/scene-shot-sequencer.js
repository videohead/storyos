( function( $ ) {
	'use strict';

	const config = window.worldgraphSceneShots || {};
	const root = document.querySelector( '[data-worldgraph-shot-sequencer]' );
	if ( ! root ) {
		return;
	}

	const list = root.querySelector( '[data-shot-list]' );
	const status = root.querySelector( '[data-shot-status]' );
	if ( ! list ) {
		return;
	}

	let savedOrder = readOrder();
	let request = null;
	let queuedSave = false;

	function readOrder() {
		return Array.from( list.querySelectorAll( '[data-shot-id]' ) ).map( ( item ) => item.dataset.shotId );
	}

	function refreshPositions() {
		const items = Array.from( list.querySelectorAll( '[data-shot-id]' ) );
		items.forEach( ( item, index ) => {
			const position = item.querySelector( '[data-shot-position]' );
			if ( position ) {
				position.textContent = String( index + 1 );
			}
			const up = item.querySelector( '[data-shot-move="up"]' );
			const down = item.querySelector( '[data-shot-move="down"]' );
			if ( up ) {
				up.disabled = index === 0;
			}
			if ( down ) {
				down.disabled = index === items.length - 1;
			}
		} );
	}

	function restoreOrder() {
		savedOrder.forEach( ( shotId ) => {
			const item = list.querySelector( `[data-shot-id="${ CSS.escape( shotId ) }"]` );
			if ( item ) {
				list.appendChild( item );
			}
		} );
		refreshPositions();
	}

	function setStatus( message, isError ) {
		if ( ! status ) {
			return;
		}
		status.textContent = message;
		status.classList.toggle( 'is-error', Boolean( isError ) );
	}

	function saveOrder() {
		if ( request ) {
			queuedSave = true;
			return;
		}
		const order = readOrder();
		setStatus( config.i18n?.saving || 'Saving Shot order…', false );

		request = $.ajax( {
			url: config.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: config.action,
				nonce: config.nonce,
				scene_id: config.sceneId,
				ordered_ids: order,
			},
		} ).done( ( response ) => {
			if ( ! response?.success ) {
				restoreOrder();
				setStatus( response?.data?.message || config.i18n?.error || 'The Shot order could not be saved.', true );
				return;
			}
			savedOrder = order;
			setStatus( response.data?.message || config.i18n?.saved || 'Shot order saved.', false );
		} ).fail( ( xhr ) => {
			queuedSave = false;
			restoreOrder();
			setStatus( xhr.responseJSON?.data?.message || config.i18n?.error || 'The Shot order could not be saved.', true );
		} ).always( () => {
			request = null;
			if ( queuedSave ) {
				queuedSave = false;
				if ( readOrder().join( ',' ) !== savedOrder.join( ',' ) ) {
					saveOrder();
				}
			}
		} );
	}

	$( list ).sortable( {
		axis: 'y',
		handle: '.worldgraph-shot-sequencer__handle',
		items: '> [data-shot-id]',
		placeholder: 'worldgraph-shot-sequencer__placeholder',
		update: () => {
			refreshPositions();
			saveOrder();
		},
	} );

	list.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-shot-move]' );
		if ( ! button ) {
			return;
		}
		const item = button.closest( '[data-shot-id]' );
		if ( ! item ) {
			return;
		}
		if ( 'up' === button.dataset.shotMove && item.previousElementSibling ) {
			list.insertBefore( item, item.previousElementSibling );
		} else if ( 'down' === button.dataset.shotMove && item.nextElementSibling ) {
			list.insertBefore( item.nextElementSibling, item );
		} else {
			return;
		}
		refreshPositions();
		button.focus();
		saveOrder();
	} );

	refreshPositions();
}( jQuery ) );
