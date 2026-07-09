define([
	'jquery'
], function ($) {
	'use strict';

	let DEFAULT_OPTIONS = {
		inputSelector: '#search-input-autocomplate, #search, input[name="q"]',
		panelSelector: '#search_autocomplete, .mst-searchautocomplete__autocomplete',
		mirasvitPanelSelector: '.mst-searchautocomplete__autocomplete',
		resultsRootSelector: '.searchsuite-autocomplete, .mst-searchautocomplete__autocomplete',
		fallbackEndpoint: '',
		searchResultUrl: '/catalogsearch/result/',
		minQueryLength: 2,
		fallbackDelay: 260,
		fallbackTimeout: 8000,
		fallbackSuggestLimit: 6,
		fallbackProductLimit: 6
	};
	let AUTO_BOOT_KEY = '__awaSearchCompatAutoBoot';
	let AUTO_OBSERVER_KEY = '__awaSearchCompatAutoObserver';
	let SEARCH_FORM_SELECTOR = 'form.form.minisearch, #search_mini_form';

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function toText(value) {
		return $('<div/>').html(value || '').text();
	}

	function isB2bRestrictedMode() {
		return !!(document.body && document.body.classList.contains('b2b-restricted-mode'));
	}

	function findScoped($form, selector) {
		var $scope;
		var $found;

		if (!$form || !$form.length) {
			return $(selector).first();
		}

		$found = $form.find(selector).first();
		if ($found.length) {
			return $found;
		}

		$scope = $form.closest('.block-search, .header .search, .top-search');
		if ($scope.length) {
			$found = $scope.find(selector).first();
			if ($found.length) {
				return $found;
			}
		}

		return $(selector).first();
	}

	function visible(el) {
		if (!el) {
			return false;
		}

		return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
	}

	function panelHasContent($panel) {
		if (!$panel || !$panel.length) {
			return false;
		}

		return $panel.find([
			'li',
			'.mst-searchautocomplete__item',
			'.awa-fallback-item',
			'.qs-option-info',
			'.suggest a',
			'.product a',
			'.no-result'
		].join(',')).length > 0;
	}

	function isSearchFocused($form, $input) {
		if ($form.is(':focus-within')) {
			return true;
		}

		if (!$input || !$input.length) {
			return false;
		}

		return $input.get(0) === document.activeElement;
	}

	function setPanelAriaHidden($panel, value) {
		if (!$panel || !$panel.length) {
			return;
		}

		if ($panel.attr('aria-hidden') !== value) {
			$panel.attr('aria-hidden', value);
		}
	}

	function setInputAriaExpanded($input, value) {
		if (!$input || !$input.length) {
			return;
		}

		if ($input.attr('aria-expanded') !== value) {
			$input.attr('aria-expanded', value);
		}
	}

	function promotePanelOpen($form, $panel, $input) {
		if (!panelHasContent($panel) || !isSearchFocused($form, $input)) {
			return false;
		}

		$form.addClass('is-open').removeClass('is-empty');
		$panel.addClass('is-open active has-results');
		setPanelAriaHidden($panel, 'false');
		$panel.removeAttr('hidden');

		if ($input.length) {
			setInputAriaExpanded($input, 'true');
		}

		if (document.body) {
			document.body.classList.add('searchautocomplete__active');
		}

		return true;
	}

	function demotePanelClosed($form, $panel, $input) {
		$form.removeClass('is-open has-results').addClass('is-empty');
		$panel.removeClass('is-open active has-results');
		setPanelAriaHidden($panel, 'true');

		if ($input.length) {
			setInputAriaExpanded($input, 'false');
		}

		if (document.body) {
			document.body.classList.remove('searchautocomplete__active');
		}
	}

	function applyTitles($root) {
		$root.find('a[href]').each(function () {
			var $a = $(this);
			let text = $.trim($a.text());

			if (!text) {
				return;
			}

			if (!$a.attr('title')) {
				$a.attr('title', text);
			}

			if (!$a.attr('aria-label')) {
				$a.attr('aria-label', text);
			}
		});
	}

	function syncState($form, options) {
		var $input = findScoped($form, options.inputSelector);
		var $panel = resolveActivePanel($form, options);
		let panelEl = $panel.get(0);
		var $resultsRoot = findScoped($form, options.resultsRootSelector);
		let hasPanel = $panel.length > 0;
		let isVisible = hasPanel && visible(panelEl);
		let hasResults = false;

		if (isMirasvitPanelOpen($form, options)) {
			isVisible = true;
			hasResults = $panel.find('.mst-searchautocomplete__item, li').length > 0 ||
				$.trim($panel.text()).length > 0;
			if ($input.length) {
				setInputAriaExpanded($input, 'true');
				$input.attr('aria-controls', $panel.attr('id') || 'search_autocomplete');
			}
			setPanelAriaHidden($panel, 'false');
		} else if (hasPanel && panelHasContent($panel) && isSearchFocused($form, $input)) {
			promotePanelOpen($form, $panel, $input);
			isVisible = true;
		}

		if ($resultsRoot.length && visible($resultsRoot.get(0))) {
			hasResults = $resultsRoot.find('li').length > 0;
		} else if (hasPanel) {
			hasResults = $.trim($panel.text()).length > 0 || $panel.children().length > 0;
		}

		$form.toggleClass('is-open', !!isVisible)
			.toggleClass('has-results', !!hasResults)
			.toggleClass('is-empty', !hasResults);

		if ($input.length) {
			setInputAriaExpanded($input, isVisible ? 'true' : 'false');
		}

		if (hasPanel) {
			setPanelAriaHidden($panel, isVisible ? 'false' : 'true');
			$panel.toggleClass('is-open', !!isVisible)
				.toggleClass('has-results', !!hasResults);

			if (isVisible) {
				$panel.removeAttr('hidden');
			} else if (!panelHasContent($panel)) {
				$panel.attr('hidden', 'hidden');
			}
		}

		if (!isVisible && !isSearchFocused($form, $input)) {
			if (document.body) {
				document.body.classList.remove('searchautocomplete__active');
			}
		}

		if ($resultsRoot.length) {
			$resultsRoot.attr('data-awa-component', 'search-results')
				.toggleClass('is-open', !!isVisible)
				.toggleClass('has-results', !!hasResults);

			$resultsRoot.find('ul').attr('role', 'listbox');
			$resultsRoot.find('li').attr('role', 'option');
			applyTitles($resultsRoot);
		}
	}

	function getFallbackPanel($form, options) {
		var $panel = findScoped($form, options.panelSelector);

		if ($panel.length) {
			return $panel;
		}

		return findScoped($form, options.resultsRootSelector);
	}

	function getMirasvitPanel($form, options) {
		return findScoped($form, options.mirasvitPanelSelector || '.mst-searchautocomplete__autocomplete');
	}

	function isMirasvitPanelOpen($form, options) {
		var $mirasvit = getMirasvitPanel($form, options);
		var el;

		if (!$mirasvit.length) {
			return false;
		}

		el = $mirasvit.get(0);
		if ($mirasvit.hasClass('_active') || $mirasvit.hasClass('is-open')) {
			return visible(el) || $mirasvit.find('.mst-searchautocomplete__item, li').length > 0;
		}

		return visible(el) && $mirasvit.find('.mst-searchautocomplete__item, li').length > 0;
	}

	function resolveActivePanel($form, options) {
		var $mirasvit = getMirasvitPanel($form, options);

		if (isMirasvitPanelOpen($form, options)) {
			return $mirasvit;
		}

		return findScoped($form, options.panelSelector);
	}

	function hasNativeResults($form, options) {
		var $resultsRoot = findScoped($form, options.resultsRootSelector);
		var $panel = getFallbackPanel($form, options);
		var $nativeItems = $();

		if (isMirasvitPanelOpen($form, options)) {
			return true;
		}

		if ($resultsRoot.length) {
			$nativeItems = $nativeItems.add($resultsRoot.find('li').not('.awa-fallback-item'));
		}

		if ($panel.length) {
			$nativeItems = $nativeItems.add($panel.find('li').not('.awa-fallback-item'));
		}

		return $nativeItems.length > 0;
	}

	function extractSuggestItems(suggestRaw) {
		let suggest = [];

		$.each(suggestRaw || [], function (_, item) {
			let label = '';
			let url = '';

			if (typeof item === 'string') {
				label = item;
			} else if (item && typeof item === 'object') {
				label = item.query_text || item.label || item.value || item.name || item.title || '';
				url = item.url || '';
			}

			label = $.trim(toText(label));
			url = $.trim(url);

			if (label) {
				suggest.push({
					label: label,
					url: url
				});
			}
		});

		return suggest;
	}

	function extractProductItems(productRaw) {
		let products = [];
		let hidePrice = isB2bRestrictedMode();

		$.each(productRaw || [], function (_, item) {
			if (!item || typeof item !== 'object') {
				return;
			}

			products.push({
				name: $.trim(toText(item.name || item.title || '')),
				url: $.trim(item.url || '#'),
				image: $.trim(item.image || item.imageUrl || ''),
				priceText: hidePrice ? '' : $.trim(toText(item.price || item.priceText || '')),
				sku: $.trim(toText(item.sku || '')),
				fitment: $.trim(toText(item.fitment || ''))
			});
		});

		return products;
	}

	function normalizeFallbackPayload(payload) {
		let suggest = [];
		let products = [];

		if ($.isArray(payload)) {
			suggest = suggest.concat(extractSuggestItems(payload));
		}

		if (payload && $.isArray(payload.result)) {
			$.each(payload.result, function (_, chunk) {
				if (!chunk || typeof chunk !== 'object') {
					return;
				}

				if (chunk.code === 'suggest') {
					suggest = suggest.concat(extractSuggestItems(chunk.data));
					return;
				}

				if (chunk.code === 'product') {
					products = products.concat(extractProductItems(chunk.data));
				}
			});
		}

		if (payload && $.isArray(payload.indexes)) {
			$.each(payload.indexes, function (_, index) {
				if (!index || typeof index !== 'object') {
					return;
				}

				if (index.identifier === 'magento_search_query') {
					suggest = suggest.concat(extractSuggestItems(index.items));
					return;
				}

				if (index.identifier === 'magento_catalog_product') {
					products = products.concat(extractProductItems(index.items));
				}
			});
		}

		return {
			suggest: suggest,
			products: products
		};
	}

	function buildFallbackMarkup(normalized, options, query) {
		let html = '';
		let i;
		let suggestion;
		let suggestionHref;
		let product;
		let searchResultUrl = (options.searchResultUrl || '/catalogsearch/result/').replace(/\/+$/, '');

		if (normalized.suggest.length) {
			html += '<div class="suggest">';
			html += '<ul role="listbox">';

			for (i = 0; i < normalized.suggest.length && i < options.fallbackSuggestLimit; i += 1) {
				suggestion = normalized.suggest[i];
				suggestionHref = suggestion.url || (searchResultUrl + '/?q=' + encodeURIComponent(suggestion.label));

				html += '<li class="awa-fallback-item" role="option">';
				html += '<a href="' + escapeHtml(suggestionHref) + '">' + escapeHtml(suggestion.label) + '</a>';
				html += '</li>';
			}

			html += '</ul>';
			html += '</div>';
		}

		if (normalized.products.length) {
			html += '<div class="product">';
			html += '<ul role="listbox">';

			for (i = 0; i < normalized.products.length && i < options.fallbackProductLimit; i += 1) {
				product = normalized.products[i];
				html += '<li class="awa-fallback-item" role="option">';

				if (product.image) {
					html += '<div class="qs-option-image">';
					html += '<a href="' + escapeHtml(product.url || '#') + '">';
					html += '<img src="' + escapeHtml(product.image) + '" alt="' + escapeHtml(product.name || query) + '" loading="lazy" />';
					html += '</a>';
					html += '</div>';
				}

				html += '<div class="qs-option-info">';
				html += '<div class="qs-option-title"><a href="' + escapeHtml(product.url || '#') + '">' + escapeHtml(product.name || query) + '</a></div>';

				if (product.sku) {
					html += '<div class="awa-ac-product-sku">SKU: <span>' + escapeHtml(product.sku) + '</span></div>';
				}

				if (product.fitment) {
					html += '<div class="awa-ac-product-fitment" title="Compatibilidade" aria-label="Compatibilidade do produto">' + escapeHtml(product.fitment) + '</div>';
				}

				if (product.priceText) {
					html += '<div class="qs-option-price">' + escapeHtml(product.priceText) + '</div>';
				}

				html += '</div>';
				html += '</li>';
			}

			html += '</ul>';
			html += '</div>';
		}

		if (!html) {
			html = '<div class="no-result">Nenhum resultado encontrado.</div>';
		}

		return html;
	}

	function clearFallback($form, options) {
		var $panel = getFallbackPanel($form, options);
		var $input = findScoped($form, options.inputSelector);

		if (!$panel.length) {
			return;
		}

		if ($panel.attr('data-awa-fallback-rendered') === 'true') {
			$panel.empty();
			$panel.removeAttr('data-awa-fallback-rendered');
			$panel.hide();
			demotePanelClosed($form, $panel, $input);
		}
	}

	function renderFallback($form, options, payload, query) {
		var $panel = findScoped($form, '#search_autocomplete');
		var $input = findScoped($form, options.inputSelector);
		let normalized;
		let html;

		if (!$panel.length) {
			return;
		}

		normalized = normalizeFallbackPayload(payload);
		html = buildFallbackMarkup(normalized, options, query);

		$panel.html(html)
			.show()
			.attr('data-awa-fallback-rendered', 'true');

		promotePanelOpen($form, $panel, $input);
		applyTitles($panel);
	}

	function resolveFallbackEndpoint($form, options) {
		let explicit = options.fallbackEndpoint || '';
		let attrEndpoint = $form.attr('data-awa-search-endpoint') || '';

		if (explicit) {
			return explicit;
		}

		if (attrEndpoint) {
			return attrEndpoint;
		}

		return '/search/ajax/suggest';
	}

	function buildCacheKey(query, categoryValue) {
		return query + '::' + (categoryValue || '');
	}

	function runFallbackRequest($form, options, state, query) {
		let endpoint = resolveFallbackEndpoint($form, options);
		var $category = $form.find('#choose_category');
		let categoryValue = $category.length ? $.trim($category.val() || '') : '';
		let cacheKey = buildCacheKey(query, categoryValue);
		let params = {
			q: query
		};

		if (!endpoint || hasNativeResults($form, options)) {
			clearFallback($form, options);
			return;
		}

		if (categoryValue) {
			params.cat = categoryValue;
		}

		if (state.xhr && state.xhr.abort) {
			state.xhr.abort();
		}

		state.requestId += 1;
		state.lastRequestId = state.requestId;

		state.xhr = $.ajax({
			url: endpoint,
			method: 'GET',
			dataType: 'json',
			data: params,
			cache: false,
			timeout: options.fallbackTimeout
		}).done(function (response) {
			if (state.lastRequestId !== state.requestId) {
				return;
			}

			if (!query || query.length < options.minQueryLength || hasNativeResults($form, options)) {
				clearFallback($form, options);
				return;
			}

			state.cache[cacheKey] = response;
			renderFallback($form, options, response, query);
			syncState($form, options);
		}).fail(function (_xhr, status) {
			if (status === 'abort') {
				return;
			}

			clearFallback($form, options);
			syncState($form, options);
		}).always(function () {
			state.xhr = null;
		});
	}

	function scheduleFallbackRequest($form, options, state, query) {
		var $category = $form.find('#choose_category');
		let categoryValue = $category.length ? $.trim($category.val() || '') : '';
		let cacheKey = buildCacheKey(query, categoryValue);

		if (state.timer) {
			window.clearTimeout(state.timer);
		}

		if (!query || query.length < options.minQueryLength) {
			clearFallback($form, options);
			return;
		}

		if (state.cache[cacheKey]) {
			if (!hasNativeResults($form, options)) {
				renderFallback($form, options, state.cache[cacheKey], query);
				syncState($form, options);
			}
			return;
		}

		state.timer = window.setTimeout(function () {
			runFallbackRequest($form, options, state, query);
		}, options.fallbackDelay);
	}

	function initCompat(config, element) {
		let options = $.extend({}, DEFAULT_OPTIONS, config || {});
		var $form = $(element);
		let observer;
		let bodyObserver;
		let panelNode;
		let scheduled = false;
		let scopeNode;
		let fallbackState = {
			timer: null,
			xhr: null,
			cache: {},
			requestId: 0,
			lastRequestId: 0
		};

		if (!$form.length || $form.data('awaSearchCompatInit')) {
			return;
		}

		function flushSync() {
			scheduled = false;
			attachPanelObserver();
			syncState($form, options);
		}

		function scheduleSync() {
			if (scheduled) {
				return;
			}

			scheduled = true;

			if (typeof window.requestAnimationFrame === 'function') {
				window.requestAnimationFrame(flushSync);
				return;
			}

			window.setTimeout(flushSync, 0);
		}

		function attachPanelObserver() {
			let nextPanelNode;

			if (!observer) {
				return false;
			}

			nextPanelNode = resolveActivePanel($form, options).get(0) ||
				findScoped($form, options.mirasvitPanelSelector).get(0) ||
				findScoped($form, '#search_autocomplete').get(0);
			if (!nextPanelNode) {
				return false;
			}

			if (panelNode === nextPanelNode) {
				return true;
			}

			observer.disconnect();
			panelNode = nextPanelNode;
			observer.observe(panelNode, {
				subtree: true,
				childList: true,
				attributes: true,
				attributeFilter: ['class', 'style']
			});

			return true;
		}

		$form.attr({
			'data-awa-component': $form.attr('data-awa-component') || 'search-autocomplete',
			'data-awa-initialized': 'true'
		}).addClass('is-ready');

		scheduleSync();

		$form.on('focusin.awaSearchCompat input.awaSearchCompat keyup.awaSearchCompat', options.inputSelector, function (event) {
			let query = $.trim($(this).val() || '');

			if (event.type === 'keyup' && event.key === 'Escape') {
				clearFallback($form, options);
				demotePanelClosed($form, findScoped($form, options.panelSelector), $(this));
			}

			if (event.type === 'input' || event.type === 'keyup' || event.type === 'focusin') {
				scheduleFallbackRequest($form, options, fallbackState, query);
			}

			scheduleSync();
		});

		$form.on('focusout.awaSearchCompat', options.inputSelector, function () {
			window.setTimeout(function () {
				scheduleSync();
			}, 100);
		});

		$form.on('change.awaSearchCompat', '#choose_category', function () {
			var $input = findScoped($form, options.inputSelector);
			let query = $.trim($input.val() || '');
			scheduleFallbackRequest($form, options, fallbackState, query);
			scheduleSync();
		});

		if (typeof window.MutationObserver === 'function') {
			observer = new window.MutationObserver(function () {
				scheduleSync();
			});

			attachPanelObserver();
			scopeNode = $form.closest('.block-search, .header .search, .top-search').get(0) || document.body;

			if (!panelNode && scopeNode) {
				bodyObserver = new window.MutationObserver(function () {
					attachPanelObserver();
					scheduleSync();
				});

				bodyObserver.observe(scopeNode, {
					subtree: true,
					childList: true
				});
			}

			$form.data('awaSearchCompatObserver', observer);
			if (bodyObserver) {
				$form.data('awaSearchCompatBodyObserver', bodyObserver);
			}
		}

		$form.data('awaSearchCompatInit', 1);
		$form.attr('data-awa-search-compat-init', '1');
	}

	function bootAll(config) {
		let options = $.extend({}, DEFAULT_OPTIONS, config || {});

		$(SEARCH_FORM_SELECTOR).each(function () {
			initCompat(options, this);
		});
	}

	function shouldObserveMutation(mutations) {
		let i;
		let j;
		let mutation;
		let addedNodes;

		for (i = 0; i < mutations.length; i += 1) {
			mutation = mutations[i];
			if (!mutation || !mutation.addedNodes || !mutation.addedNodes.length) {
				continue;
			}

			addedNodes = mutation.addedNodes;
			for (j = 0; j < addedNodes.length; j += 1) {
				if (!addedNodes[j] || addedNodes[j].nodeType !== 1) {
					continue;
				}

				if ($(addedNodes[j]).is(SEARCH_FORM_SELECTOR) || $(addedNodes[j]).find(SEARCH_FORM_SELECTOR).length) {
					return true;
				}
			}
		}

		return false;
	}

	function autoBoot() {
		if (window[AUTO_BOOT_KEY]) {
			return;
		}

		window[AUTO_BOOT_KEY] = true;

		if (document.readyState === 'loading') {
			$(function () {
				bootAll({});
			});
		} else {
			bootAll({});
		}

		$(document).on('contentUpdated.awaSearchCompatAuto', function (event) {
			if (!event || !event.target || $(event.target).is(SEARCH_FORM_SELECTOR) || $(event.target).find(SEARCH_FORM_SELECTOR).length) {
				bootAll({});
			}
		});

		let headerScope = document.querySelector(
			'.awa-site-header, #header.header-container, .page-header, header.page-header'
		);

		if (window.MutationObserver && headerScope && !window[AUTO_OBSERVER_KEY]) {
			window[AUTO_OBSERVER_KEY] = new window.MutationObserver(function (mutations) {
				if (!shouldObserveMutation(mutations)) {
					return;
				}

				bootAll({});

				let $forms = $(SEARCH_FORM_SELECTOR);
				if ($forms.length && $forms.filter('[data-awa-search-compat-init="1"]').length >= $forms.length) {
					window[AUTO_OBSERVER_KEY].disconnect();
					window[AUTO_OBSERVER_KEY] = null;
				}
			});

			window[AUTO_OBSERVER_KEY].observe(headerScope, {
				childList: true,
				subtree: true
			});
		}
	}

	autoBoot();

	return function (config, element) {
		initCompat(config, element);
	};
});
