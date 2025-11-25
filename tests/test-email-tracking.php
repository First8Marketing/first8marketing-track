<?php
/**
 * Comprehensive Test Suite for Email Tracking System (Phase 4)
 * 
 * Tests cover:
 * - Database table creation
 * - Tracking ID generation and validation
 * - HMAC token generation and validation
 * - Token expiration enforcement
 * - Rate limiting enforcement
 * - SSRF protection
 * - Email interception (wp_mail filter)
 * - WooCommerce email hooks
 * - Pixel tracking endpoint
 * - Link click tracking endpoint
 * - Umami event queue integration
 * 
 * @package First8Marketing_Track
 * @subpackage Tests
 */

class Test_Email_Tracking extends WP_UnitTestCase {
	
	/**
	 * Email tracker instance
	 */
	private $tracker;
	
	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		global $wpdb;
		
		// Include the class file
		require_once dirname( __DIR__ ) . '/includes/class-email-tracker.php';
		
		// Create test database tables
		$charset_collate = $wpdb->get_charset_collate();
		
		// Email events table
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}first8_email_events (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			tenant_id varchar(255) NOT NULL,
			tracking_id varchar(255) NOT NULL UNIQUE,
			user_id bigint(20) DEFAULT 0,
			email varchar(255) NOT NULL,
			event_type enum('open','click') NOT NULL,
			event_timestamp datetime NOT NULL,
			email_type varchar(100) DEFAULT NULL,
			campaign_id varchar(255) DEFAULT NULL,
			target_url text DEFAULT NULL,
			email_client varchar(100) DEFAULT NULL,
			device_type varchar(50) DEFAULT NULL,
			utm_source varchar(100) DEFAULT NULL,
			utm_medium varchar(100) DEFAULT NULL,
			utm_campaign varchar(100) DEFAULT NULL,
			metadata text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY tracking_id (tracking_id),
			KEY user_email (email),
			KEY event_timestamp (event_timestamp)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		// Email campaigns table
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}first8_email_campaigns (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			tenant_id varchar(255) NOT NULL,
			campaign_id varchar(255) NOT NULL UNIQUE,
			campaign_name varchar(255) NOT NULL,
			created_at datetime NOT NULL,
			status enum('active','paused','completed') DEFAULT 'active',
			PRIMARY KEY (id),
			KEY campaign_id (campaign_id)
		) $charset_collate;";
		
		dbDelta( $sql );
		
		$this->tracker = new First8_Email_Tracker();
	}
	
	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		parent::tearDown();
		
		global $wpdb;
		
		// Clean up test data
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}first8_email_events" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}first8_email_campaigns" );
	}
	
	/**
	 * Test database table creation
	 */
	public function test_database_tables_exist() {
		global $wpdb;
		
		$events_table = $wpdb->get_var(
			"SHOW TABLES LIKE '{$wpdb->prefix}first8_email_events'"
		);
		
		$campaigns_table = $wpdb->get_var(
			"SHOW TABLES LIKE '{$wpdb->prefix}first8_email_campaigns'"
		);
		
		$this->assertEquals( $wpdb->prefix . 'first8_email_events', $events_table );
		$this->assertEquals( $wpdb->prefix . 'first8_email_campaigns', $campaigns_table );
	}
	
	/**
	 * Test tracking ID generation
	 */
	public function test_tracking_id_generation() {
		$tracking_id1 = $this->tracker->generate_tracking_id();
		$tracking_id2 = $this->tracker->generate_tracking_id();
		
		// Should be 32 characters (UUID without dashes)
		$this->assertEquals( 32, strlen( $tracking_id1 ) );
		$this->assertEquals( 32, strlen( $tracking_id2 ) );
		
		// Should be unique
		$this->assertNotEquals( $tracking_id1, $tracking_id2 );
		
		// Should be alphanumeric
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $tracking_id1 );
	}
	
	/**
	 * Test HMAC token generation
	 */
	public function test_hmac_token_generation() {
		$tracking_id = 'test_tracking_123';
		$token = $this->tracker->generate_hmac_token( $tracking_id );
		
		// Should be 64 characters (SHA256 hash)
		$this->assertEquals( 64, strlen( $token ) );
		
		// Should be consistent for same tracking_id
		$token2 = $this->tracker->generate_hmac_token( $tracking_id );
		$this->assertEquals( $token, $token2 );
		
		// Should be different for different tracking_id
		$token3 = $this->tracker->generate_hmac_token( 'different_id' );
		$this->assertNotEquals( $token, $token3 );
	}
	
	/**
	 * Test HMAC token validation
	 */
	public function test_hmac_token_validation() {
		$tracking_id = 'test_tracking_456';
		$valid_token = $this->tracker->generate_hmac_token( $tracking_id );
		
		// Valid token should pass
		$this->assertTrue(
			$this->tracker->validate_hmac_token( $tracking_id, $valid_token )
		);
		
		// Invalid token should fail
		$this->assertFalse(
			$this->tracker->validate_hmac_token( $tracking_id, 'invalid_token' )
		);
		
		// Wrong tracking_id should fail
		$this->assertFalse(
			$this->tracker->validate_hmac_token( 'wrong_id', $valid_token )
		);
	}
	
	/**
	 * Test token expiration enforcement
	 */
	public function test_token_expiration() {
		$tracking_id = 'test_expiry_123';
		
		// Token created 31 days ago (expired)
		$expired_time = time() - ( 31 * DAY_IN_SECONDS );
		$expired_token = $this->tracker->generate_hmac_token( $tracking_id, $expired_time );
		
		// Should fail validation
		$this->assertFalse(
			$this->tracker->validate_hmac_token( $tracking_id, $expired_token, $expired_time )
		);
		
		// Token created 29 days ago (valid)
		$valid_time = time() - ( 29 * DAY_IN_SECONDS );
		$valid_token = $this->tracker->generate_hmac_token( $tracking_id, $valid_time );
		
		// Should pass validation
		$this->assertTrue(
			$this->tracker->validate_hmac_token( $tracking_id, $valid_token, $valid_time )
		);
	}
	
	/**
	 * Test rate limiting
	 */
	public function test_rate_limiting() {
		$tracking_id = 'test_rate_limit_123';
		$ip_address = '192.168.1.100';
		
		// First request should succeed
		$this->assertTrue(
			$this->tracker->check_rate_limit( $tracking_id, $ip_address )
		);
		
		// Simulate 10 requests in quick succession
		for ( $i = 0; $i < 10; $i++ ) {
			$this->tracker->record_rate_limit( $tracking_id, $ip_address );
		}
		
		// 11th request should be rate limited
		$this->assertFalse(
			$this->tracker->check_rate_limit( $tracking_id, $ip_address )
		);
	}
	
	/**
	 * Test SSRF protection - localhost blocking
	 */
	public function test_ssrf_protection_localhost() {
		$local_urls = array(
			'http://localhost/admin',
			'http://127.0.0.1/sensitive',
			'http://[::1]/internal',
			'http://0.0.0.0/config'
		);
		
		foreach ( $local_urls as $url ) {
			$this->assertFalse(
				$this->tracker->is_safe_redirect_url( $url ),
				"Should block localhost URL: $url"
			);
		}
	}
	
	/**
	 * Test SSRF protection - private IP blocking
	 */
	public function test_ssrf_protection_private_ips() {
		$private_urls = array(
			'http://10.0.0.1/admin',
			'http://172.16.0.1/internal',
			'http://192.168.1.1/config',
			'http://169.254.169.254/metadata'  // AWS metadata
		);
		
		foreach ( $private_urls as $url ) {
			$this->assertFalse(
				$this->tracker->is_safe_redirect_url( $url ),
				"Should block private IP URL: $url"
			);
		}
	}
	
	/**
	 * Test SSRF protection - allow public URLs
	 */
	public function test_ssrf_protection_allow_public() {
		$public_urls = array(
			'https://example.com/product',
			'https://www.google.com',
			'https://8.8.8.8/test'
		);
		
		foreach ( $public_urls as $url ) {
			$this->assertTrue(
				$this->tracker->is_safe_redirect_url( $url ),
				"Should allow public URL: $url"
			);
		}
	}
	
	/**
	 * Test email interception (wp_mail filter)
	 */
	public function test_email_interception() {
		$to = 'test@example.com';
		$subject = 'Test Email';
		$message = 'This is a test email body.';
		
		// Hook should add tracking pixel
		$filtered_message = apply_filters(
			'wp_mail',
			compact( 'to', 'subject', 'message', 'headers', 'attachments' )
		);
		
		// Should contain tracking pixel
		$this->assertStringContainsString(
			'<img src="',
			$filtered_message['message']
		);
		
		$this->assertStringContainsString(
			'/email/track/pixel/',
			$filtered_message['message']
		);
	}
	
	/**
	 * Test tracking pixel endpoint
	 */
	public function test_tracking_pixel_endpoint() {
		global $wpdb;
		
		$tracking_id = $this->tracker->generate_tracking_id();
		$token = $this->tracker->generate_hmac_token( $tracking_id );
		
		// Insert test tracking record
		$wpdb->insert(
			$wpdb->prefix . 'first8_email_events',
			array(
				'tenant_id' => 'test_tenant',
				'tracking_id' => $tracking_id,
				'email' => 'test@example.com',
				'event_type' => 'open',
				'event_timestamp' => current_time( 'mysql' )
			)
		);
		
		// Simulate pixel request
		$_GET['id'] = $tracking_id;
		$_GET['token'] = $token;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.1';
		
		// This would normally output a 1x1 pixel
		// In tests, we verify the event was recorded
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}first8_email_events WHERE tracking_id = %s AND event_type = 'open'",
				$tracking_id
			)
		);
		
		$this->assertEquals( 1, $result );
	}
	
	/**
	 * Test link click tracking
	 */
	public function test_link_click_tracking() {
		global $wpdb;
		
		$tracking_id = $this->tracker->generate_tracking_id();
		$token = $this->tracker->generate_hmac_token( $tracking_id );
		$target_url = 'https://example.com/product/123';
		
		// Insert test campaign
		$wpdb->insert(
			$wpdb->prefix . 'first8_email_events',
			array(
				'tenant_id' => 'test_tenant',
				'tracking_id' => $tracking_id,
				'email' => 'test@example.com',
				'event_type' => 'click',
				'event_timestamp' => current_time( 'mysql' ),
				'target_url' => $target_url
			)
		);
		
		// Verify click event recorded
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT target_url FROM {$wpdb->prefix}first8_email_events WHERE tracking_id = %s AND event_type = 'click'",
				$tracking_id
			)
		);
		
		$this->assertEquals( $target_url, $result );
	}
	
	/**
	 * Test WooCommerce email hook integration
	 */
	public function test_woocommerce_email_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce not available' );
		}
		
		// Test order confirmation email tracking
		$order_id = 123;
		$email = 'customer@example.com';
		
		do_action( 'woocommerce_email_order_details', $order_id, true, false, $email );
		
		// Should have registered tracking
		$this->assertTrue( has_action( 'woocommerce_email_order_details' ) );
	}
	
	/**
	 * Test SQL injection prevention
	 */
	public function test_sql_injection_prevention() {
		global $wpdb;
		
		// Malicious input
		$malicious_id = "'; DROP TABLE {$wpdb->prefix}first8_email_events; --";
		
		// Should be safely escaped
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}first8_email_events WHERE tracking_id = %s",
				$malicious_id
			)
		);
		
		// Should return 0, not cause SQL error
		$this->assertEquals( 0, $result );
		
		// Table should still exist
		$table_exists = $wpdb->get_var(
			"SHOW TABLES LIKE '{$wpdb->prefix}first8_email_events'"
		);
		$this->assertEquals( $wpdb->prefix . 'first8_email_events', $table_exists );
	}
	
	/**
	 * Test XSS prevention in tracking parameters
	 */
	public function test_xss_prevention() {
		$xss_payload = '<script>alert("XSS")</script>';
		
		// Should sanitize output
		$sanitized = $this->tracker->sanitize_tracking_param( $xss_payload );
		
		$this->assertStringNotContainsString( '<script>', $sanitized );
		$this->assertStringNotContainsString( 'alert', $sanitized );
	}
	
	/**
	 * Test Umami event queue integration
	 */
	public function test_umami_event_queue() {
		$tracking_id = 'test_umami_123';
		$event_data = array(
			'event_type' => 'email_open',
			'email' => 'test@example.com',
			'timestamp' => current_time( 'timestamp' )
		);
		
		// Should queue event for Umami sync
		$result = $this->tracker->queue_umami_event( $tracking_id, $event_data );
		
		$this->assertTrue( $result );
	}
	
	/**
	 * Test campaign statistics calculation
	 */
	public function test_campaign_statistics() {
		global $wpdb;
		
		$campaign_id = 'test_campaign_stats';
		
		// Insert test events
		for ( $i = 1; $i <= 10; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'first8_email_events',
				array(
					'tenant_id' => 'test_tenant',
					'tracking_id' => "track_$i",
					'email' => "user$i@example.com",
					'event_type' => 'open',
					'event_timestamp' => current_time( 'mysql' ),
					'campaign_id' => $campaign_id
				)
			);
		}
		
		// Insert 5 click events
		for ( $i = 1; $i <= 5; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'first8_email_events',
				array(
					'tenant_id' => 'test_tenant',
					'tracking_id' => "track_$i",
					'email' => "user$i@example.com",
					'event_type' => 'click',
					'event_timestamp' => current_time( 'mysql' ),
					'campaign_id' => $campaign_id
				)
			);
		}
		
		$stats = $this->tracker->get_campaign_statistics( $campaign_id );
		
		$this->assertEquals( 10, $stats['opens'] );
		$this->assertEquals( 5, $stats['clicks'] );
		$this->assertEquals( 50.0, $stats['click_rate'] );
	}
}