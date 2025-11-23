<?php
/**
 * Event Queue Processor Hook
 *
 * Processes pending events from the persistent queue and sends them to Umami.
 *
 * @package First8Marketing_Track
 * @since 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process pending events from the queue.
 *
 * This function is called by wp-cron every 5 minutes to batch-send
 * pending events to Umami Analytics.
 *
 * @return array Processing results with status and counts.
 */
function umami_process_pending_events() {
	// Load required classes.
	require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-persistent-event-queue.php';

	$queue = new Persistent_Event_Queue();
	
	// Get pending events (batch of 100).
	$events = $queue->get_pending( 100 );
	
	if ( empty( $events ) ) {
		error_log( '[Umami Queue Processor] No pending events to process' );
		return array(
			'status'    => 'success',
			'processed' => 0,
			'failed'    => 0,
		);
	}

	$processed_count = 0;
	$failed_count    = 0;

	// Get Umami configuration.
	$umami_host       = get_option( 'umami_api_url', '' );
	$umami_website_id = get_option( 'umami_website_id', '' );

	if ( empty( $umami_host ) || empty( $umami_website_id ) ) {
		error_log( '[Umami Queue Processor] Umami not configured - events will remain queued' );
		return array(
			'status'  => 'error',
			'message' => 'Umami not configured',
		);
	}

	// Process each event.
	foreach ( $events as $event ) {
		$event_id   = $event['id'];
		$event_name = $event['event_name'];
		$event_data = $event['event_data'];

		// Send event to Umami via HTTP API.
		$result = umami_send_event_to_api( $umami_host, $umami_website_id, $event_name, $event_data );

		if ( is_wp_error( $result ) ) {
			// Increment retry count.
			$queue->increment_retry( $event_id );

			// Check if max retries exceeded.
			if ( $event['retry_count'] >= 2 ) {
				// Move to dead letter queue.
				$queue->mark_failed( $event_id, $result->get_error_message() );
				error_log( "[Umami Queue Processor] Event {$event_id} moved to dead letter queue: " . $result->get_error_message() );
			} else {
				error_log( "[Umami Queue Processor] Event {$event_id} failed, will retry: " . $result->get_error_message() );
			}

			$failed_count++;
		} else {
			// Mark as processed.
			$queue->mark_processed( $event_id );
			$processed_count++;
		}
	}

	error_log( "[Umami Queue Processor] Batch complete: {$processed_count} processed, {$failed_count} failed" );

	return array(
		'status'    => 'success',
		'processed' => $processed_count,
		'failed'    => $failed_count,
		'remaining' => $queue->get_pending_count(),
	);
}

/**
 * Send event to Umami API.
 *
 * @param string $umami_host       Umami API host URL.
 * @param string $umami_website_id Website ID.
 * @param string $event_name       Event name.
 * @param array  $event_data       Event data.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function umami_send_event_to_api( $umami_host, $umami_website_id, $event_name, $event_data ) {
	$url = trailingslashit( $umami_host ) . 'api/send';

	$payload = array(
		'type'    => 'event',
		'payload' => array(
			'website' => $umami_website_id,
			'name'    => $event_name,
			'data'    => $event_data,
			'url'     => home_url( $_SERVER['REQUEST_URI'] ?? '/' ),
		),
	);

	$response = wp_remote_post(
		$url,
		array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'Umami WP Connect/' . UMAMI_WP_VERSION,
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 10,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );

	if ( $status_code < 200 || $status_code >= 300 ) {
		$body = wp_remote_retrieve_body( $response );
		return new WP_Error(
			'umami_api_error',
			sprintf( 'Umami API returned status %d: %s', $status_code, $body )
		);
	}

	return true;
}

/**
 * Cleanup old processed events from the queue.
 *
 * Runs daily to prevent table growth. Keeps processed events for 7 days
 * and failed events indefinitely for manual review.
 */
function umami_cleanup_processed_events() {
	require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-persistent-event-queue.php';

	$queue = new Persistent_Event_Queue();
	
	// Clean up events older than 7 days.
	$deleted = $queue->cleanup_processed( 7 );

	error_log( "[Umami Queue Cleanup] Deleted {$deleted} old processed events" );

	return array(
		'status'  => 'success',
		'deleted' => $deleted,
	);
}

// Register custom cron interval for queue processing (5 minutes).
add_filter(
	'cron_schedules',
	function( $schedules ) {
		$schedules['umami_queue_interval'] = array(
			'interval' => 300, // 5 minutes in seconds.
			'display'  => esc_html__( 'Every 5 Minutes (Umami Queue)', 'first8marketing-track' ),
		);
		return $schedules;
	}
);

// Hook the queue processor to the scheduled event.
add_action( 'umami_process_event_queue', 'umami_process_pending_events' );

// Hook the cleanup function to the scheduled event.
add_action( 'umami_cleanup_event_queue', 'umami_cleanup_processed_events' );