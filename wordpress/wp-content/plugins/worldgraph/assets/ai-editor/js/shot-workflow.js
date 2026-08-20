/* World Graph Studio filmmaking agent chat for classic Story Graph editors. */
( function() {
	'use strict';

	function request( path, options ) {
		options = options || {};
		options.headers = Object.assign( {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.worldgraphAIWorkflow.nonce
		}, options.headers || {} );

		return window.fetch( window.worldgraphAIWorkflow.restUrl + path, options ).then( function( response ) {
			return response.json().catch( function() {
				throw new Error( 'The server returned an unreadable response.' );
			} ).then( function( body ) {
				if ( ! response.ok ) {
					throw new Error( body.error || 'The AI request failed.' );
				}
				return body;
			} );
		} );
	}

	function setBusy( workflow, isBusy, message ) {
		workflow.querySelectorAll( '.worldgraph-ai-workflow__run, .worldgraph-ai-workflow__agent, .worldgraph-ai-workflow__prompt, .worldgraph-ai-workflow__clear' ).forEach( function( element ) {
			element.disabled = isBusy;
		} );
		workflow.querySelector( '.worldgraph-ai-workflow__status' ).textContent = message || '';
	}

	function appendMessage( workflow, role, content, label ) {
		var messages = workflow.querySelector( '.worldgraph-ai-workflow__messages' );
		var empty = messages.querySelector( '.worldgraph-ai-workflow__empty' );
		var message = document.createElement( 'div' );
		var heading = document.createElement( 'strong' );
		var body = document.createElement( 'div' );

		if ( empty ) {
			empty.remove();
		}

		message.className = 'worldgraph-ai-workflow__message worldgraph-ai-workflow__message--' + role;
		heading.textContent = label || ( 'user' === role ? 'You' : 'Agent' );
		body.className = 'worldgraph-ai-workflow__message-content';
		body.textContent = content;
		message.append( heading, body );
		messages.append( message );
		messages.scrollTop = messages.scrollHeight;
	}

	function loadAgents( workflow ) {
		request( '/ai/agents' ).then( function( response ) {
			var select = workflow.querySelector( '.worldgraph-ai-workflow__agent' );
			select.replaceChildren();
			if ( ! response.success || ! response.data.length ) {
				select.append( new Option( 'No enabled agents', '' ) );
				workflow.querySelector( '.worldgraph-ai-workflow__status' ).textContent = 'No filmmaking agents are enabled in AI Settings.';
				return;
			}

			response.data.forEach( function( agent ) {
				select.append( new Option( agent.label || agent.name, agent.name ) );
			} );
			if ( Array.from( select.options ).some( function( option ) { return 'Director' === option.value; } ) ) {
				select.value = 'Director';
			}
			select.disabled = false;
		} ).catch( function() {
			workflow.querySelector( '.worldgraph-ai-workflow__status' ).textContent = 'Unable to load agents.';
		} );
	}

	function getBoundedHistory( messages ) {
		var totalLength = 0;
		return messages.slice( -20 ).reverse().reduce( function( history, message ) {
			var content = message.content.slice( 0, 10000 );
			if ( totalLength + content.length > 40000 ) {
				return history;
			}
			totalLength += content.length;
			history.unshift( { role: message.role, content: content } );
			return history;
		}, [] );
	}

	function runWorkflow( workflow, action ) {
		var postId = Number( workflow.dataset.postId || window.worldgraphAIWorkflow.postId );
		var prompt = workflow.querySelector( '.worldgraph-ai-workflow__prompt' ).value.trim();
		var agent = workflow.querySelector( '.worldgraph-ai-workflow__agent' ).value;
		var history = getBoundedHistory( workflow.worldgraphMessages );
		var defaults = {
			analyze: 'Analyze this Story Graph element and recommend specific improvements.',
			continuity: 'Check this Story Graph element for continuity risks and explain how to resolve them.'
		};
		var body;

		if ( ! prompt ) {
			prompt = defaults[ action ] || '';
		}
		if ( ! prompt ) {
			workflow.querySelector( '.worldgraph-ai-workflow__status' ).textContent = 'Enter a message before sending.';
			return;
		}

		body = {
			post_id: postId,
			prompt: prompt,
			agent: agent,
			action: action,
			messages: history
		};
		appendMessage( workflow, 'user', prompt, 'You' );
		workflow.worldgraphMessages.push( { role: 'user', content: prompt } );
		workflow.querySelector( '.worldgraph-ai-workflow__prompt' ).value = '';

		setBusy( workflow, true, 'Working with the configured LLM...' );
		request( '/ai/chat', { method: 'POST', body: JSON.stringify( body ) } ).then( function( response ) {
			if ( ! response.success ) {
				throw new Error( response.error || 'The AI request failed.' );
			}
			var content = response.data || 'The agent returned no content.';
			appendMessage( workflow, 'assistant', content, response.agent || 'Agent' );
			workflow.worldgraphMessages.push( { role: 'assistant', content: content } );
			setBusy( workflow, false, response.agent ? 'Completed by ' + response.agent + '.' : 'Completed.' );
		} ).catch( function( error ) {
			appendMessage( workflow, 'error', error.message || 'The AI request failed.', 'Error' );
			setBusy( workflow, false, 'Unable to complete the request.' );
		} );
	}

	function clearChat( workflow ) {
		var messages = workflow.querySelector( '.worldgraph-ai-workflow__messages' );
		workflow.worldgraphMessages = [];
		messages.replaceChildren();
		var empty = document.createElement( 'p' );
		empty.className = 'worldgraph-ai-workflow__empty';
		empty.textContent = 'Start a conversation about this story element.';
		messages.append( empty );
		workflow.querySelector( '.worldgraph-ai-workflow__status' ).textContent = 'Chat cleared.';
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.worldgraph-ai-workflow' ).forEach( function( workflow ) {
			workflow.worldgraphMessages = [];
			loadAgents( workflow );
			workflow.querySelectorAll( '.worldgraph-ai-workflow__run' ).forEach( function( button ) {
				button.addEventListener( 'click', function() {
					runWorkflow( workflow, button.dataset.action );
				} );
			} );
			workflow.querySelector( '.worldgraph-ai-workflow__prompt' ).addEventListener( 'keydown', function( event ) {
				if ( 'Enter' === event.key && ! event.shiftKey ) {
					event.preventDefault();
					runWorkflow( workflow, 'chat' );
				}
			} );
			workflow.querySelector( '.worldgraph-ai-workflow__clear' ).addEventListener( 'click', function() {
				clearChat( workflow );
			} );
		} );
	} );
}() );
