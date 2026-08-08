/**
 * Continuity Validation Admin Panel JavaScript.
 *
 * @package StoryOS
 */

(function($) {
	'use strict';

	/**
	 * ContinuityPanel class.
	 */
	class ContinuityPanel {
		constructor() {
			this.$runBtn = $('#storyos-run-validation');
			this.$clearBtn = $('#storyos-clear-all');
			this.$loading = $('#storyos-loading');
			this.$summary = $('#storyos-summary');
			this.$issuesContainer = null;
			this.strings = window.storyos_continuity?.strings || {};

			this.init();
		}

		/**
		 * Initialize the panel.
		 */
		init() {
			if (this.$runBtn.length) {
				this.$runBtn.on('click', $.proxy(this.runValidation, this));
			}
			if (this.$clearBtn.length) {
				this.$clearBtn.on('click', $.proxy(this.clearIssues, this));
			}
		}

		/**
		 * Run continuity validation.
		 */
		runValidation() {
			if (this.$runBtn.hasClass('disabled')) {
				return;
			}

			this.$runBtn.prop('disabled', true).addClass('disabled');
			this.$loading.show();
			this.$summary.hide();

			const self = this;
			const data = {
				action: 'storyos_run_validation',
				nonce: window.storyos_continuity?.nonce || '',
				episode_id: 0,
				scene_ids: []
			};

			$.ajax({
				url: window.storyos_continuity?.ajax_url || '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: data,
				beforeSend: function() {
					self.$loading.show();
				},
				success: function(response) {
					if (response.success) {
						self.renderSummary(response.data.summary);
						self.renderIssues(response.data.issues || []);
						self.$clearBtn.show();
					} else {
						self.showError(response.data || self.strings.error || 'Error running validation.');
					}
				},
				error: function() {
					self.showError(self.strings.error || 'Error running validation.');
				},
				complete: function() {
					self.$loading.hide();
					self.$runBtn.prop('disabled', false).removeClass('disabled');
				}
			});
		}

		/**
		 * Clear all issues.
		 */
		clearIssues() {
			if (!confirm(this.strings.confirm || 'Are you sure you want to clear all continuity issues?')) {
				return;
			}

			const self = this;
			const data = {
				action: 'storyos_clear_issues',
				nonce: window.storyos_continuity?.nonce || ''
			};

			$.ajax({
				url: window.storyos_continuity?.ajax_url || '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						self.$issuesContainer?.empty();
						self.$summary.find('.storyos-summary-number').text('0');
						self.$clearBtn.hide();
						self.showNoIssues();
					}
				}
			});
		}

		/**
		 * Render summary cards.
		 *
		 * @param {Object} summary The summary object.
		 */
		renderSummary(summary) {
			const $errors = this.$summary.find('.storyos-card-errors .storyos-summary-number');
			const $warnings = this.$summary.find('.storyos-card-warnings .storyos-summary-number');
			const $infos = this.$summary.find('.storyos-card-infos .storyos-summary-number');
			const $total = this.$summary.find('.storyos-card-total .storyos-summary-number');

			$errors.text(summary.errors || 0);
			$warnings.text(summary.warnings || 0);
			$infos.text(summary.infos || 0);
			$total.text(summary.total || 0);

			this.$summary.show();
		}

		/**
		 * Render issues list.
		 *
		 * @param {Array} issues The issues array.
		 */
		renderIssues(issues) {
			if (!issues || issues.length === 0) {
				this.showNoIssues();
				return;
			}

			// Group by category.
			const byCategory = {};
			issues.forEach(function(issue) {
				const category = issue.category || 'general';
				if (!byCategory[category]) {
					byCategory[category] = [];
				}
				byCategory[category].push(issue);
			});

			let html = '';
			$.each(byCategory, function(category, categoryIssues) {
				html += '<div class="storyos-category-section">';
				html += '<h2>' + self.capitalizeFirst(category) + '</h2>';

				categoryIssues.forEach(function(issue) {
					html += self.renderIssueCard(issue);
				});

				html += '</div>';
			});

			this.$issuesContainer = $('#storyos-issues-container');
			if (this.$issuesContainer.length) {
				this.$issuesContainer.html(html);
			} else {
				$('.storyos-no-issues').replaceWith('<div id="storyos-issues-container">' + html + '</div>');
			}
		}

		/**
		 * Render a single issue card.
		 *
		 * @param {Object} issue The issue object.
		 * @returns {string}
		 */
		renderIssueCard(issue) {
			const severity = issue.severity || 'warning';
			const category = issue.category || 'general';
			const description = issue.description || '';
			const suggestion = issue.suggestion || '';
			const entities = issue.entities || [];

			let html = '<div class="storyos-issue-card storyos-issue-' + severity + '">';

			// Header with severity and category.
			html += '<div class="storyos-issue-header">';
			html += '<span class="storyos-issue-severity" style="background-color: ' + self.severityColor(severity) + '">' + self.capitalizeFirst(severity) + '</span>';
			html += '<span class="storyos-issue-category">' + self.capitalizeFirst(category) + '</span>';
			html += '</div>';

			// Description.
			html += '<div class="storyos-issue-description">' + self.escapeHtml(description) + '</div>';

			// Entities.
			if (entities.length > 0) {
				html += '<div class="storyos-issue-entities">';
				entities.forEach(function(entity) {
					html += '<span class="storyos-entity-tag">' + self.capitalizeFirst(entity.type || '') + ' #' + (entity.id || '') + '</span>';
				});
				html += '</div>';
			}

			// Suggestion.
			if (suggestion) {
				html += '<div class="storyos-issue-suggestion"><strong>Suggestion:</strong> ' + self.escapeHtml(suggestion) + '</div>';
			}

			html += '</div>';
			return html;
		}

		/**
		 * Show no issues message.
		 */
		showNoIssues() {
			const html = '<div class="storyos-no-issues">' +
				'<span class="dashicons dashicons-yes-alt"></span>' +
				'<p>No continuity issues found.</p>' +
				'</div>';

			this.$issuesContainer = $('#storyos-issues-container');
			if (this.$issuesContainer.length) {
				this.$issuesContainer.html(html);
			} else {
				$('.storyos-summary').after(html);
			}
		}

		/**
		 * Show error message.
		 *
		 * @param {string} message The error message.
		 */
		showError(message) {
			const html = '<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>';
			$('.storyos-actions').after(html);
			setTimeout(function() {
				$('.notice').fadeOut();
			}, 5000);
		}

		/**
		 * Get severity color.
		 *
		 * @param {string} severity The severity.
		 * @returns {string}
		 */
		severityColor(severity) {
			const colors = {
				error: '#d63638',
				warning: '#dba617',
				info: '#2271b1'
			};
			return colors[severity] || '#646970';
		}

		/**
		 * Capitalize first letter.
		 *
		 * @param {string} str The string.
		 * @returns {string}
		 */
		capitalizeFirst(str) {
			return str.charAt(0).toUpperCase() + str.slice(1);
		}

		/**
		 * Escape HTML.
		 *
		 * @param {string} str The string.
		 * @returns {string}
		 */
		escapeHtml(str) {
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	}

	// Initialize when DOM is ready.
	$(document).ready(function() {
		window.storyosContinuityPanel = new ContinuityPanel();
	});

})(jQuery);
