<?php
/**
 * File: menu.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

add_action(
	'admin_menu',
	function () {

		$general_page = add_menu_page(
			'umami Connect',
			'umami Connect',
			'manage_options',
			'umami_connect',
			'umami_connect_settings_page',
			'dashicons-chart-area',
			90
		);
		add_action( "load-{$general_page}", 'umami_connect_add_help' );

		$self_protection_page = add_submenu_page(
			'umami_connect',
			'Self protection',
			'Self protection',
			'manage_options',
			'umami_connect_self_protection',
			'umami_connect_self_protection_page',
		);
		add_action( "load-{$self_protection_page}", 'umami_connect_add_help_self_protection' );

		$automation_page = add_submenu_page(
			'umami_connect',
			'Automation',
			'Automation',
			'manage_options',
			'umami_connect_automation',
			'umami_connect_automation_page'
		);
		add_action( "load-{$automation_page}", 'umami_connect_add_help_automation' );

		$events_overview_page = add_submenu_page(
			'umami_connect',
			'Events overview',
			'Events overview',
			'edit_posts',
			'umami_connect_events_overview',
			'umami_connect_render_events_overview_page'
		);
		add_action( "load-{$events_overview_page}", 'umami_connect_add_help_events_overview' );
		add_action( "load-{$events_overview_page}", 'umami_connect_add_screen_options_events_overview' );

		$advanced_page = add_submenu_page(
			'umami_connect',
			'Advanced',
			'Advanced',
			'manage_options',
			'umami_connect_advanced',
			'umami_connect_advanced_page'
		);
		add_action( "load-{$advanced_page}", 'umami_connect_add_help_advanced' );

		$update_menu_title = 'Update';
		if ( umami_connect_has_update() ) {
			$update_menu_title = 'Update <span class="update-plugins count-1"><span class="update-count">1</span></span>';
		}

		$update_page = add_submenu_page(
			'umami_connect',
			'Update',
			$update_menu_title,
			'manage_options',
			'umami_connect_update',
			'umami_connect_update_page'
		);
		add_action( "load-{$update_page}", 'umami_connect_add_help_update' );
	}
);

// Backward compatibility: redirect old Update page slug to new one.
add_action(
	'admin_init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple redirect based on page parameter.
		if ( isset( $_GET['page'] ) && sanitize_key( wp_unslash( $_GET['page'] ) ) === 'umami_connect_support' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=umami_connect_update' ), 301 );
			exit;
		}
	}
);

/**
 * Add help tabs to the main settings page.
 */
function umami_connect_add_help() {
	$screen = get_current_screen();

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_setup',
			'title'   => 'Setup',
			'content' => '<p><strong>Quick Setup Guide</strong></p>' .
						'<ol>' .
						'<li>Create an account at <a href="https://umami.is" target="_blank">umami.is</a> (Cloud) or set up your own Umami instance (Self-hosted).</li>' .
						'<li>Add your website in your Umami dashboard and copy the Website ID.</li>' .
						'<li>Paste the Website ID below and select your mode (Cloud or Self-hosted).</li>' .
						'<li>Save the settings - tracking will start automatically.</li>' .
						'</ol>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_settings',
			'title'   => 'Settings',
			'content' => '<p><strong>Mode</strong><br>' .
						'Choose between Cloud (umami.is hosted service) or Self-hosted (your own Umami instance).</p>' .
						'<p><strong>Website ID</strong><br>' .
						'Your unique Umami Website ID in UUID format (e.g., 12345678-1234-1234-1234-123456789abc). Find this in your Umami dashboard under Settings → Websites.</p>' .
						'<p><strong>Host URL</strong><br>' .
						'Only required for self-hosted instances. Enter your Umami installation URL (e.g., https://analytics.yourdomain.com).</p>' .
						'<p><strong>Script Loading</strong><br>' .
						'<strong>defer</strong>: Execute after HTML is parsed (recommended).<br>' .
						'<strong>async</strong>: Load as soon as possible (may execute before DOM is ready).</p>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>Support & Resources</strong></p>' .
		'<p><a href="https://github.com/ceviixx/umami-wp-connect" target="_blank">GitHub</a></p>' .
		'<p><a href="https://discord.gg/f46SeUS3jn" target="_blank">Discord</a></p>' .
		'<p><a href="https://umami.is/docs" target="_blank">Umami Documentation</a></p>'
	);
}

