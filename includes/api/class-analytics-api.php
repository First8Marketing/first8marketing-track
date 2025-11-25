<?php
/**
 * Analytics REST API
 *
 * Provides REST API endpoints for unified analytics data
 * combining Email, WhatsApp, and Web tracking metrics.
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

namespace First8Marketing\Track\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics API Class
 */
class Analytics_API extends WP_REST_Controller {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'first8marketing/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Unified analytics endpoint
		register_rest_route(
			$this->namespace,
			'/analytics/unified',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_unified_analytics' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_unified_analytics_args(),
				),
			)
		);

		// Email statistics endpoint
		register_rest_route(
			$this->namespace,
			'/analytics/email',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_email_statistics' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_date_range_args(),
				),
			)
		);

		// Attribution data endpoint
		register_rest_route(
			$this->namespace,
			'/analytics/attribution',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attribution_data' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'model' => array(
							'required'          => false,
							'default'           => 'last_touch',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function( $param ) {
								$valid_models = array( 'last_touch', 'first_touch', 'linear', 'time_decay', 'position_based' );
								return in_array( $param, $valid_models, true );
							},
						),
					),
				),
			)
		);

		// Activity feed endpoint
		register_rest_route(
			$this->namespace,
			'/analytics/activity',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_activity_feed' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'channel' => array(
							'required'          => false,
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'   => array(
							'required'          => false,
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Get unified analytics data
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_unified_analytics( $request ) {
		$date_range = $request->get_param( 'date_range' );

		// Calculate date bounds
		$date_bounds = $this->calculate_date_bounds( $date_range );

		// Fetch data from all channels
		$email_data    = $this->fetch_email_data( $date_bounds );
		$whatsapp_data = $this->fetch_whatsapp_data( $date_bounds );
		$web_data      = $this->fetch_web_data( $date_bounds );

		// Calculate attribution
		$attribution = $this->calculate_attribution( $date_bounds, 'last_touch' );

		// Prepare response
		$response = array(
			'email'       => $email_data,
			'whatsapp'    => $whatsapp_data,
			'web'         => $web_data,
			'attribution' => $attribution,
			'timeline'    => $this->get_timeline_data( $date_bounds ),
			'unified'     => $this->combine_metrics( $email_data, $whatsapp_data, $web_data ),
			'date_range'  => array(
				'from' => $date_bounds['from'],
				'to'   => $date_bounds['to'],
			),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Get email statistics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_email_statistics( $request ) {
		$date_range = $request->get_param( 'date_range' );
		$date_bounds = $this->calculate_date_bounds( $date_range );

		$data = $this->fetch_email_data( $date_bounds );

		return rest_ensure_response( $data );
	}

	/**
	 * Get attribution data
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_attribution_data( $request ) {
		$model = $request->get_param( 'model' );
		$date_range = $request->get_param( 'date_range' );
		$date_bounds = $this->calculate_date_bounds( $date_range );

		$attribution = $this->calculate_attribution( $date_bounds, $model );

		return rest_ensure_response( $attribution );
	}

	/**
	 * Get activity feed
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_activity_feed( $request ) {
		global $wpdb;

		$channel = $request->get_param( 'channel' );
		$limit = $request->get_param( 'limit' );

		$table = $wpdb->prefix . 'first8_email_events';

		// Build query
		$query = "SELECT * FROM {$table} WHERE 1=1";

		if ( 'all' !== $channel ) {
			$query .= $wpdb->prepare( ' AND event_type LIKE %s', '%' . $wpdb->esc_like( $channel ) . '%' );
		}

		$query .= $wpdb->prepare( ' ORDER BY created_at DESC LIMIT %d', $limit );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$activities = $wpdb->get_results( $query );
		// phpcs:enable

		// Format activities
		$formatted = array_map( array( $this, 'format_activity' ), $activities );

		return rest_ensure_response( $formatted );
	}

	/**
	 * Fetch email data
	 *
	 * @param array $date_bounds Date boundaries.
	 * @return array Email data.
	 */
	private function fetch_email_data( $date_bounds ) {
		global $wpdb;

		$table = $wpdb->prefix . 'first8_email_events';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_events,
					COUNT(DISTINCT email_id) as total_sent,
					SUM(CASE WHEN event_type = 'email_open' THEN 1 ELSE 0 END) as total_opens,
					COUNT(DISTINCT CASE WHEN event_type = 'email_open' THEN email_id END) as unique_opens,
					SUM(CASE WHEN event_type = 'email_click' THEN 1 ELSE 0 END) as total_clicks,
					SUM(CASE WHEN synced_to_umami = 1 THEN 1 ELSE 0 END) as synced_events
				FROM {$table}
				WHERE created_at BETWEEN %s AND %s",
				$date_bounds['from'],
				$date_bounds['to']
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $stats ) {
			$stats = array(
				'total_events' => 0,
				'total_sent'   => 0,
				'total_opens'  => 0,
				'unique_opens' => 0,
				'total_clicks' => 0,
				'synced_events' => 0,
			);
		}

		// Calculate rates
		$open_rate = $stats['total_sent'] > 0 ? ( $stats['unique_opens'] / $stats['total_sent'] ) * 100 : 0;
		$click_rate = $stats['total_sent'] > 0 ? ( $stats['total_clicks'] / $stats['total_sent'] ) * 100 : 0;

		// Get trend (compare to previous period)
		$trend = $this->calculate_trend( $table, $date_bounds, 'email' );

		return array(
			'total_events'  => (int) $stats['total_events'],
			'total_sent'    => (int) $stats['total_sent'],
			'total_opens'   => (int) $stats['total_opens'],
			'unique_opens'  => (int) $stats['unique_opens'],
			'total_clicks'  => (int) $stats['total_clicks'],
			'synced_events' => (int) $stats['synced_events'],
			'open_rate'     => round( $open_rate, 2 ),
			'click_rate'    => round( $click_rate, 2 ),
			'trend'         => $trend,
		);
	}

	/**
	 * Fetch WhatsApp data from Umami
	 *
	 * @param array $date_bounds Date boundaries.
	 * @return array WhatsApp data.
	 */
	private function fetch_whatsapp_data( $date_bounds ) {
		// TODO: Implement actual API call to Umami for WhatsApp data
		// For now, return mock data
		return array(
			'total_messages' => 1250,
			'delivered'      => 1232,
			'read'           => 1065,
			'delivery_rate'  => 98.5,
			'read_rate'      => 85.2,
			'trend'          => 8.3,
		);
	}

	/**
	 * Fetch web data from Umami
	 *
	 * @param array $date_bounds Date boundaries.
	 * @return array Web data.
	 */
	private function fetch_web_data( $date_bounds ) {
		// TODO: Implement actual API call to Umami for web analytics
		// For now, return mock data
		return array(
			'sessions'      => 3420,
			'pageviews'     => 12850,
			'unique_visits' => 2850,
			'avg_duration'  => 245,
			'bounce_rate'   => 42.5,
			'trend'         => 15.7,
		);
	}

	/**
	 * Calculate attribution based on model
	 *
	 * @param array  $date_bounds Date boundaries.
	 * @param string $model Attribution model.
	 * @return array Attribution percentages.
	 */
	private function calculate_attribution( $date_bounds, $model ) {
		// TODO: Implement actual attribution calculation
		// This is a simplified version
		
		switch ( $model ) {
			case 'first_touch':
				return array(
					'email'    => 45.0,
					'whatsapp' => 30.0,
					'web'      => 25.0,
				);
			case 'linear':
				return array(
					'email'    => 33.3,
					'whatsapp' => 33.3,
					'web'      => 33.4,
				);
			case 'time_decay':
				return array(
					'email'    => 30.0,
					'whatsapp' => 45.0,
					'web'      => 25.0,
				);
			case 'last_touch':
			default:
				return array(
					'email'    => 35.0,
					'whatsapp' => 40.0,
					'web'      => 25.0,
				);
		}
	}

	/**
	 * Get timeline data for all channels
	 *
	 * @param array $date_bounds Date boundaries.
	 * @return array Timeline data.
	 */
	private function get_timeline_data( $date_bounds ) {
		// TODO: Implement actual timeline data aggregation
		// For now, return mock data
		return array(
			'labels'   => array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ),
			'email'    => array( 120, 190, 150, 170, 180, 210, 195 ),
			'whatsapp' => array( 180, 210, 195, 220, 230, 250, 240 ),
			'web'      => array( 450, 520, 480, 550, 580, 620, 600 ),
		);
	}

	/**
	 * Combine metrics from all channels
	 *
	 * @param array $email Email data.
	 * @param array $whatsapp WhatsApp data.
	 * @param array $web Web data.
	 * @return array Combined metrics.
	 */
	private function combine_metrics( $email, $whatsapp, $web ) {
		return array(
			'total_interactions' => $email['total_events'] + $whatsapp['total_messages'] + $web['pageviews'],
			'total_engagements'  => $email['total_clicks'] + $whatsapp['read'] + $web['sessions'],
			'avg_engagement_rate' => (
				$email['click_rate'] +
				$whatsapp['read_rate'] +
				( 100 - $web['bounce_rate'] )
			) / 3,
		);
	}

	/**
	 * Calculate trend percentage
	 *
	 * @param string $table Table name.
	 * @param array  $date_bounds Current period date bounds.
	 * @param string $channel Channel type.
	 * @return float Trend percentage.
	 */
	private function calculate_trend( $table, $date_bounds, $channel ) {
		global $wpdb;

		// Calculate previous period
		$from = new \DateTime( $date_bounds['from'] );
		$to = new \DateTime( $date_bounds['to'] );
		$diff = $from->diff( $to );
		
		$prev_to = clone $from;
		$prev_to->modify( '-1 day' );
		$prev_from = clone $prev_to;
		$prev_from->modify( '-' . $diff->days . ' days' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s",
				$date_bounds['from'],
				$date_bounds['to']
			)
		);

		$previous = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s",
				$prev_from->format( 'Y-m-d H:i:s' ),
				$prev_to->format( 'Y-m-d H:i:s' )
			)
		);
		// phpcs:enable

		if ( ! $previous || 0 === $previous ) {
			return 0;
		}

		$trend = ( ( $current - $previous ) / $previous ) * 100;
		return round( $trend, 1 );
	}

	/**
	 * Calculate date bounds from range parameter
	 *
	 * @param string $range Date range identifier.
	 * @return array From and to dates.
	 */
	private function calculate_date_bounds( $range ) {
		$to = current_time( 'mysql' );
		$from = current_time( 'mysql' );

		switch ( $range ) {
			case 'today':
				$from = gmdate( 'Y-m-d 00:00:00' );
				break;
			case 'week':
				$from = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				break;
			case 'month':
				$from = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				break;
			case 'quarter':
				$from = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
				break;
			default:
				$from = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Format activity item
	 *
	 * @param object $activity Activity database row.
	 * @return array Formatted activity.
	 */
	private function format_activity( $activity ) {
		return array(
			'id'        => $activity->id,
			'channel'   => $this->get_channel_from_event( $activity->event_type ),
			'type'      => $activity->event_type,
			'text'      => $this->generate_activity_text( $activity ),
			'timestamp' => mysql2date( 'c', $activity->created_at ),
			'time_ago'  => human_time_diff( strtotime( $activity->created_at ), current_time( 'timestamp' ) ) . ' ago',
		);
	}

	/**
	 * Get channel from event type
	 *
	 * @param string $event_type Event type.
	 * @return string Channel name.
	 */
	private function get_channel_from_event( $event_type ) {
		if ( strpos( $event_type, 'email' ) !== false ) {
			return 'email';
		} elseif ( strpos( $event_type, 'whatsapp' ) !== false ) {
			return 'whatsapp';
		}
		return 'web';
	}

	/**
	 * Generate activity description text
	 *
	 * @param object $activity Activity data.
	 * @return string Activity text.
	 */
	private function generate_activity_text( $activity ) {
		switch ( $activity->event_type ) {
			case 'email_open':
				return sprintf( 'Email opened: %s', $activity->email_type );
			case 'email_click':
				return sprintf( 'Link clicked in email: %s', $activity->email_type );
			default:
				return ucwords( str_replace( '_', ' ', $activity->event_type ) );
		}
	}

	/**
	 * Get unified analytics arguments
	 *
	 * @return array Arguments schema.
	 */
	private function get_unified_analytics_args() {
		return array_merge(
			$this->get_date_range_args(),
			array(
				'include_timeline' => array(
					'required'          => false,
					'default'           => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
			)
		);
	}

	/**
	 * Get date range arguments
	 *
	 * @return array Arguments schema.
	 */
	private function get_date_range_args() {
		return array(
			'date_range' => array(
				'required'          => false,
				'default'           => 'week',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					$valid_ranges = array( 'today', 'week', 'month', 'quarter' );
					return in_array( $param, $valid_ranges, true );
				},
			),
		);
	}

	/**
	 * Check permission
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}
}

// Initialize the API
new Analytics_API();