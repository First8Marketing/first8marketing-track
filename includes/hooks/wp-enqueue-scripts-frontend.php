<?php
/**
 * File: wp-enqueue-scripts-frontend.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'umami-autotrack',
			plugins_url( 'assets/umami-autotrack.js', UMAMI_CONNECT_PLUGIN_FILE ),
			array(),
			'1.2.0',
			true
		);

		$cfg = array(
			'autotrackLinks'   => get_option( 'umami_autotrack_links', '1' ) === '1',
			'autotrackButtons' => get_option( 'umami_autotrack_buttons', '1' ) === '1',
			'autotrackForms'   => get_option( 'umami_autotrack_forms', '1' ) === '1',
			'consentRequired'  => get_option( 'umami_consent_required', '0' ) === '1',
			'consentFlag'      => (string) get_option( 'umami_consent_flag', 'umamiConsentGranted' ),
			'debug'            => get_option( 'umami_debug', '0' ) === '1',
		);
		wp_add_inline_script( 'umami-autotrack', 'window.__UMAMI_CONNECT__ = ' . wp_json_encode( $cfg ) . ';', 'before' );
	},
	30
);
