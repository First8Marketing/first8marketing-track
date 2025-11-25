<?php
/**
 * Email Tracking Admin Page
 *
 * Displays email tracking statistics and campaign analytics.
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

namespace First8Marketing\Track\Admin;

use First8Marketing\Track\Email_Tracker;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Tracking Admin Page Class
 */
class Email_Tracking_Page {

	/**
	 * Email tracker instance
	 *
	 * @var Email_Tracker
	 */
	private $tracker;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->tracker = Email_Tracker::get_instance();
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'first8marketing',
			__( 'Email Tracking', 'first8marketing-track' ),
			__( 'Email Tracking', 'first8marketing-track' ),
			'manage_options',
			'f8m-email-tracking',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'first8marketing_page_f8m-email-tracking' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'f8m-email-tracking-admin', UMAMI_WP_PLUGIN_URL . 'assets/css/email-tracking-admin.css', array(), UMAMI_WP_VERSION );
	}

	/**
	 * Render admin page
	 */
	public function render_page() {
		$stats = $this->get_email_stats();
		$campaigns = $this->get_campaigns();
		$recent_events = $this->get_recent_events();
		?>
		<div class="wrap f8m-email-tracking">
			<h1><?php esc_html_e( 'Email Tracking Statistics', 'first8marketing-track' ); ?></h1>

			<!-- Statistics Cards -->
			<div class="f8m-stats-grid">
				<div class="f8m-stat-card">
					<div class="f8m-stat-icon">📧</div>
					<div class="f8m-stat-content">
						<div class="f8m-stat-label"><?php esc_html_e( 'Total Events', 'first8marketing-track' ); ?></div>
						<div class="f8m-stat-value"><?php echo esc_html( number_format( $stats['total_events'] ) ); ?></div>
					</div>
				</div>

				<div class="f8m-stat-card">
					<div class="f8m-stat-icon">👁️</div>
					<div class="f8m-stat-content">
						<div class="f8m-stat-label"><?php esc_html_e( 'Email Opens', 'first8marketing-track' ); ?></div>
						<div class="f8m-stat-value"><?php echo esc_html( number_format( $stats['total_opens'] ) ); ?></div>
					</div>
				</div>

				<div class="f8m-stat-card">
					<div class="f8m-stat-icon">🖱️</div>
					<div class="f8m-stat-content">
						<div class="f8m-stat-label"><?php esc_html_e( 'Link Clicks', 'first8marketing-track' ); ?></div>
						<div class="f8m-stat-value"><?php echo esc_html( number_format( $stats['total_clicks'] ) ); ?></div>
					</div>
				</div>

				<div class="f8m-stat-card">
					<div class="f8m-stat-icon">📊</div>
					<div class="f8m-stat-content">
						<div class="f8m-stat-label"><?php esc_html_e( 'Open Rate', 'first8marketing-track' ); ?></div>
						<div class="f8m-stat-value"><?php echo esc_html( number_format( $stats['open_rate'], 1 ) ); ?>%</div>
					</div>
				</div>

				<div class="f8m-stat-card">
					<div class="f8m-stat-icon">🎯</div>
					<div class="f8m-stat-content">
						<div class="f8m-stat-label"><?php esc_html_e( 'Click Rate', 'first8marketing-track' ); ?></div>
						<div class="f8m-stat-value"><?php echo esc_html( number_format( $stats['click_rate'], 1 ) ); ?>%</div>
					</div>
				</div>

				<div class="f8m-stat-card">
					<div class="f8m-stat-icon">🔄</div>
					<div class="f8m-stat-content">
						<div class="f8m-stat-label"><?php esc_html_e( 'Synced to Umami', 'first8marketing-track' ); ?></div>
						<div class="f8m-stat-value"><?php echo esc_html( number_format( $stats['synced_events'] ) ); ?></div>
					</div>
				</div>
			</div>

			<!-- Campaigns Table -->
			<div class="f8m-section">
				<h2><?php esc_html_e( 'Campaigns', 'first8marketing-track' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campaign ID', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Campaign Name', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Type', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Status', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Total Sent', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Opens', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Open Rate', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'first8marketing-track' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $campaigns ) ) : ?>
							<?php foreach ( $campaigns as $campaign ) : ?>
								<tr>
									<td><?php echo esc_html( $campaign->campaign_id ); ?></td>
									<td><?php echo esc_html( $campaign->campaign_name ); ?></td>
									<td><?php echo esc_html( $campaign->campaign_type ); ?></td>
									<td>
										<span class="f8m-status f8m-status-<?php echo esc_attr( $campaign->status ); ?>">
											<?php echo esc_html( ucfirst( $campaign->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( number_format( $campaign->total_sent ) ); ?></td>
									<td><?php echo esc_html( number_format( $campaign->total_opens ) ); ?></td>
									<td><?php echo esc_html( number_format( $campaign->total_clicks ) ); ?></td>
									<td><?php echo esc_html( number_format( $this->calculate_open_rate( $campaign ), 1 ) ); ?>%</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=f8m-email-tracking&campaign=' . urlencode( $campaign->campaign_id ) ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View Details', 'first8marketing-track' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="9" style="text-align: center;">
									<?php esc_html_e( 'No campaigns found. Email tracking will start automatically when emails are sent.', 'first8marketing-track' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Recent Events -->
			<div class="f8m-section">
				<h2><?php esc_html_e( 'Recent Events', 'first8marketing-track' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date/Time', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Event Type', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Email Type', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Campaign', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Email Client', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Device', 'first8marketing-track' ); ?></th>
							<th><?php esc_html_e( 'Status', 'first8marketing-track' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $recent_events ) ) : ?>
							<?php foreach ( $recent_events as $event ) : ?>
								<tr>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event->created_at ) ) ); ?></td>
									<td>
										<span class="f8m-event-type f8m-event-<?php echo esc_attr( $event->event_type ); ?>">
											<?php echo esc_html( str_replace( '_', ' ', ucwords( $event->event_type, '_' ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $event->email_type ); ?></td>
									<td><?php echo esc_html( $event->campaign_id ); ?></td>
									<td><?php echo esc_html( $event->email_client ?: 'Unknown' ); ?></td>
									<td><?php echo esc_html( ucfirst( $event->device_type ?: 'unknown' ) ); ?></td>
									<td>
										<?php if ( $event->synced_to_umami ) : ?>
											<span class="f8m-sync-status f8m-synced">✓ <?php esc_html_e( 'Synced', 'first8marketing-track' ); ?></span>
										<?php else : ?>
											<span class="f8m-sync-status f8m-pending">⏳ <?php esc_html_e( 'Pending', 'first8marketing-track' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="7" style="text-align: center;">
									<?php esc_html_e( 'No events recorded yet.', 'first8marketing-track' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Settings Section -->
			<div class="f8m-section">
				<h2><?php esc_html_e( 'Settings', 'first8marketing-track' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'f8m_email_tracking_settings', 'f8m_email_tracking_nonce' ); ?>
					<input type="hidden" name="action" value="f8m_update_email_tracking_settings" />

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="f8m_email_tracking_enabled"><?php esc_html_e( 'Enable Email Tracking', 'first8marketing-track' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="f8m_email_tracking_enabled" id="f8m_email_tracking_enabled" value="1" <?php checked( get_option( 'f8m_email_tracking_enabled', true ), true ); ?> />
									<?php esc_html_e( 'Track email opens and clicks', 'first8marketing-track' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When enabled, tracking pixels and link tracking will be added to emails.', 'first8marketing-track' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="f8m_tenant_id"><?php esc_html_e( 'Tenant ID', 'first8marketing-track' ); ?></label>
							</th>
							<td>
								<input type="text" name="f8m_tenant_id" id="f8m_tenant_id" value="<?php echo esc_attr( get_option( 'f8m_tenant_id', 'default' ) ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Multi-tenant identifier for data isolation.', 'first8marketing-track' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'first8marketing-track' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Get email statistics
	 *
	 * @return array Statistics.
	 */
	private function get_email_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'first8_email_events';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_events,
				SUM(CASE WHEN event_type = 'email_open' THEN 1 ELSE 0 END) as total_opens,
				SUM(CASE WHEN event_type = 'email_click' THEN 1 ELSE 0 END) as total_clicks,
				SUM(CASE WHEN synced_to_umami = 1 THEN 1 ELSE 0 END) as synced_events
			FROM {$table}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $stats ) {
			$stats = array(
				'total_events' => 0,
				'total_opens' => 0,
				'total_clicks' => 0,
				'synced_events' => 0,
			);
		}

		// Calculate rates
		$stats['open_rate'] = $stats['total_events'] > 0 ? ( $stats['total_opens'] / $stats['total_events'] ) * 100 : 0;
		$stats['click_rate'] = $stats['total_events'] > 0 ? ( $stats['total_clicks'] / $stats['total_events'] ) * 100 : 0;

		return $stats;
	}

	/**
	 * Get campaigns
	 *
	 * @return array Campaigns.
	 */
	private function get_campaigns() {
		global $wpdb;

		$table = $wpdb->prefix . 'first8_email_campaigns';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$campaigns = $wpdb->get_results(
			"SELECT * FROM {$table}
			ORDER BY created_at DESC
			LIMIT 50"
		);
		// phpcs:enable

		return $campaigns ?: array();
	}

	/**
	 * Get recent events
	 *
	 * @param int $limit Number of events to retrieve.
	 * @return array Events.
	 */
	private function get_recent_events( $limit = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'first8_email_events';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			)
		);
		// phpcs:enable

		return $events ?: array();
	}

	/**
	 * Calculate open rate for campaign
	 *
	 * @param object $campaign Campaign object.
	 * @return float Open rate percentage.
	 */
	private function calculate_open_rate( $campaign ) {
		if ( $campaign->total_sent <= 0 ) {
			return 0;
		}

		return ( $campaign->unique_opens / $campaign->total_sent ) * 100;
	}
}