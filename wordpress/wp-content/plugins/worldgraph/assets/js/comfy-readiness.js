/**
 * Interactive ComfyUI readiness checklist controller.
 */
( function () {
	'use strict';

	const config = window.worldgraphComfyReadiness;
	const root = document.querySelector( '.worldgraph-comfy-readiness' );

	if (
		! config ||
		! config.ajaxUrl ||
		! config.actions ||
		! config.nonce ||
		! config.i18n ||
		! root
	) {
		return;
	}

	const steps = root.querySelector( '#worldgraph-comfy-readiness-steps' );
	const message = root.querySelector( '#worldgraph-comfy-readiness-message' );
	const recheckButton = root.querySelector( '#worldgraph-comfy-recheck' );
	const provisionButton = root.querySelector( '#worldgraph-comfy-provision' );

	if ( ! steps || ! message || ! recheckButton || ! provisionButton ) {
		return;
	}

	const buttons = [ recheckButton, provisionButton ];

	/**
	 * Toggle both checklist controls while a request is active.
	 *
	 * @param {boolean} disabled Whether the buttons should be disabled.
	 */
	function setBusy( disabled ) {
		buttons.forEach( function ( button ) {
			button.disabled = disabled;
		} );
	}

	/**
	 * Run one readiness action and refresh the component.
	 *
	 * @param {string} action WordPress AJAX action.
	 */
	function call( action ) {
		setBusy( true );
		message.textContent = config.i18n.checking;

		fetch( config.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: new URLSearchParams( {
				action: action,
				nonce: config.nonce,
			} ),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				const data = response.data && typeof response.data === 'object' ? response.data : {};

				if ( data.html ) {
					steps.innerHTML = data.html;
				}
				message.textContent = data.message || '';
			} )
			.catch( function () {
				message.textContent = config.i18n.failed;
			} )
			.finally( function () {
				setBusy( false );
			} );
	}

	if ( config.actions.check ) {
		recheckButton.addEventListener( 'click', function () {
			call( config.actions.check );
		} );
	}
	if ( config.actions.provision ) {
		provisionButton.addEventListener( 'click', function () {
			call( config.actions.provision );
		} );
	}
}() );
