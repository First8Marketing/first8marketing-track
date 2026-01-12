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
		// Move script to footer for better performance (non-blocking)
		add_action( 'wp_footer', array( $this, 'inject_tracking_script' ), 5 );
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
	 * Queue event for batch sending
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_data Event data.
	 */
	private function queue_event( $event_name, $event_data ) {
		// Get current queue.
		$queue = get_transient( 'umami_event_queue' );
		if ( false === $queue ) {
			$queue = array();
		}

		// Add event to queue.
		$queue[] = array(
			'name'      => $event_name,
			'data'      => $event_data,
			'timestamp' => time(),
		);

		// Save queue (expires in 5 minutes).
		set_transient( 'umami_event_queue', $queue, 300 );
	}

	/**
	 * Send queued events to Umami analytics
	 *
	 * @return array Results with success count and errors
	 */
	public function send_queued_events() {
		$results = array(
			'success_count' => 0,
			'error_count'   => 0,
			'errors'        => array(),
		);

		// Get Umami configuration.
		$api_url    = get_option( 'umami_api_url' );
		$website_id = get_option( 'umami_website_id' );

		// Validate configuration.
		if ( empty( $api_url ) ) {
			$error = 'Umami API URL not configured';
			error_log( '[Umami Track] ' . $error );
			$results['errors'][] = $error;
			return $results;
		}

		if ( empty( $website_id ) ) {
			$error = 'Umami website ID not configured';
			error_log( '[Umami Track] ' . $error );
			$results['errors'][] = $error;
			return $results;
		}

		// Get events from queue.
		$queue = get_transient( 'umami_event_queue' );
		if ( false === $queue || empty( $queue ) ) {
			error_log( '[Umami Track] No events in queue to send' );
			return $results;
		}

		$total_events = count( $queue );
		error_log( sprintf( '[Umami Track] Processing %d queued events', $total_events ) );

		// Process events in batches of 100.
		$batch_size     = 100;
		$batches        = array_chunk( $queue, $batch_size );
		$remaining      = array();
		$last_send_time = time();

		foreach ( $batches as $batch_index => $batch ) {
			error_log( sprintf( '[Umami Track] Sending batch %d/%d (%d events)', $batch_index + 1, count( $batches ), count( $batch ) ) );

			$batch_result = $this->send_event_batch( $batch, $api_url, $website_id );

			if ( $batch_result['success'] ) {
				$results['success_count'] += count( $batch );
				error_log( sprintf( '[Umami Track] Successfully sent batch %d', $batch_index + 1 ) );
			} else {
				$results['error_count'] += count( $batch );
				$results['errors'][]   = $batch_result['error'];
				// Re-queue failed events for retry.
				$remaining = array_merge( $remaining, $batch );
				error_log( sprintf( '[Umami Track] Failed to send batch %d: %s', $batch_index + 1, $batch_result['error'] ) );
			}
		}

		// Update queue with remaining events.
		if ( ! empty( $remaining ) ) {
			set_transient( 'umami_event_queue', $remaining, 300 );
			error_log( sprintf( '[Umami Track] Re-queued %d failed events', count( $remaining ) ) );
		} else {
			delete_transient( 'umami_event_queue' );
			error_log( '[Umami Track] Queue cleared' );
		}

		// Update last send timestamp.
		update_option( 'umami_last_send_time', $last_send_time );

		error_log( sprintf(
			'[Umami Track] Send complete: %d succeeded, %d failed',
			$results['success_count'],
			$results['error_count']
		) );

		return $results;
	}

	/**
	 * Send a batch of events to Umami with retry logic
	 *
	 * @param array  $events Events to send.
	 * @param string $api_url Umami API URL.
	 * @param string $website_id Website ID.
	 * @return array Result with success flag and error message
	 */
	private function send_event_batch( $events, $api_url, $website_id ) {
		$max_attempts  = 3;
		$backoff_times = array( 0, 1, 2 ); // Seconds: immediate, 1s, 2s.

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			// Apply backoff delay.
			if ( $backoff_times[ $attempt - 1 ] > 0 ) {
				sleep( $backoff_times[ $attempt - 1 ] );
			}

			error_log( sprintf( '[Umami Track] Attempt %d/%d to send %d events', $attempt, $max_attempts, count( $events ) ) );

			// Send each event individually (Umami /api/send accepts single events).
			$all_success = true;
			$last_error  = '';

			foreach ( $events as $event ) {
				$payload = $this->build_umami_payload( $event, $website_id );

				$response = wp_remote_post(
					rtrim( $api_url, '/' ) . '/api/send',
					array(
						'headers' => array(
							'Content-Type' => 'application/json',
							'User-Agent'   => 'WordPress/First8-Marketing-Track/' . UMAMI_WP_VERSION,
						),
						'body'    => wp_json_encode( $payload ),
						'timeout' => 15,
					)
				);

				if ( is_wp_error( $response ) ) {
					$all_success = false;
					$last_error  = $response->get_error_message();
					error_log( sprintf(
						'[Umami Track] HTTP error for event "%s": %s',
						$event['name'],
						$last_error
					) );
					continue;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $code ) {
					$all_success = false;
					$body        = wp_remote_retrieve_body( $response );
					$last_error  = sprintf( 'HTTP %d: %s', $code, $body );
					error_log( sprintf(
						'[Umami Track] API error for event "%s": %s',
						$event['name'],
						$last_error
					) );
					continue;
				}
			}

			if ( $all_success ) {
				return array(
					'success' => true,
					'error'   => '',
				);
			}

			// Retry on failure unless it's the last attempt.
			if ( $attempt < $max_attempts ) {
				error_log( sprintf( '[Umami Track] Retrying in %d seconds...', $backoff_times[ $attempt ] ) );
			}
		}

		return array(
			'success' => false,
			'error'   => $last_error ? $last_error : 'Unknown error',
		);
	}

	/**
	 * Build Umami API payload from WordPress event data
	 *
	 * @param array  $event WordPress event data.
	 * @param string $website_id Website ID.
	 * @return array Umami-compatible payload
	 */
	private function build_umami_payload( $event, $website_id ) {
		// Get site information.
		$site_url = wp_parse_url( home_url() );
		$hostname = $site_url['host'];
		$language = get_bloginfo( 'language' );

		// Build event data object (WooCommerce fields go here).
		$event_data = array();
		if ( ! empty( $event['data'] ) ) {
			// Pass through all event data including WooCommerce fields.
			$event_data = $event['data'];
		}

		// Build payload according to Umami /api/send schema.
		$payload = array(
			'type'    => 'event',
			'payload' => array(
				'website'  => $website_id,
				'hostname' => $hostname,
				'language' => $language,
				'referrer' => '',
				'screen'   => '1920x1080', // Default screen resolution.
				'title'    => get_bloginfo( 'name' ),
				'url'      => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/',
				'name'     => $event['name'],
				'data'     => $event_data,
			),
		);

		return $payload;
	}

	/**
	 * Test connection to Umami API
	 *
	 * @return bool|WP_Error True if connected, WP_Error if failed
	 */
	public function test_umami_connection() {
		$api_url    = get_option( 'umami_api_url' );
		$website_id = get_option( 'umami_website_id' );

		if ( empty( $api_url ) || empty( $website_id ) ) {
			return new WP_Error( 'config_missing', 'Umami API URL or website ID not configured' );
		}

		// Send test event.
		$test_event = array(
			'name'      => 'connection_test',
			'data'      => array( 'source' => 'wordpress_plugin' ),
			'timestamp' => time(),
		);

		$payload = $this->build_umami_payload( $test_event, $website_id );

		$response = wp_remote_post(
			rtrim( $api_url, '/' ) . '/api/send',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WordPress/First8-Marketing-Track/' . UMAMI_WP_VERSION,
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body = wp_remote_retrieve_body( $response );
			return new WP_Error( 'api_error', sprintf( 'HTTP %d: %s', $code, $body ) );
		}

		return true;
	}
}