/**
 * Umami Connect Add Help Self Protection.
 */
function umami_connect_add_help_self_protection() {
	$screen = get_current_screen();

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_self_protection',
			'title'   => 'Overview',
			'content' => '<p><strong>Self Protection</strong></p>' .
						'<p>Prevent logged-in users (like admins, editors, or authors) from being tracked by Umami Analytics.</p>' .
						'<p>This helps keep your analytics data focused on actual visitors rather than your own site activity.</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_self_protection_settings',
			'title'   => 'Settings',
			'content' => '<p><strong>Do not track my own visits</strong><br>' .
						'When enabled, logged-in WordPress users will not be tracked. This works by setting <code>localStorage.umami.disabled = "true"</code> in the browser.</p>' .
						'<p>Disable this option if you want to measure admin/editor visits as well.</p>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>Support & Resources</strong></p>' .
		'<p><a href="https://github.com/ceviixx/umami-wp-connect" target="_blank">GitHub</a></p>' .
		'<p><a href="https://discord.gg/f46SeUS3jn" target="_blank">Discord</a></p>' .
		'<p><a href="https://umami.is/docs" target="_blank">Umami Documentation</a></p>'
	);
}

/**
 * Umami Connect Add Help Automation.
 */
function umami_connect_add_help_automation() {
	$screen = get_current_screen();

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_automation',
			'title'   => 'Overview',
			'content' => '<p><strong>Automation</strong></p>' .
						'<p>Automatically track user interactions like link clicks, button clicks, and form submissions without manual configuration.</p>' .
						'<p>Tracking attributes are injected server-side for supported elements. You can override them using <code>data-umami-event</code> attributes.</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_automation_settings',
			'title'   => 'Settings',
			'content' => '<p><strong>Auto-track links</strong><br>' .
						'Automatically adds click event tracking to all <code>&lt;a&gt;</code> links on your site.</p>' .
						'<p><strong>Auto-track buttons</strong><br>' .
						'Adds click event tracking to Gutenberg Button blocks and native <code>&lt;button&gt;</code> elements.</p>' .
						'<p><strong>Auto-track forms</strong><br>' .
						'Sends a submit event when forms are submitted on your site.</p>' .
						'<p><em>Note: Manually configured tracking in the editor will always be respected and not overridden.</em></p>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>Support & Resources</strong></p>' .
		'<p><a href="https://github.com/ceviixx/umami-wp-connect" target="_blank">GitHub</a></p>' .
		'<p><a href="https://discord.gg/f46SeUS3jn" target="_blank">Discord</a></p>' .
		'<p><a href="https://umami.is/docs" target="_blank">Umami Documentation</a></p>'
	);
}

/**
 * Umami Connect Add Help Events Overview.
 */
function umami_connect_add_help_events_overview() {
	$screen = get_current_screen();

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_events_overview',
			'title'   => 'Overview',
			'content' => '<p><strong>Events Overview</strong></p>' .
						'<p>Overview of all configured tracking events in your Gutenberg blocks. Events are custom tracking points that measure specific user interactions on your website.</p>' .
						'<p>This page helps you understand how visitors engage with your content beyond basic page views and manage all tracking configurations in one place.</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_events_creating',
			'title'   => 'Creating Events',
			'content' => '<p><strong>Supported Blocks</strong><br>' .
						'Buttons, Paragraphs, Headings, Quotes, and Pull Quotes can be configured for event tracking.</p>' .
						'<p><strong>Block Inspector</strong><br>' .
						'Select any supported block in Gutenberg and look for the "Umami Event Tracking" panel in the block inspector.</p>' .
						'<p><strong>Event Configuration</strong><br>' .
						'• <strong>Event Name:</strong> Add a descriptive name (e.g., "Newsletter Signup", "Download PDF")<br>' .
						'• <strong>Data Pairs:</strong> Add key-value pairs to track additional context (optional)<br>' .
						'• <strong>Link Events:</strong> Connect events to specific page URLs for cross-page tracking</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_events_managing',
			'title'   => 'Managing Events',
			'content' => '<p><strong>Filter View</strong><br>' .
						'Use "All", "Events", or "Candidates" to filter the display:<br>' .
						'• <strong>Events:</strong> Blocks with configured event names and tracking data<br>' .
						'• <strong>Candidates:</strong> Supported blocks without event configuration (potential events)</p>' .
						'<p><strong>Search & Edit</strong><br>' .
						'• Use the search box to find specific events by name or post title<br>' .
						'• Click "Edit Page/Post" to open Gutenberg and modify event settings<br>' .
						'• Use "Delete" to remove event tracking while preserving the original block</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_events_best_practices',
			'title'   => 'Best Practices',
			'content' => '<p><strong>Event Naming</strong><br>' .
						'Use clear, descriptive event names and avoid special characters for better dashboard readability.</p>' .
						'<p><strong>Data Context</strong><br>' .
						'Add data pairs to capture important context like user type, content category, or interaction details.</p>' .
						'<p><strong>Testing & Maintenance</strong><br>' .
						'• Test events in your Umami dashboard after configuration<br>' .
						'• Regularly review and clean up unused event configurations<br>' .
						'• Changes made in Gutenberg are reflected immediately in your tracking</p>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>Support & Resources</strong></p>' .
		'<p><a href="https://github.com/ceviixx/umami-wp-connect" target="_blank">GitHub</a></p>' .
		'<p><a href="https://discord.gg/f46SeUS3jn" target="_blank">Discord</a></p>' .
		'<p><a href="https://umami.is/docs" target="_blank">Umami Documentation</a></p>'
	);
}

