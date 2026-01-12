<?php
/**
 * Umami WooCommerce Integration Class
 * Handles WooCommerce event tracking
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
 * Umami WooCommerce Class
 */
class Umami_WooCommerce {

	/**
	 * Single instance
	 *
	 * @var Umami_WooCommerce
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Umami_WooCommerce
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
		if ( ! get_option( 'enable_woocommerce', true ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Product view tracking.
		add_action( 'woocommerce_after_single_product', array( $this, 'track_product_view' ) );

		// Add to cart tracking.
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 6 );

		// Remove from cart tracking.
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_remove_from_cart' ), 10, 2 );

		// Checkout tracking.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_checkout' ), 10, 3 );

		// Purchase tracking.
		add_action( 'woocommerce_thankyou', array( $this, 'track_purchase' ) );

		// Add tracking data to footer.
		add_action( 'wp_footer', array( $this, 'add_woocommerce_tracking_data' ) );
	}

	/**
	 * Track product view
	 */
	public function track_product_view() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$categories    = $this->get_product_categories( $product );
		$category_ids  = $this->get_product_category_ids( $product );
		$first_cat_id  = ! empty( $category_ids ) ? $category_ids[0] : null;

		$product_data = array(
			'product_id'      => $product->get_id(),
			'product_name'    => $product->get_name(),
			'product_price'   => $product->get_price(),
			'product_type'    => $product->get_type(),
			'categories'      => $categories,
			// WooCommerce custom fields for Umami.
			'wc_product_id'   => (string) $product->get_id(),
			'wc_category_id'  => $first_cat_id ? (string) $first_cat_id : null,
			'wc_product_name' => $product->get_name(),
			'wc_price'        => (float) $product->get_price(),
		);

		Umami_Tracker::get_instance()->track_event( 'product_view', $product_data );
	}

	/**
	 * Track add to cart
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Cart item data.
	 */
	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce hook signature.
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );

		if ( ! $product ) {
			return;
		}

		$event_data = array(
			'product_id'    => $product->get_id(),
			'product_name'  => $product->get_name(),
			'product_price' => $product->get_price(),
			'quantity'      => $quantity,
			'cart_value'    => $product->get_price() * $quantity,
			'categories'    => $this->get_product_categories( $product ),
		);

		Umami_Tracker::get_instance()->track_event( 'add_to_cart', $event_data );
	}

	/**
	 * Track remove from cart
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param object $cart          Cart object.
	 */
	public function track_remove_from_cart( $cart_item_key, $cart ) {
		$cart_item = $cart->removed_cart_contents[ $cart_item_key ];
		$product   = $cart_item['data'];

		if ( ! $product ) {
			return;
		}

		$event_data = array(
			'product_id'   => $product->get_id(),
			'product_name' => $product->get_name(),
			'quantity'     => $cart_item['quantity'],
		);

		Umami_Tracker::get_instance()->track_event( 'remove_from_cart', $event_data );
	}

	/**
	 * Track checkout start
	 *
	 * @param int    $order_id Order ID.
	 * @param array  $posted_data Posted data.
	 * @param object $order    Order object.
	 */
	public function track_checkout( $order_id, $posted_data, $order ) {
		$event_data = array(
			'order_id'    => $order_id,
			'order_total' => $order->get_total(),
			'items_count' => $order->get_item_count(),
		);

		Umami_Tracker::get_instance()->track_event( 'checkout_start', $event_data );
	}

	/**
	 * Track purchase completion
	 *
	 * @param int $order_id Order ID.
	 */
	public function track_purchase( $order_id ) {
		if ( empty( $order_id ) ) {
			error_log( '[Umami WooCommerce] Invalid order ID provided for tracking' );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			error_log( sprintf( '[Umami WooCommerce] Order %d not found', $order_id ) );
			return;
		}

		// Prevent duplicate tracking - check BEFORE tracking.
		if ( $order->get_meta( '_umami_tracked' ) ) {
			error_log( sprintf( '[Umami WooCommerce] Order %d already tracked, skipping', $order_id ) );
			return;
		}

		// Mark as tracked BEFORE sending to prevent race conditions
		$order->update_meta_data( '_umami_tracked', time() );
		$order->save();

		try {
			$event_data = array(
				'order_id'       => $order_id,
				'revenue'        => $order->get_total(),
				'tax'            => $order->get_total_tax(),
				'shipping'       => $order->get_shipping_total(),
				'items_count'    => $order->get_item_count(),
				'payment_method' => $order->get_payment_method(),
				// WooCommerce custom fields for Umami.
				'wc_order_id'    => (string) $order_id,
				'wc_revenue'     => (float) $order->get_total(),
				'wc_tax'         => (float) $order->get_total_tax(),
				'wc_shipping'    => (float) $order->get_shipping_total(),
			);

			$result = Umami_Tracker::get_instance()->track_event( 'purchase', $event_data );

			if ( is_wp_error( $result ) ) {
				error_log( sprintf(
					'[Umami WooCommerce] Failed to track purchase for order %d: %s',
					$order_id,
					$result->get_error_message()
				) );
				// Remove tracking flag if event failed to send
				$order->delete_meta_data( '_umami_tracked' );
				$order->save();
			} else {
				error_log( sprintf(
					'[Umami WooCommerce] Successfully tracked purchase for order %d (revenue: %s)',
					$order_id,
					$order->get_total()
				) );
			}

		} catch ( Exception $e ) {
			error_log( sprintf(
				'[Umami WooCommerce] Exception tracking purchase for order %d: %s',
				$order_id,
				$e->getMessage()
			) );
			// Remove tracking flag on exception
			$order->delete_meta_data( '_umami_tracked' );
			$order->save();
		}
	}

	/**
	 * Add WooCommerce tracking data to footer
	 */
	public function add_woocommerce_tracking_data() {
		// This will be used by the JavaScript tracker to send additional WooCommerce data with events.
	}

	/**
	 * Get product categories
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_product_categories( $product ) {
		$categories = array();
		$terms      = get_the_terms( $product->get_id(), 'product_cat' );

		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = $term->name;
			}
		}

		return $categories;
	}

	/**
	 * Get product category IDs
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_product_category_ids( $product ) {
		$category_ids = array();
		$terms        = get_the_terms( $product->get_id(), 'product_cat' );

		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$category_ids[] = $term->term_id;
			}
		}

		return $category_ids;
	}
}
