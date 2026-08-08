/**
 * StoryOS Admin JavaScript.
 *
 * Minimal JS for admin dashboard interactions.
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		// Placeholder for future AJAX interactions.
		$(document).on('click', '.storyos-action', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var action = $btn.data('action');

			$.ajax({
				url: storyosAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_' + action,
					nonce: storyosAdmin.nonce
				},
				success: function(response) {
					console.log('StoryOS action completed:', response);
				},
				error: function(xhr) {
					console.error('StoryOS action failed:', xhr);
				}
			});
		});

	});
})(jQuery);
