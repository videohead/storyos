/* global worldgraphSummaryTool */
(function () {
	'use strict';

	const source = document.getElementById('worldgraph-summary-source');
	const length = document.getElementById('worldgraph-summary-length');
	const focus = document.getElementById('worldgraph-summary-focus');
	const output = document.getElementById('worldgraph-summary-output');
	const generate = document.getElementById('worldgraph-generate-summary');
	const save = document.getElementById('worldgraph-save-summary');
	const status = document.getElementById('worldgraph-summary-status');

	if (!source || !generate || !save) {
		return;
	}

	function request(action, fields) {
		const body = new URLSearchParams({ action, nonce: worldgraphSummaryTool.nonce, ...fields });
		return fetch(worldgraphSummaryTool.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body }).then((response) => response.json());
	}

	generate.addEventListener('click', function () {
		generate.disabled = true;
		save.disabled = true;
		status.textContent = worldgraphSummaryTool.strings.generating;
		request('worldgraph_generate_summary', { source_id: source.value, length: length.value, focus: focus.value })
			.then((response) => {
				if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : worldgraphSummaryTool.strings.error);
				output.value = response.data.summary;
				save.disabled = false;
				status.textContent = worldgraphSummaryTool.strings.generated;
			})
			.catch((error) => { status.textContent = error.message; })
			.finally(() => { generate.disabled = false; });
	});

	save.addEventListener('click', function () {
		save.disabled = true;
		status.textContent = worldgraphSummaryTool.strings.saving;
		request('worldgraph_save_summary', { source_id: source.value, summary: output.value })
			.then((response) => {
				if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : worldgraphSummaryTool.strings.error);
				status.textContent = worldgraphSummaryTool.strings.saved;
			})
			.catch((error) => { status.textContent = error.message; save.disabled = false; });
	});
})();
