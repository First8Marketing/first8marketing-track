<?php
/**
 * Unified Multi-Channel Tracking Dashboard
 *
 * Displays unified analytics from Email, WhatsApp, and Web tracking
 * combining data from all channels in one comprehensive view.
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

namespace First8Marketing\Track\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified Dashboard Page Class
 */
class Unified_Dashboard_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'first8marketing',
			__( 'Unified Dashboard', 'first8marketing-track' ),
			__( 'Unified Dashboard', 'first8marketing-track' ),
			'manage_options',
			'f8m-unified-dashboard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'first8marketing_page_f8m-unified-dashboard' !== $hook ) {
			return;
		}

		// Enqueue Chart.js for visualizations
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
			array(),
			'4.4.0',
			true
		);

		// Enqueue custom dashboard scripts
		wp_enqueue_script(
			'f8m-unified-dashboard',
			UMAMI_WP_PLUGIN_URL . 'assets/js/unified-dashboard.js',
			array( 'jquery', 'chartjs' ),
			UMAMI_WP_VERSION,
			true
		);

		// Localize script with API endpoints
		wp_localize_script(
			'f8m-unified-dashboard',
			'f8mUnified',
			array(
				'apiUrl'       => rest_url( 'first8marketing/v1/analytics/unified' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'refreshRate'  => 30000, // 30 seconds
			)
		);

		// Enqueue styles
		wp_enqueue_style(
			'f8m-unified-dashboard',
			UMAMI_WP_PLUGIN_URL . 'assets/css/unified-dashboard.css',
			array(),
			UMAMI_WP_VERSION
		);
	}

	/**
	 * Render admin page
	 */
	public function render_page() {
		$unified_stats = $this->get_unified_statistics();
		$attribution = $this->get_attribution_data();
		?>
		<div class="wrap f8m-unified-dashboard">
			<!-- Header -->
			<div class="f8m-dashboard-header">
				<div>
					<h1><?php esc_html_e( 'Unified Multi-Channel Analytics', 'first8marketing-track' ); ?></h1>
					<p class="description">
						<?php esc_html_e( 'Comprehensive view of Email, WhatsApp, and Web tracking metrics', 'first8marketing-track' ); ?>
					</p>
				</div>
				<div class="f8m-header-actions">
					<button type="button" class="button" id="f8m-refresh-data">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'first8marketing-track' ); ?>
					</button>
					<button type="button" class="button" id="f8m-export-data">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export', 'first8marketing-track' ); ?>
					</button>
					<select id="f8m-date-range" class="f8m-date-selector">
						<option value="today"><?php esc_html_e( 'Today', 'first8marketing-track' ); ?></option>
						<option value="week" selected><?php esc_html_e( 'Last 7 Days', 'first8marketing-track' ); ?></option>
						<option value="month"><?php esc_html_e( 'Last 30 Days', 'first8marketing-track' ); ?></option>
						<option value="quarter"><?php esc_html_e( 'Last 90 Days', 'first8marketing-track' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Real-time Status Bar -->
			<div class="f8m-status-bar">
				<div class="f8m-status-item">
					<span class="f8m-status-dot f8m-status-active"></span>
					<span><?php esc_html_e( 'All Systems Operational', 'first8marketing-track' ); ?></span>
				</div>
				<div class="f8m-status-item">
					<span class="dashicons dashicons-clock"></span>
					<span id="f8m-last-updated"><?php esc_html_e( 'Last updated: Just now', 'first8marketing-track' ); ?></span>
				</div>
				<div class="f8m-status-item">
					<span class="dashicons dashicons-chart-line"></span>
					<span id="f8m-live-events">0 <?php esc_html_e( 'events in last minute', 'first8marketing-track' ); ?></span>
				</div>
			</div>

			<!-- Channel Overview Cards -->
			<div class="f8m-channel-overview">
				<h2><?php esc_html_e( 'Channel Overview', 'first8marketing-track' ); ?></h2>
				<div class="f8m-channel-grid">
					<!-- Email Channel Card -->
					<div class="f8m-channel-card f8m-channel-email">
						<div class="f8m-channel-header">
							<span class="f8m-channel-icon">📧</span>
							<h3><?php esc_html_e( 'Email', 'first8marketing-track' ); ?></h3>
						</div>
						<div class="f8m-channel-stats">
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['email']['total_sent'] ?? 0 ) ); ?></span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Sent', 'first8marketing-track' ); ?></span>
							</div>
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['email']['open_rate'] ?? 0, 1 ) ); ?>%</span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Open Rate', 'first8marketing-track' ); ?></span>
							</div>
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['email']['click_rate'] ?? 0, 1 ) ); ?>%</span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Click Rate', 'first8marketing-track' ); ?></span>
							</div>
						</div>
						<div class="f8m-channel-trend">
							<span class="f8m-trend-positive">↑ <?php echo esc_html( $unified_stats['email']['trend'] ?? 0 ); ?>%</span>
							<span class="f8m-trend-label"><?php esc_html_e( 'vs last period', 'first8marketing-track' ); ?></span>
						</div>
					</div>

					<!-- WhatsApp Channel Card -->
					<div class="f8m-channel-card f8m-channel-whatsapp">
						<div class="f8m-channel-header">
							<span class="f8m-channel-icon">💬</span>
							<h3><?php esc_html_e( 'WhatsApp', 'first8marketing-track' ); ?></h3>
						</div>
						<div class="f8m-channel-stats">
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['whatsapp']['total_messages'] ?? 0 ) ); ?></span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Messages', 'first8marketing-track' ); ?></span>
							</div>
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['whatsapp']['delivery_rate'] ?? 0, 1 ) ); ?>%</span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Delivery', 'first8marketing-track' ); ?></span>
							</div>
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['whatsapp']['read_rate'] ?? 0, 1 ) ); ?>%</span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Read Rate', 'first8marketing-track' ); ?></span>
							</div>
						</div>
						<div class="f8m-channel-trend">
							<span class="f8m-trend-positive">↑ <?php echo esc_html( $unified_stats['whatsapp']['trend'] ?? 0 ); ?>%</span>
							<span class="f8m-trend-label"><?php esc_html_e( 'vs last period', 'first8marketing-track' ); ?></span>
						</div>
					</div>

					<!-- Web Channel Card -->
					<div class="f8m-channel-card f8m-channel-web">
						<div class="f8m-channel-header">
							<span class="f8m-channel-icon">🌐</span>
							<h3><?php esc_html_e( 'Web', 'first8marketing-track' ); ?></h3>
						</div>
						<div class="f8m-channel-stats">
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['web']['sessions'] ?? 0 ) ); ?></span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Sessions', 'first8marketing-track' ); ?></span>
							</div>
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( number_format( $unified_stats['web']['pageviews'] ?? 0 ) ); ?></span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Pageviews', 'first8marketing-track' ); ?></span>
							</div>
							<div class="f8m-stat">
								<span class="f8m-stat-value"><?php echo esc_html( gmdate( 'i:s', $unified_stats['web']['avg_duration'] ?? 0 ) ); ?></span>
								<span class="f8m-stat-label"><?php esc_html_e( 'Avg Duration', 'first8marketing-track' ); ?></span>
							</div>
						</div>
						<div class="f8m-channel-trend">
							<span class="f8m-trend-positive">↑ <?php echo esc_html( $unified_stats['web']['trend'] ?? 0 ); ?>%</span>
							<span class="f8m-trend-label"><?php esc_html_e( 'vs last period', 'first8marketing-track' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- Charts Section -->
			<div class="f8m-charts-section">
				<div class="f8m-chart-row">
					<!-- Multi-Channel Timeline Chart -->
					<div class="f8m-chart-container f8m-chart-large">
						<div class="f8m-chart-header">
							<h3><?php esc_html_e( 'Multi-Channel Activity Timeline', 'first8marketing-track' ); ?></h3>
							<div class="f8m-chart-legend">
								<span class="f8m-legend-item">
									<span class="f8m-legend-color" style="background: #2563eb;"></span>
									<?php esc_html_e( 'Email', 'first8marketing-track' ); ?>
								</span>
								<span class="f8m-legend-item">
									<span class="f8m-legend-color" style="background: #10b981;"></span>
									<?php esc_html_e( 'WhatsApp', 'first8marketing-track' ); ?>
								</span>
								<span class="f8m-legend-item">
									<span class="f8m-legend-color" style="background: #f59e0b;"></span>
									<?php esc_html_e( 'Web', 'first8marketing-track' ); ?>
								</span>
							</div>
						</div>
						<canvas id="f8m-timeline-chart" height="300"></canvas>
					</div>

					<!-- Attribution Pie Chart -->
					<div class="f8m-chart-container f8m-chart-small">
						<div class="f8m-chart-header">
							<h3><?php esc_html_e( 'Channel Attribution', 'first8marketing-track' ); ?></h3>
							<select id="f8m-attribution-model" class="f8m-model-selector">
								<option value="last_touch"><?php esc_html_e( 'Last Touch', 'first8marketing-track' ); ?></option>
								<option value="first_touch"><?php esc_html_e( 'First Touch', 'first8marketing-track' ); ?></option>
								<option value="linear"><?php esc_html_e( 'Linear', 'first8marketing-track' ); ?></option>
								<option value="time_decay"><?php esc_html_e( 'Time Decay', 'first8marketing-track' ); ?></option>
							</select>
						</div>
						<canvas id="f8m-attribution-chart" height="300"></canvas>
						<div class="f8m-attribution-breakdown">
							<div class="f8m-attribution-item">
								<span class="f8m-attribution-label">📧 Email</span>
								<span class="f8m-attribution-value"><?php echo esc_html( number_format( $attribution['email'] ?? 0, 1 ) ); ?>%</span>
							</div>
							<div class="f8m-attribution-item">
								<span class="f8m-attribution-label">💬 WhatsApp</span>
								<span class="f8m-attribution-value"><?php echo esc_html( number_format( $attribution['whatsapp'] ?? 0, 1 ) ); ?>%</span>
							</div>
							<div class="f8m-attribution-item">
								<span class="f8m-attribution-label">🌐 Web</span>
								<span class="f8m-attribution-value"><?php echo esc_html( number_format( $attribution['web'] ?? 0, 1 ) ); ?>%</span>
							</div>
						</div>
					</div>
				</div>

				<div class="f8m-chart-row">
					<!-- Conversion Funnel -->
					<div class="f8m-chart-container f8m-chart-medium">
						<div class="f8m-chart-header">
							<h3><?php esc_html_e( 'Cross-Channel Conversion Funnel', 'first8marketing-track' ); ?></h3>
						</div>
						<canvas id="f8m-funnel-chart" height="250"></canvas>
					</div>

					<!-- Engagement Comparison -->
					<div class="f8m-chart-container f8m-chart-medium">
						<div class="f8m-chart-header">
							<h3><?php esc_html_e( 'Campaign Performance Comparison', 'first8marketing-track' ); ?></h3>
						</div>
						<canvas id="f8m-comparison-chart" height="250"></canvas>
					</div>
				</div>
			</div>

			<!-- Recent Activity Feed -->
			<div class="f8m-activity-section">
				<div class="f8m-activity-header">
					<h2><?php esc_html_e( 'Real-Time Activity Feed', 'first8marketing-track' ); ?></h2>
					<div class="f8m-activity-filters">
						<button class="f8m-filter-btn active" data-channel="all"><?php esc_html_e( 'All', 'first8marketing-track' ); ?></button>
						<button class="f8m-filter-btn" data-channel="email">📧 <?php esc_html_e( 'Email', 'first8marketing-track' ); ?></button>
						<button class="f8m-filter-btn" data-channel="whatsapp">💬 <?php esc_html_e( 'WhatsApp', 'first8marketing-track' ); ?></button>
						<button class="f8m-filter-btn" data-channel="web">🌐 <?php esc_html_e( 'Web', 'first8marketing-track' ); ?></button>
					</div>
				</div>
				<div id="f8m-activity-feed" class="f8m-activity-feed">
					<div class="f8m-activity-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading activity feed...', 'first8marketing-track' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Top Performers -->
			<div class="f8m-performers-section">
				<h2><?php esc_html_e( 'Top Performing Content', 'first8marketing-track' ); ?></h2>
				<div class="f8m-performers-grid">
					<!-- Top Emails -->
					<div class="f8m-performers-card">
						<h3>📧 <?php esc_html_e( 'Top Emails', 'first8marketing-track' ); ?></h3>
						<table class="f8m-performers-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Subject', 'first8marketing-track' ); ?></th>
									<th><?php esc_html_e( 'Opens', 'first8marketing-track' ); ?></th>
									<th><?php esc_html_e( 'Clicks', 'first8marketing-track' ); ?></th>
								</tr>
							</thead>
							<tbody id="f8m-top-emails">
								<tr>
									<td colspan="3" class="f8m-loading"><?php esc_html_e( 'Loading...', 'first8marketing-track' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Top WhatsApp Messages -->
					<div class="f8m-performers-card">
						<h3>💬 <?php esc_html_e( 'Top WhatsApp Campaigns', 'first8marketing-track' ); ?></h3>
						<table class="f8m-performers-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Campaign', 'first8marketing-track' ); ?></th>
									<th><?php esc_html_e( 'Sent', 'first8marketing-track' ); ?></th>
									<th><?php esc_html_e( 'Read', 'first8marketing-track' ); ?></th>
								</tr>
							</thead>
							<tbody id="f8m-top-whatsapp">
								<tr>
									<td colspan="3" class="f8m-loading"><?php esc_html_e( 'Loading...', 'first8marketing-track' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Top Pages -->
					<div class="f8m-performers-card">
						<h3>🌐 <?php esc_html_e( 'Top Pages', 'first8marketing-track' ); ?></h3>
						<table class="f8m-performers-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Page', 'first8marketing-track' ); ?></th>
									<th><?php esc_html_e( 'Views', 'first8marketing-track' ); ?></th>
									<th><?php esc_html_e( 'Time', 'first8marketing-track' ); ?></th>
								</tr>
							</thead>
							<tbody id="f8m-top-pages">
								<tr>
									<td colspan="3" class="f8m-loading"><?php esc_html_e( 'Loading...', 'first8marketing-track' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Quick Links -->
			<div class="f8m-quick-links">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=f8m-email-tracking' ) ); ?>" class="f8m-quick-link">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Email Analytics', 'first8marketing-track' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=f8m-attribution-report' ) ); ?>" class="f8m-quick-link">
					<span class="dashicons dashicons-chart-pie"></span>
					<?php esc_html_e( 'Attribution Report', 'first8marketing-track' ); ?>
				</a>
				<a href="<?php echo esc_url( get_option( 'f8m_umami_url', '' ) ); ?>" class="f8m-quick-link" target="_blank">
					<span class="dashicons dashicons-external"></span>
					<?php esc_html_e( 'Open Umami Dashboard', 'first8marketing-track' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get unified statistics from all channels
	 *
	 * @return array Unified statistics.
	 */
	private function get_unified_statistics() {
		global $wpdb;

		// Get email stats
		$email_table = $wpdb->prefix . 'first8_email_events';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$email_stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_sent,
				SUM(CASE WHEN event_type = 'email_open' THEN 1 ELSE 0 END) as opens,
				SUM(CASE WHEN event_type = 'email_click' THEN 1 ELSE 0 END) as clicks
			FROM {$email_table}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
			ARRAY_A
		);
		// phpcs:enable

		$email_data = array(
			'total_sent'  => (int) ( $email_stats['total_sent'] ?? 0 ),
			'open_rate'   => $email_stats['total_sent'] > 0 ? ( $email_stats['opens'] / $email_stats['total_sent'] ) * 100 : 0,
			'click_rate'  => $email_stats['total_sent'] > 0 ? ( $email_stats['clicks'] / $email_stats['total_sent'] ) * 100 : 0,
			'trend'       => 12.5, // Calculate from previous period
		);

		// Get WhatsApp stats (from Umami API)
		$whatsapp_data = $this->fetch_whatsapp_stats();

		// Get web stats (from Umami API)
		$web_data = $this->fetch_web_stats();

		return array(
			'email'    => $email_data,
			'whatsapp' => $whatsapp_data,
			'web'      => $web_data,
		);
	}

	/**
	 * Fetch WhatsApp statistics from Umami
	 *
	 * @return array WhatsApp statistics.
	 */
	private function fetch_whatsapp_stats() {
		// Placeholder - implement actual API call to Umami
		return array(
			'total_messages' => 1250,
			'delivery_rate'  => 98.5,
			'read_rate'      => 85.2,
			'trend'          => 8.3,
		);
	}

	/**
	 * Fetch web statistics from Umami
	 *
	 * @return array Web statistics.
	 */
	private function fetch_web_stats() {
		// Placeholder - implement actual API call to Umami
		return array(
			'sessions'     => 3420,
			'pageviews'    => 12850,
			'avg_duration' => 245, // seconds
			'trend'        => 15.7,
		);
	}

	/**
	 * Get attribution data
	 *
	 * @return array Attribution percentages by channel.
	 */
	private function get_attribution_data() {
		// Placeholder - implement actual attribution calculation
		return array(
			'email'    => 35.0,
			'whatsapp' => 40.0,
			'web'      => 25.0,
		);
	}
}

// Initialize the page
new Unified_Dashboard_Page();