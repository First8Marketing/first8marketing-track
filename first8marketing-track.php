<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Main plugin file.
/**
 * Plugin Name: First8 Marketing - Track
 * Plugin URI: https://first8marketing.com
 * Description: Advanced analytics tracking with Umami for WordPress and WooCommerce events
 * Version: 1.0.0
 * Author: First8 Marketing
 * Author URI: https://first8marketing.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: first8marketing-track
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'UMAMI_WP_VERSION', '1.0.0' );
define( 'UMAMI_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMAMI_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UMAMI_WP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Umami WordPress Connector Class
 */
class Umami_WP_Connect {

	/**
	 * Single instance of the class
	 *
	 * @var Umami_WP_Connect
	 */
	private static $instance = null;

	/**
	 * Get single instance
	 *
	 * @return Umami_WP_Connect
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-umami-tracker.php';
		require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-umami-admin.php';
		require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-umami-events.php';
		require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-link-manager.php';
		require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-link-shortcodes.php';

		// Load WooCommerce integration if WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			require_once UMAMI_WP_PLUGIN_DIR . 'includes/class-umami-woocommerce.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Initialize components.
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		// Initialize tracker.
		Umami_Tracker::get_instance();

		// Initialize admin.
		if ( is_admin() ) {
			Umami_Admin::get_instance();
		}

		// Initialize event tracking.
		Umami_Events::get_instance();

		// Initialize WooCommerce tracking.
		if ( class_exists( 'WooCommerce' ) ) {
			Umami_WooCommerce::get_instance();
		}

		// Initialize link manager.
		$link_manager = new \First8Marketing\Track\Link_Manager();
		$link_manager->init();

		// Initialize link shortcodes.
		$link_shortcodes = new \First8Marketing\Track\Link_Shortcodes();
		$link_shortcodes->init();

		do_action( 'umami_wp_connect_init' );
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'first8marketing-track',
			false,
			dirname( UMAMI_WP_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Set default options.
		$default_options = array(
			'umami_website_id'       => '',
			'umami_script_url'       => '',
			'umami_api_url'          => '',
			'umami_api_key'          => '',
			'track_logged_in_users'  => false,
			'track_admin_pages'      => false,
			'enable_woocommerce'     => true,
			'enable_form_tracking'   => true,
			'enable_click_tracking'  => true,
			'enable_scroll_tracking' => true,
		);

		foreach ( $default_options as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

// Initialize the plugin.
Umami_WP_Connect::get_instance();
