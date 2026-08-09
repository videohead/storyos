/**
 * StoryOS Plugins Manager JavaScript.
 *
 * @package StoryOS
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		/**
		 * Toggle plugin active/inactive state.
		 */
		$(document).on('click', '.storyos-toggle-plugin', function(e) {
			e.preventDefault();

			var $button = $(this);
			var pluginSlug = $button.data('plugin');
			var isActive = $button.closest('tr').find('.status-active').length > 0;

			$.ajax({
				url: storyosPlugins.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_toggle_plugin',
					nonce: storyosPlugins.nonce,
					slug: pluginSlug
				},
				beforeSend: function() {
					$button.prop('disabled', true).text('Processing...');
				},
				success: function(response) {
					if (response.success) {
						var active = !!(response.data && response.data.active);

						// Update the button text.
						$button.text(active ? 'Disable' : 'Enable');

						// Update status indicators.
						var $statusCell = $button.closest('tr').find('td:nth-child(2)');
						$statusCell.find('.status-active, .status-inactive').remove();
						$statusCell.prepend(
							'<span class="status-' + (active ? 'active' : 'inactive') + '">' +
							(active ? 'Active' : 'Inactive') + '</span>'
						);

						// Show success notice.
						showNotice(response.data.message, 'success');

						if (response.data.reload_required) {
							setTimeout(function() {
								window.location.reload();
							}, 500);
						}
					} else {
							if (response.data && response.data.settings_url) {
								showNotice(response.data.message || 'Please configure this plugin first.', 'error');
								setTimeout(function() {
									window.location.href = response.data.settings_url;
								}, 400);
								return;
							}
						showNotice(response.data.message || 'Failed to toggle plugin.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred. Please try again.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		});

		/**
		 * Test plugin connection.
		 */
		$(document).on('click', '.storyos-test-connection', function(e) {
			e.preventDefault();

			var $button = $(this);
			var pluginSlug = $button.data('plugin');

			$.ajax({
				url: storyosPlugins.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_test_connection',
					nonce: storyosPlugins.nonce,
					slug: pluginSlug
				},
				beforeSend: function() {
					$button.prop('disabled', true).text('Testing...');
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Connection test successful.', 'success');
					} else {
						showNotice(response.data.message || 'Connection test failed.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred during connection test.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		});

		/**
		 * Show admin notice.
		 *
		 * @param {string} message
		 * @param {string} type
		 */
		function showNotice(message, type) {
			var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
			var notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';

			$('.wrap.storyos-plugins').prepend(notice);

			// Auto-dismiss after 5 seconds.
			setTimeout(function() {
				$('.notice.is-dismissible').fadeOut();
			}, 5000);
		}

	})(jQuery);