/**
 * Umami Connect Add Help Update.
 */
function umami_connect_add_help_update() {
	$screen = get_current_screen();

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_update_overview',
			'title'   => 'Overview',
			'content' => '<p><strong>Plugin Updates</strong></p>' .
						'<p>This page allows you to manually update the plugin directly from GitHub releases.</p>' .
						'<p>You will see your current version, the latest available version, and release notes with all changes.</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'umami_help_update_usage',
			'title'   => 'Usage',
			'content' => '<p><strong>Check for Updates</strong><br>' .
						'The page automatically fetches the latest release information from GitHub when you visit it.</p>' .
						'<p><strong>Install Update</strong><br>' .
						'Click the update button to download and install the latest version. The plugin will be automatically reactivated after the update.</p>' .
						'<p><strong>Development Mode</strong><br>' .
						'Updates are disabled on localhost:8080 to prevent accidental overwrites during development.</p>' .
						'<p><strong>Release Notes</strong><br>' .
						'Each release includes detailed notes about new features, improvements, and bug fixes.</p>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>Support & Resources</strong></p>' .
		'<p><a href="https://github.com/ceviixx/umami-wp-connect" target="_blank">GitHub</a></p>' .
		'<p><a href="https://discord.gg/f46SeUS3jn" target="_blank">Discord</a></p>' .
		'<p><a href="https://umami.is/docs" target="_blank">Umami Documentation</a></p>'
	);
}

/**
 * Umami Connect Add Help Advanced.
 */
