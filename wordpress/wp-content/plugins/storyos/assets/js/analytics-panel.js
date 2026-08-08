/**
 * Story Graph Analytics Panel JavaScript.
 *
 * @package StoryOS
 */

(function ($) {
	'use strict';

	/**
	 * Analytics Panel class.
	 */
	class AnalyticsPanel {
		constructor() {
			this.bindEvents();
		}

		/**
		 * Bind event listeners.
		 */
		bindEvents() {
			$('#fetch-analytics-btn').on('click', () => this.fetchAnalytics());
			$('#fetch-network-btn').on('click', () => this.fetchNetwork());
			$('#clear-cache-btn').on('click', () => this.clearCache());
		}

		/**
		 * Fetch analytics from orchestrator.
		 */
		fetchAnalytics() {
			const self = this;
			const $btn = $('#fetch-analytics-btn');
			const $loading = $('#analytics-loading');
			const $error = $('#analytics-error');
			const $content = $('#analytics-content');
			const $noData = $('#no-data-state');
			const $network = $('#network-section');

			// Disable button, show loading.
			$btn.prop('disabled', true).text('Loading...');
			$loading.show();
			$error.hide();
			$content.hide();
			$network.hide();
			$noData.hide();

			$.ajax({
				url: storyosAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_fetch_analytics',
					nonce: storyosAnalytics.nonce,
				},
				success(response) {
					if (response.success) {
						self.renderSummary(response.data);
						self.renderEntityCounts(response.data);
						self.renderMostConnected(response.data);
						self.renderRelationshipDistribution(response.data);
						self.renderIsolatedEntities(response.data);
						$content.show();
						$network.show();

						const source = response.data.cached ? ' (cached)' : ' (from orchestrator)';
						self.showNotice('Analytics loaded' + source, 'success');
					} else {
						self.showError(response.data.message || storyosAnalytics.strings.error);
					}
				},
				error() {
					self.showError(storyosAnalytics.strings.fetchError);
				},
				complete() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Fetch Analytics');
					$loading.hide();
				},
			});
		}

		/**
		 * Fetch character network from orchestrator.
		 */
		fetchNetwork() {
			const self = this;
			const $btn = $('#fetch-network-btn');
			const $loading = $('#network-loading');
			const $content = $('#network-content');

			$btn.prop('disabled', true).text('Loading...');
			$loading.show();
			$content.hide();

			$.ajax({
				url: storyosAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_fetch_network',
					nonce: storyosAnalytics.nonce,
				},
				success(response) {
					if (response.success) {
						self.renderStrongestRelationships(response.data);
						self.renderScenePresence(response.data);
						$content.show();

						const source = response.data.cached ? ' (cached)' : ' (from orchestrator)';
						self.showNotice('Network data loaded' + source, 'success');
					} else {
						self.showError(response.data.message || storyosAnalytics.strings.networkError);
					}
				},
				error() {
					self.showError(storyosAnalytics.strings.networkError);
				},
				complete() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-groups" style="margin-top: 3px;"></span> Fetch Character Network');
					$loading.hide();
				},
			});
		}

		/**
		 * Clear cache.
		 */
		clearCache() {
			const self = this;
			const $btn = $('#clear-cache-btn');

			$btn.prop('disabled', true).text('Clearing...');

			$.ajax({
				url: storyosAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_clear_cache',
					nonce: storyosAnalytics.nonce,
				},
				success(response) {
					if (response.success) {
						self.showNotice(storyosAnalytics.strings.cacheCleared, 'success');
					} else {
						self.showError(response.data.message || 'Failed to clear cache.');
					}
				},
				error() {
					self.showError('Failed to clear cache.');
				},
				complete() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Clear Cache');
				},
			});
		}

		/**
		 * Render summary cards.
		 *
		 * @param {Object} data The analytics data.
		 */
		renderSummary(data) {
			$('#total-entities').text(data.total_entities || 0);
			$('#total-relationships').text(data.total_relationships || 0);
			$('#network-density').text(((data.density || 0) * 100).toFixed(2) + '%');
			$('#isolated-count').text((data.isolated_entities || []).length);
		}

		/**
		 * Render entity counts.
		 *
		 * @param {Object} data The analytics data.
		 */
		renderEntityCounts(data) {
			const $container = $('#entity-counts');
			$container.empty();

			const counts = data.entity_counts || {};
			const labels = {
				'storyos_project': 'Projects',
				'storyos_character': 'Characters',
				'storyos_location': 'Locations',
				'storyos_scene': 'Scenes',
				'storyos_shot': 'Shots',
				'storyos_asset': 'Assets',
				'storyos_prop': 'Props',
				'storyos_episode': 'Episodes',
				'storyos_storyboard_frame': 'Storyboards',
				'storyos_editorial_artifact': 'Editorial',
			};

			for (const [type, count] of Object.entries(counts)) {
				const label = labels[type] || type;
				$container.append(
					'<div class="storyos-entity-count">' +
					'<span class="storyos-entity-count-number">' + count + '</span>' +
					'<span class="storyos-entity-count-label">' + label + '</span>' +
					'</div>'
				);
			}
		}

		/**
		 * Render most connected entities.
		 *
		 * @param {Object} data The analytics data.
		 */
		renderMostConnected(data) {
			const $tbody = $('#most-connected-body');
			$tbody.empty();

			const entities = data.most_connected || [];
			if (entities.length === 0) {
				$tbody.append('<tr><td colspan="3">No connected entities found.</td></tr>');
				return;
			}

			entities.forEach(entity => {
				const name = entity.name || 'Unknown';
				const type = entity.type || '';
				const connections = entity.connection_count || 0;

				$tbody.append(
					'<tr>' +
					'<td>' + $('<div>').text(name).html() + '</td>' +
					'<td>' + $('<div>').text(type).html() + '</td>' +
					'<td>' + connections + '</td>' +
					'</tr>'
				);
			});
		}

		/**
		 * Render relationship type distribution.
		 *
		 * @param {Object} data The analytics data.
		 */
		renderRelationshipDistribution(data) {
			const $container = $('#relationship-distribution');
			$container.empty();

			// Fetch graph data for distribution.
			$.ajax({
				url: storyosAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'storyos_fetch_graph',
					nonce: storyosAnalytics.nonce,
				},
				success: (response) => {
					if (response.success && response.data.edges) {
						const distribution = this.computeDistribution(response.data.edges);
						this.displayDistribution(distribution);
					}
				},
			});
		}

		/**
		 * Compute distribution from edges.
		 *
		 * @param {Array} edges The relationship edges.
		 * @returns {Object} Distribution object.
		 */
		computeDistribution(edges) {
			const dist = {};
			edges.forEach(edge => {
				const type = edge.type || 'unknown';
				dist[type] = (dist[type] || 0) + 1;
			});
			return dist;
		}

		/**
		 * Display distribution.
		 *
		 * @param {Object} distribution The distribution object.
		 */
		displayDistribution(distribution) {
			const $container = $('#relationship-distribution');
			$container.empty();

			for (const [type, count] of Object.entries(distribution)) {
				$container.append(
					'<div class="storyos-distribution-item">' +
					'<span class="storyos-distribution-type">' + $('<div>').text(type).html() + '</span>' +
					'<span class="storyos-distribution-count">' + count + '</span>' +
					'</div>'
				);
			}
		}

		/**
		 * Render isolated entities.
		 *
		 * @param {Object} data The analytics data.
		 */
		renderIsolatedEntities(data) {
			const $tbody = $('#isolated-body');
			$tbody.empty();

			const entities = data.isolated_entities || [];
			if (entities.length === 0) {
				$tbody.append('<tr><td colspan="3">No isolated entities found. Great connectivity!</td></tr>');
				return;
			}

			entities.forEach(entity => {
				const name = entity.name || 'Unknown';
				const type = entity.type || '';

				$tbody.append(
					'<tr>' +
					'<td>' + $('<div>').text(name).html() + '</td>' +
					'<td>' + $('<div>').text(type).html() + '</td>' +
					'<td>No relationships</td>' +
					'</tr>'
				);
			});
		}

		/**
		 * Render strongest character relationships.
		 *
		 * @param {Object} data The network data.
		 */
		renderStrongestRelationships(data) {
			const $tbody = $('#strongest-body');
			$tbody.empty();

			const relationships = data.strongest_relationships || [];
			if (relationships.length === 0) {
				$tbody.append('<tr><td colspan="4">No character relationships found.</td></tr>');
				return;
			}

			relationships.forEach(rel => {
				const charA = rel.character_a || 'Unknown';
				const charB = rel.character_b || 'Unknown';
				const relationship = rel.relationship || 'Related';
				const cooccurrences = rel.cooccurrences || 0;

				$tbody.append(
					'<tr>' +
					'<td>' + $('<div>').text(charA).html() + '</td>' +
					'<td>' + $('<div>').text(charB).html() + '</td>' +
					'<td>' + $('<div>').text(relationship).html() + '</td>' +
					'<td>' + cooccurrences + '</td>' +
					'</tr>'
				);
			});
		}

		/**
		 * Render character scene presence.
		 *
		 * @param {Object} data The network data.
		 */
		renderScenePresence(data) {
			const $tbody = $('#scene-presence-body');
			$tbody.empty();

			const presence = data.character_scene_presence || [];
			if (presence.length === 0) {
				$tbody.append('<tr><td colspan="3">No scene presence data.</td></tr>');
				return;
			}

			presence.forEach(char => {
				const name = char.name || 'Unknown';
				const scenes = char.scenes || 0;
				const shots = char.shots || 0;

				$tbody.append(
					'<tr>' +
					'<td>' + $('<div>').text(name).html() + '</td>' +
					'<td>' + scenes + '</td>' +
					'<td>' + shots + '</td>' +
					'</tr>'
				);
			});
		}

		/**
		 * Show error notice.
		 *
		 * @param {string} message The error message.
		 */
		showError(message) {
			const $error = $('#analytics-error');
			$('#analytics-error-message').text(message);
			$error.show();
			setTimeout(() => $error.fadeOut(), 5000);
		}

		/**
		 * Show success notice.
		 *
		 * @param {string} message The success message.
		 */
		showNotice(message, type) {
			const notice = $(
				'<div class="notice notice-success is-dismissible">' +
				'<p>' + message + '</p>' +
				'</div>'
			);
			$('.wrap h1').after(notice);
			setTimeout(() => notice.fadeOut(), 3000);
		}
	}

	// Initialize when DOM is ready.
	$(document).ready(() => {
		window.storyosAnalyticsPanel = new AnalyticsPanel();
	});

})(jQuery);
