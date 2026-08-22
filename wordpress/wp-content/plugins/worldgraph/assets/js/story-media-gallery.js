( function( $ ) {
	'use strict';

	const config = window.worldgraphStoryGallery || {};

	function mediaLabel( attachment ) {
		if ( 'audio' === attachment.type ) {
			return config.audio || 'Audio';
		}
		if ( 'video' === attachment.type ) {
			return config.video || 'Video';
		}
		return config.file || 'Media';
	}

	function createItem( attachment ) {
		const item = document.createElement( 'li' );
		item.className = 'worldgraph-story-gallery__item';
		item.dataset.attachmentId = String( attachment.id );

		const handle = document.createElement( 'span' );
		handle.className = 'worldgraph-story-gallery__handle dashicons dashicons-move';
		handle.setAttribute( 'aria-hidden', 'true' );
		item.appendChild( handle );

		const preview = document.createElement( 'span' );
		preview.className = 'worldgraph-story-gallery__preview';
		if ( 'image' === attachment.type ) {
			const image = document.createElement( 'img' );
			image.src = attachment.sizes?.thumbnail?.url || attachment.url;
			image.alt = '';
			preview.appendChild( image );
		} else {
			const icon = document.createElement( 'span' );
			icon.className = `dashicons ${ 'audio' === attachment.type ? 'dashicons-format-audio' : 'dashicons-format-video' }`;
			icon.setAttribute( 'aria-hidden', 'true' );
			preview.appendChild( icon );
		}
		item.appendChild( preview );

		const details = document.createElement( 'span' );
		details.className = 'worldgraph-story-gallery__details';
		const title = document.createElement( 'strong' );
		title.textContent = attachment.title || mediaLabel( attachment );
		const mime = document.createElement( 'small' );
		mime.textContent = attachment.mime || mediaLabel( attachment );
		details.append( title, mime );
		item.appendChild( details );

		const actions = document.createElement( 'span' );
		actions.className = 'worldgraph-story-gallery__actions';
		const moveUp = document.createElement( 'button' );
		moveUp.type = 'button';
		moveUp.className = 'button-link';
		moveUp.dataset.galleryMove = 'up';
		moveUp.textContent = config.moveUp || 'Move media up';
		const moveDown = document.createElement( 'button' );
		moveDown.type = 'button';
		moveDown.className = 'button-link';
		moveDown.dataset.galleryMove = 'down';
		moveDown.textContent = config.moveDown || 'Move media down';
		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'button-link-delete';
		remove.dataset.galleryRemove = '';
		remove.textContent = config.remove || 'Remove from gallery';
		actions.append( moveUp, moveDown, remove );
		item.appendChild( actions );

		return item;
	}

	document.querySelectorAll( '[data-worldgraph-story-gallery]' ).forEach( ( root ) => {
		const input = root.querySelector( '[data-gallery-input]' );
		const list = root.querySelector( '[data-gallery-items]' );
		const empty = root.querySelector( '[data-gallery-empty]' );
		const add = root.querySelector( '[data-gallery-add]' );
		if ( ! input || ! list || ! add || ! window.wp?.media ) {
			return;
		}

		function updateValue() {
			const items = Array.from( list.querySelectorAll( '[data-attachment-id]' ) );
			const ids = items.map( ( item ) => item.dataset.attachmentId );
			input.value = ids.join( ',' );
			if ( empty ) {
				empty.hidden = ids.length > 0;
			}
			items.forEach( ( item, index ) => {
				const up = item.querySelector( '[data-gallery-move="up"]' );
				const down = item.querySelector( '[data-gallery-move="down"]' );
				if ( up ) {
					up.disabled = index === 0;
				}
				if ( down ) {
					down.disabled = index === items.length - 1;
				}
			} );
		}

		$( list ).sortable( {
			axis: 'y',
			handle: '.worldgraph-story-gallery__handle',
			items: '> [data-attachment-id]',
			placeholder: 'worldgraph-story-gallery__placeholder',
			update: updateValue,
		} );

		list.addEventListener( 'click', ( event ) => {
			const move = event.target.closest( '[data-gallery-move]' );
			if ( move ) {
				const item = move.closest( '[data-attachment-id]' );
				if ( item && 'up' === move.dataset.galleryMove && item.previousElementSibling ) {
					list.insertBefore( item, item.previousElementSibling );
				} else if ( item && 'down' === move.dataset.galleryMove && item.nextElementSibling ) {
					list.insertBefore( item.nextElementSibling, item );
				}
				updateValue();
				move.focus();
				return;
			}
			const button = event.target.closest( '[data-gallery-remove]' );
			if ( ! button ) {
				return;
			}
			button.closest( '[data-attachment-id]' )?.remove();
			updateValue();
		} );

		add.addEventListener( 'click', () => {
			// A fresh frame avoids Backbone retaining media that the editor removed
			// from this gallery between successive openings.
			const frame = window.wp.media( {
				title: config.title || 'Choose story media',
				button: { text: config.button || 'Add to story gallery' },
				library: { type: [ 'image', 'audio', 'video' ] },
				multiple: true,
			} );
			frame.on( 'select', () => {
				const existing = new Set( Array.from( list.querySelectorAll( '[data-attachment-id]' ) ).map( ( item ) => item.dataset.attachmentId ) );
				frame.state().get( 'selection' ).toJSON().forEach( ( attachment ) => {
					if ( ! existing.has( String( attachment.id ) ) ) {
						list.appendChild( createItem( attachment ) );
						existing.add( String( attachment.id ) );
					}
				} );
				updateValue();
			} );
			frame.open();
		} );

		updateValue();
	} );
}( jQuery ) );
