<?php
/**
 * File: admin-init-settings.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_host_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );
					if ( '' === $value ) {
						return '';
					}
					if ( ! preg_match( '~^https?://~i', $value ) ) {
						$value = 'https://' . $value;
					}
					return esc_url_raw( rtrim( $value, '/' ) );
				},
				'default'           => '',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_disable_auto_track',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '0',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_domains',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );
					$value = preg_replace( '/\s+/', '', $value );
					return $value;
				},
				'default'           => '',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_tag',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return sanitize_text_field( $value );
				},
				'default'           => '',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_exclude_search',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '0',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_exclude_hash',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '0',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_do_not_track',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '0',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_before_send_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 'disabled', 'function_name', 'inline', true ), true ) ? $value : 'disabled';
				},
				'default'           => 'disabled',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_before_send',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );
					if ( '' === $value ) {
						return '';
					}
					return preg_match( '/^[A-Za-z_$][A-Za-z0-9_$.]*$/', $value ) ? $value : '';
				},
				'default'           => '',
			)
		);
		register_setting(
			'umami_connect_advanced',
			'umami_tracker_before_send_inline',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );
					if ( '' === $value ) {
						return '';
					}
					$value = preg_replace( '/<\/?script[^>]*>/i', '', $value );

					if ( ! preg_match( '/^function\s*\(/', $value ) ) {
						add_settings_error(
							'umami_tracker_before_send_inline',
							'invalid_function',
							'beforeSend inline code must start with "function(".',
							'error'
						);
						return get_option( 'umami_tracker_before_send_inline', '' );
					}

					return $value;
				},
				'default'           => '',
			)
		);
		register_setting(
			'umami_connect_general',
			'umami_script_loading',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$v = strtolower( trim( (string) $v ) );
					return in_array( $v, array( 'async', 'defer', true ), true ) ? $v : 'async';
				},
				'default'           => 'async',
			)
		);

		register_setting(
			'umami_connect_general',
			'umami_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$v = strtolower( trim( (string) $v ) );
					return in_array( $v, array( 'cloud', 'self', true ), true ) ? $v : 'cloud';
				},
				'default'           => 'cloud',
			)
		);

		register_setting(
			'umami_connect_general',
			'umami_host',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$mode  = get_option( 'umami_mode', 'cloud' );
					$value = trim( (string) $value );

					if ( 'self' === $mode && '' === $value ) {
						add_settings_error(
							'umami_host',
							'umami_host_required',
							'Host URL is required for self-hosted mode.',
							'error'
						);
						return get_option( 'umami_host', '' );
					}

					if ( '' === $value ) {
						return '';
					}

					if ( ! preg_match( '~^https?://~i', $value ) ) {
						$value = 'https://' . $value;
					}

					return esc_url_raw( rtrim( $value, '/' ) );
				},
				'default'           => '',
			)
		);

		register_setting(
			'umami_connect_general',
			'umami_website_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );

					if ( '' === $value ) {
						add_settings_error(
							'umami_website_id',
							'umami_website_id_required',
							'Website ID is required.',
							'error'
						);
						return get_option( 'umami_website_id', '' );
					}

					$value = strtolower( $value );

					if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value ) ) {
						add_settings_error(
							'umami_website_id',
							'umami_website_id_invalid',
							'Website ID must be a valid UUID (e.g., 12345678-1234-1234-1234-123456789abc).',
							'error'
						);
						return get_option( 'umami_website_id', '' );
					}

					return $value;
				},
				'default'           => '',
			)
		);

		register_setting(
			'umami_connect_self',
			'umami_exclude_logged_in',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '1',
			)
		);

		register_setting(
			'umami_connect_automation',
			'umami_autotrack_links',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '1',
			)
		);
		register_setting(
			'umami_connect_automation',
			'umami_autotrack_buttons',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '1',
			)
		);
		register_setting(
			'umami_connect_automation',
			'umami_autotrack_forms',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '1',
			)
		);
		register_setting(
			'umami_connect_automation',
			'umami_consent_required',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '0',
			)
		);
		register_setting(
			'umami_connect_automation',
			'umami_consent_flag',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );
					if ( '' === $value ) {
						return 'umamiConsentGranted';
					}
					return preg_match( '/^[A-Za-z_$][A-Za-z0-9_$.]*$/', $value ) ? $value : 'umamiConsentGranted';
				},
				'default'           => 'umamiConsentGranted',
			)
		);
		register_setting(
			'umami_connect_automation',
			'umami_debug',
			array(
				'type'              => 'string',
				'sanitize_callback' => function( $v ) { return $v ? '1' : '0'; },
				'default'           => '0',
			)
		);

		add_settings_section(
			'umami_connect_general',
			null,
			null,
			'umami_connect'
		);

		add_settings_field(
			'umami_mode',
			'Mode',
			function () {
				$value = get_option( 'umami_mode', 'cloud' );
				echo '<select id="umami_mode" name="umami_mode">';
				echo '<option value="cloud"' . selected( $value, 'cloud', false ) . '>Cloud (umami.is)</option>';
				echo '<option value="self"' . selected( $value, 'self', false ) . '>Self-hosted</option>';
				echo '</select>';
			},
			'umami_connect',
			'umami_connect_general'
		);

		add_settings_field(
			'umami_host',
			'Host URL',
			function () {
				$value = get_option( 'umami_host', '' );
				echo '<input type="url" id="umami_host" name="umami_host" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://analytics.yourdomain.com" />';
			},
			'umami_connect',
			'umami_connect_general'
		);

		add_settings_field(
			'umami_website_id',
			'Website ID',
			function () {
				$value = get_option( 'umami_website_id', '' );
				echo '<input type="text" id="umami_website_id" name="umami_website_id" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="12345678-1234-1234-1234-123456789abc" required />';
			},
			'umami_connect',
			'umami_connect_general'
		);

		add_settings_field(
			'umami_script_loading',
			'Script Loading',
			function () {
				$value = get_option( 'umami_script_loading', 'async' );
				echo '<select name="umami_script_loading">';
				echo '<option value="async"' . selected( $value, 'async', false ) . '>Async</option>';
				echo '<option value="defer"' . selected( $value, 'defer', false ) . '>Defer</option>';
				echo '</select>';
			},
			'umami_connect',
			'umami_connect_general'
		);

		add_settings_section(
			'umami_connect_self_protection',
			null,
			null,
			'umami_connect_self_protection'
		);

		add_settings_field(
			'umami_exclude_logged_in',
			'Exclude Logged-in Users',
			function () {
				$value = get_option( 'umami_exclude_logged_in', '1' );
				echo '<label><input type="checkbox" name="umami_exclude_logged_in" value="1"' . checked( $value, '1', false ) . ' /> Do not track my own visits</label>';
				echo '<p class="description">Exclude logged-in WordPress users from tracking.</p>';
			},
			'umami_connect_self_protection',
			'umami_connect_self_protection'
		);

		add_settings_section(
			'umami_connect_automation',
			null,
			null,
			'umami_connect_automation'
		);

		add_settings_field(
			'umami_autotrack_links',
			'Auto-track Links',
			function () {
				$value = get_option( 'umami_autotrack_links', '1' );
				echo '<label><input type="checkbox" name="umami_autotrack_links" value="1"' . checked( $value, '1', false ) . ' /> Automatically track link clicks</label>';
			},
			'umami_connect_automation',
			'umami_connect_automation'
		);

		add_settings_field(
			'umami_autotrack_buttons',
			'Auto-track Buttons',
			function () {
				$value = get_option( 'umami_autotrack_buttons', '1' );
				echo '<label><input type="checkbox" name="umami_autotrack_buttons" value="1"' . checked( $value, '1', false ) . ' /> Automatically track button clicks</label>';
			},
			'umami_connect_automation',
			'umami_connect_automation'
		);

		add_settings_field(
			'umami_autotrack_forms',
			'Auto-track Forms',
			function () {
				$value = get_option( 'umami_autotrack_forms', '1' );
				echo '<label><input type="checkbox" name="umami_autotrack_forms" value="1"' . checked( $value, '1', false ) . ' /> Automatically track form submissions</label>';
			},
			'umami_connect_automation',
			'umami_connect_automation'
		);

		// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.WhiteSpace.PrecisionAlignment.Found -- Planned feature.

		/*
		 * Commented out - Future features for consent management and debugging.
		add_settings_field(
			'umami_consent_required',
			'Consent Required',
			function () {
				$value = get_option( 'umami_consent_required', '0' );
				echo '<label><input type="checkbox" name="umami_consent_required" value="1"' . checked( $value, '1', false ) . ' /> Wait for user consent before tracking</label>';
			},
			'umami_connect_automation',
			'umami_connect_automation'
		);

		add_settings_field(
			'umami_debug',
			'Debug Mode',
			function () {
				$value = get_option( 'umami_debug', '0' );
				echo '<label><input type="checkbox" name="umami_debug" value="1"' . checked( $value, '1', false ) . ' /> Enable debug logging in browser console</label>';
			},
			'umami_connect_automation',
			'umami_connect_automation'
		);
		*/

		add_settings_section(
			'umami_connect_advanced',
			'Advanced Settings',
			null,
			'umami_connect_advanced'
		);
	}
);
