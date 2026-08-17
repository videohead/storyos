/* StoryOS AI workflow for the classic Shot editor. */
( function() {
	'use strict';

	function request( path, options ) {
		options = options || {};
		options.headers = Object.assign( {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.storyosAIWorkflow.nonce
		}, options.headers || {} );

		return window.fetch( window.storyosAIWorkflow.restUrl + path, options ).then( function( response ) {
			return response.json();
		} );
	}

	function setBusy( workflow, isBusy, message ) {
		workflow.querySelectorAll( '.storyos-ai-workflow__run, .storyos-ai-workflow__agent, .storyos-ai-workflow__prompt' ).forEach( function( element ) {
			element.disabled = isBusy;
		} );
		workflow.querySelector( '.storyos-ai-workflow__status' ).textContent = message || '';
	}

	function showResult( workflow, content, isError ) {
		var result = workflow.querySelector( '.storyos-ai-workflow__result' );
		result.hidden = false;
		result.classList.toggle( 'storyos-ai-workflow__result--error', isError );
		result.textContent = content;
	}

	function loadAgents( workflow ) {
		request( '/ai/agents' ).then( function( response ) {
			var select = workflow.querySelector( '.storyos-ai-workflow__agent' );
			select.replaceChildren();
			if ( ! response.success || ! response.data.length ) {
				select.append( new Option( 'No enabled agents', '' ) );
				workflow.querySelector( '.storyos-ai-workflow__status' ).textContent = 'No filmmaking agents are enabled in AI Settings.';
				return;
			}

			response.data.forEach( function( agent ) {
				select.append( new Option( agent.name, agent.name ) );
			} );
			select.disabled = false;
		} ).catch( function() {
			workflow.querySelector( '.storyos-ai-workflow__status' ).textContent = 'Unable to load agents.';
		} );
	}

	function runWorkflow( workflow, action ) {
		var postId = Number( workflow.dataset.postId || window.storyosAIWorkflow.postId );
		var prompt = workflow.querySelector( '.storyos-ai-workflow__prompt' ).value.trim();
		var agent = workflow.querySelector( '.storyos-ai-workflow__agent' ).value;
		var endpoint = '/ai/' + action;
		var body = { post_id: postId };

		if ( 'continuity' !== action ) {
			if ( ! prompt ) {
				workflow.querySelector( '.storyos-ai-workflow__status' ).textContent = 'Enter an instruction before running this action.';
				return;
			}
			body.prompt = prompt;
		}
		if ( 'generate' === action ) {
			body.agent = agent;
		}

		setBusy( workflow, true, 'Working with the configured LLM...' );
		request( endpoint, { method: 'POST', body: JSON.stringify( body ) } ).then( function( response ) {
			if ( ! response.success ) {
				throw new Error( response.error || 'The AI request failed.' );
			}
			showResult( workflow, response.data || 'The agent returned no content.', false );
			setBusy( workflow, false, response.agent ? 'Completed by ' + response.agent + '.' : 'Completed.' );
		} ).catch( function( error ) {
			showResult( workflow, error.message || 'The AI request failed.', true );
			setBusy( workflow, false, 'Unable to complete the request.' );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.storyos-ai-workflow' ).forEach( function( workflow ) {
			loadAgents( workflow );
			workflow.querySelectorAll( '.storyos-ai-workflow__run' ).forEach( function( button ) {
				button.addEventListener( 'click', function() {
					runWorkflow( workflow, button.dataset.action );
				} );
			} );
		} );
	} );
}() );