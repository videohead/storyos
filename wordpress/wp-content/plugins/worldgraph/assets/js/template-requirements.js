/**
 * ComfyUI requirements controls for the Template edit screen.
 *
 * @package WorldGraph
 */

( function () {
	'use strict';

	const config = window.worldgraphTemplateRequirements;
	const result = document.getElementById( 'worldgraph-requirements-result' );

	if ( ! config || ! result ) {
		return;
	}

	const i18n = config.i18n || {};
	const providerTemplateField = ( config.providerTemplateFieldIds || [] )
		.map( function ( fieldId ) {
			return document.getElementById( fieldId );
		} )
		.find( function ( field ) {
			return Boolean( field );
		} );

	/**
	 * Return the currently selected provider Template ID.
	 *
	 * @return {string} Provider Template ID.
	 */
	function providerTemplateId() {
		return providerTemplateField ? providerTemplateField.value.trim() : '';
	}

	/**
	 * Send a request to a Template requirements AJAX action.
	 *
	 * @param {string} action AJAX action.
	 * @param {Object} extra  Additional request fields.
	 * @return {Promise<Object>} Parsed WordPress AJAX response.
	 */
	function request( action, extra ) {
		const payload = {
			action,
			nonce: config.nonce,
			post_id: config.postId,
		};

		Object.assign( payload, extra || {} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: new URLSearchParams( payload ),
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Display a result message with the appropriate success or error color.
	 *
	 * @param {Object} response WordPress AJAX response.
	 * @param {string} fallback Fallback message.
	 */
	function showResponse( response, fallback ) {
		result.textContent = response.data && response.data.message ?
			response.data.message :
			fallback;
		result.style.color = response.success ? '#008a20' : '#b32d2e';
	}

	/**
	 * Run a standard requirements action and manage its button state.
	 *
	 * @param {string}      action AJAX action.
	 * @param {HTMLElement} button Triggering button.
	 */
	function call( action, button ) {
		button.disabled = true;
		result.textContent = i18n.checking;

		request( action, {
			provider_template_id: providerTemplateId(),
		} )
			.then( function ( response ) {
				showResponse( response, i18n.requirementFailed );
			} )
			.catch( function () {
				result.textContent = i18n.requirementFailed;
				result.style.color = '#b32d2e';
			} )
			.finally( function () {
				button.disabled = false;
			} );
	}

	/**
	 * Bind a button to a standard requirements action when it is present.
	 *
	 * @param {string} elementId Button element ID.
	 * @param {string} action    AJAX action.
	 */
	function bindAction( elementId, action ) {
		const button = document.getElementById( elementId );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			call( action, button );
		} );
	}

	bindAction(
		'worldgraph-check-requirements',
		'worldgraph_check_template_requirements'
	);
	bindAction(
		'worldgraph-install-models',
		'worldgraph_install_template_models'
	);

	const discoverButton = document.getElementById(
		'worldgraph-discover-comfy-templates'
	);
	const searchField = document.getElementById(
		'worldgraph-comfy-template-search'
	);
	const discoveryResults = document.getElementById(
		'worldgraph-comfy-template-results'
	);

	if ( discoverButton && searchField && discoveryResults ) {
		discoverButton.addEventListener( 'click', function () {
			discoverButton.disabled = true;
			discoveryResults.textContent = i18n.searching;

			request( 'worldgraph_discover_comfy_templates', {
				search: searchField.value,
			} )
				.then( function ( response ) {
					discoveryResults.replaceChildren();

					if ( ! response.success ) {
						discoveryResults.textContent = response.data &&
							response.data.message ?
							response.data.message :
							i18n.discoveryFailed;
						return;
					}

					( response.data.templates || [] ).forEach( function ( template ) {
						const row = document.createElement( 'p' );
						const select = document.createElement( 'button' );

						select.type = 'button';
						select.className = 'button-link';
						select.textContent = template.name || template.id;
						select.addEventListener( 'click', function () {
							if ( providerTemplateField ) {
								providerTemplateField.value = template.id;
								providerTemplateField.dispatchEvent(
									new Event( 'change', { bubbles: true } )
								);
							}

							discoveryResults
								.querySelectorAll( '.button-link' )
								.forEach( function ( item ) {
									item.classList.remove( 'current' );
								} );
							select.classList.add( 'current' );
						} );
						row.append(
							select,
							document.createTextNode( ' (' + template.id + ')' )
						);
						discoveryResults.append( row );
					} );
				} )
				.catch( function () {
					discoveryResults.textContent = i18n.discoveryFailed;
				} )
				.finally( function () {
					discoverButton.disabled = false;
				} );
		} );
	}

	/**
	 * Bind an action that requires a selected provider Template.
	 *
	 * @param {string} elementId Button element ID.
	 * @param {string} action    AJAX action.
	 */
	function bindProviderTemplateAction( elementId, action ) {
		const button = document.getElementById( elementId );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( ! providerTemplateId() ) {
				result.textContent = i18n.selectTemplate;
				return;
			}

			call( action, button );
		} );
	}

	bindProviderTemplateAction(
		'worldgraph-download-comfy-requirements',
		'worldgraph_download_comfy_template_requirements'
	);
	bindProviderTemplateAction(
		'worldgraph-import-provider-template',
		'worldgraph_import_provider_template_definition'
	);

	const smokeTestButton = document.getElementById(
		'worldgraph-run-template-smoke-test'
	);

	if ( smokeTestButton ) {
		smokeTestButton.addEventListener( 'click', function () {
			smokeTestButton.disabled = true;
			result.textContent = i18n.runningSmokeTest;

			request( 'worldgraph_run_template_smoke_test' )
				.then( function ( response ) {
					showResponse( response, i18n.smokeTestFailed );
				} )
				.catch( function () {
					result.textContent = i18n.smokeTestFailed;
					result.style.color = '#b32d2e';
				} )
				.finally( function () {
					smokeTestButton.disabled = false;
				} );
		} );
	}
}() );
