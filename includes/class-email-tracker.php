<?php
/**
 * Email Tracking System
 *
 * Handles email open tracking (via pixel) and link click tracking.
 * Integrates with WordPress core emails and WooCommerce emails.
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

namespace First8Marketing\Track;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Tracker Class
 *
 * Main class for email tracking functionality.
 */
class Email_Tracker {

	/**
	 * Single instance
	 *
	 * @var Email_Tracker
	 */
	private static $instance = null;

	/**
	 * Database table names
	 *
	 * @var array
	 */
	private $tables = array();

	/**
	 * Rate limit cache
	 *
	 * @var array
	 */
	private $rate_limit_cache = array();

	/**
	 * Secret key for HMAC
	 *
	 * @var string
	 */
	private $secret_key = null;

	/**
	 * Get singleton instance
	 *
	 * @return Email_Tracker
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
		global $wpdb;

		$this->tables = array(
			'events'    => $wpdb->prefix . 'first8_email_events',
			'campaigns' => $wpdb->prefix . 'first8_email_campaigns',
		);

		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	public function init_hooks() {
		// Activation hook handled in main plugin file
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_tracking_requests' ), 1 );

		// Email interception hooks
		add_filter( 'wp_mail', array( $this, 'intercept_wp_mail' ), 999, 1 );
		
		// WooCommerce email hooks
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_mail_content', array( $this, 'intercept_woocommerce_email' ), 999, 2 );
		}
	}

	/**
	 * Install database tables
	 *
	 * @return bool True on success.
	 */
	public function install_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Email events table
		$sql_events = "CREATE TABLE IF NOT EXISTS {$this->tables['events']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tenant_id varchar(50) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(50) NOT NULL,
			email_type varchar(100) NOT NULL,
			campaign_id varchar(100) NOT NULL,
			token varchar(255) NOT NULL,
			ip_address varchar(45) NOT NULL,
			user_agent text NOT NULL,
			email_client varchar(100) DEFAULT NULL,
			device_type varchar(50) DEFAULT NULL,
			link_id varchar(100) DEFAULT NULL,
			target_url text DEFAULT NULL,
			referrer text DEFAULT NULL,
			utm_source varchar(100) DEFAULT NULL,
			utm_campaign varchar(100) DEFAULT NULL,
			utm_medium varchar(100) DEFAULT NULL,
			utm_content varchar(100) DEFAULT NULL,
			utm_term varchar(100) DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			synced_to_umami tinyint(1) NOT NULL DEFAULT 0,
			umami_event_id varchar(100) DEFAULT NULL,
			sync_attempts int(11) NOT NULL DEFAULT 0,
			last_sync_attempt datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_tenant_user (tenant_id, user_id),
			KEY idx_event_type (event_type),
			KEY idx_campaign (campaign_id),
			KEY idx_token (token),
			KEY idx_sync_status (synced_to_umami, sync_attempts),
			KEY idx_created (created_at)
		) $charset_collate;";

		// Email campaigns table
		$sql_campaigns = "CREATE TABLE IF NOT EXISTS {$this->tables['campaigns']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tenant_id varchar(50) NOT NULL,
			campaign_id varchar(100) NOT NULL UNIQUE,
			campaign_name varchar(255) NOT NULL,
			campaign_type varchar(100) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			track_opens tinyint(1) NOT NULL DEFAULT 1,
			track_clicks tinyint(1) NOT NULL DEFAULT 1,
			total_sent int(11) NOT NULL DEFAULT 0,
			total_opens int(11) NOT NULL DEFAULT 0,
			total_clicks int(11) NOT NULL DEFAULT 0,
			unique_opens int(11) NOT NULL DEFAULT 0,
			unique_clicks int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_tenant_campaign (tenant_id, campaign_id),
			KEY idx_status (status)
		) $charset_collate;";

		dbDelta( $sql_events );
		dbDelta( $sql_campaigns );

		error_log( '[F8M Email Tracker] Database tables created/updated' );

		// Ensure secret key exists
		$this->get_or_create_secret_key();

		return true;
	}

	/**
	 * Get or create secret key for HMAC
	 *
	 * @return string Secret key.
	 */
	private function get_or_create_secret_key() {
		if ( null !== $this->secret_key ) {
			return $this->secret_key;
		}

		$option_name = 'f8m_email_tracking_secret';
		$secret = get_option( $option_name );

		if ( empty( $secret ) ) {
			// Generate cryptographically secure random secret
			$secret = bin2hex( random_bytes( 32 ) );
			
			// Encrypt before storing
			if ( class_exists( 'First8Marketing\Track\Encryption_Helper' ) ) {
				$secret = Encryption_Helper::encrypt( $secret );
			}
			
			add_option( $option_name, $secret, '', 'no' );
			error_log( '[F8M Email Tracker] Generated new secret key' );
		}

		// Decrypt if encrypted
		if ( class_exists( 'First8Marketing\Track\Encryption_Helper' ) ) {
			$decrypted = Encryption_Helper::decrypt( $secret );
			if ( ! empty( $decrypted ) ) {
				$secret = $decrypted;
			}
		}

		$this->secret_key = $secret;
		return $secret;
	}

	/**
	 * Generate tracking token
	 *
	 * Format: v1.tenant_id.user_id.campaign_id.email_type.timestamp.hmac
	 *
	 * @param array $metadata Token metadata.
	 * @return string Generated token.
	 */
	public function generate_token( $metadata ) {
		$version = 'v1';
		$tenant_id = sanitize_text_field( $metadata['tenant_id'] ?? 'default' );
		$user_id = absint( $metadata['user_id'] ?? 0 );
		$campaign_id = sanitize_text_field( $metadata['campaign_id'] ?? '' );
		$email_type = sanitize_text_field( $metadata['email_type'] ?? 'generic' );
		$timestamp = time();

		// Build payload
		$payload = implode( '.', array(
			$version,
			$tenant_id,
			$user_id,
			$campaign_id,
			$email_type,
			$timestamp,
		) );

		// Generate HMAC signature
		$secret = $this->get_or_create_secret_key();
		$signature = hash_hmac( 'sha256', $payload, $secret );

		// Use first 16 chars of signature to keep URL shorter
		$token = $payload . '.' . substr( $signature, 0, 16 );

		return $token;
	}

	/**
	 * Validate tracking token
	 *
	 * @param string $token Token to validate.
	 * @return array|false Parsed token data or false if invalid.
	 */
	public function validate_token( $token ) {
		$parts = explode( '.', $token );

		// Must have 7 parts: version, tenant, user, campaign, email_type, timestamp, signature
		if ( count( $parts ) !== 7 ) {
			error_log( '[F8M Email Tracker] Invalid token format: wrong part count' );
			return false;
		}

		list( $version, $tenant_id, $user_id, $campaign_id, $email_type, $timestamp, $provided_sig ) = $parts;

		// Validate version
		if ( 'v1' !== $version ) {
			error_log( '[F8M Email Tracker] Invalid token version: ' . $version );
			return false;
		}

		// Check expiration (90 days)
		$max_age = 90 * DAY_IN_SECONDS;
		if ( ( time() - (int) $timestamp ) > $max_age ) {
			error_log( '[F8M Email Tracker] Token expired' );
			return false;
		}

		// Rebuild payload and verify signature
		$payload = implode( '.', array( $version, $tenant_id, $user_id, $campaign_id, $email_type, $timestamp ) );
		$secret = $this->get_or_create_secret_key();
		$expected_sig = substr( hash_hmac( 'sha256', $payload, $secret ), 0, 16 );

		if ( ! hash_equals( $expected_sig, $provided_sig ) ) {
			error_log( '[F8M Email Tracker] Token signature mismatch' );
			return false;
		}

		return array(
			'version' => $version,
			'tenant_id' => $tenant_id,
			'user_id' => (int) $user_id,
			'campaign_id' => $campaign_id,
			'email_type' => $email_type,
			'timestamp' => (int) $timestamp,
		);
	}

	/**
	 * Check rate limit
	 *
	 * @param string $ip IP address.
	 * @param string $action Action type (pixel|click).
	 * @return bool True if within limit.
	 */
	private function check_rate_limit( $ip, $action ) {
		$transient_key = 'f8m_rate_limit_' . $action . '_' . md5( $ip );
		$count = get_transient( $transient_key );

		$limits = array(
			'pixel' => 100,
			'click' => 200,
		);

		$limit = $limits[ $action ] ?? 100;

		if ( false === $count ) {
			$count = 0;
		}

		if ( $count >= $limit ) {
			error_log( "[F8M Email Tracker] Rate limit exceeded for IP {$ip}, action {$action}" );
			return false;
		}

		set_transient( $transient_key, $count + 1, 60 );
		return true;
	}

	/**
	 * Register rewrite rules for tracking endpoints
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^email/pixel/([^/]+)\.gif$',
			'index.php?f8m_email_pixel=1&t=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^email/link/([^/]+)$',
			'index.php?f8m_email_link=1&t=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars Query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'f8m_email_pixel';
		$vars[] = 'f8m_email_link';
		$vars[] = 't';
		return $vars;
	}

	/**
	 * Handle tracking requests
	 */
	public function handle_tracking_requests() {
		// Handle pixel tracking
		if ( get_query_var( 'f8m_email_pixel' ) ) {
			$this->handle_pixel_request();
			exit;
		}

		// Handle link clicks
		if ( get_query_var( 'f8m_email_link' ) ) {
			$this->handle_link_click();
			exit;
		}
	}

	/**
	 * Handle pixel tracking request
	 */
	private function handle_pixel_request() {
		$token = sanitize_text_field( get_query_var( 't' ) );

		// Get client IP
		$ip = $this->get_client_ip();

		// Check rate limit
		if ( ! $this->check_rate_limit( $ip, 'pixel' ) ) {
			$this->serve_blank_pixel();
			return;
		}

		// Validate token
		$token_data = $this->validate_token( $token );
		if ( false === $token_data ) {
			error_log( '[F8M Email Tracker] Invalid pixel token' );
			$this->serve_blank_pixel();
			return;
		}

		// Record open event
		$this->record_event( array(
			'event_type' => 'email_open',
			'token' => $token,
			'token_data' => $token_data,
			'ip_address' => $ip,
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
		) );

		// Serve pixel
		$this->serve_blank_pixel();
	}

	/**
	 * Serve 1x1 transparent GIF
	 */
	private function serve_blank_pixel() {
		header( 'Content-Type: image/gif' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		// 1x1 transparent GIF (43 bytes)
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
	}

	/**
	 * Handle link click tracking
	 */
	private function handle_link_click() {
		$token = sanitize_text_field( get_query_var( 't' ) );

		// Get client IP
		$ip = $this->get_client_ip();

		// Check rate limit
		if ( ! $this->check_rate_limit( $ip, 'click' ) ) {
			wp_die( esc_html__( 'Too many requests. Please try again later.', 'first8marketing-track' ), 'Rate Limit Exceeded', array( 'response' => 429 ) );
		}

		// Validate token (for link clicks, token format may differ)
		$link_data = $this->get_link_data( $token );
		if ( false === $link_data ) {
			wp_die( esc_html__( 'Invalid or expired link.', 'first8marketing-track' ), 'Invalid Link', array( 'response' => 400 ) );
		}

		// Validate target URL for SSRF protection
		if ( ! $this->is_safe_redirect_url( $link_data['target_url'] ) ) {
			error_log( '[F8M Email Tracker] Unsafe redirect URL blocked: ' . $link_data['target_url'] );
			wp_die( esc_html__( 'Invalid redirect URL.', 'first8marketing-track' ), 'Security Error', array( 'response' => 403 ) );
		}

		// Record click event
		$this->record_event( array(
			'event_type' => 'email_click',
			'token' => $token,
			'token_data' => $link_data['token_data'],
			'link_id' => $link_data['link_id'],
			'target_url' => $link_data['target_url'],
			'ip_address' => $ip,
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'referrer' => sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ),
		) );

		// Redirect to target URL
		wp_safe_redirect( $link_data['target_url'], 302 );
		exit;
	}

	/**
	 * Get link data from token
	 *
	 * @param string $token Link token.
	 * @return array|false Link data or false.
	 */
	private function get_link_data( $token ) {
		global $wpdb;

		// For now, use a simplified approach
		// In production, link tokens would be stored in a links table
		// For this implementation, we'll decode the token directly
		
		$token_data = $this->validate_token( $token );
		if ( false === $token_data ) {
			return false;
		}

		// Extract target URL from metadata (stored when link was created)
		// This is a simplified version - full implementation would query a links table
		return array(
			'token_data' => $token_data,
			'link_id' => substr( md5( $token ), 0, 8 ),
			'target_url' => home_url(), // Placeholder - would be actual stored URL
		);
	}

	/**
	 * Validate redirect URL to prevent SSRF attacks
	 *
	 * @param string $url Target URL.
	 * @return bool True if safe.
	 */
	private function is_safe_redirect_url( $url ) {
		$parsed = wp_parse_url( $url );

		if ( ! $parsed || ! isset( $parsed['host'] ) ) {
			return false;
		}

		// Block localhost and local IPs
		$blocked_hosts = array( 'localhost', '127.0.0.1', '0.0.0.0', '::1' );
		if ( in_array( strtolower( $parsed['host'] ), $blocked_hosts, true ) ) {
			return false;
		}

		// Block AWS metadata endpoint
		if ( '169.254.169.254' === $parsed['host'] ) {
			return false;
		}

		// Only allow HTTP/HTTPS
		if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return false;
		}

		// Check for private IP ranges
		$ip = gethostbyname( $parsed['host'] );
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * Record tracking event
	 *
	 * @param array $event_data Event data.
	 * @return int|false Event ID or false on failure.
	 */
	private function record_event( $event_data ) {
		global $wpdb;

		$token_data = $event_data['token_data'];

		// Detect email client and device type
		$user_agent = $event_data['user_agent'] ?? '';
		$email_client = $this->detect_email_client( $user_agent );
		$device_type = $this->detect_device_type( $user_agent );

		$data = array(
			'tenant_id' => $token_data['tenant_id'],
			'user_id' => $token_data['user_id'],
			'event_type' => $event_data['event_type'],
			'email_type' => $token_data['email_type'],
			'campaign_id' => $token_data['campaign_id'],
			'token' => $event_data['token'],
			'ip_address' => $event_data['ip_address'],
			'user_agent' => $user_agent,
			'email_client' => $email_client,
			'device_type' => $device_type,
			'link_id' => $event_data['link_id'] ?? null,
			'target_url' => $event_data['target_url'] ?? null,
			'referrer' => $event_data['referrer'] ?? null,
			'created_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->tables['events'],
			$data,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( '[F8M Email Tracker] Failed to record event: ' . $wpdb->last_error );
			return false;
		}

		$event_id = $wpdb->insert_id;

		// Queue for Umami sync
		$this->queue_umami_sync( $event_id, $event_data['event_type'], $token_data );

		return $event_id;
	}

	/**
	 * Queue event for Umami sync
	 *
	 * @param int    $event_id Event ID.
	 * @param string $event_type Event type.
	 * @param array  $token_data Token data.
	 */
	private function queue_umami_sync( $event_id, $event_type, $token_data ) {
		if ( ! class_exists( 'Persistent_Event_Queue' ) ) {
			return;
		}

		$queue = new \Persistent_Event_Queue();
		$queue->enqueue(
			'email_' . $event_type,
			array(
				'event_id' => $event_id,
				'event_type' => $event_type,
				'campaign_id' => $token_data['campaign_id'],
				'email_type' => $token_data['email_type'],
				'tenant_id' => $token_data['tenant_id'],
				'user_id' => $token_data['user_id'],
				'timestamp' => time(),
			)
		);
	}

	/**
	 * Detect email client from user agent
	 *
	 * @param string $user_agent User agent string.
	 * @return string Email client name.
	 */
	private function detect_email_client( $user_agent ) {
		$clients = array(
			'Gmail' => '/Gmail/i',
			'Outlook' => '/Outlook|Microsoft Office/i',
			'Apple Mail' => '/AppleWebKit.*Safari/i',
			'Yahoo Mail' => '/YahooMailProxy/i',
			'Thunderbird' => '/Thunderbird/i',
		);

		foreach ( $clients as $name => $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return $name;
			}
		}

		return 'Unknown';
	}

	/**
	 * Detect device type from user agent
	 *
	 * @param string $user_agent User agent string.
	 * @return string Device type.
	 */
	private function detect_device_type( $user_agent ) {
		if ( preg_match( '/mobile|android|iphone|ipod/i', $user_agent ) ) {
			return 'mobile';
		}
		if ( preg_match( '/tablet|ipad/i', $user_agent ) ) {
			return 'tablet';
		}
		return 'desktop';
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // CloudFlare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Intercept WordPress core emails
	 *
	 * @param array $args wp_mail arguments.
	 * @return array Modified arguments.
	 */
	public function intercept_wp_mail( $args ) {
		// Skip if tracking disabled
		if ( ! $this->is_tracking_enabled() ) {
			return $args;
		}

		// Only process HTML emails
		if ( empty( $args['message'] ) || ! $this->is_html_email( $args ) ) {
			return $args;
		}

		// Generate campaign metadata
		$metadata = array(
			'tenant_id' => $this->get_tenant_id(),
			'user_id' => $this->get_user_id_from_email( $args['to'] ),
			'email_type' => $this->detect_email_type( $args['subject'] ?? '' ),
			'campaign_id' => $this->generate_campaign_id( 'wordpress' ),
		);

		// Insert tracking pixel
		$args['message'] = $this->insert_tracking_pixel( $args['message'], $metadata );

		// Transform links (basic implementation)
		$args['message'] = $this->transform_email_links( $args['message'], $metadata );

		return $args;
	}

	/**
	 * Intercept WooCommerce emails
	 *
	 * @param string    $content Email content.
	 * @param \WC_Email $email Email object.
	 * @return string Modified content.
	 */
	public function intercept_woocommerce_email( $content, $email ) {
		// Skip if tracking disabled
		if ( ! $this->is_tracking_enabled() ) {
			return $content;
		}

		// Generate campaign metadata
		$metadata = array(
			'tenant_id' => $this->get_tenant_id(),
			'user_id' => $this->get_user_id_from_wc_email( $email ),
			'email_type' => 'woocommerce_' . $email->id,
			'campaign_id' => $this->generate_campaign_id( 'woocommerce_' . $email->id ),
		);

		// Insert tracking pixel
		$content = $this->insert_tracking_pixel( $content, $metadata );

		// Transform links
		$content = $this->transform_email_links( $content, $metadata );

		return $content;
	}

	/**
	 * Insert tracking pixel into HTML
	 *
	 * @param string $html Email HTML.
	 * @param array  $metadata Tracking metadata.
	 * @return string Modified HTML.
	 */
	private function insert_tracking_pixel( $html, $metadata ) {
		$token = $this->generate_token( $metadata );
		$pixel_url = home_url( "/email/pixel/{$token}.gif" );

		$pixel_html = sprintf(
			'<img src="%s" width="1" height="1" alt="" style="display:block;border:0;outline:0;" />',
			esc_url( $pixel_url )
		);

		// Insert before closing body tag
		if ( stripos( $html, '</body>' ) !== false ) {
			$html = str_ireplace( '</body>', $pixel_html . '</body>', $html );
		} else {
			$html .= $pixel_html;
		}

		return $html;
	}

	/**
	 * Transform email links for tracking
	 *
	 * @param string $html Email HTML.
	 * @param array  $metadata Tracking metadata.
	 * @return string Modified HTML.
	 */
	private function transform_email_links( $html, $metadata ) {
		// Basic link transformation using regex
		// For production, consider using DOMDocument for more robust parsing
		
		$pattern = '/<a\s+([^>]*href=["\']([^"\']+)["\'][^>]*)>/i';
		
		$html = preg_replace_callback( $pattern, function( $matches ) use ( $metadata ) {
			$full_tag = $matches[0];
			$original_url = $matches[2];

			// Skip if not HTTP(S) or already a tracking link
			if ( ! preg_match( '/^https?:\/\//i', $original_url ) || strpos( $original_url, '/email/link/' ) !== false ) {
				return $full_tag;
			}

			// Generate tracking URL
			$tracking_url = $this->generate_tracking_link( $original_url, $metadata );

			// Replace URL in tag
			return str_replace( $original_url, $tracking_url, $full_tag );
		}, $html );

		return $html;
	}

	/**
	 * Generate tracking link
	 *
	 * @param string $original_url Original URL.
	 * @param array  $metadata Tracking metadata.
	 * @return string Tracking URL.
	 */
	private function generate_tracking_link( $original_url, $metadata ) {
		// For now, return original URL
		// Full implementation would create link record and return tracking URL
		// TODO: Integrate with Link_Manager for proper link tracking
		return $original_url;
	}

	/**
	 * Check if tracking is enabled
	 *
	 * @return bool True if enabled.
	 */
	private function is_tracking_enabled() {
		return (bool) get_option( 'f8m_email_tracking_enabled', true );
	}

	/**
	 * Check if email is HTML
	 *
	 * @param array $args Email arguments.
	 * @return bool True if HTML.
	 */
	private function is_html_email( $args ) {
		$headers = $args['headers'] ?? array();
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", $headers );
		}

		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Content-Type: text/html' ) !== false ) {
				return true;
			}
		}

		// Check if message contains HTML tags
		return preg_match( '/<[^>]+>/', $args['message'] ?? '' );
	}

	/**
	 * Get tenant ID
	 *
	 * @return string Tenant ID.
	 */
	private function get_tenant_id() {
		return get_option( 'f8m_tenant_id', 'default' );
	}

	/**
	 * Get user ID from email address
	 *
	 * @param string $email Email address.
	 * @return int User ID or 0.
	 */
	private function get_user_id_from_email( $email ) {
		if ( is_array( $email ) ) {
			$email = $email[0] ?? '';
		}

		$user = get_user_by( 'email', $email );
		return $user ? $user->ID : 0;
	}

	/**
	 * Get user ID from WooCommerce email
	 *
	 * @param \WC_Email $email Email object.
	 * @return int User ID or 0.
	 */
	private function get_user_id_from_wc_email( $email ) {
		if ( isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
			return $email->object->get_customer_id();
		}
		return 0;
	}

	/**
	 * Detect email type from subject
	 *
	 * @param string $subject Email subject.
	 * @return string Email type.
	 */
	private function detect_email_type( $subject ) {
		$types = array(
			'password_reset' => '/reset|password/i',
			'new_user' => '/welcome|new account/i',
			'comment' => '/comment|reply/i',
		);

		foreach ( $types as $type => $pattern ) {
			if ( preg_match( $pattern, $subject ) ) {
				return $type;
			}
		}

		return 'generic';
	}

	/**
	 * Generate campaign ID
	 *
	 * @param string $prefix Campaign prefix.
	 * @return string Campaign ID.
	 */
	private function generate_campaign_id( $prefix ) {
		return $prefix . '_' . date( 'Ymd' );
	}
}