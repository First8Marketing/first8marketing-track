<?php
/**
 * Umami Admin Class
 * Handles admin settings page
 *
 * @package UmamiWPConnect
 */

// Exit if accessed directly
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
            __( 'Umami Analytics Settings', 'umami-wp-connect' ),
            __( 'Umami Analytics', 'umami-wp-connect' ),
            'manage_options',
            'umami-wp-connect',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting( 'umami_settings', 'umami_website_id' );
        register_setting( 'umami_settings', 'umami_script_url' );
        register_setting( 'umami_settings', 'umami_api_url' );
        register_setting( 'umami_settings', 'umami_api_key' );

        // Tracking settings
        register_setting( 'umami_settings', 'enable_tracking' );
        register_setting( 'umami_settings', 'enable_form_tracking' );
        register_setting( 'umami_settings', 'enable_woocommerce' );
        register_setting( 'umami_settings', 'track_logged_in_users' );
        register_setting( 'umami_settings', 'exclude_roles' );

        // Advanced settings
        register_setting( 'umami_settings', 'enable_debug' );
        register_setting( 'umami_settings', 'batch_size' );
        register_setting( 'umami_settings', 'batch_interval' );

        // General section
        add_settings_section(
            'umami_general_section',
            __( 'General Settings', 'umami-wp-connect' ),
            array( $this, 'render_general_section' ),
            'umami-wp-connect'
        );

        // Tracking section
        add_settings_section(
            'umami_tracking_section',
            __( 'Tracking Settings', 'umami-wp-connect' ),
            array( $this, 'render_tracking_section' ),
            'umami-wp-connect'
        );

        // Advanced section
        add_settings_section(
            'umami_advanced_section',
            __( 'Advanced Settings', 'umami-wp-connect' ),
            array( $this, 'render_advanced_section' ),
            'umami-wp-connect'
        );

        // Add fields
        $this->add_settings_fields();
    }

    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General fields
        add_settings_field(
            'umami_website_id',
            __( 'Website ID', 'umami-wp-connect' ),
            array( $this, 'render_text_field' ),
            'umami-wp-connect',
            'umami_general_section',
            array(
                'label_for' => 'umami_website_id',
                'description' => __( 'Your Umami website ID', 'umami-wp-connect' ),
            )
        );

        add_settings_field(
            'umami_script_url',
            __( 'Script URL', 'umami-wp-connect' ),
            array( $this, 'render_text_field' ),
            'umami-wp-connect',
            'umami_general_section',
            array(
                'label_for' => 'umami_script_url',
                'description' => __( 'URL to the Umami tracking script', 'umami-wp-connect' ),
                'default' => 'https://analytics.umami.is/script.js',
            )
        );

        add_settings_field(
            'umami_api_url',
            __( 'API URL', 'umami-wp-connect' ),
            array( $this, 'render_text_field' ),
            'umami-wp-connect',
            'umami_general_section',
            array(
                'label_for' => 'umami_api_url',
                'description' => __( 'URL to the Umami API endpoint', 'umami-wp-connect' ),
            )
        );

        // Tracking fields
        add_settings_field(
            'enable_tracking',
            __( 'Enable Tracking', 'umami-wp-connect' ),
            array( $this, 'render_checkbox_field' ),
            'umami-wp-connect',
            'umami_tracking_section',
            array(
                'label_for' => 'enable_tracking',
                'description' => __( 'Enable Umami tracking on your site', 'umami-wp-connect' ),
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
                do_settings_sections( 'umami-wp-connect' );
                submit_button( __( 'Save Settings', 'umami-wp-connect' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configure your Umami analytics connection.', 'umami-wp-connect' ) . '</p>';
    }

    /**
     * Render tracking section
     */
    public function render_tracking_section() {
        echo '<p>' . esc_html__( 'Configure what events to track.', 'umami-wp-connect' ) . '</p>';
    }

    /**
     * Render advanced section
     */
    public function render_advanced_section() {
        echo '<p>' . esc_html__( 'Advanced configuration options.', 'umami-wp-connect' ) . '</p>';
    }

    /**
     * Render text field
     *
     * @param array $args Field arguments
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
     * @param array $args Field arguments
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
     * @param string $hook Current admin page hook
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
}

