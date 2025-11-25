/**
 * Unified Dashboard JavaScript
 * Handles charts, real-time updates, and interactive features
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

(function($) {
	'use strict';

	let charts = {};
	let refreshInterval;

	/**
	 * Initialize the dashboard
	 */
	function init() {
		initializeCharts();
		bindEvents();
		startAutoRefresh();
		loadActivityFeed();
		loadTopPerformers();
	}

	/**
	 * Initialize all Chart.js charts
	 */
	function initializeCharts() {
		// Timeline Chart
		initTimelineChart();
		
		// Attribution Pie Chart
		initAttributionChart();
		
		// Funnel Chart
		initFunnelChart();
		
		// Comparison Bar Chart
		initComparisonChart();
	}

	/**
	 * Initialize multi-channel timeline chart
	 */
	function initTimelineChart() {
		const ctx = document.getElementById('f8m-timeline-chart');
		if (!ctx) return;

		// Generate sample data for last 7 days
		const labels = getLast7Days();
		
		charts.timeline = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Email Opens',
						data: [120, 190, 150, 170, 180, 210, 195],
						borderColor: '#2563eb',
						backgroundColor: 'rgba(37, 99, 235, 0.1)',
						tension: 0.4,
						fill: true
					},
					{
						label: 'WhatsApp Messages',
						data: [180, 210, 195, 220, 230, 250, 240],
						borderColor: '#10b981',
						backgroundColor: 'rgba(16, 185, 129, 0.1)',
						tension: 0.4,
						fill: true
					},
					{
						label: 'Web Sessions',
						data: [450, 520, 480, 550, 580, 620, 600],
						borderColor: '#f59e0b',
						backgroundColor: 'rgba(245, 158, 11, 0.1)',
						tension: 0.4,
						fill: true
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					mode: 'index',
					intersect: false,
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: '#1e293b',
						padding: 12,
						titleColor: '#fff',
						bodyColor: '#fff',
						borderColor: '#334155',
						borderWidth: 1
					}
				},
				scales: {
					x: {
						grid: {
							display: false
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: '#f3f4f6'
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize attribution pie chart
	 */
	function initAttributionChart() {
		const ctx = document.getElementById('f8m-attribution-chart');
		if (!ctx) return;

		charts.attribution = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Email', 'WhatsApp', 'Web'],
				datasets: [{
					data: [35, 40, 25],
					backgroundColor: [
						'#2563eb',
						'#10b981',
						'#f59e0b'
					],
					borderWidth: 2,
					borderColor: '#fff'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								return context.label + ': ' + context.parsed + '%';
							}
						}
					}
				},
				cutout: '60%'
			}
		});
	}

	/**
	 * Initialize conversion funnel chart
	 */
	function initFunnelChart() {
		const ctx = document.getElementById('f8m-funnel-chart');
		if (!ctx) return;

		charts.funnel = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: ['Impressions', 'Clicks', 'Visits', 'Leads', 'Conversions'],
				datasets: [{
					label: 'Count',
					data: [10000, 3500, 2800, 850, 420],
					backgroundColor: [
						'#3b82f6',
						'#2563eb',
						'#1d4ed8',
						'#1e40af',
						'#1e3a8a'
					],
					borderRadius: 4
				}]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						callbacks: {
							afterLabel: function(context) {
								if (context.dataIndex === 0) return '';
								const prevValue = context.dataset.data[context.dataIndex - 1];
								const conversion = ((context.parsed.x / prevValue) * 100).toFixed(1);
								return 'Conversion: ' + conversion + '%';
							}
						}
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						grid: {
							color: '#f3f4f6'
						}
					},
					y: {
						grid: {
							display: false
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize campaign comparison chart
	 */
	function initComparisonChart() {
		const ctx = document.getElementById('f8m-comparison-chart');
		if (!ctx) return;

		charts.comparison = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: ['Email Campaign 1', 'WhatsApp Promo', 'Web Banner A', 'Email Campaign 2', 'WhatsApp Update'],
				datasets: [{
					label: 'Engagement Rate',
					data: [45, 62, 38, 52, 58],
					backgroundColor: '#2563eb',
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					}
				},
				scales: {
					x: {
						grid: {
							display: false
						}
					},
					y: {
						beginAtZero: true,
						max: 100,
						grid: {
							color: '#f3f4f6'
						},
						ticks: {
							callback: function(value) {
								return value + '%';
							}
						}
					}
				}
			}
		});
	}

	/**
	 * Bind event handlers
	 */
	function bindEvents() {
		// Refresh button
		$('#f8m-refresh-data').on('click', refreshData);

		// Export button
		$('#f8m-export-data').on('click', exportData);

		// Date range selector
		$('#f8m-date-range').on('change', function() {
			refreshData();
		});

		// Attribution model selector
		$('#f8m-attribution-model').on('change', function() {
			updateAttributionChart($(this).val());
		});

		// Activity feed filters
		$('.f8m-filter-btn').on('click', function() {
			$('.f8m-filter-btn').removeClass('active');
			$(this).addClass('active');
			filterActivityFeed($(this).data('channel'));
		});
	}

	/**
	 * Start auto-refresh interval
	 */
	function startAutoRefresh() {
		if (f8mUnified && f8mUnified.refreshRate) {
			refreshInterval = setInterval(function() {
				refreshData();
			}, f8mUnified.refreshRate);
		}
	}

	/**
	 * Refresh dashboard data
	 */
	function refreshData() {
		const dateRange = $('#f8m-date-range').val();
		
		// Show loading state
		$('#f8m-refresh-data').prop('disabled', true).addClass('updating-message');

		// Fetch unified analytics data
		$.ajax({
			url: f8mUnified.apiUrl,
			method: 'GET',
			data: {
				date_range: dateRange
			},
			headers: {
				'X-WP-Nonce': f8mUnified.nonce
			},
			success: function(data) {
				updateDashboard(data);
				updateLastUpdatedTime();
			},
			error: function(xhr, status, error) {
				console.error('Failed to refresh data:', error);
				showNotice('Failed to refresh data. Please try again.', 'error');
			},
			complete: function() {
				$('#f8m-refresh-data').prop('disabled', false).removeClass('updating-message');
			}
		});
	}

	/**
	 * Update dashboard with new data
	 */
	function updateDashboard(data) {
		// Update channel cards
		updateChannelCards(data);
		
		// Update charts
		updateCharts(data);
		
		// Update activity feed
		loadActivityFeed();
		
		// Update top performers
		loadTopPerformers();
	}

	/**
	 * Update channel overview cards
	 */
	function updateChannelCards(data) {
		// Email channel
		if (data.email) {
			$('.f8m-channel-email .f8m-stat-value').eq(0).text(formatNumber(data.email.total_sent));
			$('.f8m-channel-email .f8m-stat-value').eq(1).text(data.email.open_rate.toFixed(1) + '%');
			$('.f8m-channel-email .f8m-stat-value').eq(2).text(data.email.click_rate.toFixed(1) + '%');
		}

		// WhatsApp channel
		if (data.whatsapp) {
			$('.f8m-channel-whatsapp .f8m-stat-value').eq(0).text(formatNumber(data.whatsapp.total_messages));
			$('.f8m-channel-whatsapp .f8m-stat-value').eq(1).text(data.whatsapp.delivery_rate.toFixed(1) + '%');
			$('.f8m-channel-whatsapp .f8m-stat-value').eq(2).text(data.whatsapp.read_rate.toFixed(1) + '%');
		}

		// Web channel
		if (data.web) {
			$('.f8m-channel-web .f8m-stat-value').eq(0).text(formatNumber(data.web.sessions));
			$('.f8m-channel-web .f8m-stat-value').eq(1).text(formatNumber(data.web.pageviews));
			$('.f8m-channel-web .f8m-stat-value').eq(2).text(formatTime(data.web.avg_duration));
		}
	}

	/**
	 * Update all charts with new data
	 */
	function updateCharts(data) {
		if (data.timeline && charts.timeline) {
			charts.timeline.data.datasets[0].data = data.timeline.email;
			charts.timeline.data.datasets[1].data = data.timeline.whatsapp;
			charts.timeline.data.datasets[2].data = data.timeline.web;
			charts.timeline.update();
		}

		if (data.attribution && charts.attribution) {
			updateAttributionChart('last_touch', data.attribution);
		}
	}

	/**
	 * Update attribution chart based on model
	 */
	function updateAttributionChart(model, data) {
		if (!charts.attribution) return;

		// In real implementation, fetch data based on model
		// For now, use default data
		charts.attribution.update();
	}

	/**
	 * Load and display activity feed
	 */
	function loadActivityFeed() {
		const $feed = $('#f8m-activity-feed');
		
		// Simulate loading recent activities
		setTimeout(function() {
			const activities = generateSampleActivities();
			renderActivityFeed(activities);
		}, 500);
	}

	/**
	 * Generate sample activity data
	 */
	function generateSampleActivities() {
		return [
			{
				channel: 'email',
				icon: '📧',
				text: 'john@example.com opened "Summer Sale Newsletter"',
				time: '2 minutes ago'
			},
			{
				channel: 'whatsapp',
				icon: '💬',
				text: 'New WhatsApp message received from +1234567890',
				time: '5 minutes ago'
			},
			{
				channel: 'web',
				icon: '🌐',
				text: 'New session started from Google search',
				time: '7 minutes ago'
			},
			{
				channel: 'email',
				icon: '📧',
				text: 'sarah@example.com clicked link in "Product Update"',
				time: '10 minutes ago'
			},
			{
				channel: 'whatsapp',
				icon: '💬',
				text: 'WhatsApp campaign "Flash Sale" sent to 250 contacts',
				time: '15 minutes ago'
			}
		];
	}

	/**
	 * Render activity feed
	 */
	function renderActivityFeed(activities) {
		const $feed = $('#f8m-activity-feed');
		$feed.empty();

		activities.forEach(function(activity) {
			const $item = $('<div>', { class: 'f8m-activity-item', 'data-channel': activity.channel });
			
			$item.append(
				$('<div>', { class: 'f8m-activity-icon' }).text(activity.icon),
				$('<div>', { class: 'f8m-activity-content' }).append(
					$('<p>', { class: 'f8m-activity-text' }).text(activity.text),
					$('<div>', { class: 'f8m-activity-meta' }).text(activity.time)
				)
			);
			
			$feed.append($item);
		});
	}

	/**
	 * Filter activity feed by channel
	 */
	function filterActivityFeed(channel) {
		if (channel === 'all') {
			$('.f8m-activity-item').show();
		} else {
			$('.f8m-activity-item').hide();
			$('.f8m-activity-item[data-channel="' + channel + '"]').show();
		}
	}

	/**
	 * Load top performers data
	 */
	function loadTopPerformers() {
		// Simulate loading top performers
		setTimeout(function() {
			renderTopEmails();
			renderTopWhatsApp();
			renderTopPages();
		}, 500);
	}

	/**
	 * Render top emails table
	 */
	function renderTopEmails() {
		const data = [
			{ subject: 'Summer Sale - 50% Off', opens: 1250, clicks: 420 },
			{ subject: 'New Product Launch', opens: 980, clicks: 315 },
			{ subject: 'Weekly Newsletter #42', opens: 750, clicks: 180 }
		];

		const $tbody = $('#f8m-top-emails');
		$tbody.empty();

		data.forEach(function(item) {
			$tbody.append(
				$('<tr>').append(
					$('<td>').text(item.subject),
					$('<td>').text(formatNumber(item.opens)),
					$('<td>').text(formatNumber(item.clicks))
				)
			);
		});
	}

	/**
	 * Render top WhatsApp campaigns table
	 */
	function renderTopWhatsApp() {
		const data = [
			{ campaign: 'Flash Sale Alert', sent: 500, read: 485 },
			{ campaign: 'Order Confirmation', sent: 320, read: 318 },
			{ campaign: 'Customer Support', sent: 180, read: 175 }
		];

		const $tbody = $('#f8m-top-whatsapp');
		$tbody.empty();

		data.forEach(function(item) {
			$tbody.append(
				$('<tr>').append(
					$('<td>').text(item.campaign),
					$('<td>').text(formatNumber(item.sent)),
					$('<td>').text(formatNumber(item.read))
				)
			);
		});
	}

	/**
	 * Render top pages table
	 */
	function renderTopPages() {
		const data = [
			{ page: '/products/best-seller', views: 2450, time: '03:45' },
			{ page: '/blog/ultimate-guide', views: 1820, time: '05:20' },
			{ page: '/pricing', views: 1560, time: '02:30' }
		];

		const $tbody = $('#f8m-top-pages');
		$tbody.empty();

		data.forEach(function(item) {
			$tbody.append(
				$('<tr>').append(
					$('<td>').text(item.page),
					$('<td>').text(formatNumber(item.views)),
					$('<td>').text(item.time)
				)
			);
		});
	}

	/**
	 * Export dashboard data
	 */
	function exportData() {
		const dateRange = $('#f8m-date-range').val();
		
		// Create CSV export
		const csvContent = generateCSVExport();
		const blob = new Blob([csvContent], { type: 'text/csv' });
		const url = window.URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = 'unified-analytics-' + dateRange + '-' + Date.now() + '.csv';
		a.click();
		window.URL.revokeObjectURL(url);
		
		showNotice('Data exported successfully', 'success');
	}

	/**
	 * Generate CSV export
	 */
	function generateCSVExport() {
		// Simplified CSV generation - expand as needed
		return 'Channel,Metric,Value\n' +
			   'Email,Total Sent,1250\n' +
			   'Email,Open Rate,45.2%\n' +
			   'WhatsApp,Messages,2450\n' +
			   'Web,Sessions,5620\n';
	}

	/**
	 * Update last updated timestamp
	 */
	function updateLastUpdatedTime() {
		const now = new Date();
		const timeString = now.toLocaleTimeString();
		$('#f8m-last-updated').text('Last updated: ' + timeString);
	}

	/**
	 * Show admin notice
	 */
	function showNotice(message, type) {
		const $notice = $('<div>', {
			class: 'notice notice-' + type + ' is-dismissible',
			html: '<p>' + message + '</p>'
		});
		
		$('.f8m-unified-dashboard').prepend($notice);
		
		setTimeout(function() {
			$notice.fadeOut(function() {
				$(this).remove();
			});
		}, 3000);
	}

	/**
	 * Utility: Format number with commas
	 */
	function formatNumber(num) {
		return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	/**
	 * Utility: Format time in seconds to MM:SS
	 */
	function formatTime(seconds) {
		const mins = Math.floor(seconds / 60);
		const secs = seconds % 60;
		return mins + ':' + (secs < 10 ? '0' : '') + secs;
	}

	/**
	 * Utility: Get last 7 days labels
	 */
	function getLast7Days() {
		const labels = [];
		const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		
		for (let i = 6; i >= 0; i--) {
			const d = new Date();
			d.setDate(d.getDate() - i);
			labels.push(days[d.getDay()]);
		}
		
		return labels;
	}

	/**
	 * Cleanup on page unload
	 */
	$(window).on('beforeunload', function() {
		if (refreshInterval) {
			clearInterval(refreshInterval);
		}
		
		// Destroy charts
		Object.keys(charts).forEach(function(key) {
			if (charts[key]) {
				charts[key].destroy();
			}
		});
	});

	// Initialize when document is ready
	$(document).ready(init);

})(jQuery);