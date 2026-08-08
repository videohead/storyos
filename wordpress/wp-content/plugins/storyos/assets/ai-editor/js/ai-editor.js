/**
 * AI Editor — Gutenberg sidebar panel.
 *
 * @package StoryOS
 */

( function( React, wp ) {
	const { PanelBody, TextareaControl, SelectControl, Button, Spinner, TabPanel, Notice, TextControl } = wp.components;
	const { __ } = wp.i18n;
	const { useState, useEffect } = React;

	function AIEditorPanel() {
		const [ settings, setSettings ] = useState( null );
		const [ messages, setMessages ] = useState( [] );
		const [ input, setInput ] = useState( '' );
		const [ isLoading, setIsLoading ] = useState( false );
		const [ error, setError ] = useState( null );
		const [ agents, setAgents ] = useState( [] );
		const [ selectedAgent, setSelectedAgent ] = useState( 'story' );
		const [ currentPostId, setCurrentPostId ] = useState( window.storyosAI?.postId || 0 );

		// Load settings and agents on mount.
		useEffect( function() {
			fetch( window.storyosAI.restUrl + '/ai/settings?_wpnonce=' + window.storyosAI.nonce )
				.then( function( response ) { return response.json(); } )
				.then( function( data ) {
					if ( data.success ) {
						setSettings( data.data );
					}
				} )
				.catch( function() {
					setError( 'Failed to load AI settings.' );
				} );

			fetch( window.storyosAI.restUrl + '/ai/agents?_wpnonce=' + window.storyosAI.nonce )
				.then( function( response ) { return response.json(); } )
				.then( function( data ) {
					if ( data.success ) {
						setAgents( data.data );
						if ( data.data.length > 0 && ! selectedAgent ) {
							setSelectedAgent( data.data[0].name );
						}
					}
				} );
		}, [] );

		function sendMessage() {
			if ( ! input.trim() || isLoading ) return;

			const userMessage = { role: 'user', content: input };
			setMessages( function( prev ) { return prev.concat( [ userMessage ] ); } );
			setInput( '' );
			setIsLoading( true );
			setError( null );

			const body = {
				prompt: input,
				agent: selectedAgent,
				action: 'chat',
			};

			if ( currentPostId ) {
				body.post_id = currentPostId;
			}

			fetch( window.storyosAI.restUrl + '/ai/chat?_wpnonce=' + window.storyosAI.nonce, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( body ),
			} )
			.then( function( response ) { return response.json(); } )
			.then( function( data ) {
				setIsLoading( false );
				if ( data.success ) {
					setMessages( function( prev ) {
						return prev.concat( [
							{ role: 'assistant', content: data.data, agent: data.agent, backend: data.backend }
						] );
					} );
				} else {
					setError( data.error || 'AI response failed.' );
					setMessages( function( prev ) {
						return prev.concat( [
							{ role: 'error', content: data.error || 'AI response failed.' }
						] );
					} );
				}
			} )
			.catch( function() {
				setIsLoading( false );
				setError( 'Network error. Please try again.' );
			} );
		}

		function handleKeyDown( event ) {
			if ( event.shiftKey && event.key === 'Enter' ) {
				// Allow newline with Shift+Enter.
				return;
			}
			if ( event.key === 'Enter' && ! event.shiftKey ) {
				event.preventDefault();
				sendMessage();
			}
		}

		if ( ! settings ) {
			return React.createElement( 'div', { style: { padding: '20px', textAlign: 'center' } },
				React.createElement( Spinner ),
				React.createElement( 'p', null, __( 'Loading AI Editor...' ) )
			);
		}

		const containerStyle = {
			padding: '16px',
			maxHeight: '500px',
			overflowY: 'auto',
		};

		const messagesContainerStyle = {
			maxHeight: '300px',
			overflowY: 'auto',
			padding: '10px',
			background: '#f6f7f7',
			border: '1px solid #dcdcdc',
			borderRadius: '4px',
			marginBottom: '12px',
		};

		const messageStyle = function( role ) {
			return {
				marginBottom: '10px',
				padding: '10px',
				borderRadius: '4px',
				background: 'user' === role ? '#e8f4fd' : '#fff',
				border: '1px solid ' + ( 'user' === role ? '#2271b1' : '#dcdcdc' ),
			};
		};

		return React.createElement( PanelBody, { title: __( 'AI Editor' ), initialOpen: true },
			// Settings summary.
			React.createElement( 'div', { style: { marginBottom: '16px', fontSize: '12px', color: '#646970' } },
				React.createElement( 'div', null, __( 'Backend:' ), ' ', settings.backend ),
				React.createElement( 'div', null, __( 'Model:' ), ' ', settings.model ),
			),

			// Agent selector.
			agents.length > 0 && React.createElement( SelectControl, {
				label: __( 'Agent' ),
				value: selectedAgent,
				options: agents.map( function( agent ) {
					return { label: agent.name, value: agent.name };
				} ),
				onChange: function( value ) { setSelectedAgent( value ); },
			} ),

			// Messages display.
			React.createElement( 'div', { style: messagesContainerStyle },
				messages.map( function( msg, index ) {
					return React.createElement( 'div', { key: index, style: messageStyle( msg.role ) },
						'error' === msg.role && React.createElement( 'strong', { style: { color: '#d63638' } }, 'Error: ' ),
						'assistant' === msg.role && React.createElement( 'strong', null, msg.agent + ': ' ),
						React.createElement( 'div', { dangerouslySetInnerHTML: { __html: formatMessage( msg.content ) } } ),
						'assistant' === msg.role && React.createElement( 'small', { style: { color: '#646970', display: 'block', marginTop: '4px' } },
							msg.backend || ''
						),
					);
				} )
			),

			// Error notice.
			error && React.createElement( Notice, {
				status: 'error',
				isDismissible: true,
				onRemove: function() { setError( null ); },
			}, error ),

			// Input area.
			React.createElement( TextareaControl, {
				label: __( 'Ask AI Editor' ),
				value: input,
				onChange: function( value ) { setInput( value ); },
				placeholder: __( 'Type your message...' ),
				rows: 3,
				disabled: isLoading,
				onKeyDown: handleKeyDown,
			} ),

			// Send button.
			React.createElement( Button, {
				variant: 'primary',
				onClick: sendMessage,
				isBusy: isLoading,
				disabled: isLoading || ! input.trim(),
			}, isLoading ? __( 'Processing...' ) : __( 'Send' ) ),
		);
	}

	/**
	 * Format message content (convert newlines to <br>).
	 */
	function formatMessage( content ) {
		if ( ! content ) return '';
		// Simple markdown-like formatting.
		return content
			.replace( /\n/g, '<br>' )
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /\*(.+?)\*/g, '<em>$1</em>' )
			.replace( /`(.+?)`/g, '<code>$1</code>' );
	}

	// Register the panel.
	wp.registerBlockType( 'storyos/ai-editor-panel', {
		title: __( 'AI Editor' ),
		category: 'common',
		edit: function() {
			return React.createElement( AIEditorPanel );
		},
		save: function() {
			return null;
		},
	} );

	// Also expose as a sidebar component.
	if ( wp.editor && wp.editor.PluginSidebar ) {
		const { PluginSidebar } = wp.editor;

		function AISidebar() {
			return React.createElement( AIEditorPanel );
		}

		wp.plugins.registerPlugin( 'storyos-ai-editor', {
			render: function() {
				return React.createElement( PluginSidebar, {
					name: 'storyos-ai-editor-sidebar',
					title: __( 'AI Editor' ),
					icon: 'admin-generic',
				},
					React.createElement( AIEditorPanel )
				);
			},
		} );
	}

} )( window.React, window.wp );
