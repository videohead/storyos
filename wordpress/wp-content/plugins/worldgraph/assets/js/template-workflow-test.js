/**
 * Template Workflow Test — queue a prompt-driven test run for one Template and
 * report the Asset number it produces, with an agentic prompt assistant.
 */
( function () {
	'use strict';

	var config = window.worldgraphTemplateWorkflowTest;
	var root = document.getElementById( 'worldgraph-template-test' );
	if ( ! config || ! root ) {
		return;
	}

	var i18n = config.i18n || {};
	var capability = config.capability || {};
	var inputs = capability.inputs || [];
	var TERMINAL = [ 'completed', 'failed', 'cancelled' ];
	var mediaValues = {};
	var mediaFrames = {};
	var history = [];
	var pollTimer = null;

	function text( tag, className, value ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== value ) {
			node.textContent = value;
		}
		return node;
	}

	function request( url, options ) {
		return fetch( url, Object.assign( { credentials: 'same-origin' }, options, {
			headers: Object.assign( { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce }, ( options || {} ).headers || {} )
		} ) ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				if ( ! response.ok ) {
					throw new Error( body && body.message ? body.message : i18n.requestFailed );
				}
				return body;
			} );
		} );
	}

	function promptField() {
		return document.getElementById( 'worldgraph-template-test-input-prompt' );
	}

	function renderCapability() {
		var host = document.getElementById( 'worldgraph-template-test-capability' );
		host.replaceChildren();

		var summary = text( 'p', 'worldgraph-template-test__summary' );
		summary.append( text( 'strong', '', capability.label || '' ) );
		if ( capability.description ) {
			summary.append( document.createTextNode( ' — ' + capability.description ) );
		}
		host.append( summary );

		( capability.blockers || [] ).forEach( function ( blocker ) {
			var notice = text( 'div', 'notice notice-warning inline' );
			notice.append( text( 'p', '', blocker ) );
			host.append( notice );
		} );
	}

	function renderMediaField( wrapper, slot ) {
		var preview = text( 'span', 'worldgraph-template-test__media-preview', i18n.noMedia );
		var select = text( 'button', 'button', i18n.selectMedia );
		var clear = text( 'button', 'button-link', i18n.clearMedia );
		select.type = 'button';
		clear.type = 'button';

		select.addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! mediaFrames[ slot.slot ] ) {
				mediaFrames[ slot.slot ] = window.wp.media( {
					title: i18n.chooseMedia,
					button: { text: i18n.useMedia },
					multiple: false
				} );
				mediaFrames[ slot.slot ].on( 'select', function () {
					var attachment = mediaFrames[ slot.slot ].state().get( 'selection' ).first().toJSON();
					mediaValues[ slot.slot ] = String( attachment.id );
					preview.textContent = ( attachment.filename || attachment.title || '' ) + ' (#' + attachment.id + ')';
				} );
			}
			mediaFrames[ slot.slot ].open();
		} );

		clear.addEventListener( 'click', function () {
			delete mediaValues[ slot.slot ];
			preview.textContent = i18n.noMedia;
		} );

		wrapper.append( preview, document.createTextNode( ' ' ), select, document.createTextNode( ' ' ), clear );
	}

	function renderFields() {
		var host = document.getElementById( 'worldgraph-template-test-fields' );
		host.replaceChildren();

		inputs.forEach( function ( slot ) {
			var wrapper = text( 'p', 'worldgraph-template-test__field' );
			var label = text( 'label', '', slot.label + ( slot.required ? ' *' : '' ) );
			label.htmlFor = 'worldgraph-template-test-input-' + slot.slot;
			wrapper.append( label, document.createElement( 'br' ) );

			if ( 'media' === slot.type ) {
				renderMediaField( wrapper, slot );
			} else {
				var field = document.createElement( 'textarea' );
				field.id = 'worldgraph-template-test-input-' + slot.slot;
				field.className = 'large-text';
				field.rows = 'prompt' === slot.slot ? 4 : 2;
				wrapper.append( field );
			}

			host.append( wrapper );
		} );
	}

	function collectInputs() {
		var payload = {};
		var missingPrompt = false;
		var missingMedia = false;

		inputs.forEach( function ( slot ) {
			if ( 'media' === slot.type ) {
				if ( mediaValues[ slot.slot ] ) {
					payload[ slot.slot ] = mediaValues[ slot.slot ];
				} else if ( slot.required ) {
					missingMedia = true;
				}
				return;
			}

			var field = document.getElementById( 'worldgraph-template-test-input-' + slot.slot );
			var value = field ? field.value.trim() : '';
			if ( value ) {
				payload[ slot.slot ] = value;
			} else if ( slot.required ) {
				missingPrompt = true;
			}
		} );

		return { inputs: payload, missingPrompt: missingPrompt, missingMedia: missingMedia };
	}

	function setStatus( message, tone ) {
		var host = document.getElementById( 'worldgraph-template-test-status' );
		host.replaceChildren( text( 'p', 'worldgraph-template-test__status-' + ( tone || 'info' ), message ) );
	}

	function statusMessage( status ) {
		if ( 'queued' === status ) {
			return i18n.queued;
		}
		if ( 'importing' === status || 'import_cleanup' === status || 'import_retry' === status ) {
			return i18n.importing;
		}
		if ( 'completed' === status ) {
			return i18n.completed;
		}
		if ( 'failed' === status ) {
			return i18n.failed;
		}
		if ( 'cancelled' === status ) {
			return i18n.cancelled;
		}
		return i18n.running;
	}

	function renderResult( generation ) {
		var host = document.getElementById( 'worldgraph-template-test-result' );
		host.replaceChildren();

		if ( generation.error ) {
			host.append( text( 'p', 'worldgraph-template-test__status-error', generation.error ) );
		}

		if ( generation.asset_id ) {
			var assetLine = text( 'p', 'worldgraph-template-test__asset' );
			assetLine.append( text( 'strong', '', i18n.assetNumber + ' #' + generation.asset_id + ' ' ) );
			var assetLink = text( 'a', '', i18n.openAsset );
			assetLink.href = config.assetEditUrlBase + generation.asset_id;
			assetLine.append( assetLink );
			host.append( assetLine );
		}

		if ( generation.attachment_id && generation.url ) {
			var mediaLine = text( 'p', '' );
			var mediaLink = text( 'a', '', i18n.attachment + ' #' + generation.attachment_id );
			mediaLink.href = generation.url;
			mediaLink.target = '_blank';
			mediaLink.rel = 'noreferrer noopener';
			mediaLine.append( mediaLink );
			host.append( mediaLine );

			if ( 'video' === generation.type ) {
				var video = document.createElement( 'video' );
				video.controls = true;
				video.src = generation.url;
				video.className = 'worldgraph-template-test__preview';
				host.append( video );
			} else if ( 'audio' === generation.type ) {
				var audio = document.createElement( 'audio' );
				audio.controls = true;
				audio.src = generation.url;
				host.append( audio );
			} else {
				var image = document.createElement( 'img' );
				image.src = generation.thumbnail_url || generation.url;
				image.alt = '';
				image.className = 'worldgraph-template-test__preview';
				host.append( image );
			}
		}
	}

	function poll( generationId, deadline ) {
		window.clearTimeout( pollTimer );
		pollTimer = window.setTimeout( function () {
			request( config.generationUrl + '/' + generationId, { method: 'GET' } ).then( function ( generation ) {
				setStatus( statusMessage( generation.status ), 'failed' === generation.status ? 'error' : 'info' );
				if ( TERMINAL.indexOf( generation.status ) !== -1 ) {
					renderResult( generation );
					toggleRun( true );
					return;
				}
				if ( Date.now() > deadline ) {
					setStatus( i18n.timedOut, 'info' );
					toggleRun( true );
					return;
				}
				poll( generationId, deadline );
			} ).catch( function ( error ) {
				setStatus( error.message || i18n.requestFailed, 'error' );
				toggleRun( true );
			} );
		}, config.pollIntervalMs );
	}

	function toggleRun( enabled ) {
		document.getElementById( 'worldgraph-template-test-run' ).disabled = ! enabled;
	}

	function run() {
		var collected = collectInputs();
		if ( collected.missingPrompt ) {
			setStatus( i18n.promptMissing, 'error' );
			return;
		}
		if ( collected.missingMedia ) {
			setStatus( i18n.mediaMissing, 'error' );
			return;
		}

		toggleRun( false );
		document.getElementById( 'worldgraph-template-test-result' ).replaceChildren();
		setStatus( i18n.queueing, 'info' );

		request( config.generationUrl, {
			method: 'POST',
			body: JSON.stringify( {
				type: capability.outputType,
				workflow: String( config.templateId ),
				prompt: collected.inputs.prompt || '',
				inputs: collected.inputs
			} )
		} ).then( function ( generation ) {
			setStatus( statusMessage( generation.status ), 'info' );
			poll( generation.id, Date.now() + config.pollTimeoutMs );
		} ).catch( function ( error ) {
			setStatus( error.message || i18n.requestFailed, 'error' );
			toggleRun( true );
		} );
	}

	function assistantBrief() {
		var slots = inputs.map( function ( slot ) {
			return slot.slot + ( slot.required ? ' (required)' : ' (optional)' );
		} ).join( ', ' );

		return [
			'You are helping test a generation Template inside World Graph Studio.',
			'Template modality: ' + ( capability.label || capability.modality ) + '.',
			'Output type: ' + capability.outputType + '.',
			'Available input slots: ' + slots + '.',
			'Write generation prompts that suit this modality. When you propose a prompt, return it on its own inside a fenced code block so it can be used directly.'
		].join( ' ' );
	}

	function extractPrompt( content ) {
		var fenced = /```(?:[a-z]*\n)?([\s\S]*?)```/i.exec( content );
		return ( fenced ? fenced[ 1 ] : content ).trim();
	}

	function appendMessage( role, content ) {
		var log = document.getElementById( 'worldgraph-template-test-chat-log' );
		var entry = text( 'div', 'worldgraph-template-test__message worldgraph-template-test__message--' + role );
		entry.append( text( 'strong', '', 'user' === role ? i18n.you : i18n.assistant ) );
		entry.append( text( 'p', '', content ) );

		if ( 'assistant' === role ) {
			var use = text( 'button', 'button-link', i18n.usePrompt );
			use.type = 'button';
			use.addEventListener( 'click', function () {
				var field = promptField();
				if ( field ) {
					field.value = extractPrompt( content );
					field.focus();
				}
			} );
			entry.append( use );
		}

		log.append( entry );
		log.scrollTop = log.scrollHeight;
		return entry;
	}

	function sendChat() {
		var field = document.getElementById( 'worldgraph-template-test-chat-input' );
		var message = field.value.trim();
		if ( ! message ) {
			return;
		}

		var button = document.getElementById( 'worldgraph-template-test-chat-send' );
		button.disabled = true;
		appendMessage( 'user', message );
		field.value = '';
		var pending = appendMessage( 'assistant', i18n.thinking );

		var current = promptField();
		var composed = assistantBrief()
			+ ( current && current.value.trim() ? '\n\nCurrent draft prompt:\n' + current.value.trim() : '' )
			+ '\n\nRequest:\n' + message;

		request( config.chatUrl, {
			method: 'POST',
			body: JSON.stringify( { prompt: composed, action: 'generate', messages: history.slice( -10 ) } )
		} ).then( function ( response ) {
			var content = response && response.data ? String( response.data ) : '';
			if ( ! response || ! response.success || ! content ) {
				throw new Error( ( response && response.error ) || i18n.chatError );
			}
			pending.remove();
			appendMessage( 'assistant', content );
			history.push( { role: 'user', content: message }, { role: 'assistant', content: content } );
		} ).catch( function ( error ) {
			pending.remove();
			appendMessage( 'assistant', error.message || i18n.chatError );
		} ).finally( function () {
			button.disabled = false;
		} );
	}

	renderCapability();
	renderFields();
	document.getElementById( 'worldgraph-template-test-run' ).addEventListener( 'click', run );
	document.getElementById( 'worldgraph-template-test-chat-send' ).addEventListener( 'click', sendChat );
}() );
