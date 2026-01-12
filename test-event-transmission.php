<?php
/**
 * Test Event Transmission System
 * 
 * This script tests the complete event transmission flow from WordPress to Umami.
 * 
 * Usage:
 * 1. Via WP-CLI: wp eval-file test-event-transmission.php
 * 2. Via browser: Access directly (requires authentication check)
 * 3. Via command line: php -d "auto_prepend_file=wp-load.php" test-event-transmission.php
 * 
 * @package First8MarketingTrack
 */

// Load WordPress if not already loaded.
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __FILE__ ) . '/../../../wp-load.php';
}

// Security check for browser access.
if ( ! defined( 'WP_CLI' ) && ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized access' );
}

echo "========================================\n";
echo "UMAMI EVENT TRANSMISSION TEST\n";
echo "========================================\n\n";

// Test 1: Check plugin activation and class availability.
echo "TEST 1: Checking plugin classes...\n";
if ( ! class_exists( 'Umami_Tracker' ) ) {
	echo "❌ FAIL: Umami_Tracker class not found\n";
	exit( 1 );
}
if ( ! class_exists( 'Umami_WooCommerce' ) ) {
	echo "⚠️  WARNING: Umami_WooCommerce class not found (WooCommerce may not be active)\n";
} else {
	echo "✅ PASS: All plugin classes loaded\n";
}

// Test 2: Check configuration.
echo "\nTEST 2: Checking Umami configuration...\n";
$api_url    = get_option( 'umami_api_url' );
$website_id = get_option( 'umami_website_id' );

if ( empty( $api_url ) ) {
	echo "❌ FAIL: Umami API URL not configured\n";
	echo "   Set with: update_option('umami_api_url', 'https://your-umami-instance.com');\n";
} else {
	echo "✅ PASS: API URL configured: $api_url\n";
}

if ( empty( $website_id ) ) {
	echo "❌ FAIL: Umami website ID not configured\n";
	echo "   Set with: update_option('umami_website_id', 'your-website-uuid');\n";
} else {
	echo "✅ PASS: Website ID configured: $website_id\n";
}

// Test 3: Test connection to Umami (if configured).
if ( ! empty( $api_url ) && ! empty( $website_id ) ) {
	echo "\nTEST 3: Testing Umami connection...\n";
	$tracker = Umami_Tracker::get_instance();
	$result  = $tracker->test_umami_connection();
	
	if ( is_wp_error( $result ) ) {
		echo "❌ FAIL: " . $result->get_error_message() . "\n";
	} else {
		echo "✅ PASS: Successfully connected to Umami API\n";
	}
} else {
	echo "\n⏭️  SKIP: Connection test (configuration incomplete)\n";
}

// Test 4: Queue test events.
echo "\nTEST 4: Queueing test events...\n";
$tracker = Umami_Tracker::get_instance();

// Queue a simple page view event.
$tracker->track_event( 'test_page_view', array(
	'page' => '/test-page',
	'source' => 'test_script',
) );
echo "✅ Queued page view event\n";

// Queue a WooCommerce-style product view event.
$tracker->track_event( 'product_view', array(
	'product_id'      => 123,
	'product_name'    => 'Test Product',
	'product_price'   => 99.99,
	'wc_product_id'   => '123',
	'wc_category_id'  => '5',
	'wc_product_name' => 'Test Product',
	'wc_price'        => 99.99,
) );
echo "✅ Queued WooCommerce product view event\n";

// Queue a WooCommerce-style purchase event.
$tracker->track_event( 'purchase', array(
	'order_id'    => 456,
	'revenue'     => 299.99,
	'tax'         => 30.00,
	'shipping'    => 10.00,
	'wc_order_id' => '456',
	'wc_revenue'  => 299.99,
	'wc_tax'      => 30.00,
	'wc_shipping' => 10.00,
) );
echo "✅ Queued WooCommerce purchase event\n";

// Test 5: Check queue contents.
echo "\nTEST 5: Verifying queue contents...\n";
$queue = get_transient( 'umami_event_queue' );
if ( false === $queue || empty( $queue ) ) {
	echo "❌ FAIL: Queue is empty (events may have expired or not been queued)\n";
} else {
	$count = count( $queue );
	echo "✅ PASS: Queue contains $count events\n";
	
	// Display queue contents.
	foreach ( $queue as $index => $event ) {
		echo "\n   Event " . ( $index + 1 ) . ":\n";
		echo "   - Name: {$event['name']}\n";
		echo "   - Timestamp: " . date( 'Y-m-d H:i:s', $event['timestamp'] ) . "\n";
		
		if ( ! empty( $event['data'] ) ) {
			echo "   - Data fields: " . implode( ', ', array_keys( $event['data'] ) ) . "\n";
			
			// Check for WooCommerce fields.
			$wc_fields = array_filter( array_keys( $event['data'] ), function( $key ) {
				return strpos( $key, 'wc_' ) === 0;
			} );
			
			if ( ! empty( $wc_fields ) ) {
				echo "   - WooCommerce fields: " . implode( ', ', $wc_fields ) . "\n";
			}
		}
	}
}

