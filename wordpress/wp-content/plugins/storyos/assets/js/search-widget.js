/**
 * StoryOS Search Widget — Interactive search with autocomplete and entity filters.
 *
 * @package StoryOS
 */

(function($) {
	'use strict';

	/**
	 * SearchWidget class.
	 */
	class SearchWidget {
		constructor($widget) {
			this.$widget = $widget;
			this.$input = $widget.find('.storyos-search-input');
			this.$results = $widget.find('.storyos-search-results');
			this.$form = $widget.find('.storyos-search-form');
			this.$filters = $widget.find('.storyos-search-filters input[type="checkbox"]');
			this.mode = $widget.data('mode') || 'hybrid';
			this.maxResults = $widget.data('max-results') || 20;
			this.highlightedIndex = -1;
			this.searchTimeout = null;
			this.isOpen = false;

			this.init();
		}

		/**
		 * Initialize the search widget.
		 */
		init() {
			this.bindEvents();
			this.loadEntityColors();
		}

		/**
		 * Bind event listeners.
		 */
		bindEvents() {
			// Input events
			this.$input.on('input', $.proxy(this.handleInput, this));
			this.$input.on('keydown', $.proxy(this.handleKeydown, this));
			this.$input.on('focus', $.proxy(this.handleFocus, this));

			// Form submission
			this.$form.on('submit', $.proxy(this.handleSubmit, this));

			// Close on outside click
			$(document).on('click', $.proxy(this.handleOutsideClick, this));

			// Filter changes
			this.$filters.on('change', $.proxy(this.handleFilterChange, this));
		}

		/**
		 * Load entity type colors from CSS custom properties.
		 */
		loadEntityColors() {
			this.entityColors = {};
			this.$filters.each(function() {
				const $label = $(this).closest('.storyos-filter-item');
				const color = $label.css('--entity-color') || $label.attr('style')?.match(/--entity-color:\s*([^;]+)/)?.[1];
				if (color) {
					const value = color.trim();
					if (value.startsWith('#') || value.startsWith('rgb')) {
						// Extract from inline style
						const match = $label.attr('style').match(/--entity-color:\s*([^;]+)/);
						if (match) {
							window.storyosSearch.entityColors = window.storyosSearch.entityColors || {};
							window.storyosSearch.entityColors[$(this).val()] = match[1].trim();
						}
					}
				}
			});
		}

		/**
		 * Handle input event with debounced search.
		 */
		handleInput() {
			const query = this.$input.val().trim();

			if (this.searchTimeout) {
				clearTimeout(this.searchTimeout);
			}

			if (query.length < 2) {
				this.hideResults();
				return;
			}

			this.searchTimeout = setTimeout(() => {
				this.search(query);
			}, 300);
		}

		/**
		 * Handle focus event — show suggestions.
		 */
		handleFocus() {
			const query = this.$input.val().trim();
			if (query.length >= 2) {
				this.search(query);
			}
		}

		/**
		 * Handle keyboard navigation.
		 */
		handleKeydown(e) {
			const results = this.$results.find('.storyos-search-result-item');

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					this.highlightedIndex = Math.min(this.highlightedIndex + 1, results.length - 1);
					this.updateHighlight(results);
					break;

				case 'ArrowUp':
					e.preventDefault();
					this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
					this.updateHighlight(results);
					break;

				case 'Enter':
					if (this.highlightedIndex >= 0 && this.highlightedIndex < results.length) {
						e.preventDefault();
						results[this.highlightedIndex].click();
					}
					break;

				case 'Escape':
					this.hideResults();
					this.$input.blur();
					break;

				case 'Tab':
					this.hideResults();
					break;
			}
		}

		/**
		 * Update highlighted result.
		 */
		updateHighlight(results) {
			results.removeClass('highlighted');
			if (this.highlightedIndex >= 0) {
				results.eq(this.highlightedIndex).addClass('highlighted').scrollIntoView({
					block: 'nearest',
					behavior: 'smooth'
				});
			}
		}

		/**
		 * Handle form submission.
		 */
		handleSubmit(e) {
			e.preventDefault();
			const query = this.$input.val().trim();
			if (query) {
				this.search(query);
			}
		}

		/**
		 * Handle outside click.
		 */
		handleOutsideClick(e) {
			if (!this.$widget.is(e.target) && this.$widget.has(e.target).length === 0) {
				this.hideResults();
			}
		}

		/**
		 * Handle filter change.
		 */
		handleFilterChange() {
			const query = this.$input.val().trim();
			if (query.length >= 2) {
				this.search(query);
			}
		}

		/**
		 * Get selected entity types from filters.
		 */
		getSelectedEntityTypes() {
			return this.$filters.filter(':checked').map(function() {
				return $(this).val();
			}).get();
		}

		/**
		 * Perform search.
		 */
		search(query) {
			const entityTypes = this.getSelectedEntityTypes();

			this.showLoading();

			$.ajax({
				url: window.storyosSearch.search_url,
				type: 'POST',
				data: {
					query: query,
					entity_types: entityTypes,
					mode: this.mode,
					top_k: this.maxResults,
					nonce: window.storyosSearch.nonce
				},
				success: $.proxy(this.handleResponse, this),
				error: $.proxy(this.handleError, this),
				timeout: 10000
			});
		}

		/**
		 * Handle search response.
		 */
		handleResponse(response) {
			if (response.success && response.results && response.results.length > 0) {
				this.renderResults(response.results);
			} else {
				this.showNoResults();
			}
		}

		/**
		 * Handle search error.
		 */
		handleError(xhr, status, error) {
			console.error('StoryOS Search Error:', status, error);
			this.showNoResults();
		}

		/**
		 * Render search results.
		 */
		renderResults(results) {
			let html = '<div class="storyos-search-results-header">Search Results</div>';

			results.forEach((result, index) => {
				const entityType = result.entity_type || 'unknown';
				const color = this.getEntityColor(entityType);
				const icon = this.getEntityIcon(entityType);
				const score = result.score ? (result.score * 100).toFixed(1) : '';

				html += `
					<div class="storyos-search-result-item" data-index="${index}" data-url="${result.url || ''}">
						<div class="storyos-result-icon" style="background-color: ${color};">
							<span class="dashicons dashicons-${icon}"></span>
						</div>
						<div class="storyos-result-content">
							<div class="storyos-result-title">${this.escapeHtml(result.title || '')}</div>
							${result.snippet ? `<div class="storyos-result-snippet">${this.escapeHtml(result.snippet)}</div>` : ''}
						</div>
						<div class="storyos-result-meta">
							<span class="storyos-result-type">${this.escapeHtml(this.getEntityLabel(entityType))}</span>
							${score ? `<span class="storyos-result-score">${score}%</span>` : ''}
						</div>
					</div>
				`;
			});

			this.$results.html(html);
			this.showResults();

			// Bind click events to results
			this.$results.find('.storyos-search-result-item').on('click', $.proxy(this.handleResultClick, this));
		}

		/**
		 * Handle result click.
		 */
		handleResultClick(e) {
			const $item = $(e.currentTarget);
			const url = $item.data('url');
			if (url) {
				window.location.href = url;
			}
		}

		/**
		 * Get entity type color.
		 */
		getEntityColor(entityType) {
			const colors = {
				characters: '#d63384',
				scenes: '#0073aa',
				locations: '#46b450',
				shots: '#ffba00',
				props: '#722094',
				assets: '#c36d17',
				storyboard_frames: '#2563eb',
				editorial_artifacts: '#dc2626'
			};
			return colors[entityType] || '#6c757d';
		}

		/**
		 * Get entity type icon.
		 */
		getEntityIcon(entityType) {
			const icons = {
				characters: 'admin-users',
				scenes: 'format-image',
				locations: 'admin-location',
				shots: 'format-video',
				props: 'admin-collapse',
				assets: 'admin-appearance',
				storyboard_frames: 'slides',
				editorial_artifacts: 'admin-tools'
			};
			return icons[entityType] || 'admin-generic';
		}

		/**
		 * Get entity type label.
		 */
		getEntityLabel(entityType) {
			const labels = {
				characters: 'Character',
				scenes: 'Scene',
				locations: 'Location',
				shots: 'Shot',
				props: 'Prop',
				assets: 'Asset',
				storyboard_frames: 'Storyboard',
				editorial_artifacts: 'Editorial'
			};
			return labels[entityType] || entityType;
		}

		/**
		 * Show loading state.
		 */
		showLoading() {
			this.$results.html(`
				<div class="storyos-search-loading">
					<span class="dashicons dashicons-admin-generic"></span> Searching...
				</div>
			`).show();
		}

		/**
		 * Show no results message.
		 */
		showNoResults() {
			this.$results.html(`
				<div class="storyos-search-no-results">
					<span class="dashicons dashicons-editor-help"></span>
					No results found
				</div>
			`).show();
		}

		/**
		 * Show results dropdown.
		 */
		showResults() {
			this.$results.show();
			this.isOpen = true;
		}

		/**
		 * Hide results dropdown.
		 */
		hideResults() {
			this.$results.hide();
			this.highlightedIndex = -1;
			this.isOpen = false;
		}

		/**
		 * Escape HTML entities.
		 */
		escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, m => map[m]);
		}
	}

	/**
	 * Initialize all StoryOS search widgets on page.
	 */
	$(document).ready(function() {
		$('.storyos-search-widget').each(function() {
			new SearchWidget($(this));
		});
	});

	/**
	 * Admin bar search integration.
	 */
	$(document).on('click', '.storyos-admin-search-trigger', function(e) {
		e.preventDefault();
		const query = prompt('Search StoryOS entities:');
		if (query && query.trim()) {
			window.location.href = '/wp-admin/edit.php?s=' + encodeURIComponent(query.trim());
		}
	});

	/**
	 * Entity type filter clicks in admin bar.
	 */
	$(document).on('click', '.storyos-search-entity-filter', function(e) {
		e.preventDefault();
		const entityType = $(this).data('entity');
		if (entityType) {
			window.location.href = '/wp-admin/edit.php?post_type=storyos_' + entityType;
		}
	});

})(jQuery);