function umami_connect_add_help_advanced() {
	$screen = get_current_screen();

	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'host-url'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$screen->set_help_sidebar(
		'<p><strong>Support & Resources</strong></p>' .
		'<p><a href="https://github.com/ceviixx/umami-wp-connect" target="_blank">GitHub</a></p>' .
		'<p><a href="https://discord.gg/f46SeUS3jn" target="_blank">Discord</a></p>' .
		'<p><a href="https://umami.is/docs/tracker-configuration" target="_blank">Tracker configuration</a></p>'
	);

	switch ( $tab ) {
		case 'host-url':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_host_overview',
					'title'   => 'Overview',
					'content' => '<p><strong>Host URL override</strong></p>' .
								'<p>Overrides the endpoint the tracker uses to send analytics events. Useful when:</p>' .
								'<ul><li>You load <code>script.js</code> from a CDN.</li><li>Your Umami collector runs on a different domain.</li></ul>' .
								'<p>Maps to <code>data-host-url</code>.</p>',
				)
			);
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_host_usage',
					'title'   => 'Usage',
					'content' => '<p>Enter a full URL (e.g., <code>https://analytics.example.com</code>). Leave empty to use the script\'s own host.</p>',
				)
			);
			break;
		case 'auto-track':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_autotrack',
					'title'   => 'Overview',
					'content' => '<p><strong>Disable auto tracking</strong></p>' .
								'<p>Umami\'s tracker can automatically collect page views and clicks. Disable it if you want full manual control.</p>' .
								'<p>Maps to <code>data-auto-track="false"</code>.</p>' .
								'<p><em>Note:</em> The plugin\'s Automation features are separate and can still emit events.</p>',
				)
			);
			break;
		case 'domains':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_domains',
					'title'   => 'Overview',
					'content' => '<p><strong>Allowed domains</strong></p>' .
								'<p>Restrict the tracker to specific hostnames. Comma-separated list, no spaces.</p>' .
								'<p>Example: <code>example.com,blog.example.com</code></p>' .
								'<p>Maps to <code>data-domains</code>.</p>',
				)
			);
			break;
		case 'tag':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_tag',
					'title'   => 'Overview',
					'content' => '<p><strong>Event tag</strong></p>' .
								'<p>Add a tag to all events so you can filter them in reports. Maps to <code>data-tag</code>.</p>' .
								'<p>Example: <code>umami-eu</code></p>',
				)
			);
			break;
		case 'exclude-search':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_exclude_search',
					'title'   => 'Overview',
					'content' => '<p><strong>Exclude search</strong></p>' .
								'<p>Ignores URL query parameters when collecting page views. Maps to <code>data-exclude-search</code>.</p>',
				)
			);
			break;
		case 'exclude-hash':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_exclude_hash',
					'title'   => 'Overview',
					'content' => '<p><strong>Exclude hash</strong></p>' .
								'<p>Ignores the URL hash (fragment) when collecting page views. Maps to <code>data-exclude-hash</code>.</p>',
				)
			);
			break;
		case 'dnt':
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_dnt',
					'title'   => 'Overview',
					'content' => '<p><strong>Do Not Track</strong></p>' .
								'<p>Respects the user\'s browser DNT preference. Maps to <code>data-do-not-track</code>.</p>',
				)
			);
			break;
		case 'before-send':
		default:
			$screen->add_help_tab(
				array(
					'id'      => 'umami_help_adv_before_send',
					'title'   => 'Overview',
					'content' => '<p><strong>beforeSend</strong></p>' .
								'<p>Let you inspect or modify the payload before it is sent. Return the payload to continue or a false-y value to cancel sending.</p>' .
								'<p>Three modes:</p>' .
								'<ul>' .
								'<li><strong>Disabled</strong>: No beforeSend hook is active (default).</li>' .
								'<li><strong>Function name</strong>: Provide the global function path (e.g., <code>MyApp.handlers.beforeSend</code>). Use the "Check function" button to verify it is available on the public site.</li>' .
								'<li><strong>Inline</strong>: Define the function body directly. Use the "Test function" button to validate before saving.</li>' .
								'</ul>' .
								'<p>Maps to <code>data-before-send</code>.</p>',
				)
			);
			break;
	}
}

/**
 * Add screen options for Events Overview page
 */
function umami_connect_add_screen_options_events_overview() {
	$screen = get_current_screen();

	if ( ! $screen || 'umami-connect_page_umami_connect_events_overview' !== $screen->id ) {
		return;
	}

	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Events per page', 'umami-connect' ),
			'default' => 20,
			'option'  => 'events_per_page',
		)
	);

	add_filter( 'manage_' . $screen->id . '_columns', 'umami_connect_events_overview_columns' );

	add_filter( 'set-screen-option', 'umami_connect_set_screen_option', 10, 3 );
}

/**
 * Define available columns for Events Overview
 *
 * @param array $columns The columns array.
 * @return array
 */
function umami_connect_events_overview_columns( $columns ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress filter signature.
	return array(
		'event'      => __( 'Event', 'umami-connect' ),
		'post'       => __( 'Post/Page', 'umami-connect' ),
		'block_type' => __( 'Block Type', 'umami-connect' ),
		'label'      => __( 'Label/Text', 'umami-connect' ),
		'data_pairs' => __( 'Data Pairs', 'umami-connect' ),
	);
}

/**
 * Handle screen option saving
 *
 * @param mixed  $status The status.
 * @param string $option The option name.
 * @param mixed  $value  The option value.
 * @return mixed
 */
function umami_connect_set_screen_option( $status, $option, $value ) {
	if ( 'events_per_page' === $option ) {
		return $value;
	}
	return $status;
}
