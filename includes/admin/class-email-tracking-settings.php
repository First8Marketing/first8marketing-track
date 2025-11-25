<?php
/**
 * Email Tracking Settings Handler
 *
 * Handles saving email tracking settings from admin page.
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

namespace First8Marketing\Track\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Tracking Settings Class
 */
class Email_Tracking_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_post_f8m_update_email_tracking_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Save email tracking settings
	 */
	public function save_settings() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'first8marketing-track' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['f8m_email_tracking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f8m_email_tracking_nonce'] ) ), 'f8m_email_tracking_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'first8marketing-track' ) );
		}

		// Save settings
		$enabled = isset( $_POST['f8m_email_tracking_enabled'] ) ? 1 : 0;
		update_option( 'f8m_email_tracking_enabled', $enabled );

		if ( isset( $_POST['f8m_tenant_id'] ) ) {
			$tenant_id = sanitize_text_field( wp_unslash( $_POST['f8m_tenant_id'] ) );
			update_option( 'f8m_tenant_id', $tenant_id );
		}

		// Redirect back with success message
		$redirect_url = add_query_arg(
			array(
				'page'    => 'f8m-email-tracking',
				'updated' => 'true',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}

// Initialize settings handler
new Email_Tracking_Settings();