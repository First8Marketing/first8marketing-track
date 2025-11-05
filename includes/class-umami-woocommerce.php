<?php
/**
 * Umami WooCommerce Integration Class
 * Handles WooCommerce event tracking
 *
 * @package UmamiWPConnect
 */

// Exit if accessed directly
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
        // Product view tracking
        add_action( 'woocommerce_after_single_product', array( $this, 'track_product_view' ) );

        // Add to cart tracking
        add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 6 );

        // Remove from cart tracking
        add_action( 'woocommerce_cart_item_removed', array( $this, 'track_remove_from_cart' ), 10, 2 );

        // Checkout tracking
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_checkout' ), 10, 3 );

        // Purchase tracking
        add_action( 'woocommerce_thankyou', array( $this, 'track_purchase' ) );

        // Add tracking data to footer
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

        $product_data = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'product_type' => $product->get_type(),
            'categories' => $this->get_product_categories( $product ),
        );

        Umami_Tracker::get_instance()->track_event( 'product_view', $product_data );
    }

    /**
     * Track add to cart
     *
     * @param string $cart_item_key Cart item key
     * @param int    $product_id    Product ID
     * @param int    $quantity      Quantity
     * @param int    $variation_id  Variation ID
     * @param array  $variation     Variation data
     * @param array  $cart_item_data Cart item data
     */
    public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        $product = wc_get_product( $variation_id ? $variation_id : $product_id );

        if ( ! $product ) {
            return;
        }

        $event_data = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'product_price' => $product->get_price(),
            'quantity' => $quantity,
            'cart_value' => $product->get_price() * $quantity,
            'categories' => $this->get_product_categories( $product ),
        );

        Umami_Tracker::get_instance()->track_event( 'add_to_cart', $event_data );
    }

    /**
     * Track remove from cart
     *
     * @param string $cart_item_key Cart item key
     * @param object $cart          Cart object
     */
    public function track_remove_from_cart( $cart_item_key, $cart ) {
        $cart_item = $cart->removed_cart_contents[ $cart_item_key ];
        $product = $cart_item['data'];

        if ( ! $product ) {
            return;
        }

        $event_data = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'quantity' => $cart_item['quantity'],
        );

        Umami_Tracker::get_instance()->track_event( 'remove_from_cart', $event_data );
    }

    /**
     * Track checkout start
     *
     * @param int    $order_id Order ID
     * @param array  $posted_data Posted data
     * @param object $order    Order object
     */
    public function track_checkout( $order_id, $posted_data, $order ) {
        $event_data = array(
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'items_count' => $order->get_item_count(),
        );

        Umami_Tracker::get_instance()->track_event( 'checkout_start', $event_data );
    }

    /**
     * Track purchase completion
     *
     * @param int $order_id Order ID
     */
    public function track_purchase( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Prevent duplicate tracking
        if ( $order->get_meta( '_umami_tracked' ) ) {
            return;
        }

        $event_data = array(
            'order_id' => $order_id,
            'revenue' => $order->get_total(),
            'tax' => $order->get_total_tax(),
            'shipping' => $order->get_shipping_total(),
            'items_count' => $order->get_item_count(),
            'payment_method' => $order->get_payment_method(),
        );

        Umami_Tracker::get_instance()->track_event( 'purchase', $event_data );

        // Mark as tracked
        $order->update_meta_data( '_umami_tracked', true );
        $order->save();
    }

    /**
     * Add WooCommerce tracking data to footer
     */
    public function add_woocommerce_tracking_data() {
        // This will be used by the JavaScript tracker
        // to send additional WooCommerce data with events
    }

    /**
     * Get product categories
     *
     * @param WC_Product $product Product object
     * @return array
     */
    private function get_product_categories( $product ) {
        $categories = array();
        $terms = get_the_terms( $product->get_id(), 'product_cat' );

        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $categories[] = $term->name;
            }
        }

        return $categories;
    }
}

