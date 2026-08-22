/**
 * Guided Story Graph representative-media generation controls.
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

	function isTerminal( status ) {
		return [ 'completed', 'completed_with_errors', 'cancelled', 'failed' ].indexOf( status ) !== -1;
	}

	function targetInfo( value ) {
		if ( 0 === value.indexOf( 'single:' ) ) {
			return { kind: 'single', intent: value.slice( 7 ) };
		}
		if ( 'workflow:item' === value ) {
			return { kind: 'workflow', scope: 'item' };
		}
		if ( 'workflow:project' === value ) {
			return { kind: 'workflow', scope: 'project' };
		}
		return { kind: '' };
	}

	function currentTarget( panel ) {
		return panel.querySelector( '.worldgraph-generate-asset__action-select' ).value || '';
	}

	function currentMode( panel ) {
		var selected = panel.querySelector( '.worldgraph-generate-asset__modes input:checked' );
		return selected ? selected.value : '';
	}

	function actionForIntent( panel, intent ) {
		var match = null;
		( panel._worldgraphActions || [] ).some( function ( action ) {
			if ( intent === action.intent ) {
				match = action;
				return true;
			}
			return false;
		} );
		return match;
	}

	function selectHasValue( select, value ) {
		var found = false;
		Array.prototype.some.call( select.options, function ( option ) {
			if ( String( value ) === option.value ) {
				found = true;
				return true;
			}
			return false;
		} );
		return found;
	}

	function selectHasEnabledOption( select ) {
		return Array.prototype.some.call( select.options, function ( option ) {
			return ! option.disabled;
		} );
	}

	function countedLabel( count, singular, plural ) {
		return count + ' ' + ( 1 === parseInt( count, 10 ) ? singular : plural );
	}

	function templateSelect( panel, type ) {
		return panel.querySelector( 'video' === type ? '.worldgraph-generate-asset__video-template' : '.worldgraph-generate-asset__template' );
	}

	function templateContainer( panel, type ) {
		return panel.querySelector( 'video' === type ? '.worldgraph-generate-asset__video-template-option' : '.worldgraph-generate-asset__image-template-option' );
	}

	function setTemplateVisibility( panel, type, visible, help ) {
		var container = templateContainer( panel, type );
		var select = templateSelect( panel, type );
		container.hidden = ! visible;
		select.disabled = ! visible;
		container.querySelector( 'p' ).textContent = visible ? help : '';
	}

	function fillTemplateSelect( select, templates, defaultTemplateId, allowConfigured, savedValue, type ) {
		select.textContent = '';
		var placeholder = document.createElement( 'option' );
		placeholder.value = allowConfigured ? '0' : '';
		placeholder.textContent = allowConfigured ? strings.configuredPerItem : ( 'video' === type ? strings.chooseVideo : strings.chooseImage );
		placeholder.disabled = ! allowConfigured;
		select.appendChild( placeholder );

		( templates || [] ).forEach( function ( template ) {
			var option = document.createElement( 'option' );
			option.value = String( template.id );
			option.textContent = template.name + ( template.modality ? ' (' + template.modality + ')' : '' );
			select.appendChild( option );
		} );

		var desired = null !== savedValue && 'undefined' !== typeof savedValue ? String( savedValue ) : String( defaultTemplateId || '' );
		if ( desired && selectHasValue( select, desired ) ) {
			select.value = desired;
		} else if ( defaultTemplateId && selectHasValue( select, String( defaultTemplateId ) ) ) {
			select.value = String( defaultTemplateId );
		} else {
			select.value = allowConfigured ? '0' : '';
		}
	}

	function rememberTemplateSelections( panel ) {
		var target = panel._worldgraphRenderedTarget;
		if ( ! target ) {
			return;
		}
		panel._worldgraphTemplateSelections = panel._worldgraphTemplateSelections || {};
		panel._worldgraphTemplateSelections[ target ] = {
			image: panel.querySelector( '.worldgraph-generate-asset__template' ).value,
			video: panel.querySelector( '.worldgraph-generate-asset__video-template' ).value
		};
	}

	function savedTemplate( panel, target, type ) {
		var selections = ( panel._worldgraphTemplateSelections || {} )[ target ] || {};
		return Object.prototype.hasOwnProperty.call( selections, type ) ? selections[ type ] : null;
	}

	function rememberDirectOptions( panel ) {
		var target = panel._worldgraphRenderedTarget || '';
		if ( 0 !== target.indexOf( 'single:' ) ) {
			return;
		}
		panel._worldgraphDirectOptions = panel._worldgraphDirectOptions || {};
		panel._worldgraphDirectOptions[ target ] = {
			featured: panel.querySelector( '.worldgraph-generate-asset__featured' ).checked,
			createAsset: panel.querySelector( '.worldgraph-generate-asset__create' ).checked
		};
	}

	function appendOption( group, value, label ) {
		var option = document.createElement( 'option' );
		option.value = value;
		option.textContent = label;
		group.appendChild( option );
	}

	function buildModes( panel, body, preferredMode ) {
		var actions = panel._worldgraphActions || [];
		var availability = {
			image: actions.some( function ( action ) { return 'image' === action.type; } ),
			sequence: ( parseInt( body.total_jobs, 10 ) || 0 ) > 1 || '1' === panel.dataset.isProject,
			video: actions.some( function ( action ) { return 'video' === action.type; } )
		};
		var firstAvailable = '';
		Array.prototype.forEach.call( panel.querySelectorAll( '.worldgraph-generate-asset__modes input' ), function ( input ) {
			var available = !! availability[ input.value ];
			var note = input.closest( '.worldgraph-generate-asset__mode' ).querySelector( 'small' );
			note.dataset.availableText = note.dataset.availableText || note.textContent;
			note.textContent = available ? note.dataset.availableText : strings.notAvailable;
			input.dataset.available = available ? '1' : '0';
			input.disabled = ! available;
			input.closest( '.worldgraph-generate-asset__mode' ).classList.toggle( 'is-unavailable', ! available );
			if ( available && ! firstAvailable ) {
				firstAvailable = input.value;
			}
		} );
		var selectedMode = preferredMode && availability[ preferredMode ] ? preferredMode : firstAvailable;
		if ( selectedMode ) {
			panel.querySelector( '.worldgraph-generate-asset__modes input[value="' + selectedMode + '"]' ).checked = true;
		}
		return selectedMode;
	}

	function buildActionOptions( panel, body, mode, preferredTarget ) {
		var select = panel.querySelector( '.worldgraph-generate-asset__action-select' );
		var label = panel.querySelector( '.worldgraph-generate-asset__selection-label strong' );
		var actions = panel._worldgraphActions || [];
		select.textContent = '';

		if ( 'image' === mode || 'video' === mode ) {
			label.textContent = 'video' === mode ? strings.videoSelection : strings.imageSelection;
			actions.filter( function ( action ) {
				return mode === action.type;
			} ).forEach( function ( action ) {
				appendOption( select, 'single:' + action.intent, action.label );
			} );
		} else if ( 'sequence' === mode ) {
			label.textContent = strings.sequenceSelection;
			if ( ( parseInt( body.total_jobs, 10 ) || 0 ) > 1 ) {
				appendOption( select, 'workflow:item', body.workflow.label + ' (' + body.total_jobs + ' ' + strings.outputs + ')' );
			}
			if ( '1' === panel.dataset.isProject ) {
				appendOption( select, 'workflow:project', strings.allProjectMedia );
			}
		}

		if ( ! select.options.length ) {
			appendOption( select, '', strings.notAvailable );
			select.disabled = true;
			return;
		}
		select.disabled = false;
		select.value = preferredTarget && selectHasValue( select, preferredTarget ) ? preferredTarget : select.options[0].value;
	}

	function clearElement( element ) {
		while ( element.firstChild ) {
			element.removeChild( element.firstChild );
		}
	}

	function renderSingleSummary( panel, action ) {
		var workflow = panel.querySelector( '.worldgraph-generate-asset__workflow' );
		var definition = panel._worldgraphPromptBody.workflow || {};
		clearElement( workflow );
		var strong = document.createElement( 'strong' );
		strong.textContent = action.label + ' — ' + ( 'video' === action.type ? strings.video : strings.stillImage );
		workflow.appendChild( strong );
		if ( definition.description ) {
			var detail = document.createElement( 'span' );
			detail.textContent = ' — ' + definition.description;
			workflow.appendChild( detail );
		}
	}

	function renderPlanSummary( panel, body ) {
		var workflow = panel.querySelector( '.worldgraph-generate-asset__workflow' );
		var counts = body.counts || {};
		clearElement( workflow );
		var strong = document.createElement( 'strong' );
		strong.textContent = 'project' === body.scope ? strings.allProjectMedia : ( body.workflow.label || strings.workflow );
		workflow.appendChild( strong );
		var detail = document.createElement( 'span' );
		detail.textContent = ' — ' + countedLabel( body.total_jobs, strings.jobSingular, strings.jobs ) + ': ' + countedLabel( counts.image || 0, strings.image, strings.images ) + ', ' + countedLabel( counts.video || 0, strings.video, strings.videos ) + ( 'project' === body.scope ? '; ' + body.sources + ' ' + strings.sources : '' );
		workflow.appendChild( detail );
	}

	function renderPlanPreview( panel, body ) {
		var lines = [ strings.workflowPrompts ];
		var tasks = body.tasks || [];
		tasks.slice( 0, 24 ).forEach( function ( task ) {
			var source = 'project' === body.scope ? task.source_title + ' — ' : '';
			lines.push( '• ' + source + task.label + ' (' + ( 'video' === task.type ? strings.video : strings.stillImage ) + ')' );
		} );
		if ( tasks.length > 24 ) {
			lines.push( '… ' + ( tasks.length - 24 ) + ' ' + strings.moreOutputs );
		}
		panel.querySelector( '.worldgraph-generate-asset__context-preview' ).textContent = lines.join( '\n' );
	}

	function activeBatch( panel ) {
		if ( panel._worldgraphActiveBatch && ! isTerminal( panel._worldgraphActiveBatch.status ) ) {
			return panel._worldgraphActiveBatch;
		}
		if ( panel._worldgraphKnownBatches && panel._worldgraphKnownBatches.length ) {
			return panel._worldgraphKnownBatches[0];
		}
		return panel._worldgraphBatchTransition ? { status: 'checking' } : null;
	}

	function rememberBatch( panel, body ) {
		if ( ! body || ! body.batch_id || isTerminal( body.status ) ) {
			return;
		}
		panel._worldgraphKnownBatches = panel._worldgraphKnownBatches || [];
		panel._worldgraphKnownBatches = panel._worldgraphKnownBatches.filter( function ( batch ) {
			return String( batch.batch_id ) !== String( body.batch_id );
		} );
		panel._worldgraphKnownBatches.push( body );
		panel._worldgraphKnownBatches.sort( function ( left, right ) {
			return parseInt( right.batch_id, 10 ) - parseInt( left.batch_id, 10 );
		} );
	}

	function forgetBatch( panel, batchId ) {
		panel._worldgraphKnownBatches = ( panel._worldgraphKnownBatches || [] ).filter( function ( batch ) {
			return String( batch.batch_id ) !== String( batchId );
		} );
	}

	function updatePrimaryState( panel ) {
		var button = panel.querySelector( '.worldgraph-generate-asset__run' );
		var select = panel.querySelector( '.worldgraph-generate-asset__action-select' );
		var info = targetInfo( currentTarget( panel ) );
		var enabled = ! panel._worldgraphLoading && ! panel._worldgraphBusy;

		var controlsLocked = !! panel._worldgraphLoading || !! panel._worldgraphBusy;
		select.disabled = controlsLocked || ! select.options.length;
		Array.prototype.forEach.call( panel.querySelectorAll( '.worldgraph-generate-asset__modes input' ), function ( input ) {
			input.disabled = controlsLocked || '1' !== input.dataset.available;
		} );
		[ 'image', 'video' ].forEach( function ( type ) {
			var template = templateSelect( panel, type );
			template.disabled = controlsLocked || templateContainer( panel, type ).hidden || ! selectHasEnabledOption( template );
		} );
		panel.querySelector( '.worldgraph-generate-asset__prompt' ).disabled = controlsLocked;
		panel.querySelector( '.worldgraph-generate-asset__refresh-context' ).disabled = controlsLocked;
		var directOptions = panel.querySelector( '.worldgraph-generate-asset__direct-options' );
		panel.querySelector( '.worldgraph-generate-asset__create' ).disabled = controlsLocked || directOptions.hidden;
		var action = 'single' === info.kind ? actionForIntent( panel, info.intent ) : null;
		panel.querySelector( '.worldgraph-generate-asset__featured' ).disabled = controlsLocked || directOptions.hidden || ! action || 'image' !== action.type;
		if ( 'single' === info.kind ) {
			action = actionForIntent( panel, info.intent );
			enabled = enabled && !! action && parseInt( templateSelect( panel, action ? action.type : 'image' ).value, 10 ) > 0;
		} else if ( 'workflow' === info.kind ) {
			enabled = enabled && ! activeBatch( panel ) && panel._worldgraphDisplayedPlanScope === info.scope && panel._worldgraphDisplayedPlan && !! panel._worldgraphDisplayedPlan.ready;
		} else {
			enabled = false;
		}
		button.disabled = ! enabled;
	}

	function selectionStatus( panel, message, isError ) {
		if ( ! activeBatch( panel ) ) {
			setStatus( panel, message, isError );
		}
	}

	function renderSingle( panel, action, target ) {
		var type = action.type;
		var templates = ( panel._worldgraphTemplates || {} )[ type ] || [];
		var select = templateSelect( panel, type );
		var savedOptions = ( panel._worldgraphDirectOptions || {} )[ target ];
		panel._worldgraphDisplayedPlan = null;
		panel._worldgraphDisplayedPlanScope = '';

		setTemplateVisibility( panel, 'image', 'image' === type, strings.singleTemplateHelp );
		setTemplateVisibility( panel, 'video', 'video' === type, strings.singleTemplateHelp );
		fillTemplateSelect( select, templates, action.default_template_id || 0, false, savedTemplate( panel, target, type ), type );

		var directOptions = panel.querySelector( '.worldgraph-generate-asset__direct-options' );
		directOptions.hidden = false;
		var featuredOption = panel.querySelector( '.worldgraph-generate-asset__featured-option' );
		featuredOption.hidden = 'image' !== type;
		panel.querySelector( '.worldgraph-generate-asset__featured' ).disabled = 'image' !== type;
		panel.querySelector( '.worldgraph-generate-asset__featured' ).checked = savedOptions ? savedOptions.featured : !! action.featured;
		panel.querySelector( '.worldgraph-generate-asset__create' ).checked = savedOptions ? savedOptions.createAsset : true;
		panel.querySelector( '.worldgraph-generate-asset__prompt-help' ).textContent = strings.singlePromptHelp;
		panel.querySelector( '.worldgraph-generate-asset__choice-description' ).textContent = strings.singleChoiceHelp;
		panel.querySelector( '.worldgraph-generate-asset__context-preview' ).textContent = action.prompt || '';
		panel.querySelector( '.worldgraph-generate-asset__run' ).textContent = ( 'video' === type ? strings.createVideo : strings.createImage ) + ' ' + action.label;
		renderSingleSummary( panel, action );
		selectionStatus( panel, action.configured ? '' : ( 'video' === type ? strings.unconfiguredVideo : strings.unconfiguredImage ), ! action.configured );
		updatePrimaryState( panel );
	}

	function renderPlanLoading( panel, scope ) {
		panel._worldgraphDisplayedPlan = null;
		panel._worldgraphDisplayedPlanScope = '';
		[ 'image', 'video' ].forEach( function ( type ) {
			var select = templateSelect( panel, type );
			select.textContent = '';
			var loading = document.createElement( 'option' );
			loading.value = '';
			loading.textContent = strings.planning;
			loading.disabled = true;
			select.appendChild( loading );
		} );
		setTemplateVisibility( panel, 'image', false, '' );
		setTemplateVisibility( panel, 'video', false, '' );
		panel.querySelector( '.worldgraph-generate-asset__direct-options' ).hidden = true;
		panel.querySelector( '.worldgraph-generate-asset__prompt-help' ).textContent = strings.batchPromptHelp;
		panel.querySelector( '.worldgraph-generate-asset__choice-description' ).textContent = 'project' === scope ? strings.projectChoiceHelp : strings.itemChoiceHelp;
		panel.querySelector( '.worldgraph-generate-asset__context-preview' ).textContent = strings.planning;
		panel.querySelector( '.worldgraph-generate-asset__run' ).textContent = 'project' === scope ? strings.reviewProject : strings.reviewQueue;
		selectionStatus( panel, strings.planning );
		updatePrimaryState( panel );
	}

	function renderPlan( panel, body, target ) {
		var counts = body.counts || {};
		var defaults = body.default_template_ids || {};
		var imageVisible = ( parseInt( counts.image, 10 ) || 0 ) > 0;
		var videoVisible = ( parseInt( counts.video, 10 ) || 0 ) > 0;
		panel._worldgraphDisplayedPlan = body;
		panel._worldgraphDisplayedPlanScope = body.scope;

		setTemplateVisibility( panel, 'image', imageVisible, strings.batchTemplateHelp );
		setTemplateVisibility( panel, 'video', videoVisible, strings.batchTemplateHelp );
		if ( imageVisible ) {
			fillTemplateSelect( templateSelect( panel, 'image' ), body.image_templates || [], defaults.image || 0, !! body.ready, savedTemplate( panel, target, 'image' ), 'image' );
		}
		if ( videoVisible ) {
			fillTemplateSelect( templateSelect( panel, 'video' ), body.video_templates || [], defaults.video || 0, !! body.ready, savedTemplate( panel, target, 'video' ), 'video' );
		}

		panel.querySelector( '.worldgraph-generate-asset__direct-options' ).hidden = true;
		panel.querySelector( '.worldgraph-generate-asset__prompt-help' ).textContent = strings.batchPromptHelp;
		panel.querySelector( '.worldgraph-generate-asset__choice-description' ).textContent = 'project' === body.scope ? strings.projectChoiceHelp : strings.itemChoiceHelp;
		panel.querySelector( '.worldgraph-generate-asset__run' ).textContent = 'project' === body.scope
			? strings.reviewProject + ' (' + body.total_jobs + ' ' + strings.jobs + ')'
			: strings.reviewQueue + ' ' + body.workflow.label + ' (' + body.total_jobs + ' ' + strings.jobs + ')';
		renderPlanSummary( panel, body );
		renderPlanPreview( panel, body );
		if ( body.ready ) {
			selectionStatus( panel, '' );
		} else {
			selectionStatus( panel, ( body.blockers || [] ).length + ' ' + strings.missingTemplates, true );
		}
		updatePrimaryState( panel );
	}

	function ensurePlan( panel, scope, force ) {
		panel._worldgraphPlanCache = panel._worldgraphPlanCache || {};
		panel._worldgraphPlanRequests = panel._worldgraphPlanRequests || {};
		if ( force ) {
			delete panel._worldgraphPlanCache[ scope ];
			delete panel._worldgraphPlanRequests[ scope ];
		}
		if ( panel._worldgraphPlanCache[ scope ] ) {
			return Promise.resolve( panel._worldgraphPlanCache[ scope ] );
		}
		if ( panel._worldgraphPlanRequests[ scope ] ) {
			return panel._worldgraphPlanRequests[ scope ];
		}

		var epoch = panel._worldgraphPlanEpoch || 0;
		var planRequest = request( settings.restUrl + '/plan?post_id=' + encodeURIComponent( panel.dataset.postId ) + '&scope=' + encodeURIComponent( scope ) )
			.then( function ( body ) {
				if ( epoch === panel._worldgraphPlanEpoch ) {
					panel._worldgraphPlanCache[ scope ] = body;
				}
				if ( panel._worldgraphPlanRequests[ scope ] === planRequest ) {
					delete panel._worldgraphPlanRequests[ scope ];
				}
				return body;
			} )
			.catch( function ( error ) {
				if ( panel._worldgraphPlanRequests[ scope ] === planRequest ) {
					delete panel._worldgraphPlanRequests[ scope ];
				}
				throw error;
			} );
		panel._worldgraphPlanRequests[ scope ] = planRequest;
		return planRequest;
	}

	function renderTarget( panel ) {
		rememberTemplateSelections( panel );
		rememberDirectOptions( panel );
		var target = currentTarget( panel );
		var info = targetInfo( target );
		var token = ( panel._worldgraphSelectionToken || 0 ) + 1;
		panel._worldgraphSelectionToken = token;
		panel._worldgraphRenderedTarget = target;

		if ( 'single' === info.kind ) {
			var action = actionForIntent( panel, info.intent );
			if ( action ) {
				renderSingle( panel, action, target );
			}
			return;
		}

		if ( 'workflow' === info.kind ) {
			renderPlanLoading( panel, info.scope );
			ensurePlan( panel, info.scope, false )
				.then( function ( body ) {
					if ( token !== panel._worldgraphSelectionToken || target !== currentTarget( panel ) ) {
						return;
					}
					renderPlan( panel, body, target );
				} )
				.catch( function ( error ) {
					if ( token === panel._worldgraphSelectionToken && target === currentTarget( panel ) ) {
						setStatus( panel, error.message, true );
						updatePrimaryState( panel );
					}
				} );
		}
	}

	function legacyActions( body ) {
		var actions = [];
		Object.keys( body.outputs || {} ).forEach( function ( type ) {
			actions.push( body.outputs[ type ] );
		} );
		if ( ! actions.length && body.intent ) {
			actions.push( {
				type: 'image',
				intent: body.intent,
				label: body.workflow && body.workflow.label ? body.workflow.label : strings.stillImage,
				prompt: body.prompt || '',
				configured: !! body.configured,
				default_template_id: body.default_template_id || 0
			} );
		}
		return actions;
	}

	function activeBatchesFromPrompt( body ) {
		return [ body.latest_batch, body.latest_project_batch ].filter( function ( batch ) {
			return batch && batch.batch_id && ! isTerminal( batch.status );
		} ).sort( function ( left, right ) {
			return parseInt( right.batch_id, 10 ) - parseInt( left.batch_id, 10 );
		} );
	}

	function loadPrompt( panel, force ) {
		if ( ! force && panel._worldgraphPromptBody ) {
			return Promise.resolve( panel._worldgraphPromptBody );
		}

		var preferredMode = currentMode( panel );
		var preferredTarget = currentTarget( panel );
		panel._worldgraphLoading = true;
		panel._worldgraphSelectionToken = ( panel._worldgraphSelectionToken || 0 ) + 1;
		panel.querySelector( '.worldgraph-generate-asset__refresh-context' ).disabled = true;
		setStatus( panel, strings.loading );
		updatePrimaryState( panel );

		return request( settings.restUrl + '/prompt?post_id=' + encodeURIComponent( panel.dataset.postId ) )
			.then( function ( body ) {
				panel._worldgraphPromptBody = body;
				panel._worldgraphActions = body.actions || legacyActions( body );
				panel._worldgraphTemplates = {
					image: body.image_templates || body.templates || [],
					video: body.video_templates || []
				};
				panel._worldgraphPlanEpoch = ( panel._worldgraphPlanEpoch || 0 ) + 1;
				panel._worldgraphPlanCache = {};
				panel._worldgraphPlanRequests = {};
				var selectedMode = buildModes( panel, body, preferredMode );
				buildActionOptions( panel, body, selectedMode, selectedMode === preferredMode ? preferredTarget : '' );
				panel._worldgraphRenderedMode = selectedMode;
				if ( panel._worldgraphPollTimer ) {
					window.clearTimeout( panel._worldgraphPollTimer );
				}
				panel._worldgraphWatchToken = ( panel._worldgraphWatchToken || 0 ) + 1;
				panel._worldgraphActiveBatch = null;
				panel._worldgraphKnownBatches = activeBatchesFromPrompt( body );
				delete panel.dataset.batchId;
				panel.querySelector( '.worldgraph-generate-asset__cancel' ).hidden = true;
				panel._worldgraphLoading = false;
				panel.querySelector( '.worldgraph-generate-asset__refresh-context' ).disabled = false;
				renderTarget( panel );
				if ( panel._worldgraphKnownBatches.length ) {
					watchBatch( panel, panel._worldgraphKnownBatches[0] );
				}
				return body;
			} )
			.catch( function ( error ) {
				panel._worldgraphLoading = false;
				panel.querySelector( '.worldgraph-generate-asset__refresh-context' ).disabled = false;
				setStatus( panel, error.message, true );
				updatePrimaryState( panel );
				throw error;
			} );
	}

	function renderResult( panel, body, type ) {
		var result = panel.querySelector( '.worldgraph-generate-asset__result' );
		var messages = [ 'video' === type ? strings.doneVideo : strings.done ];
		if ( body.featured ) {
			messages.push( strings.featured );
		}
		if ( body.asset_id ) {
			messages.push( strings.assetCreated );
		}
		messages.push( strings.reloadHint );
		clearElement( result );
		if ( body.thumbnail_url || body.url ) {
			var media;
			if ( 'video' === type ) {
				media = document.createElement( 'video' );
				media.controls = true;
				media.src = body.url;
			} else {
				media = document.createElement( 'img' );
				media.src = body.thumbnail_url || body.url;
				media.alt = '';
			}
			media.width = 150;
			result.appendChild( media );
		}
		var caption = document.createElement( 'p' );
		caption.className = 'description';
		caption.textContent = messages.join( ' ' );
		result.appendChild( caption );
		result.hidden = false;
	}

	function generateSingle( panel, action ) {
		var templateId = parseInt( templateSelect( panel, action.type ).value, 10 ) || 0;
		if ( ! templateId ) {
			setStatus( panel, 'video' === action.type ? strings.unconfiguredVideo : strings.unconfiguredImage, true );
			return;
		}

		var payload = {
			post_id: parseInt( panel.dataset.postId, 10 ),
			type: action.type,
			intent: action.intent,
			prompt: panel.querySelector( '.worldgraph-generate-asset__prompt' ).value.trim(),
			set_featured: 'image' === action.type && panel.querySelector( '.worldgraph-generate-asset__featured' ).checked,
			create_asset: panel.querySelector( '.worldgraph-generate-asset__create' ).checked,
			template_id: templateId
		};
		panel._worldgraphBusy = true;
		updatePrimaryState( panel );
		setStatus( panel, 'video' === action.type ? strings.generatingVideo : strings.generatingImage );
		request( settings.restUrl, { method: 'POST', body: JSON.stringify( payload ) } )
			.then( function ( body ) {
				if ( 'queued' === body.status ) {
					var queued = 'video' === action.type ? strings.queuedVideo : strings.queuedImage;
					setStatus( panel, queued + ( body.generation_id ? ' (' + strings.job + ' #' + body.generation_id + ')' : '' ) );
					return;
				}
				setStatus( panel, '' );
				renderResult( panel, body, action.type );
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} )
			.then( function () {
				panel._worldgraphBusy = false;
				updatePrimaryState( panel );
			} );
	}

	function startBatch( panel, scope ) {
		var body = panel._worldgraphPlanCache && panel._worldgraphPlanCache[ scope ];
		if ( ! body || ! body.ready || activeBatch( panel ) ) {
			updatePrimaryState( panel );
			return;
		}
		var summary = countedLabel( body.total_jobs, strings.jobSingular, strings.jobs ) + ' (' + countedLabel( body.counts.image || 0, strings.image, strings.images ) + ', ' + countedLabel( body.counts.video || 0, strings.video, strings.videos ) + ').\n\n';
		if ( ! window.confirm( summary + ( 'project' === scope ? strings.confirmProject : strings.confirmItem ) ) ) {
			return;
		}

		var idempotencyProperty = 'project' === scope ? '_worldgraphProjectBatchKey' : '_worldgraphItemBatchKey';
		panel[ idempotencyProperty ] = panel[ idempotencyProperty ] || uuid();
		panel._worldgraphBusy = true;
		rememberTemplateSelections( panel );
		updatePrimaryState( panel );
		setStatus( panel, strings.starting );
		var payload = {
			post_id: parseInt( panel.dataset.postId, 10 ),
			scope: scope,
			base_prompt: panel.querySelector( '.worldgraph-generate-asset__prompt' ).value.trim(),
			image_template_id: ( parseInt( body.counts.image, 10 ) || 0 ) > 0 ? parseInt( templateSelect( panel, 'image' ).value, 10 ) || 0 : 0,
			video_template_id: ( parseInt( body.counts.video, 10 ) || 0 ) > 0 ? parseInt( templateSelect( panel, 'video' ).value, 10 ) || 0 : 0,
			idempotency_key: panel[ idempotencyProperty ]
		};
		request( settings.restUrl + '/batches', { method: 'POST', body: JSON.stringify( payload ) } )
			.then( function ( response ) {
				panel[ idempotencyProperty ] = '';
				setStatus( panel, strings.batchQueued + ' #' + response.batch_id );
				watchBatch( panel, response );
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} )
			.then( function () {
				panel._worldgraphBusy = false;
				updatePrimaryState( panel );
			} );
	}

	function runSelection( panel ) {
		var info = targetInfo( currentTarget( panel ) );
		if ( 'single' === info.kind ) {
			var action = actionForIntent( panel, info.intent );
			if ( action ) {
				generateSingle( panel, action );
			}
		} else if ( 'workflow' === info.kind ) {
			startBatch( panel, info.scope );
		}
	}

	function renderBatch( panel, body ) {
		var progress = panel.querySelector( '.worldgraph-generate-asset__progress' );
		var progressBar = progress.querySelector( 'progress' );
		var label = progress.querySelector( 'span' );
		var percent = parseInt( body.progress_percent, 10 ) || 0;
		var terminal = isTerminal( body.status );
		progress.hidden = false;
		progressBar.value = percent;
		label.textContent = strings.batchProgress + ': ' + percent + '% — ' + ( body.completed || 0 ) + '/' + ( body.total || 0 ) + ' completed, ' + ( body.active || 0 ) + ' active, ' + ( body.failed || 0 ) + ' failed, ' + ( body.cancelled || 0 ) + ' cancelled.';
		setStatus( panel, strings.batchQueued + ' #' + body.batch_id + ' — ' + body.status, 'failed' === body.status || 'completed_with_errors' === body.status );
		panel.querySelector( '.worldgraph-generate-asset__cancel' ).hidden = terminal || 'cancelling' === body.status;
		if ( terminal ) {
			forgetBatch( panel, body.batch_id );
			panel._worldgraphActiveBatch = null;
			delete panel.dataset.batchId;
		} else {
			rememberBatch( panel, body );
			panel._worldgraphActiveBatch = body;
			panel.dataset.batchId = body.batch_id;
		}
		updatePrimaryState( panel );
	}

	function resumeKnownBatch( panel ) {
		var next = ( panel._worldgraphKnownBatches || [] )[0];
		if ( ! next || panel._worldgraphBatchTransition ) {
			updatePrimaryState( panel );
			return;
		}
		panel._worldgraphBatchTransition = true;
		updatePrimaryState( panel );
		request( settings.restUrl + '/batches/' + encodeURIComponent( next.batch_id ) )
			.then( function ( body ) {
				panel._worldgraphBatchTransition = false;
				if ( isTerminal( body.status ) ) {
					forgetBatch( panel, body.batch_id );
					resumeKnownBatch( panel );
				} else {
					watchBatch( panel, body );
				}
			} )
			.catch( function ( error ) {
				panel._worldgraphBatchTransition = false;
				setStatus( panel, error.message, true );
				updatePrimaryState( panel );
				panel._worldgraphPollTimer = window.setTimeout( function () {
					resumeKnownBatch( panel );
				}, Math.max( 30000, settings.pollIntervalMs || 15000 ) );
			} );
	}

	function watchBatch( panel, initial ) {
		if ( panel._worldgraphPollTimer ) {
			window.clearTimeout( panel._worldgraphPollTimer );
		}
		var watchToken = ( panel._worldgraphWatchToken || 0 ) + 1;
		panel._worldgraphWatchToken = watchToken;
		rememberBatch( panel, initial );
		renderBatch( panel, initial );
		if ( isTerminal( initial.status ) ) {
			window.setTimeout( function () {
				resumeKnownBatch( panel );
			}, 0 );
			return;
		}

		panel._worldgraphPollTimer = window.setTimeout( function poll() {
			if ( watchToken !== panel._worldgraphWatchToken || ! panel.dataset.batchId ) {
				return;
			}
			request( settings.restUrl + '/batches/' + encodeURIComponent( panel.dataset.batchId ) )
				.then( function ( body ) {
					if ( watchToken !== panel._worldgraphWatchToken ) {
						return;
					}
					renderBatch( panel, body );
					if ( ! isTerminal( body.status ) ) {
						panel._worldgraphPollTimer = window.setTimeout( poll, settings.pollIntervalMs || 15000 );
					} else {
						resumeKnownBatch( panel );
					}
				} )
				.catch( function ( error ) {
					setStatus( panel, error.message, true );
					panel._worldgraphPollTimer = window.setTimeout( poll, Math.max( 30000, settings.pollIntervalMs || 15000 ) );
				} );
		}, settings.pollIntervalMs || 15000 );
	}

	function cancelBatch( panel ) {
		if ( ! panel.dataset.batchId || panel._worldgraphBusy || ! window.confirm( strings.cancelBatch ) ) {
			return;
		}
		panel._worldgraphBusy = true;
		updatePrimaryState( panel );
		request( settings.restUrl + '/batches/' + encodeURIComponent( panel.dataset.batchId ) + '/cancel', { method: 'POST', body: '{}' } )
			.then( function ( body ) {
				setStatus( panel, strings.cancelled );
				watchBatch( panel, body );
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} )
			.then( function () {
				panel._worldgraphBusy = false;
				updatePrimaryState( panel );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.worldgraph-generate-asset' ), function ( panel ) {
			panel._worldgraphTemplateSelections = {};
			panel._worldgraphModeTargets = {};
			Array.prototype.forEach.call( panel.querySelectorAll( '.worldgraph-generate-asset__modes input' ), function ( input ) {
				input.addEventListener( 'change', function () {
					if ( ! input.checked || ! panel._worldgraphPromptBody ) {
						return;
					}
					rememberTemplateSelections( panel );
					if ( panel._worldgraphRenderedMode ) {
						panel._worldgraphModeTargets[ panel._worldgraphRenderedMode ] = currentTarget( panel );
					}
					panel._worldgraphRenderedMode = input.value;
					buildActionOptions( panel, panel._worldgraphPromptBody, input.value, panel._worldgraphModeTargets[ input.value ] || '' );
					renderTarget( panel );
				} );
			} );
			panel.querySelector( '.worldgraph-generate-asset__action-select' ).addEventListener( 'change', function () {
				panel._worldgraphModeTargets[ currentMode( panel ) ] = currentTarget( panel );
				renderTarget( panel );
			} );
			panel.querySelector( '.worldgraph-generate-asset__template' ).addEventListener( 'change', function () {
				rememberTemplateSelections( panel );
				updatePrimaryState( panel );
			} );
			panel.querySelector( '.worldgraph-generate-asset__video-template' ).addEventListener( 'change', function () {
				rememberTemplateSelections( panel );
				updatePrimaryState( panel );
			} );
			panel.querySelector( '.worldgraph-generate-asset__refresh-context' ).addEventListener( 'click', function () {
				loadPrompt( panel, true ).catch( function () {} );
			} );
			panel.querySelector( '.worldgraph-generate-asset__run' ).addEventListener( 'click', function () {
				runSelection( panel );
			} );
			panel.querySelector( '.worldgraph-generate-asset__cancel' ).addEventListener( 'click', function () {
				cancelBatch( panel );
			} );
			loadPrompt( panel, false ).catch( function () {} );
		} );
	} );
}() );
