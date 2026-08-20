/**
 * World Graph Studio Admin JavaScript.
 *
 * Minimal JS for admin dashboard interactions.
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		// Placeholder for future AJAX interactions.
		$(document).on('click', '.worldgraph-action', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var action = $btn.data('action');

			$.ajax({
				url: worldgraphAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'worldgraph_' + action,
					nonce: worldgraphAdmin.nonce
				},
				success: function(response) {
					console.log('World Graph Studio action completed:', response);
				},
				error: function(xhr) {
					console.error('World Graph Studio action failed:', xhr);
				}
			});
		});

	});
})(jQuery);
