/**
 * World Graph Studio Import admin JavaScript.
 *
 * Provides client-side validation and UX for the import page.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var $form = $('#worldgraph-import-form');
		var $file = $('#worldgraph_json_file');
		var $submit = $form.find('input[type="submit"]');

		$form.on('submit', function(e) {
			var file = $file[0] && $file[0].files ? $file[0].files[0] : null;

			if (!file) {
				e.preventDefault();
				alert('Please choose a World Graph Studio JSON file to import.');
				return;
			}

			if (!/\.json$/i.test(file.name)) {
				e.preventDefault();
				alert('Please choose a .json document that follows the World Graph Studio import contract.');
				return;
			}

			$submit.prop('disabled', true).val('Importing…');
		});
	});
})(jQuery);
