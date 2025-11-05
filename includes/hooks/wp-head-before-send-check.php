<?php
/**
 * File: wp-head-before-send-check.php
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Diagnostic feature flag check only.
		if ( ! isset( $_GET['umami_check_before_send'] ) ) {
			return;
		}

		echo "\n<script>\n";
		echo "(function(){\n";
		echo "try {\n";
		echo "  var usp = new URLSearchParams(window.location.search);\n";
		echo "  var path = usp.get('path') || '';\n";
		echo "  var token = usp.get('token') || '';\n";
		echo "  function resolve(p){\n";
		echo "    if(!p){return undefined;}\n";
		echo "    var obj = window;\n";
		echo "    var parts = p.split('.');\n";
		echo "    for(var i=0;i<parts.length;i++){\n";
		echo "      var s = parts[i];\n";
		echo "      if(!s){return undefined;}\n";
		echo "      try { obj = obj[s]; } catch(e){ return undefined; }\n";
		echo "      if(typeof obj === 'undefined'){ return undefined; }\n";
		echo "    }\n";
		echo "    return obj;\n";
		echo "  }\n";
		echo "  var ref = resolve(path);\n";
		echo "  var exists = typeof ref !== 'undefined';\n";
		echo "  var isFunction = typeof ref === 'function';\n";
		echo "  if (window.parent) {\n";
		echo "    window.parent.postMessage({ type:'umami-before-send-check', path: path, exists: exists, isFunction: isFunction, token: token }, window.location.origin);\n";
		echo "  }\n";
		echo "} catch(e){}\n";
		echo "})();\n";
		echo "</script>\n";
	},
	1
);
