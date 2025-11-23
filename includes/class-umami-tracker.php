<?php
/**
 * Umami Tracker Class
 * Handles the injection of Umami tracking script
 *
 * @package UmamiWPConnect
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Umami Tracker Class
 */
class Umami_Tracker {

	/**
	 * Single instance
	 *
	 * @var Umami_Tracker
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Umami_Tracker
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'wp_head', array( $this, 'inject_tracking_script' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Check if tracking should be enabled for current request
	 *
	 * @return bool
	 */
	private function should_track() {
		// Don't track if website ID is not set.
		$website_id = get_option( 'umami_website_id' );
		if ( empty( $website_id ) ) {
			return false;
		}

		// Don't track admin pages unless enabled.
		if ( is_admin() && ! get_option( 'track_admin_pages', false ) ) {
			return false;
		}

		// Don't track logged-in users unless enabled.
		if ( is_user_logged_in() && ! get_option( 'track_logged_in_users', false ) ) {
			return false;
		}

		// Allow filtering.
		return apply_filters( 'umami_should_track', true );
	}

	/**
	 * Inject Umami tracking script
	 */
	public function inject_tracking_script() {
		if ( ! $this->should_track() ) {
			return;
		}

		$website_id = get_option( 'umami_website_id' );
		$script_url = get_option( 'umami_script_url', 'https://analytics.umami.is/script.js' );

		// Get user ID if logged in
		$user_id = '';
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_id      = (string) $current_user->ID;
		}

		// Output tracking script.
		?>
		<script
			async
			defer
			data-website-id="<?php echo esc_attr( $website_id ); ?>"
			src="<?php echo esc_url( $script_url ); ?>"
			data-domains="<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>"
			<?php if ( ! empty( $user_id ) ) : ?>
			data-user-id="<?php echo esc_attr( $user_id ); ?>"
			<?php endif; ?>
		></script>
		<?php

		// Add inline script to set user ID in Umami
		if ( ! empty( $user_id ) ) :
			?>
			<script>
				window.umami = window.umami || function() { (window.umami.q = window.umami.q || []).push(arguments); };
				window.umami.identify({
					userId: '<?php echo esc_js( $user_id ); ?>'
				});
			</script>
			<?php
		endif;
	}

	/**
	 * Enqueue tracking scripts
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_track() ) {
			return;
		}

		// Enqueue custom tracking script.
		wp_enqueue_script(
			'umami-wp-tracker',
			UMAMI_WP_PLUGIN_URL . 'assets/js/tracker.js',
			array(),
			UMAMI_WP_VERSION,
			true
		);

		// Pass configuration to JavaScript.
		wp_localize_script(
			'umami-wp-tracker',
			'umamiWPConfig',
			array(
				'websiteId'            => get_option( 'umami_website_id' ),
				'apiUrl'               => get_option( 'umami_api_url' ),
				'enableFormTracking'   => get_option( 'enable_form_tracking', true ),
				'enableClickTracking'  => get_option( 'enable_click_tracking', true ),
				'enableScrollTracking' => get_option( 'enable_scroll_tracking', true ),
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'umami_wp_nonce' ),
			)
		);
	}

	/**
	 * Track custom event
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_data Event data.
	 * @return bool
	 */
	public function track_event( $event_name, $event_data = array() ) {
		if ( ! $this->should_track() ) {
			return false;
		}

		// Allow filtering event data.
		$event_data = apply_filters( 'umami_event_data', $event_data, $event_name );

		// Queue event for sending.
		$this->queue_event( $event_name, $event_data );

		return true;
	}

	/**
	 * Queue event for persistent batch sending
	 *
	 * Uses database-backed persistent queue instead of transients to prevent data loss.
	 * Events are guaranteed to be processed even if server restarts or caches clear.
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_data Event data.
	 * @return bool True if queued successfully, false on failure.
	 */
	private function queue_event( $event_name, $event_data ) {
		// Use persistent database-backed queue to prevent data loss.
		$queue = new Persistent_Event_Queue();
		
		// Add event to persistent queue.
		$event_id = $queue->enqueue( $event_name, $event_data );
		
		if ( false === $event_id ) {
			error_log( '[Umami Tracker] Failed to enqueue event: ' . $event_name );
			return false;
		}
		
		// Log successful queueing for monitoring.
		error_log( "[Umami Tracker] Event queued successfully: {$event_name} (ID: {$event_id})" );
		
		return true;
	}
}

