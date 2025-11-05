<?php
/**
 * File: wp-head-user-exclusion.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_head',
	function () {
		if ( is_admin() ) {
			return;
		}

		$exclude_logged_in = get_option( 'umami_exclude_logged_in', '1' );

		if ( '1' === $exclude_logged_in && is_user_logged_in() ) {
			echo "\n<script>\n";
			echo "if (typeof localStorage !== 'undefined') {\n";
			echo "  localStorage.setItem('umami.disabled', 'true');\n";
			echo "}\n";
			echo "</script>\n";
		}
	},
	15
);
