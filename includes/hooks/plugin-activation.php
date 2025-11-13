<?php
/**
 * File: plugin-activation.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook(
	UMAMI_CONNECT_PLUGIN_FILE,
	function () {
		// Set default options on activation.
		if ( ! get_option( 'umami_mode' ) ) {
			update_option( 'umami_mode', 'cloud' );
		}
		if ( ! get_option( 'umami_script_loading' ) ) {
			update_option( 'umami_script_loading', 'async' );
		}
		if ( ! get_option( 'umami_exclude_logged_in' ) ) {
			update_option( 'umami_exclude_logged_in', '1' );
		}
		if ( ! get_option( 'umami_autotrack_links' ) ) {
			update_option( 'umami_autotrack_links', '1' );
		}
		if ( ! get_option( 'umami_autotrack_buttons' ) ) {
			update_option( 'umami_autotrack_buttons', '1' );
		}
		if ( ! get_option( 'umami_autotrack_forms' ) ) {
			update_option( 'umami_autotrack_forms', '1' );
		}
	}
);
