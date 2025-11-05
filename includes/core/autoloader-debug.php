<?php
/**
 * File: autoloader-debug.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	add_action(
		'wp_loaded',
		function () {
			if ( class_exists( 'Umami_Connect_Autoloader' ) ) {
				$loaded = Umami_Connect_Autoloader::get_loaded_files();
				// // // error_log( 'Umami Connect Autoloader Status:' );  // phpcs:ignore -- Debug code commented out.  // phpcs:ignore -- Debug code commented out.  // phpcs:ignore -- Debug code commented out.
				foreach ( $loaded as $file => $status ) {
					$status_text = $status ? 'LOADED' : 'MISSING';
					// // // error_log( "  {$file}: {$status_text}" );  // phpcs:ignore -- Debug code commented out.  // phpcs:ignore -- Debug code commented out.  // phpcs:ignore -- Debug code commented out.
				}
			}
		}
	);
}
