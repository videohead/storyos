/* global storyosDramaturgyTool */
(function () {
	'use strict';

	const source = document.getElementById('storyos-dramaturgy-source');
	const lens = document.getElementById('storyos-dramaturgy-lens');
	const question = document.getElementById('storyos-dramaturgy-question');
	const output = document.getElementById('storyos-dramaturgy-output');
	const run = document.getElementById('storyos-run-dramaturgy');
	const save = document.getElementById('storyos-save-dramaturgy');
	const status = document.getElementById('storyos-dramaturgy-status');

	if (!source || !run || !save) {
		return;
	}

	function request(action, fields) {
		const body = new URLSearchParams({ action, nonce: storyosDramaturgyTool.nonce, ...fields });
		return fetch(storyosDramaturgyTool.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body,
		}).then((response) => response.json());
	}

	run.addEventListener('click', function () {
		run.disabled = true;
		save.disabled = true;
		status.textContent = storyosDramaturgyTool.strings.running;
		request('storyos_run_dramaturgy', { source_id: source.value, lens: lens.value, question: question.value })
			.then((response) => {
				if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : storyosDramaturgyTool.strings.error);
				output.value = response.data.analysis;
				save.disabled = false;
				status.textContent = storyosDramaturgyTool.strings.ready;
			})
			.catch((error) => { status.textContent = error.message; })
			.finally(() => { run.disabled = false; });
	});

	save.addEventListener('click', function () {
		save.disabled = true;
		status.textContent = storyosDramaturgyTool.strings.saving;
		request('storyos_save_dramaturgy', { source_id: source.value, analysis: output.value })
			.then((response) => {
				if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : storyosDramaturgyTool.strings.error);
				status.textContent = storyosDramaturgyTool.strings.saved;
			})
			.catch((error) => { status.textContent = error.message; save.disabled = false; });
	});
})();
