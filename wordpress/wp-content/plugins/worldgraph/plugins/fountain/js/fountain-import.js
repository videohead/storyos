/* global FileReader */
(function () {
	'use strict';

	var form = document.getElementById('worldgraph-fountain-import-form');
	if (!form) {
		return;
	}

	var fileInput = document.getElementById('worldgraph_fountain_file');
	var jsonInput = document.getElementById('worldgraph_fountain_json');
	var status = document.getElementById('worldgraph-fountain-status');
	var submit = form.querySelector('input[type="submit"]');
	var transitionPattern = /(\bTO:\s*$|^FADE\s+IN\.?\s*$|^FADE\s+OUT\.?\s*$|^FADE TO BLACK\.?\s*$|^SMASH CUT\b|^DISSOLVE TO\b|^CUT TO\b|^MATCH CUT\b|^IRIS OUT\b)/i;
	var shotPattern = /^(CLOSE ON|WIDE SHOT|MEDIUM SHOT|MED\.? SHOT|POV|INSERT|ANGLE ON|RACK FOCUS|OVER SHOULDER|ECU|XCU|FULL SHOT|TWO[- ]SHOT|THREE[- ]SHOT|PUSH IN|PULL OUT|TILT|PAN|DOLLY|TRACK|HANDHELD|CRANE)\b/i;
	var actionVerbPattern = /\b(knocks|walks|runs|enters|exits|looks|turns|opens|closes|sits|stands|grabs|pulls|pushes|stares|glances|moves|leaves|arrives|holds|takes|puts|whispers|shouts|nods|pauses|stops|starts|drives|crosses|approaches|reveals|notices|watches|waits|listens|calls|jumps|falls|rises|lifts|drops|throws|catches|hides|follows|chases|attacks|fires|aims|points|reads|writes|dials|answers|slams|explodes|collapses|dies|breathes|laughs|cries|embraces|kisses|punches|kicks|draws|loads|unlocks|locks|breaks|fixes|builds|destroys|emerges|disappears|appears|remains|stays|goes|comes|returns|heads|signals|gestures|waves|checks|searches|finds|loses|gives|hands|passes|receives|accepts|refuses|agrees|argues|fights|wins|survives|escapes|captures|releases|cuts|stabs|shoots|misses|hits|strikes|defends|protects|covers|removes|extracts|drags|carries|sets|lays|rests|leans|bends|reacts|responds|realizes|understands|remembers|decides|chooses|offers|demands|orders|requests|asks|replies|mutters|mumbles|declares|announces|explains|shows|indicates|suggests|promises|threatens|warns|discovers|examines|observes|witnesses|sees|hears|feels|touches|steals|delivers|sends|shuts)\b/i;

	function xmlEscape(value) {
		return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function slug(value) {
		return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70) || 'untitled';
	}

	function allCaps(value) {
		var letters = value.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ]/g, '');
		return letters.length >= 2 && letters === letters.toUpperCase();
	}

	function sceneHeading(value) {
		return /^(INT\.?|EXT\.?|INT\.?\/EXT\.?|I\/E\.?)\s+/i.test(value);
	}

	function characterCue(value) {
		var stripped = value.replace(/\s*\([^)]+\)\s*$/, '').trim();
		return stripped && stripped.length <= 40 && allCaps(stripped) && !/[.!?]\s*$/.test(stripped);
	}

	function likelyAction(value) {
		var text = value.replace(/\s+/g, ' ').trim();
		if (!text || text.length < 4 || /^(INT\.?|EXT\.?|FADE\s|CUT\s|DISSOLVE)/i.test(text)) {
			return false;
		}
		if (/\.$/.test(text) && actionVerbPattern.test(text) && /^[A-Z]/.test(text) && text !== text.toUpperCase()) {
			return true;
		}
		return false;
	}

	function titlePage(lines) {
		var data = {};
		var key = null;
		var index = 0;
		for (; index < lines.length; index += 1) {
			var line = lines[index];
			if (!line.trim()) {
				if (key === null) {
					continue;
				}
				break;
			}
			var match = line.match(/^([A-Za-z][A-Za-z ]+):\s*(.*)$/);
			if (match) {
				key = match[1].trim().toLowerCase();
				data[key] = data[key] || [];
				if (match[2]) {
					data[key].push(match[2].trim());
				}
			} else if (key && /^\s{2,}\S/.test(line)) {
				data[key].push(line.trim());
			} else {
				return { bodyStart: 0 };
			}
		}
		return { title: data.title && data.title.join(' ').trim(), author: (data.author || data['written by']) && (data.author || data['written by']).join(' ').trim(), bodyStart: index + 1 };
	}

	function parseFountain(text, filename) {
		var lines = text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').map(function (line) {
			return /^\s*(#+|=)\s/.test(line) ? '' : line;
		});
		var page = titlePage(lines);
		var baseName = filename.replace(/\.(?:fountain|spmd|txt)$/i, '');
		var title = page.title || baseName;
		var author = page.author || '';
		var bodyStart = page.bodyStart;
		var scenes = [];
		var current = null;
		var previousBlank = true;
		var awaitingDialogue = false;

		function add(type, value) {
			if (current && value.trim()) {
				current.elements.push({ type: type, text: value.trim() });
			}
		}

		for (var index = bodyStart; index < lines.length; index += 1) {
			var line = lines[index].trim();
			if (!line) {
				previousBlank = true;
				awaitingDialogue = false;
				continue;
			}
			if (line.charAt(0) === '.' && line.charAt(1) !== '.') {
				current = { slugLine: line.slice(1).trim().toUpperCase(), elements: [] };
				scenes.push(current);
				previousBlank = false;
				awaitingDialogue = false;
				continue;
			}
			if (line.charAt(0) === '>' && line.charAt(1) !== '>') {
				add('transition', line.slice(1).trim().toUpperCase());
				previousBlank = false;
				continue;
			}
			if (line.charAt(0) === '@') {
				add('character_cue', line.slice(1).trim().toUpperCase());
				awaitingDialogue = true;
				previousBlank = false;
				continue;
			}
			if (sceneHeading(line)) {
				current = { slugLine: line.replace(/\s+/g, ' ').trim().toUpperCase(), elements: [] };
				scenes.push(current);
				previousBlank = false;
				awaitingDialogue = false;
				continue;
			}
			if (!current) {
				continue;
			}
			if (awaitingDialogue) {
				if (/^\([^)]*\)$/.test(line)) {
					add('parenthetical', line);
					previousBlank = false;
					continue;
				}
				if (likelyAction(line)) {
					awaitingDialogue = false;
					add('action', line);
					previousBlank = false;
					continue;
				}
				add('dialogue', line);
				previousBlank = false;
				continue;
			}
			if (transitionPattern.test(line)) {
				add('transition', line.toUpperCase());
			} else if (shotPattern.test(line)) {
				add('shot', line.toUpperCase());
			} else if (previousBlank && characterCue(line)) {
				add('character_cue', line.toUpperCase());
				awaitingDialogue = true;
			} else {
				add('action', line);
			}
			previousBlank = false;
		}
		if (!scenes.length) {
			scenes.push({ slugLine: 'INT. UNTITLED - DAY', elements: [{ type: 'action', text: text.trim() || 'Imported Fountain screenplay.' }] });
		}
		return { title: title, author: author, scenes: scenes };
	}

	function scriptToFdx(script) {
		var paragraphs = [];
		script.scenes.forEach(function (scene) {
			paragraphs.push('<Paragraph Type="Scene Heading"><Text>' + xmlEscape(scene.slugLine.toUpperCase()) + '</Text></Paragraph>');
			scene.elements.forEach(function (element) {
				if (!element.text.trim()) {
					return;
				}
				var type = { character_cue: 'Character', parenthetical: 'Parenthetical', dialogue: 'Dialogue', transition: 'Transition', shot: 'Shot', action: 'Action' }[element.type] || 'Action';
				var content = element.text;
				if ('Parenthetical' === type) {
					content = '(' + content.replace(/^\(|\)$/g, '') + ')';
				}
				if (/^(Character|Transition|Shot)$/.test(type)) {
					content = content.toUpperCase();
				}
				paragraphs.push('<Paragraph Type="' + type + '"><Text>' + xmlEscape(content) + '</Text></Paragraph>');
			});
		});
		var title = '<Paragraph Alignment="Center"><Text>' + xmlEscape(script.title.toUpperCase()) + '</Text></Paragraph>';
		var author = script.author ? '<Paragraph Alignment="Center"><Text>by ' + xmlEscape(script.author) + '</Text></Paragraph>' : '';
		return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' +
			'<FinalDraft DocumentType="Script" Template="No" Version="8"><Content>' + paragraphs.join('') + '</Content>' +
			'<TitlePage><Content>' + title + author + '</Content></TitlePage></FinalDraft>';
	}

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		var file = fileInput.files && fileInput.files[0];
		if (!file || !/\.(?:fountain|spmd|txt)$/i.test(file.name)) {
			status.textContent = 'Choose a .fountain, .spmd, or plain-text Fountain file to import.';
			return;
		}
		if ('function' !== typeof window.worldgraphParseFdx) {
			status.textContent = 'The FDX importer is unavailable.';
			return;
		}
		var reader = new FileReader();
		status.textContent = 'Converting Fountain to FDX and parsing screenplay...';
		submit.disabled = true;
		reader.onload = function () {
			try {
				var script = parseFountain(reader.result, file.name);
				jsonInput.value = window.worldgraphParseFdx(scriptToFdx(script), file.name.replace(/\.(?:fountain|spmd|txt)$/i, '.fdx'));
				status.textContent = 'Fountain converted and parsed. Submitting import...';
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
