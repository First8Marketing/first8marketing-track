<?php
/**
 * File: wp-head-script-injection.php
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

		$website_id = get_option( 'umami_website_id', '' );
		$mode       = get_option( 'umami_mode', 'cloud' );
		$host_input = get_option( 'umami_host', '' );

		if ( ! $website_id ) {
			return;
		}

		$base_host = ( 'self' === $mode )
		? ( $host_input ? $host_input : '' )
		: UMAMI_CONNECT_DEFAULT_HOST;

		if ( ! $base_host ) {
			return;
		}

		$script_url = $base_host;
		if ( ! preg_match( '~\.js(\?.*)?$~i', $script_url ) ) {
			$script_url = rtrim( $script_url, '/' ) . '/script.js';
		}

		$script_loading = get_option( 'umami_script_loading', 'async' );
		$attr           = ( 'defer' === $script_loading ) ? 'defer' : 'async';
		$attrs          = array();
		$attrs[]        = $attr;
		$attrs[]        = 'src="' . esc_url( $script_url ) . '"';
		$attrs[]        = 'data-website-id="' . esc_attr( $website_id ) . '"';

		$host_override = get_option( 'umami_tracker_host_url', '' );
		if ( ! empty( $host_override ) ) {
			$attrs[] = 'data-host-url="' . esc_url( $host_override ) . '"';
		}
		if ( get_option( 'umami_disable_auto_track', '0' ) === '1' ) {
			$attrs[] = 'data-auto-track="false"';
		}
		$domains = get_option( 'umami_tracker_domains', '' );
		if ( '' !== $domains ) {
			$attrs[] = 'data-domains="' . esc_attr( $domains ) . '"';
		}
		$tag = get_option( 'umami_tracker_tag', '' );
		if ( '' !== $tag ) {
			$attrs[] = 'data-tag="' . esc_attr( $tag ) . '"';
		}
		if ( get_option( 'umami_tracker_exclude_search', '0' ) === '1' ) {
			$attrs[] = 'data-exclude-search="true"';
		}
		if ( get_option( 'umami_tracker_exclude_hash', '0' ) === '1' ) {
			$attrs[] = 'data-exclude-hash="true"';
		}
		if ( get_option( 'umami_tracker_do_not_track', '0' ) === '1' ) {
			$attrs[] = 'data-do-not-track="true"';
		}

		$before_send_mode = get_option( 'umami_tracker_before_send_mode', 'disabled' );
		if ( 'inline' === $before_send_mode ) {
			$inline_code = get_option( 'umami_tracker_before_send_inline', '' );
			if ( '' !== $inline_code ) {
				echo "\n<script>\n";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-provided JavaScript code for beforeSend hook.
				echo 'window.__umamiBeforeSend = (' . $inline_code . ");\n";
				echo "</script>\n";
				$attrs[] = 'data-before-send="__umamiBeforeSend"';
			}
		} elseif ( 'function_name' === $before_send_mode ) {
			$before_send = get_option( 'umami_tracker_before_send', '' );
			if ( '' !== $before_send ) {
				$attrs[] = 'data-before-send="' . esc_attr( $before_send ) . '"';
			}
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs contains pre-escaped HTML attributes.
		echo "\n" . '<script ' . implode( ' ', $attrs ) . '></script>' . "\n";
	},
	20
);
