/**
 * World Graph Studio - EDL Manager admin UI.
 *
 * @package WorldGraphEDL
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $form        = $( '#worldgraph-edl-form' );
		var $status      = $( '#edl-status' );
		var $preview      = $( '#edl-preview' );
		var $previewCount = $( '#preview-clip-count' );
		var $previewTable = $( '#preview-table' );
		var $previewBody  = $( '#preview-table-body' );
		var $previewWarn  = $( '#preview-warnings' );
		var $importBtn    = $( '#edl-import-btn' );

		/**
		 * Show an admin notice above the form.
		 *
		 * @param {string} message Message to display.
		 * @param {string} type    'success' or 'error'.
		 */
		function showStatus( message, type ) {
			$status
				.removeClass( 'notice-success notice-error' )
				.addClass( 'success' === type ? 'notice-success' : 'notice-error' )
				.text( message )
				.show();
		}

		// Tab switching.
		$( '.nav-tab-wrapper .nav-tab' ).on( 'click', function () {
			var tab = $( this ).data( 'tab' );
			$( '.nav-tab-wrapper .nav-tab' ).removeClass( 'nav-tab-active' );
			$( this ).addClass( 'nav-tab-active' );
			$( '.edl-tab-content' ).hide();
			$( '#tab-' + tab ).show();
		} );

		// Preview an uploaded EDL file.
		$( '#edl-preview-btn' ).on( 'click', function () {
			var fileInput = $( '#edl_file' )[ 0 ];

			if ( ! fileInput.files.length ) {
				showStatus( 'Select an EDL file to preview.', 'error' );
				return;
			}

			var data = new FormData();
			data.append( 'action', 'worldgraph_edl_action' );
			data.append( 'edl_action', 'import' );
			data.append( 'nonce', edlConfig.nonce );
			data.append( 'edl_file', fileInput.files[ 0 ] );
			data.append( 'format', $( '#edl_format_import' ).val() );
			data.append( 'fps', $( '#edl_fps_import' ).val() );
			data.append( 'target', $( '#edl_import_target' ).val() );
			data.append( 'target_id', $( '#edl_import_target_id' ).val() );

			$.ajax( {
				url: edlConfig.ajaxUrl,
				method: 'POST',
				data: data,
				processData: false,
				contentType: false
			} ).done( function ( response ) {
				if ( ! response.success ) {
					showStatus( response.data || 'Preview failed.', 'error' );
					$importBtn.prop( 'disabled', true );
					return;
				}

				renderPreview( response.data.preview );
				showStatus( response.data.message, 'success' );
				$importBtn.prop( 'disabled', false );
			} ).fail( function () {
				showStatus( 'Preview request failed.', 'error' );
			} );
		} );

		/**
		 * Render the parsed clip preview table and any line-level warnings.
		 *
		 * @param {Object} preview Preview payload returned by the server.
		 */
		function renderPreview( preview ) {
			var clips = preview.clips || [];

			$previewCount.text( clips.length );
			$previewBody.empty();

			clips.forEach( function ( clip, index ) {
				$previewBody.append(
					$( '<tr>' ).append(
						$( '<td>' ).text( index + 1 ),
						$( '<td>' ).text( clip.reel || '' ),
						$( '<td>' ).text( clip.clip_name || '' ),
						$( '<td>' ).text( clip.tc_in || '' ),
						$( '<td>' ).text( clip.tc_out || '' ),
						$( '<td>' ).text( clip.film_in || '' ),
						$( '<td>' ).text( clip.film_out || '' ),
						$( '<td>' ).text( ( clip.frame_out || 0 ) - ( clip.frame_in || 0 ) )
					)
				);
			} );

			if ( preview.errors && preview.errors.length ) {
				var lines = preview.errors.map( function ( err ) {
					return 'Line ' + err.line + ': ' + err.content;
				} );
				$previewWarn
					.text( preview.errors.length + ' line(s) could not be parsed and were skipped:\n' + lines.join( '\n' ) )
					.show();
			} else {
				$previewWarn.hide();
			}

			$previewTable.show();
			$preview.show();
		}

		// Confirm a previewed import.
		$importBtn.on( 'click', function () {
			$.post( edlConfig.ajaxUrl, {
				action: 'worldgraph_edl_action',
				edl_action: 'confirm_import',
				nonce: edlConfig.nonce
			} ).done( function ( response ) {
				if ( ! response.success ) {
					showStatus( response.data || 'Import failed.', 'error' );
					return;
				}

				showStatus( response.data.message, 'success' );
				$importBtn.prop( 'disabled', true );
				$preview.hide();
			} ).fail( function () {
				showStatus( 'Import request failed.', 'error' );
			} );
		} );

		// Export the resolved timeline as a downloadable EDL file.
		$( '#edl-export-btn' ).on( 'click', function () {
			var $exportForm = $( '<form>', {
				method: 'POST',
				action: edlConfig.ajaxUrl
			} );

			var fields = {
				action: 'worldgraph_edl_action',
				edl_action: 'export',
				nonce: edlConfig.nonce,
				format: $( '#edl_format_export' ).val(),
				fps: $( '#edl_fps_export' ).val(),
				target: $( '#edl_target' ).val(),
				target_id: $( '#edl_target_id' ).val(),
				reel: $( '#edl_reel' ).val(),
				pre_roll: $( '#edl_pre_roll' ).val(),
				post_roll: $( '#edl_post_roll' ).val(),
				use_32char: $( '#edl_use_32char' ).is( ':checked' ) ? 1 : 0,
				drop_frame: $( '#edl_drop_frame' ).is( ':checked' ) ? 1 : 0,
				video_track: $( '#edl_video_track' ).val(),
				audio_track: $( '#edl_audio_track' ).val()
			};

			$.each( fields, function ( name, value ) {
				$exportForm.append( $( '<input>', { type: 'hidden', name: name, value: value } ) );
			} );

			if ( ! fields.target_id ) {
				showStatus( 'Enter a Project or Episode ID to export.', 'error' );
				return;
			}

			$( 'body' ).append( $exportForm );
			$exportForm.submit();
			$exportForm.remove();
		} );
	} );
} )( jQuery );
