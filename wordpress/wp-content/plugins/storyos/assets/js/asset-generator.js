/**
 * StoryOS generate asset tools.
 *
 * Runs text-to-image for a story element and attaches the result to the post.
 */
( function () {
	'use strict';

	var settings = window.storyosAssetGenerator || {};
	var strings = settings.i18n || {};

	function setStatus( panel, message, isError ) {
		var status = panel.querySelector( '.storyos-generate-asset__status' );
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

	function fillSizes( panel, sizes, selected ) {
		var select = panel.querySelector( '.storyos-generate-asset__size' );
		if ( select.options.length ) {
			return;
		}
		( sizes || [] ).forEach( function ( size ) {
			var option = document.createElement( 'option' );
			option.value = size;
			option.textContent = size;
			option.selected = size === selected;
			select.appendChild( option );
		} );
	}

	function loadPrompt( panel, force ) {
		var prompt = panel.querySelector( '.storyos-generate-asset__prompt' );
		if ( ! force && prompt.value.trim() ) {
			return;
		}

		setStatus( panel, strings.loading );

		request( settings.restUrl + '/prompt?post_id=' + encodeURIComponent( panel.dataset.postId ) )
			.then( function ( body ) {
				prompt.value = body.prompt || '';
				fillSizes( panel, body.sizes, body.size );
				setStatus( panel, body.configured ? '' : strings.unconfigured, ! body.configured );
			} )
			.catch( function ( error ) {
				setStatus( panel, error.message, true );
			} );
	}

	function renderResult( panel, body ) {
		var result = panel.querySelector( '.storyos-generate-asset__result' );
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
		var button = panel.querySelector( '.storyos-generate-asset__run' );
		var payload = {
			post_id: parseInt( panel.dataset.postId, 10 ),
			prompt: panel.querySelector( '.storyos-generate-asset__prompt' ).value,
			size: panel.querySelector( '.storyos-generate-asset__size' ).value,
			set_featured: panel.querySelector( '.storyos-generate-asset__featured' ).checked,
			create_asset: panel.querySelector( '.storyos-generate-asset__create' ).checked
		};

		button.disabled = true;
		setStatus( panel, strings.generating );

		request( settings.restUrl, { method: 'POST', body: JSON.stringify( payload ) } )
			.then( function ( body ) {
				if ( 'queued' === body.status ) {
					setStatus( panel, strings.queued + ( body.generation_id ? ' (' + strings.job + ' #' + body.generation_id + ')' : '' ) );
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

	document.addEventListener( 'DOMContentLoaded', function () {
		var panels = document.querySelectorAll( '.storyos-generate-asset' );

		Array.prototype.forEach.call( panels, function ( panel ) {
			loadPrompt( panel, false );

			panel.querySelector( '.storyos-generate-asset__suggest' ).addEventListener( 'click', function () {
				loadPrompt( panel, true );
			} );

			panel.querySelector( '.storyos-generate-asset__run' ).addEventListener( 'click', function () {
				generate( panel );
			} );
		} );
	} );
}() );
