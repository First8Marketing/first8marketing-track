<?php
/**
 * Umami Admin Class
 * Handles admin settings page
 *
 * @package UmamiWPConnect
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Umami Admin Class
 */
class Umami_Admin {

	/**
	 * Single instance
	 *
	 * @var Umami_Admin
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Umami_Admin
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Umami Analytics Settings', 'first8marketing-track' ),
			__( 'Umami Analytics', 'first8marketing-track' ),
			'manage_options',
			'first8marketing-track',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// General settings.
		register_setting( 'umami_settings', 'umami_website_id', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );
		register_setting( 'umami_settings', 'umami_script_url', array(
			'sanitize_callback' => 'esc_url_raw'
		) );
		register_setting( 'umami_settings', 'umami_api_url', array(
			'sanitize_callback' => 'esc_url_raw'
		) );
		register_setting( 'umami_settings', 'umami_api_key', array(
			'sanitize_callback' => 'sanitize_text_field'
		) );

		// Tracking settings.
		register_setting( 'umami_settings', 'enable_tracking', array(
			'sanitize_callback' => 'rest_sanitize_boolean'
		) );
		register_setting( 'umami_settings', 'enable_form_tracking', array(
			'sanitize_callback' => 'rest_sanitize_boolean'
		) );
		register_setting( 'umami_settings', 'enable_woocommerce', array(
			'sanitize_callback' => 'rest_sanitize_boolean'
		) );
		register_setting( 'umami_settings', 'track_logged_in_users', array(
			'sanitize_callback' => 'rest_sanitize_boolean'
		) );
		register_setting( 'umami_settings', 'exclude_roles', array(
			'sanitize_callback' => array( $this, 'sanitize_exclude_roles' )
		) );

		// Advanced settings.
		register_setting( 'umami_settings', 'enable_debug', array(
			'sanitize_callback' => 'rest_sanitize_boolean'
		) );
		register_setting( 'umami_settings', 'batch_size', array(
			'sanitize_callback' => 'absint'
		) );
		register_setting( 'umami_settings', 'batch_interval', array(
			'sanitize_callback' => 'absint'
		) );

		// General section.
		add_settings_section(
			'umami_general_section',
			__( 'General Settings', 'first8marketing-track' ),
			array( $this, 'render_general_section' ),
			'first8marketing-track'
		);

		// Tracking section.
		add_settings_section(
			'umami_tracking_section',
			__( 'Tracking Settings', 'first8marketing-track' ),
			array( $this, 'render_tracking_section' ),
			'first8marketing-track'
		);

		// Advanced section.
		add_settings_section(
			'umami_advanced_section',
			__( 'Advanced Settings', 'first8marketing-track' ),
			array( $this, 'render_advanced_section' ),
			'first8marketing-track'
		);

		// Add fields.
		$this->add_settings_fields();
	}

	/**
	 * Add settings fields
	 */
	private function add_settings_fields() {
		// General fields.
		add_settings_field(
			'umami_website_id',
			__( 'Website ID', 'first8marketing-track' ),
			array( $this, 'render_text_field' ),
			'first8marketing-track',
			'umami_general_section',
			array(
				'label_for'   => 'umami_website_id',
				'description' => __( 'Your Umami website ID', 'first8marketing-track' ),
			)
		);

		add_settings_field(
			'umami_script_url',
			__( 'Script URL', 'first8marketing-track' ),
			array( $this, 'render_text_field' ),
			'first8marketing-track',
			'umami_general_section',
			array(
				'label_for'   => 'umami_script_url',
				'description' => __( 'URL to the Umami tracking script', 'first8marketing-track' ),
				'default'     => 'https://analytics.umami.is/script.js',
			)
		);

		add_settings_field(
			'umami_api_url',
			__( 'API URL', 'first8marketing-track' ),
			array( $this, 'render_text_field' ),
			'first8marketing-track',
			'umami_general_section',
			array(
				'label_for'   => 'umami_api_url',
				'description' => __( 'URL to the Umami API endpoint', 'first8marketing-track' ),
			)
		);

		// Tracking fields.
		add_settings_field(
			'enable_tracking',
			__( 'Enable Tracking', 'first8marketing-track' ),
			array( $this, 'render_checkbox_field' ),
			'first8marketing-track',
			'umami_tracking_section',
			array(
				'label_for'   => 'enable_tracking',
				'description' => __( 'Enable Umami tracking on your site', 'first8marketing-track' ),
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'umami_settings' );
				do_settings_sections( 'first8marketing-track' );
				submit_button( __( 'Save Settings', 'first8marketing-track' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure your Umami analytics connection.', 'first8marketing-track' ) . '</p>';
	}

	/**
	 * Render tracking section
	 */
	public function render_tracking_section() {
		echo '<p>' . esc_html__( 'Configure what events to track.', 'first8marketing-track' ) . '</p>';
	}

	/**
	 * Render advanced section
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced configuration options.', 'first8marketing-track' ) . '</p>';
	}

	/**
	 * Render text field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$value = get_option( $args['label_for'], $args['default'] ?? '' );
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( $args['label_for'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$value = get_option( $args['label_for'], true );
		?>
		<label>
			<input
				type="checkbox"
				id="<?php echo esc_attr( $args['label_for'] ); ?>"
				name="<?php echo esc_attr( $args['label_for'] ); ?>"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<?php echo esc_html( $args['description'] ); ?>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_umami-wp-connect' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'umami-admin',
			UMAMI_WP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			UMAMI_WP_VERSION
		);

		wp_enqueue_script(
			'umami-admin',
			UMAMI_WP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			UMAMI_WP_VERSION,
			true
		);
	}

	/**
	 * Sanitize exclude roles setting
	 *
	 * @param mixed $value Input value.
	 * @return array Sanitized roles array.
	 */
	public function sanitize_exclude_roles( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid_roles = array_keys( get_editable_roles() );
		$sanitized   = array();

		foreach ( $value as $role ) {
			if ( in_array( $role, $valid_roles, true ) ) {
				$sanitized[] = sanitize_text_field( $role );
			}
		}

		return $sanitized;
	}
}

