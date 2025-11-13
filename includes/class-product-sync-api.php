<?php
/**
 * Product Sync REST API
 *
 * Provides REST API endpoints for syncing WooCommerce products
 * to the recommendation engine.
 *
 * @package First8Marketing_Track
 * @since 1.0.0
 */

namespace First8Marketing\Track;

defined( 'ABSPATH' ) || exit;

/**
 * Product Sync API Class
 *
 * Handles REST API endpoints for product synchronization.
 */
class Product_Sync_API {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'first8marketing/v1';

	/**
	 * Initialize the API
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get all products
		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
					'modified_after' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get single product
		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Get product count
		register_rest_route(
			self::NAMESPACE,
			'/products/count',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product_count' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check API permission
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if authorized, false otherwise.
	 */
	public function check_permission( $request ) {
		// Check for API key in header
		$api_key = $request->get_header( 'X-API-Key' );
		
		if ( empty( $api_key ) ) {
			return false;
		}

		// Get stored API key from settings
		$stored_key = get_option( 'f8m_track_api_key', '' );

		if ( empty( $stored_key ) ) {
			// Generate API key if not exists
			$stored_key = wp_generate_password( 32, false );
			update_option( 'f8m_track_api_key', $stored_key );
		}

		return hash_equals( $stored_key, $api_key );
	}

	/**
	 * Get products
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_products( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'first8marketing-track' ),
				array( 'status' => 400 )
			);
		}

		$page            = $request->get_param( 'page' );
		$per_page        = $request->get_param( 'per_page' );
		$modified_after  = $request->get_param( 'modified_after' );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		// Filter by modified date
		if ( ! empty( $modified_after ) ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_modified',
					'after'  => $modified_after,
				),
			);
		}

		$query    = new \WP_Query( $args );
		$products = array();

		foreach ( $query->posts as $post ) {
			$products[] = $this->format_product( $post->ID );
		}

		return rest_ensure_response(
			array(
				'products'    => $products,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Get single product
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_product( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'first8marketing-track' ),
				array( 'status' => 400 )
			);
		}

		$product_id = $request->get_param( 'id' );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new \WP_Error(
				'product_not_found',
				__( 'Product not found', 'first8marketing-track' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->format_product( $product_id ) );
	}

	/**
	 * Get product count
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_product_count( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'first8marketing-track' ),
				array( 'status' => 400 )
			);
		}

		$counts = wp_count_posts( 'product' );

		return rest_ensure_response(
			array(
				'total'     => (int) $counts->publish,
				'draft'     => (int) $counts->draft,
				'pending'   => (int) $counts->pending,
				'private'   => (int) $counts->private,
			)
		);
	}

	/**
	 * Format product data
	 *
	 * @param int $product_id Product ID.
	 * @return array Formatted product data.
	 */
	private function format_product( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array();
		}

		// Get categories
		$categories = array();
		$terms      = get_the_terms( $product_id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = $term->name;
			}
		}

		// Get tags
		$tags  = array();
		$terms = get_the_terms( $product_id, 'product_tag' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$tags[] = $term->name;
			}
		}

		// Get attributes
		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$attribute_values = wc_get_product_terms( $product_id, $attribute->get_name(), array( 'fields' => 'names' ) );
				$attributes[ $attribute->get_name() ] = $attribute_values;
			} else {
				$attributes[ $attribute->get_name() ] = $attribute->get_options();
			}
		}

		// Get image URLs
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

		$gallery_ids  = $product->get_gallery_image_ids();
		$gallery_urls = array();
		foreach ( $gallery_ids as $gallery_id ) {
			$gallery_urls[] = wp_get_attachment_url( $gallery_id );
		}

		return array(
			'id'               => $product_id,
			'name'             => $product->get_name(),
			'slug'             => $product->get_slug(),
			'description'      => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'sku'              => $product->get_sku(),
			'price'            => (float) $product->get_price(),
			'regular_price'    => (float) $product->get_regular_price(),
			'sale_price'       => (float) $product->get_sale_price(),
			'on_sale'          => $product->is_on_sale(),
			'stock_status'     => $product->get_stock_status(),
			'stock_quantity'   => $product->get_stock_quantity(),
			'categories'       => $categories,
			'tags'             => $tags,
			'attributes'       => $attributes,
			'image_url'        => $image_url,
			'gallery_urls'     => $gallery_urls,
			'permalink'        => get_permalink( $product_id ),
			'type'             => $product->get_type(),
			'featured'         => $product->is_featured(),
			'rating_count'     => $product->get_rating_count(),
			'average_rating'   => (float) $product->get_average_rating(),
			'total_sales'      => $product->get_total_sales(),
			'created_at'       => get_post_field( 'post_date', $product_id ),
			'modified_at'      => get_post_field( 'post_modified', $product_id ),
		);
	}
}

// Initialize the API
new Product_Sync_API();

