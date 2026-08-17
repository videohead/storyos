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
		const [ actionMessage, setActionMessage ] = useState( null );
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
							{ role: 'assistant', content: data.data, agent: data.agent, backend: data.backend, ai_generated: true }
						] );
					} );
				} else {
					setError( data.error || 'AI response failed.' );
					setMessages( function( prev ) {
						return prev.concat( [
							{ role: 'error', content: data.error || 'AI response failed.', ai_generated: true }
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

		/**
		 * Insert AI-generated content into the Gutenberg editor.
		 *
		 * @param {string} content The content to insert.
		 * @param {string} format The format of the content (html, plain, block).
		 * @return {void}
		 */
		function insertIntoEditor( content, format ) {
			if ( ! content ) return;

			// Check if Gutenberg editor is available.
			if ( ! wp.editor || ! wp.editor.dispatch ) {
				alert( 'The block editor is not available. Please open a post or page in the editor first.' );
				return;
			}

			var insertedContent = '';
			var blockType = 'html';

			switch ( format ) {
				case 'block':
					// Try to parse as blocks.
					if ( wp.blocks ) {
						var blocks = wp.blocks.parse( content );
						if ( blocks && blocks.length > 0 ) {
							wp.editor.dispatch( 'core/editor' ).insertBlocks( blocks );
							insertedContent = blocks.map( function( block ) {
								return wp.blocks.serialize( block );
							} ).join( '\n' );
							blockType = 'block';
						}
					}
					break;

				case 'html':
					// Insert as HTML content.
					insertedContent = '<!-- wp:html -->\n' + content + '\n<!-- /wp:html -->';
					blockType = 'html';
					break;

				case 'plain':
				default:
					// Insert as paragraph blocks.
					var paragraphs = content.split( '\n\n' ).filter( function( p ) {
						return p.trim();
					} );

					if ( paragraphs.length > 0 ) {
						var blocks = paragraphs.map( function( paragraph ) {
							return wp.blocks.create( 'core/paragraph', {
								content: paragraph.replace( /<[^>]*>/g, '' ) // Strip HTML tags for plain text
							} );
						} );

						wp.editor.dispatch( 'core/editor' ).insertBlocks( blocks );
						insertedContent = blocks.map( function( block ) {
							return wp.blocks.serialize( block );
						} ).join( '\n' );
						blockType = 'block';
					}
					break;
			}

			if ( insertedContent ) {
				// Show success notice.
				setError( null );
				setMessages( function( prev ) {
					return prev.concat( [
						{
							role         : 'system',
							content      : 'Content inserted into editor as ' + blockType + ' blocks.',
							inserted     : true,
							ai_generated : true
						}
					] );
				} );
			}
		}

		/**
		 * Copy an AI suggestion without modifying the current post.
		 *
		 * @param {string} content The content to copy.
		 * @return {void}
		 */
		function copySuggestion( content ) {
			if ( ! content ) return;

			if ( ! navigator.clipboard || ! navigator.clipboard.writeText ) {
				setError( __( 'Copying is not available in this browser. Select the suggestion text and copy it manually.' ) );
				return;
			}

			navigator.clipboard.writeText( content ).then( function() {
				setError( null );
				setActionMessage( __( 'Suggestion copied to clipboard.' ) );
			} ).catch( function() {
				setError( __( 'Could not copy the suggestion. Select the text and copy it manually.' ) );
			} );
		}

		/**
		 * Replace the complete post content with an accepted AI suggestion.
		 *
		 * @param {string} content The content to use as the new post body.
		 * @return {void}
		 */
		function replacePostContent( content ) {
			if ( ! content || ! wp.data || ! wp.data.dispatch ) return;

			if ( ! window.confirm( __( 'Replace the entire post content with this AI suggestion? This changes the current draft but can be undone before saving.' ) ) ) {
				return;
			}

			var paragraphs = content.split( /\n\s*\n/ ).filter( function( paragraph ) {
				return paragraph.trim();
			} );
			var blocks = paragraphs.map( function( paragraph ) {
				return wp.blocks.createBlock( 'core/paragraph', { content: paragraph.replace( /<[^>]*>/g, '' ) } );
			} );
			var postContent = blocks.length > 0 && wp.blocks ? wp.blocks.serialize( blocks ) : content;

			wp.data.dispatch( 'core/editor' ).editPost( { content: postContent } );
			setError( null );
			setActionMessage( __( 'Post content replaced with the AI suggestion.' ) );
		}

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

			// Messages display with accessibility improvements.
			React.createElement( 'div', { 
				style: messagesContainerStyle,
				role: 'log',
				'aria-live': 'polite',
				'aria-label': __( 'AI Editor Messages' ),
				tabIndex: 0
			},
				messages.map( function( msg, index ) {
					var messageActions = null;
					var aiLabel = msg.ai_generated ? React.createElement( 'span', {
						style: {
							display: 'inline-block',
							marginRight: '8px',
							padding: '2px 6px',
							background: '#ffe6e6',
							color: '#b32d2e',
							borderRadius: '3px',
							fontSize: '11px',
							fontWeight: 'bold',
							'aria-label': __( 'AI-generated content' ),
							className: 'screen-reader-text'
						},
						className: 'storyos-ai-badge'
					}, 'AI' ) : null;

					// Add insert buttons for assistant messages.
					if ( 'assistant' === msg.role ) {
						messageActions = React.createElement( 'div', { style: { marginTop: '8px' } },
							React.createElement( Button, {
								variant: 'secondary',
								size: 'small',
								onClick: function() { copySuggestion( msg.content ); }
							}, __( 'Copy suggestion' ) ),
							' ',
							React.createElement( Button, {
								variant: 'secondary',
								size: 'small',
								onClick: function() { replacePostContent( msg.content ); },
								disabled: ! wp.data || ! wp.data.dispatch || ! wp.blocks
							}, __( 'Replace post content' ) ),
							' ',
							React.createElement( Button, {
								variant: 'secondary',
								size: 'small',
								onClick: function() { insertIntoEditor( msg.content, 'block' ); },
								disabled: ! wp.editor || ! wp.editor.dispatch
							}, 'Insert as Blocks' ),
							' ',
							React.createElement( Button, {
								variant: 'secondary',
								size: 'small',
								onClick: function() { insertIntoEditor( msg.content, 'html' ); },
								disabled: ! wp.editor || ! wp.editor.dispatch
							}, 'Insert as HTML' ),
							' ',
							React.createElement( Button, {
								variant: 'secondary',
								size: 'small',
								onClick: function() { insertIntoEditor( msg.content, 'plain' ); },
								disabled: ! wp.editor || ! wp.editor.dispatch
							}, 'Insert as Text' )
						);
					}

					return React.createElement( 'div', { 
						key: index, 
						style: messageStyle( msg.role ),
						role: 'article',
						'aria-label': msg.role === 'error' ? 'Error message' : ( msg.role === 'assistant' ? ( msg.agent + ' response' ) : 'User message' ),
						tabIndex: 0
					},
						aiLabel,
						'error' === msg.role && React.createElement( 'strong', { style: { color: '#d63638' } }, 'Error: ' ),
						'assistant' === msg.role && React.createElement( 'strong', null, msg.agent + ': ' ),
						React.createElement( 'div', { 
							dangerouslySetInnerHTML: { __html: formatMessage( msg.content ) },
							role: 'text',
							'aria-label': msg.role === 'error' ? 'Error details' : 'Message content'
						} ),
						'assistant' === msg.role && React.createElement( 'small', { 
							style: { color: '#646970', display: 'block', marginTop: '4px' },
							'aria-label': __( 'Response generated using' )
						}, msg.backend || '' ),
						messageActions
					);
				} )
			),

			// Error notice.
			error && React.createElement( Notice, {
				status: 'error',
				isDismissible: true,
				onRemove: function() { setError( null ); },
			}, error ),

			actionMessage && React.createElement( Notice, {
				status: 'success',
				isDismissible: true,
				onRemove: function() { setActionMessage( null ); },
			}, actionMessage ),

			// Input area with accessibility improvements.
			React.createElement( TextareaControl, {
				label: __( 'Ask AI Editor' ),
				value: input,
				onChange: function( value ) { setInput( value ); },
				placeholder: __( 'Type your message...' ),
				rows: 3,
				disabled: isLoading,
				onKeyDown: handleKeyDown,
				'aria-describedby': 'ai-editor-help-text',
			} ),

			// Help text for keyboard shortcuts.
			React.createElement( 'div', {
				id: 'ai-editor-help-text',
				style: { fontSize: '12px', color: '#646970', marginBottom: '8px' }
			}, __( 'Press Enter to send, Shift+Enter for new line.' ) ),

			// Send button with accessibility improvements.
			React.createElement( Button, {
				variant: 'primary',
				onClick: sendMessage,
				isBusy: isLoading,
				disabled: isLoading || ! input.trim(),
				'aria-label': isLoading ? __( 'Processing AI request' ) : __( 'Send message to AI' ),
				'aria-busy': isLoading ? 'true' : 'false',
			}, isLoading ? __( 'Processing...' ) : __( 'Send' ) ),

			// Live region for status updates (screen reader announcements).
			React.createElement( 'div', {
				role: 'status',
				'aria-live': 'polite',
				className: 'screen-reader-text',
			}, isLoading ? __( 'AI is processing your request...' ) : '' ),
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

	// Global keyboard shortcut: Ctrl/Cmd+Enter to send message.
	document.addEventListener( 'keydown', function( event ) {
		// Check if Ctrl (or Cmd on Mac) + Enter is pressed.
		if ( ( event.ctrlKey || event.metaKey ) && event.key === 'Enter' ) {
			event.preventDefault();
			// Check if we're in the AI Editor sidebar.
			var sidebar = document.querySelector( '.storyos-ai-editor-sidebar' );
			if ( sidebar && ! sidebar.classList.contains( 'is-collapsed' ) ) {
				// Dispatch a custom event that the React component can listen to.
				window.dispatchEvent( new CustomEvent( 'storyos-ai-send' ) );
			}
		}
	} );

} )( window.React, window.wp );
