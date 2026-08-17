/**
 * StoryOS Import admin JavaScript.
 *
 * Provides client-side validation and UX for the import page.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var $form = $('#storyos-import-form');
		var $json = $('#storyos_json');
		var $submit = $form.find('input[type="submit"]');

		// Validate JSON before submit.
		$form.on('submit', function(e) {
			var raw = $json.val().trim();

			if (raw === '') {
				e.preventDefault();
				alert('Please paste a StoryOS JSON document.');
				return;
			}

			try {
				var parsed = JSON.parse(raw);
				if (!parsed.project || !parsed.world || !parsed.scenes) {
					e.preventDefault();
					alert('Invalid StoryOS JSON: missing required sections (project, world, scenes).');
					return;
				}
			} catch (err) {
				e.preventDefault();
				alert('Invalid JSON: ' + err.message);
				return;
			}

			$submit.prop('disabled', true).val('Importing…');
		});
	});
})(jQuery);