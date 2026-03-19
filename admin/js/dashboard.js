/* global Chart, wpspData */
(function () {
	'use strict';

	/* ---------- Helpers ---------- */

	var COLORS = {
		page: ['#2271b1', '#d63638'],
		db_select: '#2271b1',
		db_insert: '#00a32a',
		db_update: '#dba617',
		db_delete: '#d63638',
	};

	var OP_LABELS = {
		db_select: 'SELECT',
		db_insert: 'INSERT',
		db_update: 'UPDATE',
		db_delete: 'DELETE',
	};

	/**
	 * Sample data points to keep charts performant.
	 * For 7d view we thin out to ~200 points per series.
	 */
	function thinData(points, maxPoints) {
		if (points.length <= maxPoints) {
			return points;
		}
		var step = Math.ceil(points.length / maxPoints);
		var result = [];
		for (var i = 0; i < points.length; i += step) {
			result.push(points[i]);
		}
		return result;
	}

	/* ---------- Page Response Time Chart ---------- */

	var pageCtx = document.getElementById('wpsp-page-chart');
	var pageChart = null;

	function buildPageChart(range) {
		var data = range === '7d' ? wpspData.page7d : wpspData.page24h;
		var urls = Object.keys(data);
		var datasets = [];

		urls.forEach(function (url, idx) {
			var points = range === '7d' ? thinData(data[url], 200) : data[url];
			var label = url.length > 40 ? url.substring(0, 40) + '...' : url;
			datasets.push({
				label: label,
				data: points,
				borderColor: COLORS.page[idx % COLORS.page.length],
				backgroundColor: 'transparent',
				borderWidth: 1.5,
				pointRadius: 0,
				pointHitRadius: 6,
				tension: 0.3,
			});
		});

		// Threshold line.
		if (datasets.length > 0 && datasets[0].data.length > 0) {
			var first = datasets[0].data[0].x;
			var last = datasets[0].data[datasets[0].data.length - 1].x;
			datasets.push({
				label: wpspData.i18n.threshold + ' (' + wpspData.thresholds.page + 'ms)',
				data: [
					{ x: first, y: wpspData.thresholds.page },
					{ x: last, y: wpspData.thresholds.page },
				],
				borderColor: '#d63638',
				borderDash: [6, 4],
				borderWidth: 1.5,
				pointRadius: 0,
				fill: false,
			});
		}

		var config = {
			type: 'line',
			data: { datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				scales: {
					x: {
						type: 'time',
						time: {
							unit: range === '7d' ? 'day' : 'hour',
							displayFormats: { hour: 'HH:mm', day: 'MM/dd' },
						},
						grid: { display: false },
					},
					y: {
						beginAtZero: true,
						title: { display: true, text: wpspData.i18n.responseTime },
					},
				},
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function (ctx) {
								return ctx.dataset.label + ': ' + (ctx.parsed.y !== null ? ctx.parsed.y + 'ms' : 'N/A');
							},
						},
					},
				},
			},
		};

		if (pageChart) {
			pageChart.destroy();
		}
		pageChart = new Chart(pageCtx, config);

		// Update percentiles display.
		renderPercentiles('wpsp-page-percentiles', wpspData.pagePercentiles);
	}

	/* ---------- DB Performance Chart ---------- */

	var dbCtx = document.getElementById('wpsp-db-chart');
	var dbChart = null;

	function buildDbChart(range) {
		var data = range === '7d' ? wpspData.db7d : wpspData.db24h;
		var ops = Object.keys(data);
		var datasets = [];

		ops.forEach(function (op) {
			var points = range === '7d' ? thinData(data[op], 200) : data[op];
			datasets.push({
				label: OP_LABELS[op] || op,
				data: points,
				borderColor: COLORS[op] || '#50575e',
				backgroundColor: 'transparent',
				borderWidth: 1.5,
				pointRadius: 0,
				pointHitRadius: 6,
				tension: 0.3,
			});
		});

		// Threshold line.
		if (datasets.length > 0 && datasets[0].data.length > 0) {
			var first = datasets[0].data[0].x;
			var last = datasets[0].data[datasets[0].data.length - 1].x;
			datasets.push({
				label: wpspData.i18n.threshold + ' (' + wpspData.thresholds.db + 'ms)',
				data: [
					{ x: first, y: wpspData.thresholds.db },
					{ x: last, y: wpspData.thresholds.db },
				],
				borderColor: '#d63638',
				borderDash: [6, 4],
				borderWidth: 1.5,
				pointRadius: 0,
				fill: false,
			});
		}

		var config = {
			type: 'line',
			data: { datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				scales: {
					x: {
						type: 'time',
						time: {
							unit: range === '7d' ? 'day' : 'hour',
							displayFormats: { hour: 'HH:mm', day: 'MM/dd' },
						},
						grid: { display: false },
					},
					y: {
						beginAtZero: true,
						title: { display: true, text: wpspData.i18n.execTime },
					},
				},
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function (ctx) {
								return ctx.dataset.label + ': ' + (ctx.parsed.y !== null ? ctx.parsed.y + 'ms' : 'N/A');
							},
						},
					},
				},
			},
		};

		if (dbChart) {
			dbChart.destroy();
		}
		dbChart = new Chart(dbCtx, config);

		// Update percentiles display.
		renderPercentiles('wpsp-db-percentiles', wpspData.dbPercentiles);
	}

	/* ---------- Query Statistics Pie Chart ---------- */

	function buildQueryPie() {
		var ctx = document.getElementById('wpsp-query-pie');
		if (!ctx) {
			return;
		}
		var stats = wpspData.queryStats;

		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
				datasets: [
					{
						data: [stats.select, stats.insert, stats.update, stats.delete],
						backgroundColor: [COLORS.db_select, COLORS.db_insert, COLORS.db_update, COLORS.db_delete],
						borderWidth: 2,
						borderColor: '#fff',
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				cutout: '55%',
				plugins: {
					legend: { display: false },
				},
			},
		});
	}

	/* ---------- Percentiles Display ---------- */

	function renderPercentiles(containerId, data) {
		var el = document.getElementById(containerId);
		if (!el) {
			return;
		}
		var html = '';
		Object.keys(data).forEach(function (key) {
			var label = key.length > 30 ? key.substring(0, 30) + '...' : key;
			if (OP_LABELS[key]) {
				label = OP_LABELS[key];
			}
			html += label + ': <span>p50 ' + (data[key].p50 || '-') + 'ms</span>';
			html += ' / <span>p95 ' + (data[key].p95 || '-') + 'ms</span>';
			html += '&nbsp;&nbsp;&nbsp;';
		});
		el.innerHTML = html;
	}

	/* ---------- Range Toggle ---------- */

	function setupRangeToggles() {
		var toggles = document.querySelectorAll('.wpsp-range-toggle');
		toggles.forEach(function (toggle) {
			var chart = toggle.getAttribute('data-chart');
			var buttons = toggle.querySelectorAll('.wpsp-range-btn');

			buttons.forEach(function (btn) {
				btn.addEventListener('click', function () {
					buttons.forEach(function (b) {
						b.classList.remove('wpsp-range-btn--active');
					});
					btn.classList.add('wpsp-range-btn--active');

					var range = btn.getAttribute('data-range');
					if (chart === 'page') {
						buildPageChart(range);
					} else if (chart === 'db') {
						buildDbChart(range);
					}
				});
			});
		});
	}

	/* ---------- Chart.js date adapter ---------- */

	/**
	 * Minimal time scale adapter.
	 * Chart.js v4 needs an adapter for time scales.
	 * We load the bundled one via import map when available,
	 * otherwise fall back to a simple linear scale.
	 */
	function initCharts() {
		// Check if time scale is available (requires date adapter).
		// If not, we fall back to category scale using formatted labels.
		var hasTimeScale = Chart.registry && Chart.registry.getScale && Chart.registry.getScale('time');

		if (!hasTimeScale) {
			// Patch data to use category scale.
			patchForCategoryScale();
		}

		buildPageChart('24h');
		buildDbChart('24h');
		buildQueryPie();
		setupRangeToggles();
	}

	/**
	 * Convert {x: datetime, y: val} to plain arrays for category scale.
	 */
	function patchForCategoryScale() {
		['page24h', 'page7d', 'db24h', 'db7d'].forEach(function (key) {
			var data = wpspData[key];
			Object.keys(data).forEach(function (series) {
				data[series] = data[series].map(function (pt) {
					var d = new Date(pt.x);
					return {
						x: (d.getMonth() + 1) + '/' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'),
						y: pt.y,
					};
				});
			});
		});
	}

	/* ---------- Boot ---------- */

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCharts);
	} else {
		initCharts();
	}
})();