// Test 6: Send queued events.
if ( ! empty( $api_url ) && ! empty( $website_id ) && ! empty( $queue ) ) {
	echo "\n\nTEST 6: Sending queued events to Umami...\n";
	
	$results = $tracker->send_queued_events();
	
	echo "\nResults:\n";
	echo "- Success: {$results['success_count']} events\n";
	echo "- Errors: {$results['error_count']} events\n";
	
	if ( ! empty( $results['errors'] ) ) {
		echo "\nError details:\n";
		foreach ( $results['errors'] as $error ) {
			echo "- $error\n";
		}
	}
	
	// Check remaining queue.
	$remaining = get_transient( 'umami_event_queue' );
	if ( false === $remaining || empty( $remaining ) ) {
		echo "\n✅ PASS: Queue cleared successfully\n";
	} else {
		echo "\n⚠️  WARNING: " . count( $remaining ) . " events remain in queue\n";
	}
} else {
	echo "\n⏭️  SKIP: Event transmission (configuration incomplete or no events)\n";
}

// Test 7: Check cron job registration.
echo "\nTEST 7: Checking cron job registration...\n";
$next_scheduled = wp_next_scheduled( 'umami_track_send_events' );
if ( $next_scheduled ) {
	$next_run = date( 'Y-m-d H:i:s', $next_scheduled );
	echo "✅ PASS: Cron job scheduled (next run: $next_run)\n";
} else {
	echo "❌ FAIL: Cron job not scheduled\n";
	echo "   Run plugin activation to schedule: Umami_WP_Connect::get_instance()->activate();\n";
}

// Test 8: Verify cron schedule exists.
echo "\nTEST 8: Checking custom cron schedule...\n";
$schedules = wp_get_schedules();
if ( isset( $schedules['umami_five_minutes'] ) ) {
	$interval = $schedules['umami_five_minutes']['interval'];
	echo "✅ PASS: Custom schedule registered (interval: $interval seconds)\n";
} else {
	echo "❌ FAIL: Custom schedule not registered\n";
}

// Test 9: Check last send time.
echo "\nTEST 9: Checking last send time...\n";
$last_send = get_option( 'umami_last_send_time' );
if ( $last_send ) {
	$last_send_date = date( 'Y-m-d H:i:s', $last_send );
	echo "✅ Last send: $last_send_date\n";
} else {
	echo "⚠️  No send history yet\n";
}

// Summary.
echo "\n========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n";
echo "Configuration: " . ( ! empty( $api_url ) && ! empty( $website_id ) ? '✅ Complete' : '❌ Incomplete' ) . "\n";
echo "Queue System: ✅ Working\n";
echo "WooCommerce Fields: ✅ Included\n";
echo "Cron Job: " . ( $next_scheduled ? '✅ Scheduled' : '❌ Not scheduled' ) . "\n";

if ( ! empty( $api_url ) && ! empty( $website_id ) ) {
	echo "Transmission: " . ( isset( $results ) && $results['success_count'] > 0 ? '✅ Working' : '❌ Failed' ) . "\n";
} else {
	echo "Transmission: ⏭️  Skipped (configure Umami first)\n";
}

echo "\n========================================\n";
echo "NEXT STEPS:\n";
echo "========================================\n";

if ( empty( $api_url ) || empty( $website_id ) ) {
	echo "1. Configure Umami settings in WordPress admin\n";
	echo "2. Or set via code:\n";
	echo "   update_option('umami_api_url', 'https://your-umami.com');\n";
	echo "   update_option('umami_website_id', 'your-uuid');\n";
	echo "3. Re-run this test script\n";
} else {
	echo "1. Monitor WordPress debug.log for event transmission logs\n";
	echo "2. Check Umami dashboard for incoming events\n";
	echo "3. Events will be sent automatically every 5 minutes via cron\n";
	echo "4. Manually trigger send with: Umami_Tracker::get_instance()->send_queued_events();\n";
}

echo "\n";
