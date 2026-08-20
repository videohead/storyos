/* global storyosSummaryTool */
(function () {
	'use strict';

	const source = document.getElementById('storyos-summary-source');
	const length = document.getElementById('storyos-summary-length');
	const focus = document.getElementById('storyos-summary-focus');
	const output = document.getElementById('storyos-summary-output');
	const generate = document.getElementById('storyos-generate-summary');
	const save = document.getElementById('storyos-save-summary');
	const status = document.getElementById('storyos-summary-status');

	if (!source || !generate || !save) {
		return;
	}

	function request(action, fields) {
		const body = new URLSearchParams({ action, nonce: storyosSummaryTool.nonce, ...fields });
		return fetch(storyosSummaryTool.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body }).then((response) => response.json());
	}

	generate.addEventListener('click', function () {
		generate.disabled = true;
		save.disabled = true;
		status.textContent = storyosSummaryTool.strings.generating;
		request('storyos_generate_summary', { source_id: source.value, length: length.value, focus: focus.value })
			.then((response) => {
				if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : storyosSummaryTool.strings.error);
				output.value = response.data.summary;
				save.disabled = false;
				status.textContent = storyosSummaryTool.strings.generated;
			})
			.catch((error) => { status.textContent = error.message; })
			.finally(() => { generate.disabled = false; });
	});

	save.addEventListener('click', function () {
		save.disabled = true;
		status.textContent = storyosSummaryTool.strings.saving;
		request('storyos_save_summary', { source_id: source.value, summary: output.value })
			.then((response) => {
				if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : storyosSummaryTool.strings.error);
				status.textContent = storyosSummaryTool.strings.saved;
			})
			.catch((error) => { status.textContent = error.message; save.disabled = false; });
	});
})();
