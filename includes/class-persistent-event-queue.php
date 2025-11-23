<?php
/**
 * Persistent Event Queue
 * 
 * Replaces transient-based queue with database-backed storage
 * Prevents data loss from cache expiry, server restarts, or processing failures
 *
 * @package UmamiWPConnect
 * @since 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistent Event Queue Class
 */
class Persistent_Event_Queue {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Database instance
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Maximum retry attempts
	 *
	 * @var int
	 */
	private $max_retries = 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'umami_event_queue';
	}

	/**
	 * Create queue table on plugin activation
	 *
	 * @return void
	 */
	public function create_table() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_name varchar(255) NOT NULL,
			event_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			retry_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			failed_at datetime DEFAULT NULL,
			error_message text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY retry_count (retry_count)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Log table creation.
		error_log( '[Umami Queue] Database table created: ' . $this->table_name );
	}

	/**
	 * Enqueue event
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_data Event data.
	 * @return int|false Event ID or false on failure.
	 */
	public function enqueue( $event_name, $event_data ) {
		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'event_name' => $event_name,
				'event_data' => wp_json_encode( $event_data ),
				'status'     => 'pending',
				'retry_count' => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			error_log( '[Umami Queue] Failed to enqueue event: ' . $this->wpdb->last_error );
			return false;
		}

		$event_id = $this->wpdb->insert_id;

		// Schedule batch processing if not already scheduled.
		if ( ! wp_next_scheduled( 'umami_process_event_queue' ) ) {
			wp_schedule_single_event( time() + 60, 'umami_process_event_queue' );
		}

		// Also trigger immediate processing if queue is large.
		$queue_size = $this->get_pending_count();
		if ( $queue_size >= 100 ) {
			// Trigger immediate async processing.
			do_action( 'umami_queue_threshold_reached', $queue_size );
		}

		return $event_id;
	}

	/**
	 * Get pending events
	 *
	 * @param int $limit Number of events to retrieve.
	 * @return array
	 */
	public function get_pending( $limit = 100 ) {
		$sql = $this->wpdb->prepare(
			"SELECT id, event_name, event_data, retry_count, created_at
			FROM {$this->table_name}
			WHERE status = %s
			AND retry_count < %d
			ORDER BY created_at ASC
			LIMIT %d",
			'pending',
			$this->max_retries,
			$limit
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $results ) ) {
			return array();
		}

		// Decode JSON data.
		foreach ( $results as &$row ) {
			$row['event_data'] = json_decode( $row['event_data'], true );
		}

		return $results;
	}

	/**
	 * Mark event as processed
	 *
	 * @param int $event_id Event ID.
	 * @return bool
	 */
	public function mark_processed( $event_id ) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'status'       => 'processed',
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $event_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark event as failed and move to dead letter queue
	 *
	 * @param int    $event_id Event ID.
	 * @param string $error_message Error message.
	 * @return bool
	 */
	public function mark_failed( $event_id, $error_message ) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'status'        => 'failed',
				'failed_at'     => current_time( 'mysql' ),
				'error_message' => $error_message,
			),
			array( 'id' => $event_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Log failed event for monitoring.
		error_log(
			sprintf(
				'[Umami Queue] Event %d failed: %s',
				$event_id,
				$error_message
			)
		);

		return false !== $result;
	}

	/**
	 * Increment retry count
	 *
	 * @param int $event_id Event ID.
	 * @return bool
	 */
	public function increment_retry( $event_id ) {
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name}
			SET retry_count = retry_count + 1
			WHERE id = %d",
			$event_id
		);

		return false !== $this->wpdb->query( $sql );
	}

	/**
	 * Get pending count
	 *
	 * @return int
	 */
	public function get_pending_count() {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name}
			WHERE status = %s
			AND retry_count < %d",
			'pending',
			$this->max_retries
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Get failed events count (dead letter queue)
	 *
	 * @return int
	 */
	public function get_failed_count() {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name}
			WHERE status = %s",
			'failed'
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Clean up old processed events
	 *
	 * @param int $days_to_keep Number of days to keep processed events.
	 * @return int Number of events deleted.
	 */
	public function cleanup_processed( $days_to_keep = 7 ) {
		$sql = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name}
			WHERE status = %s
			AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			'processed',
			$days_to_keep
		);

		$deleted = $this->wpdb->query( $sql );

		if ( $deleted > 0 ) {
			error_log( "[Umami Queue] Cleaned up {$deleted} old events" );
		}

		return $deleted;
	}

	/**
	 * Get queue statistics
	 *
	 * @return array
	 */
	public function get_stats() {
		$sql = "SELECT 
			status,
			COUNT(*) as count,
			MAX(created_at) as latest,
			MIN(created_at) as oldest
		FROM {$this->table_name}
		GROUP BY status";

		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		$stats = array(
			'pending'   => 0,
			'processed' => 0,
			'failed'    => 0,
		);

		foreach ( $results as $row ) {
			$stats[ $row['status'] ] = (int) $row['count'];
			$stats[ $row['status'] . '_latest' ] = $row['latest'];
			$stats[ $row['status'] . '_oldest' ] = $row['oldest'];
		}

		return $stats;
	}

	/**
	 * Requeue failed event for retry
	 *
	 * @param int $event_id Event ID.
	 * @return bool
	 */
	public function requeue_failed( $event_id ) {
		$result = $this->wpdb->update(
			$this->table_name,
			array(
				'status'        => 'pending',
				'retry_count'   => 0,
				'failed_at'     => null,
				'error_message' => null,
			),
			array( 'id' => $event_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result ) {
			error_log( "[Umami Queue] Event {$event_id} requeued for retry" );
		}

		return false !== $result;
	}

	/**
	 * Purge all failed events
	 *
	 * @return int Number of events deleted.
	 */
	public function purge_failed() {
		$sql = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name}
			WHERE status = %s",
			'failed'
		);

		$deleted = $this->wpdb->query( $sql );

		if ( $deleted > 0 ) {
			error_log( "[Umami Queue] Purged {$deleted} failed events from dead letter queue" );
		}

		return $deleted;
	}
}