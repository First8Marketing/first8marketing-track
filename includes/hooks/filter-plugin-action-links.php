<?php
/**
 * File: filter-plugin-action-links.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

add_filter(
	'plugin_action_links_' . plugin_basename( dirname( __DIR__, 2 ) . '/umami-connect.php' ),
	function ( $links ) {
		$website_id    = get_option( 'umami_website_id', '' );
		$host          = get_option( 'umami_host', '' );
		$is_configured = ! empty( $website_id ) && ! empty( $host );

		$link_text     = $is_configured ? 'Settings' : 'Setup';
		$settings_link = '<a href="admin.php?page=umami_connect">' . $link_text . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);
