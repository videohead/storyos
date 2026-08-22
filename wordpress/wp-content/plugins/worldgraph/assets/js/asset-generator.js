/**
 * Detailed prompt, representative-workflow, and durable batch controls.
 */
( function () {
	'use strict';

	var settings = window.worldgraphAssetGenerator || {};
	var strings = settings.i18n || {};

	function setStatus( panel, message, isError ) {
		var status = panel.querySelector( '.worldgraph-generate-asset__status' );
		status.textContent = message || '';
		status.classList.toggle( 'is-error', !! isError );
	}

	function request( url, options ) {
		return fetch( url, Object.assign( {
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': settings.nonce
			}
		}, options || {} ) ).then( function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok ) {
					throw new Error( ( body && body.message ) || strings.error );
				}
				return body;
			} );
		} );
	}

	function uuid() {
		if ( window.crypto && 'function' === typeof window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}
		return Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2 );
	}

	function fillSelect( select, templates, defaultTemplateId ) {
		if ( ! select ) {
			return;
		}
		var previousValue = select.value;
		select.textContent = '';
		var automatic = document.createElement( 'option' );
		automatic.value = '0';
		automatic.textContent = strings.automaticTemplate;
		select.appendChild( automatic );
		( templates || [] ).forEach( function ( template ) {
			var option = document.createElement( 'option' );
			option.value = template.id;
			option.textContent = template.name + ( template.modality ? ' (' + template.modality + ')' : '' );
			select.appendChild( option );
		} );
		if ( previousValue && select.querySelector( 'option[value="' + previousValue + '"]' ) ) {
			select.value = previousValue;
		} else {
			select.value = defaultTemplateId && select.querySelector( 'option[value="' + defaultTemplateId + '"]' ) ? String( defaultTemplateId ) : '0';
		}
	}

	function renderWorkflow( panel, body ) {
		var workflow = panel.querySelector( '.worldgraph-generate-asset__workflow' );
		var definition = body.workflow || {};
		var counts = body.counts || {};
		var total = parseInt( body.total_jobs, 10 ) || 0;
		workflow.textContent = '';
		if ( ! definition.label ) {
			return;
		}
		var strong = document.createElement( 'strong' );
		strong.textContent = strings.workflow + ': ' + definition.label;
		workflow.appendChild( strong );
		var detail = document.createElement( 'span' );
		detail.textContent = ' — ' + ( definition.description || '' ) + ' (' + total + ' ' + strings.jobs + ': ' + ( counts.image || 0 ) + ' ' + strings.images + ', ' + ( counts.video || 0 ) + ' ' + strings.videos + ')';
		workflow.appendChild( detail );
	}

	function configureFromPlan( panel, body ) {
		var defaults = body.default_template_ids || {};
		fillSelect( panel.querySelector( '.worldgraph-generate-asset__template' ), body.image_templates || body.templates, defaults.image || body.default_template_id || 0 );
		fillSelect( panel.querySelector( '.worldgraph-generate-asset__video-template' ), body.video_templates, defaults.video || 0 );
		renderWorkflow( panel, body );
	}

	function selectedOutput( panel ) {
		return panel.querySelector( '.worldgraph-generate-asset__output' ).value;
	}

	function setDirectOutput( panel, type, announce ) {
		var outputs = panel._worldgraphOutputs || {};
		var outputSelect = panel.querySelector( '.worldgraph-generate-asset__output' );
		var videoOption = outputSelect.querySelector( 'option[value="video"]' );
		var hasVideo = !! outputs.video;
		videoOption.disabled = ! hasVideo;
		videoOption.textContent = hasVideo ? strings.videoOption : strings.videoNotAvailable;

		if ( 'video' === type && ! hasVideo ) {
			type = 'image';
			if ( announce ) {
				setStatus( panel, strings.videoUnavailable, true );
			}
		}
		outputSelect.value = type;

		var button = panel.querySelector( '.worldgraph-generate-asset__run' );
		button.textContent = 'video' === type ? strings.generateVideo : strings.generateImage;
		var featured = panel.querySelector( '.worldgraph-generate-asset__featured' );
		featured.disabled = 'video' === type;
		var preview = panel.querySelector( '.worldgraph-generate-asset__context-preview' );
		preview.textContent = outputs[ type ] ? outputs[ type ].prompt || '' : '';
	}

	function loadPrompt( panel, force ) {
		if ( ! force && panel._worldgraphContextLoaded ) {
			return Promise.resolve();
		}

		setStatus( panel, strings.loading );
		return request( settings.restUrl + '/prompt?post_id=' + encodeURIComponent( panel.dataset.postId ) )
			.then( function ( body ) {
				panel._worldgraphContextLoaded = true;
				panel._worldgraphOutputs = body.outputs || {
					image: {
						intent: body.intent || '',
						prompt: body.prompt || '',
						configured: !! body.configured,
						default_template_id: body.default_template_id || 0
					}
				};
				panel.dataset.defaultImageTemplate = String( body.default_template_id || 0 );
				panel.dataset.defaultVideoTemplate = String( ( body.default_template_ids && body.default_template_ids.video ) || 0 );
				configureFromPlan( panel, body );
				setDirectOutput( panel, selectedOutput( panel ), false );
				panel.querySelector( '.worldgraph-generate-asset__run' ).disabled = false;
				panel.querySelector( '.worldgraph-generate-asset__run-set' ).disabled = false;
				if ( panel.querySelector( '.worldgraph-generate-asset__run-project' ) ) {
					panel.querySelector( '.worldgraph-generate-asset__run-project' ).disabled = false;
				}
				var latest = body.latest_project_batch && body.latest_project_batch.batch_id && ! isTerminal( body.latest_project_batch.status ) ? body.latest_project_batch : body.latest_batch;
				if ( latest && latest.batch_id && ! isTerminal( latest.status ) ) {
					watchBatch( panel, latest );
				} else {
					var output = panel._worldgraphOutputs[ selectedOutput( panel ) ] || {};
					setStatus( panel, output.configured ? '' : ( 'video' === selectedOutput( panel ) ? strings.unconfiguredVideo : strings.unconfiguredImage ), ! output.configured );
				}
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} );
	}

	function renderResult( panel, body ) {
		var result = panel.querySelector( '.worldgraph-generate-asset__result' );
		var messages = [ strings.done ];
		if ( body.featured ) {
			messages.push( strings.featured );
		}
		if ( body.asset_id ) {
			messages.push( strings.assetCreated );
		}
		messages.push( strings.reloadHint );
		result.textContent = '';
		if ( body.thumbnail_url || body.url ) {
			var image = document.createElement( 'img' );
			image.src = body.thumbnail_url || body.url;
			image.alt = '';
			image.width = 150;
			result.appendChild( image );
		}
		var caption = document.createElement( 'p' );
		caption.className = 'description';
		caption.textContent = messages.join( ' ' );
		result.appendChild( caption );
		result.hidden = false;
	}

	function generate( panel ) {
		var button = panel.querySelector( '.worldgraph-generate-asset__run' );
		var type = selectedOutput( panel );
		var output = ( panel._worldgraphOutputs || {} )[ type ];
		var prompt = panel.querySelector( '.worldgraph-generate-asset__prompt' );
		var templateSelect = panel.querySelector( 'video' === type ? '.worldgraph-generate-asset__video-template' : '.worldgraph-generate-asset__template' );
		var defaultTemplate = 'video' === type ? panel.dataset.defaultVideoTemplate : panel.dataset.defaultImageTemplate;
		var selectedTemplate = parseInt( templateSelect.value, 10 ) || parseInt( defaultTemplate, 10 ) || 0;
		if ( ! output ) {
			setStatus( panel, strings.videoUnavailable, true );
			return;
		}
		var payload = {
			post_id: parseInt( panel.dataset.postId, 10 ),
			type: type,
			prompt: prompt.value.trim(),
			intent: output.intent || '',
			set_featured: 'image' === type && panel.querySelector( '.worldgraph-generate-asset__featured' ).checked,
			create_asset: panel.querySelector( '.worldgraph-generate-asset__create' ).checked,
			template_id: selectedTemplate
		};
		if ( ! selectedTemplate ) {
			setStatus( panel, 'video' === type ? strings.unconfiguredVideo : strings.unconfiguredImage, true );
			return;
		}

		button.disabled = true;
		setStatus( panel, 'video' === type ? strings.generatingVideo : strings.generatingImage );
		request( settings.restUrl, { method: 'POST', body: JSON.stringify( payload ) } )
			.then( function ( body ) {
				if ( 'queued' === body.status ) {
					var queued = 'video' === type ? strings.queuedVideo : strings.queuedImage;
					setStatus( panel, queued + ( body.generation_id ? ' (' + strings.job + ' #' + body.generation_id + ')' : '' ) );
					return;
				}
				setStatus( panel, '' );
				renderResult( panel, body );
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	function plan( panel, scope ) {
		setStatus( panel, strings.planning );
		return request( settings.restUrl + '/plan?post_id=' + encodeURIComponent( panel.dataset.postId ) + '&scope=' + encodeURIComponent( scope ) )
			.then( function ( body ) {
				configureFromPlan( panel, body );
				if ( ! body.ready ) {
					throw new Error( ( body.blockers || [] ).length + ' required outputs have no runnable Template.' );
				}
				return body;
			} );
	}

	function setBatchButtonsDisabled( panel, disabled ) {
		panel.querySelector( '.worldgraph-generate-asset__run-set' ).disabled = disabled;
		if ( panel.querySelector( '.worldgraph-generate-asset__run-project' ) ) {
			panel.querySelector( '.worldgraph-generate-asset__run-project' ).disabled = disabled;
		}
	}

	function startBatch( panel, scope ) {
		var idempotencyProperty = 'project' === scope ? '_worldgraphProjectBatchKey' : '_worldgraphItemBatchKey';
		setBatchButtonsDisabled( panel, true );
		plan( panel, scope )
			.then( function ( body ) {
				var summary = body.total_jobs + ' ' + strings.jobs + ' (' + ( body.counts.image || 0 ) + ' ' + strings.images + ', ' + ( body.counts.video || 0 ) + ' ' + strings.videos + ').\n\n';
				if ( ! window.confirm( summary + ( 'project' === scope ? strings.confirmProject : strings.confirmItem ) ) ) {
					return null;
				}
				setStatus( panel, strings.starting );
				panel[ idempotencyProperty ] = panel[ idempotencyProperty ] || uuid();
				var payload = {
					post_id: parseInt( panel.dataset.postId, 10 ),
					scope: scope,
					base_prompt: panel.querySelector( '.worldgraph-generate-asset__prompt' ).value.trim(),
					image_template_id: parseInt( panel.querySelector( '.worldgraph-generate-asset__template' ).value, 10 ) || 0,
					video_template_id: parseInt( panel.querySelector( '.worldgraph-generate-asset__video-template' ).value, 10 ) || 0,
					idempotency_key: panel[ idempotencyProperty ]
				};
				return request( settings.restUrl + '/batches', { method: 'POST', body: JSON.stringify( payload ) } )
					.then( function ( response ) {
						panel[ idempotencyProperty ] = '';
						return response;
					} );
			} )
			.then( function ( body ) {
				if ( body ) {
					setStatus( panel, strings.batchQueued + ' #' + body.batch_id );
					watchBatch( panel, body );
				} else {
					setBatchButtonsDisabled( panel, false );
				}
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
				setBatchButtonsDisabled( panel, false );
			} );
	}

	function isTerminal( status ) {
		return [ 'completed', 'completed_with_errors', 'cancelled', 'failed' ].indexOf( status ) !== -1;
	}

	function renderBatch( panel, body ) {
		var progress = panel.querySelector( '.worldgraph-generate-asset__progress' );
		var progressBar = progress.querySelector( 'progress' );
		var label = progress.querySelector( 'span' );
		var percent = parseInt( body.progress_percent, 10 ) || 0;
		progress.hidden = false;
		progressBar.value = percent;
		label.textContent = strings.batchProgress + ': ' + percent + '% — ' + ( body.completed || 0 ) + '/' + ( body.total || 0 ) + ' completed, ' + ( body.active || 0 ) + ' active, ' + ( body.failed || 0 ) + ' failed, ' + ( body.cancelled || 0 ) + ' cancelled.';
		setStatus( panel, strings.batchQueued + ' #' + body.batch_id + ' — ' + body.status, 'failed' === body.status || 'completed_with_errors' === body.status );
		panel.dataset.batchId = body.batch_id;
		var terminal = isTerminal( body.status );
		panel.querySelector( '.worldgraph-generate-asset__cancel' ).hidden = terminal || 'cancelling' === body.status;
		setBatchButtonsDisabled( panel, ! terminal );
	}

	function watchBatch( panel, initial ) {
		if ( panel._worldgraphPollTimer ) {
			window.clearTimeout( panel._worldgraphPollTimer );
		}
		renderBatch( panel, initial );
		if ( isTerminal( initial.status ) ) {
			return;
		}
		panel._worldgraphPollTimer = window.setTimeout( function poll() {
			request( settings.restUrl + '/batches/' + encodeURIComponent( panel.dataset.batchId ) )
				.then( function ( body ) {
					renderBatch( panel, body );
					if ( ! isTerminal( body.status ) ) {
						panel._worldgraphPollTimer = window.setTimeout( poll, settings.pollIntervalMs || 15000 );
					}
				} )
				.catch( function ( error ) {
					setStatus( panel, error.message, true );
					panel._worldgraphPollTimer = window.setTimeout( poll, Math.max( 30000, settings.pollIntervalMs || 15000 ) );
				} );
		}, settings.pollIntervalMs || 15000 );
	}

	function cancelBatch( panel ) {
		if ( ! panel.dataset.batchId || ! window.confirm( strings.cancelBatch ) ) {
			return;
		}
		request( settings.restUrl + '/batches/' + encodeURIComponent( panel.dataset.batchId ) + '/cancel', { method: 'POST', body: '{}' } )
			.then( function ( body ) {
				setStatus( panel, strings.cancelled );
				watchBatch( panel, body );
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.worldgraph-generate-asset' ), function ( panel ) {
			panel.querySelector( '.worldgraph-generate-asset__refresh-context' ).addEventListener( 'click', function () {
				loadPrompt( panel, true );
			} );
			panel.querySelector( '.worldgraph-generate-asset__output' ).addEventListener( 'change', function ( event ) {
				setDirectOutput( panel, event.target.value, true );
				var output = ( panel._worldgraphOutputs || {} )[ selectedOutput( panel ) ] || {};
				setStatus( panel, output.configured ? '' : ( 'video' === selectedOutput( panel ) ? strings.unconfiguredVideo : strings.unconfiguredImage ), ! output.configured );
			} );
			panel.querySelector( '.worldgraph-generate-asset__run' ).addEventListener( 'click', function () {
				generate( panel );
			} );
			panel.querySelector( '.worldgraph-generate-asset__run-set' ).addEventListener( 'click', function () {
				startBatch( panel, 'item' );
			} );
			var projectButton = panel.querySelector( '.worldgraph-generate-asset__run-project' );
			if ( projectButton ) {
				projectButton.addEventListener( 'click', function () {
					startBatch( panel, 'project' );
				} );
			}
			panel.querySelector( '.worldgraph-generate-asset__cancel' ).addEventListener( 'click', function () {
				cancelBatch( panel );
			} );
			loadPrompt( panel, false );
		} );
	} );
}() );
