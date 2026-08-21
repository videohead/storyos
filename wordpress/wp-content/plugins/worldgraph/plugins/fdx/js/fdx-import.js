/* global DOMParser, FileReader */
(function () {
	'use strict';

	var form = document.getElementById('worldgraph-fdx-import-form');

	var fileInput = document.getElementById('worldgraph_fdx_file');
	var jsonInput = document.getElementById('worldgraph_fdx_json');
	var status = document.getElementById('worldgraph-fdx-status');
	var submit = form.querySelector('input[type="submit"]');

	function textFrom(node) {
		return Array.prototype.map.call(node.querySelectorAll('Text'), function (textNode) {
			return textNode.textContent || '';
		}).join('').replace(/\s+/g, ' ').trim();
	}

	function slug(value) {
		return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70) || 'untitled';
	}

	function paragraphNodes(parent) {
		var nodes = [];
		Array.prototype.forEach.call(parent.children, function (child) {
			if ('Paragraph' !== child.nodeName) {
				return;
			}
			if ('General' === child.getAttribute('Type') && child.querySelector('DualDialogue')) {
				var dual = child.querySelector('DualDialogue');
				Array.prototype.forEach.call(dual.querySelectorAll('Paragraph'), function (paragraph) {
					nodes.push(paragraph);
				});
				return;
			}
			nodes.push(child);
		});
		return nodes;
	}

	function parseFdx(xml, filename) {
		var parser = new DOMParser();
		var documentNode = parser.parseFromString(xml, 'text/xml');
		if (documentNode.querySelector('parsererror') || !documentNode.documentElement || 'FinalDraft' !== documentNode.documentElement.nodeName) {
			throw new Error('The selected file is not a valid Final Draft FDX document.');
		}

		var baseName = filename.replace(/\.fdx$/i, '');
		var projectSlug = slug(baseName);
		var content = documentNode.querySelector('FinalDraft > Content');
		if (!content) {
			throw new Error('The FDX document does not contain screenplay content.');
		}

		var documentData = {
			worldgraph_version: '1.1',
			project: { id: 'project_fdx_' + projectSlug, title: baseName, description: 'Imported from Final Draft FDX.' },
			world: { id: 'world_fdx_' + projectSlug, name: baseName + ' World', description: 'Story world imported from Final Draft FDX.' },
			characters: [], locations: [], props: [], scenes: [], shots: [], sounds: [], storyboards: [],
			sequence: { id: 'sequence_fdx_' + projectSlug, name: 'Screenplay Order', order: [] }
		};
		var characters = {};
		var locations = {};
		var scene = null;
		var sceneNumber = 0;
		var currentDialogue = null;

		function addCharacter(name) {
			var cleanName = name.replace(/\s+\((?:CONT'D|V\.O\.|O\.S\.|CONTINUED)\)$/i, '').trim();
			var key = slug(cleanName);
			if (!characters[key]) {
				characters[key] = 'char_fdx_' + projectSlug + '_' + key;
				documentData.characters.push({ id: characters[key], name: cleanName, description: 'Character identified from Final Draft dialogue.' });
			}
			return { id: characters[key], name: cleanName };
		}

		function addLocation(heading) {
			var name = heading.replace(/^(INT\.|EXT\.|INT\.\/EXT\.|I\/E\.)\s*/i, '').trim();
			var key = slug(name);
			if (!locations[key]) {
				locations[key] = 'loc_fdx_' + projectSlug + '_' + key;
				documentData.locations.push({ id: locations[key], name: name, description: 'Location identified from Final Draft scene headings.' });
			}
			return locations[key];
		}

		function closeDialogue() {
			if (scene && currentDialogue && currentDialogue.text) {
			if (!scene.characters.some(function (id) { return id === currentDialogue.character.id; })) {
				scene.characters.push(currentDialogue.character.id);
			}
			currentDialogue.text = currentDialogue.text.trim();
			if (currentDialogue.text) {
				scene.dialogue.push(currentDialogue);
			}
			}
			currentDialogue = null;
		}

		function closeScene() {
			closeDialogue();
			if (!scene) {
				return;
			}
			scene.summary = scene.summary.trim();
			documentData.scenes.push(scene);
			documentData.sequence.order.push(scene.id);
			scene = null;
		}

		paragraphNodes(content).forEach(function (paragraph) {
			var type = paragraph.getAttribute('Type') || 'General';
			var text = textFrom(paragraph);
			if ('Scene Heading' === type) {
				closeScene();
				sceneNumber += 1;
				var sceneSlug = slug(text) || ('scene-' + sceneNumber);
				scene = { id: 'scene_fdx_' + projectSlug + '_' + sceneNumber + '_' + sceneSlug, title: text || ('Scene ' + sceneNumber), label: text || ('Scene ' + sceneNumber), location: addLocation(text || ('Scene ' + sceneNumber)), characters: [], props: [], summary: '', dialogue: [] };
				return;
			}
			if ('Character' === type) {
				closeDialogue();
				currentDialogue = { speaker: '', text: '', description: '' };
				currentDialogue.character = addCharacter(text || 'Unknown Character');
				currentDialogue.speaker = currentDialogue.character.name;
				return;
			}
			if ('Dialogue' === type) {
				if (!currentDialogue) {
					currentDialogue = { character: addCharacter('Unknown Character'), speaker: 'Unknown Character', text: '', description: '' };
				}
				currentDialogue.text += (currentDialogue.text ? ' ' : '') + text;
				return;
			}
			if ('Parenthetical' === type) {
				if (!currentDialogue) {
					return;
				}
				currentDialogue.description += (currentDialogue.description ? ' ' : '') + text.replace(/^\(|\)$/g, '');
				return;
			}
			if (scene) {
				closeDialogue();
				if (text) {
					scene.summary += (scene.summary ? '\n' : '') + text;
				}
			}
		});
		closeScene();
		if (!documentData.scenes.length) {
			throw new Error('The FDX document contains no Scene Heading paragraphs.');
		}

		documentData.dialogue = documentData.scenes.reduce(function (count, item) { return count + item.dialogue.length; }, 0);
		return JSON.stringify(documentData);
	}

	window.worldgraphParseFdx = parseFdx;

	if (!form) {
		return;
	}

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		var file = fileInput.files && fileInput.files[0];
		if (!file || !/\.fdx$/i.test(file.name)) {
			status.textContent = 'Choose a .fdx file to import.';
			return;
		}
		var reader = new FileReader();
		status.textContent = 'Parsing screenplay...';
		submit.disabled = true;
		reader.onload = function () {
			try {
				jsonInput.value = parseFdx(reader.result, file.name);
				status.textContent = 'Screenplay parsed. Submitting import...';
				form.submit();
			} catch (error) {
				status.textContent = error.message;
				submit.disabled = false;
			}
		};
		reader.onerror = function () {
			status.textContent = 'The selected file could not be read.';
			submit.disabled = false;
		};
		reader.readAsText(file);
	});
}());
