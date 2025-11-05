<?php
/**
 * Umami Events Class
 * Handles WordPress core event tracking
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
 * Umami Events Class
 */
class Umami_Events {

	/**
	 * Single instance
	 *
	 * @var Umami_Events
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Umami_Events
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
		// Page view tracking (handled by JavaScript).

		// Form submission tracking.
		if ( get_option( 'enable_form_tracking', true ) ) {
			add_action( 'wp_footer', array( $this, 'add_form_tracking_script' ) );
		}

		// Search tracking.
		add_action( 'pre_get_posts', array( $this, 'track_search' ) );

		// Comment tracking.
		add_action( 'comment_post', array( $this, 'track_comment' ), 10, 3 );

		// User registration tracking.
		add_action( 'user_register', array( $this, 'track_user_registration' ) );

		// Login tracking.
		add_action( 'wp_login', array( $this, 'track_login' ), 10, 2 );
	}

	/**
	 * Add form tracking script to footer
	 */
	public function add_form_tracking_script() {
		?>
		<script>
		(function() {
			// Track form submissions.
			document.addEventListener('submit', function(e) {
				if (e.target.tagName === 'FORM') {
					var formId = e.target.id || 'unknown';
					var formAction = e.target.action || window.location.href;
					
					if (typeof umami !== 'undefined') {
						umami.track('form_submit', {
							form_id: formId,
							form_action: formAction
						});
					}
				}
			}, true);
		})();
		</script>
		<?php
	}

	/**
	 * Track search queries
	 *
	 * @param WP_Query $query Query object.
	 */
	public function track_search( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$search_term = get_search_query();

			if ( ! empty( $search_term ) ) {
				Umami_Tracker::get_instance()->track_event(
					'search',
					array(
						'search_term'   => $search_term,
						'results_count' => $query->found_posts,
					)
				);
			}
		}
	}

	/**
	 * Track comment submission
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Comment approval status.
	 * @param array      $commentdata Comment data.
	 */
	public function track_comment( $comment_id, $approved, $commentdata ) {
		Umami_Tracker::get_instance()->track_event(
			'comment_submit',
			array(
				'post_id'  => $commentdata['comment_post_ID'],
				'approved' => $approved,
			)
		);
	}

	/**
	 * Track user registration
	 *
	 * @param int $user_id User ID.
	 */
	public function track_user_registration( $user_id ) {
		Umami_Tracker::get_instance()->track_event(
			'user_register',
			array(
				'user_id' => $user_id,
			)
		);
	}

	/**
	 * Track user login
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 */
	public function track_login( $user_login, $user ) {
		Umami_Tracker::get_instance()->track_event(
			'user_login',
			array(
				'user_id'    => $user->ID,
				'user_login' => $user_login,
			)
		);
	}
}

