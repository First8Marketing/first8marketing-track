<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
/**
 * File: autoloader.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader for plugin files.
 */
class Umami_Connect_Autoloader {

	/**
	 * Plugin root directory
	 *
	 * @var string
	 */
	private static $plugin_dir;

	/**
	 * File map for direct includes
	 *
	 * @var array
	 */
	private static $file_map = array();

	/**
	 * Initialize the autoloader
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public static function init( $plugin_file ) {
		self::$plugin_dir = plugin_dir_path( $plugin_file );

		// Build file map.
		self::build_file_map();

		// Load all files.
		self::load_all_files();
	}

	/**
	 * Build file mapping for organized loading
	 */
	private static function build_file_map() {
		self::$file_map = array(
			// Core files (load first).
			'core'        => array(
				'constants.php',
				'version_check.php',
			),

			// Admin files.
			'admin'       => array(
				'menu.php',
			),

			// Admin pages.
			'admin/pages' => array(
				'general.php',
				'self_protection.php',
				'automation.php',
				'update.php',
				'events_overview.php',
				'advanced.php',
			),

			// Hooks (filters and actions).
			'hooks'       => array(
				'filter-plugin-action-links.php',
				'filter-render-block.php',
				'filter-the-content.php',
				'admin-init-settings.php',
				'plugin-activation.php',
				'wp-head-before-send-check.php',
				'wp-head-script-injection.php',
				'wp-head-user-exclusion.php',
				'wp-enqueue-scripts-frontend.php',
				'enqueue-block-editor-assets.php',
			),

			// Dashboard components.
			'dashboard'   => array(
				'dashboard-status-widget.php',
			),
		);

		// Load debug helper if in debug mode.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::$file_map['core'][] = 'autoloader-debug.php';
		}
	}

	/**
	 * Load all files in the correct order
	 */
	private static function load_all_files() {
		foreach ( self::$file_map as $directory => $files ) {
			foreach ( $files as $file ) {
				self::load_file( $directory, $file );
			}
		}
	}

	/**
	 * Load a specific file
	 *
	 * @param string $directory Directory name relative to includes/.
	 * @param string $file      File name.
	 */
	private static function load_file( $directory, $file ) {
		$file_path = self::$plugin_dir . 'includes/' . $directory . '/' . $file;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Log missing file in debug mode.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( 'Umami Connect: Missing file - ' . $file_path );
		}
	}

	/**
	 * Get loaded files for debugging
	 *
	 * @return array
	 */
	public static function get_loaded_files() {
		$loaded_files = array();

		foreach ( self::$file_map as $directory => $files ) {
			foreach ( $files as $file ) {
				$file_path                                = self::$plugin_dir . 'includes/' . $directory . '/' . $file;
				$loaded_files[ $directory . '/' . $file ] = file_exists( $file_path );
			}
		}

		return $loaded_files;
	}

	/**
	 * Add a file to load dynamically
	 *
	 * @param string $directory Directory name.
	 * @param string $file      File name.
	 */
	public static function add_file( $directory, $file ) {
		if ( ! isset( self::$file_map[ $directory ] ) ) {
			self::$file_map[ $directory ] = array();
		}

		if ( ! in_array( $file, self::$file_map[ $directory ], true ) ) {
			self::$file_map[ $directory ][] = $file;
			self::load_file( $directory, $file );
		}
	}
}
