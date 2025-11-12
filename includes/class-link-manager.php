<?php
/**
 * Link Management System
 *
 * Handles link shortening, tracking, and redirection.
 *
 * @package First8Marketing\Track
 * @since 1.0.0
 */

namespace First8Marketing\Track;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Manager Class
 *
 * Manages link creation, tracking, and redirection.
 */
class Link_Manager {

	/**
	 * Custom post type name
	 *
	 * @var string
	 */
	const POST_TYPE = 'f8m_link';

	/**
	 * Initialize link manager
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'custom_permalink' ), 10, 2 );
	}

	/**
	 * Register link custom post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Links', 'first8marketing-track' ),
			'singular_name'      => __( 'Link', 'first8marketing-track' ),
			'add_new'            => __( 'Add New', 'first8marketing-track' ),
			'add_new_item'       => __( 'Add New Link', 'first8marketing-track' ),
			'edit_item'          => __( 'Edit Link', 'first8marketing-track' ),
			'new_item'           => __( 'New Link', 'first8marketing-track' ),
			'view_item'          => __( 'View Link', 'first8marketing-track' ),
			'search_items'       => __( 'Search Links', 'first8marketing-track' ),
			'not_found'          => __( 'No links found', 'first8marketing-track' ),
			'not_found_in_trash' => __( 'No links found in trash', 'first8marketing-track' ),
			'menu_name'          => __( 'Link Manager', 'first8marketing-track' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'first8marketing',
			'query_var'           => true,
			'rewrite'             => array( 'slug' => 'go' ),
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'menu_icon'           => 'dashicons-admin-links',
			'supports'            => array( 'title' ),
			'show_in_rest'        => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register link category taxonomy
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => __( 'Link Categories', 'first8marketing-track' ),
			'singular_name'     => __( 'Link Category', 'first8marketing-track' ),
			'search_items'      => __( 'Search Categories', 'first8marketing-track' ),
			'all_items'         => __( 'All Categories', 'first8marketing-track' ),
			'parent_item'       => __( 'Parent Category', 'first8marketing-track' ),
			'parent_item_colon' => __( 'Parent Category:', 'first8marketing-track' ),
			'edit_item'         => __( 'Edit Category', 'first8marketing-track' ),
			'update_item'       => __( 'Update Category', 'first8marketing-track' ),
			'add_new_item'      => __( 'Add New Category', 'first8marketing-track' ),
			'new_item_name'     => __( 'New Category Name', 'first8marketing-track' ),
			'menu_name'         => __( 'Categories', 'first8marketing-track' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'link-category' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'link_category', array( self::POST_TYPE ), $args );
	}

	/**
	 * Add meta boxes for link configuration
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'f8m_link_target',
			__( 'Link Configuration', 'first8marketing-track' ),
			array( $this, 'render_link_config_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'f8m_link_advanced',
			__( 'Advanced Options', 'first8marketing-track' ),
			array( $this, 'render_advanced_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'f8m_link_stats',
			__( 'Link Statistics', 'first8marketing-track' ),
			array( $this, 'render_stats_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render link configuration meta box
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_link_config_meta_box( $post ) {
		wp_nonce_field( 'f8m_link_meta_box', 'f8m_link_meta_box_nonce' );

		$target_url     = get_post_meta( $post->ID, '_f8m_target_url', true );
		$redirect_type  = get_post_meta( $post->ID, '_f8m_redirect_type', true ) ?: '307';
		$nofollow       = get_post_meta( $post->ID, '_f8m_nofollow', true );
		$sponsored      = get_post_meta( $post->ID, '_f8m_sponsored', true );
		$track_me       = get_post_meta( $post->ID, '_f8m_track_me', true ) !== '0';
		?>
		<table class="form-table">
			<tr>
				<th><label for="f8m_target_url"><?php esc_html_e( 'Target URL', 'first8marketing-track' ); ?></label></th>
				<td>
					<input type="url" id="f8m_target_url" name="f8m_target_url" value="<?php echo esc_url( $target_url ); ?>" class="large-text" required />
					<p class="description"><?php esc_html_e( 'The URL this link will redirect to.', 'first8marketing-track' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="f8m_redirect_type"><?php esc_html_e( 'Redirect Type', 'first8marketing-track' ); ?></label></th>
				<td>
					<select id="f8m_redirect_type" name="f8m_redirect_type">
						<option value="301" <?php selected( $redirect_type, '301' ); ?>><?php esc_html_e( '301 Permanent', 'first8marketing-track' ); ?></option>
						<option value="302" <?php selected( $redirect_type, '302' ); ?>><?php esc_html_e( '302 Temporary', 'first8marketing-track' ); ?></option>
						<option value="307" <?php selected( $redirect_type, '307' ); ?>><?php esc_html_e( '307 Temporary (default)', 'first8marketing-track' ); ?></option>
						<option value="308" <?php selected( $redirect_type, '308' ); ?>><?php esc_html_e( '308 Permanent', 'first8marketing-track' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'HTTP redirect status code.', 'first8marketing-track' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Link Attributes', 'first8marketing-track' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="f8m_nofollow" value="1" <?php checked( $nofollow, '1' ); ?> />
						<?php esc_html_e( 'Add nofollow attribute', 'first8marketing-track' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="f8m_sponsored" value="1" <?php checked( $sponsored, '1' ); ?> />
						<?php esc_html_e( 'Mark as sponsored', 'first8marketing-track' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="f8m_track_me" value="1" <?php checked( $track_me, true ); ?> />
						<?php esc_html_e( 'Track clicks', 'first8marketing-track' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render advanced options meta box
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_advanced_meta_box( $post ) {
		$expires_at        = get_post_meta( $post->ID, '_f8m_expires_at', true );
		$rotation_enabled  = get_post_meta( $post->ID, '_f8m_rotation_enabled', true );
		$rotation_urls     = get_post_meta( $post->ID, '_f8m_rotation_urls', true ) ?: array();
		?>
		<table class="form-table">
			<tr>
				<th><label for="f8m_expires_at"><?php esc_html_e( 'Expiration Date', 'first8marketing-track' ); ?></label></th>
				<td>
					<input type="datetime-local" id="f8m_expires_at" name="f8m_expires_at" value="<?php echo esc_attr( $expires_at ); ?>" />
					<p class="description"><?php esc_html_e( 'Link will stop working after this date.', 'first8marketing-track' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'URL Rotation', 'first8marketing-track' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="f8m_rotation_enabled" value="1" <?php checked( $rotation_enabled, '1' ); ?> />
						<?php esc_html_e( 'Enable URL rotation (A/B testing)', 'first8marketing-track' ); ?>
					</label>
					<div id="f8m_rotation_urls" style="margin-top: 10px;">
						<p class="description"><?php esc_html_e( 'Add multiple URLs with weights for rotation.', 'first8marketing-track' ); ?></p>
						<!-- Rotation URLs will be added via JavaScript -->
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render statistics meta box
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_stats_meta_box( $post ) {
		// Get click statistics from Umami
		$total_clicks   = $this->get_link_clicks( $post->ID );
		$unique_clicks  = $this->get_unique_clicks( $post->ID );
		$last_click     = $this->get_last_click( $post->ID );
		?>
		<div class="f8m-link-stats">
			<p><strong><?php esc_html_e( 'Total Clicks:', 'first8marketing-track' ); ?></strong> <?php echo esc_html( number_format( $total_clicks ) ); ?></p>
			<p><strong><?php esc_html_e( 'Unique Visitors:', 'first8marketing-track' ); ?></strong> <?php echo esc_html( number_format( $unique_clicks ) ); ?></p>
			<?php if ( $last_click ) : ?>
				<p><strong><?php esc_html_e( 'Last Click:', 'first8marketing-track' ); ?></strong> <?php echo esc_html( human_time_diff( strtotime( $last_click ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'first8marketing-track' ); ?></p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=f8m-link-analytics&link_id=' . $post->ID ) ); ?>" class="button"><?php esc_html_e( 'View Detailed Analytics', 'first8marketing-track' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Save meta box data
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['f8m_link_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f8m_link_meta_box_nonce'] ) ), 'f8m_link_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save target URL.
		if ( isset( $_POST['f8m_target_url'] ) ) {
			update_post_meta( $post_id, '_f8m_target_url', esc_url_raw( wp_unslash( $_POST['f8m_target_url'] ) ) );
		}

		// Save redirect type.
		if ( isset( $_POST['f8m_redirect_type'] ) ) {
			update_post_meta( $post_id, '_f8m_redirect_type', sanitize_text_field( wp_unslash( $_POST['f8m_redirect_type'] ) ) );
		}

		// Save checkboxes.
		update_post_meta( $post_id, '_f8m_nofollow', isset( $_POST['f8m_nofollow'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_f8m_sponsored', isset( $_POST['f8m_sponsored'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_f8m_track_me', isset( $_POST['f8m_track_me'] ) ? '1' : '0' );

		// Save expiration.
		if ( isset( $_POST['f8m_expires_at'] ) ) {
			update_post_meta( $post_id, '_f8m_expires_at', sanitize_text_field( wp_unslash( $_POST['f8m_expires_at'] ) ) );
		}

		// Save rotation settings.
		update_post_meta( $post_id, '_f8m_rotation_enabled', isset( $_POST['f8m_rotation_enabled'] ) ? '1' : '0' );
	}

	/**
	 * Handle link redirect
	 */
	public function handle_redirect() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		global $post;

		// Check if link is expired.
		$expires_at = get_post_meta( $post->ID, '_f8m_expires_at', true );
		if ( $expires_at && strtotime( $expires_at ) < current_time( 'timestamp' ) ) {
			wp_die( esc_html__( 'This link has expired.', 'first8marketing-track' ) );
		}

		// Get target URL.
		$target_url = get_post_meta( $post->ID, '_f8m_target_url', true );
		if ( ! $target_url ) {
			wp_die( esc_html__( 'Invalid link.', 'first8marketing-track' ) );
		}

		// Track click if enabled.
		$track_me = get_post_meta( $post->ID, '_f8m_track_me', true );
		if ( '0' !== $track_me ) {
			$this->track_click( $post->ID, $target_url );
		}

		// Get redirect type.
		$redirect_type = get_post_meta( $post->ID, '_f8m_redirect_type', true ) ?: '307';

		// Perform redirect with additional validation.
		$safe_url = esc_url_raw( $target_url );
		if ( $safe_url && wp_http_validate_url( $safe_url ) ) {
			wp_redirect( $safe_url, (int) $redirect_type );
			exit;
		} else {
			wp_die( esc_html__( 'Invalid redirect URL.', 'first8marketing-track' ) );
		}
	}

	/**
	 * Track link click
	 *
	 * @param int    $link_id    Link post ID.
	 * @param string $target_url Target URL.
	 */
	private function track_click( $link_id, $target_url ) {
		// Send event to Umami.
		$umami_events = Umami_Events::get_instance();
		$umami_events->track_event(
			'link_click',
			array(
				'link_id'    => $link_id,
				'link_slug'  => get_post_field( 'post_name', $link_id ),
				'target_url' => $target_url,
			)
		);
	}

	/**
	 * Get total clicks for a link
	 *
	 * @param int $link_id Link post ID.
	 * @return int Total clicks.
	 */
	private function get_link_clicks( $link_id ) {
		// Query Umami database for click count.
		// This would be implemented with actual database query.
		return 0;
	}

	/**
	 * Get unique clicks for a link
	 *
	 * @param int $link_id Link post ID.
	 * @return int Unique clicks.
	 */
	private function get_unique_clicks( $link_id ) {
		// Query Umami database for unique visitor count.
		return 0;
	}

	/**
	 * Get last click timestamp
	 *
	 * @param int $link_id Link post ID.
	 * @return string|null Last click timestamp.
	 */
	private function get_last_click( $link_id ) {
		// Query Umami database for last click.
		return null;
	}

	/**
	 * Custom permalink for links
	 *
	 * @param string   $post_link The post's permalink.
	 * @param \WP_Post $post      The post object.
	 * @return string Modified permalink.
	 */
	public function custom_permalink( $post_link, $post ) {
		if ( self::POST_TYPE === $post->post_type ) {
			return home_url( '/go/' . $post->post_name . '/' );
		}
		return $post_link;
	}
}


