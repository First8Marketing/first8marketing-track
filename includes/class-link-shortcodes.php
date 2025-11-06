<?php
/**
 * Link Shortcodes and Gutenberg Blocks
 *
 * Provides shortcodes and blocks for inserting links.
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
 * Link Shortcodes Class
 *
 * Handles shortcodes and Gutenberg blocks for links.
 */
class Link_Shortcodes {

	/**
	 * Initialize shortcodes
	 */
	public function init() {
		add_shortcode( 'f8m_link', array( $this, 'link_shortcode' ) );
		add_shortcode( 'first8_link', array( $this, 'link_shortcode' ) ); // Alias.
		add_action( 'init', array( $this, 'register_gutenberg_block' ) );
	}

	/**
	 * Link shortcode handler
	 *
	 * Usage:
	 * [f8m_link id="123"]Click here[/f8m_link]
	 * [f8m_link slug="my-link"]Click here[/f8m_link]
	 * [f8m_link id="123" class="button" target="_blank"]
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Link HTML.
	 */
	public function link_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'id'     => '',
				'slug'   => '',
				'class'  => '',
				'target' => '',
				'title'  => '',
			),
			$atts,
			'f8m_link'
		);

		// Get link post.
		$link_post = null;
		if ( ! empty( $atts['id'] ) ) {
			$link_post = get_post( (int) $atts['id'] );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$link_post = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, Link_Manager::POST_TYPE );
		}

		if ( ! $link_post || Link_Manager::POST_TYPE !== $link_post->post_type ) {
			return '';
		}

		// Get link URL.
		$link_url = get_permalink( $link_post->ID );

		// Get link attributes.
		$nofollow  = get_post_meta( $link_post->ID, '_f8m_nofollow', true );
		$sponsored = get_post_meta( $link_post->ID, '_f8m_sponsored', true );

		// Build link attributes.
		$link_atts = array(
			'href' => esc_url( $link_url ),
		);

		if ( ! empty( $atts['class'] ) ) {
			$link_atts['class'] = esc_attr( $atts['class'] );
		}

		if ( ! empty( $atts['target'] ) ) {
			$link_atts['target'] = esc_attr( $atts['target'] );
		}

		if ( ! empty( $atts['title'] ) ) {
			$link_atts['title'] = esc_attr( $atts['title'] );
		} else {
			$link_atts['title'] = esc_attr( $link_post->post_title );
		}

		// Add rel attributes.
		$rel = array();
		if ( '1' === $nofollow ) {
			$rel[] = 'nofollow';
		}
		if ( '1' === $sponsored ) {
			$rel[] = 'sponsored';
		}
		if ( '_blank' === $atts['target'] ) {
			$rel[] = 'noopener';
			$rel[] = 'noreferrer';
		}
		if ( ! empty( $rel ) ) {
			$link_atts['rel'] = implode( ' ', $rel );
		}

		// Build link HTML.
		$link_html = '<a';
		foreach ( $link_atts as $key => $value ) {
			$link_html .= ' ' . $key . '="' . $value . '"';
		}
		$link_html .= '>';
		$link_html .= ! empty( $content ) ? do_shortcode( $content ) : esc_html( $link_post->post_title );
		$link_html .= '</a>';

		return $link_html;
	}

	/**
	 * Register Gutenberg block
	 */
	public function register_gutenberg_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block script.
		wp_register_script(
			'f8m-link-block',
			plugins_url( 'assets/js/link-block.js', dirname( __FILE__ ) ),
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
			UMAMI_WP_VERSION,
			true
		);

		// Register block.
		register_block_type(
			'first8marketing/link',
			array(
				'editor_script'   => 'f8m-link-block',
				'render_callback' => array( $this, 'render_gutenberg_block' ),
				'attributes'      => array(
					'linkId'   => array(
						'type'    => 'number',
						'default' => 0,
					),
					'linkSlug' => array(
						'type'    => 'string',
						'default' => '',
					),
					'text'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'className' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Render Gutenberg block
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block HTML.
	 */
	public function render_gutenberg_block( $attributes ) {
		$link_id   = isset( $attributes['linkId'] ) ? (int) $attributes['linkId'] : 0;
		$link_slug = isset( $attributes['linkSlug'] ) ? sanitize_title( $attributes['linkSlug'] ) : '';
		$text      = isset( $attributes['text'] ) ? $attributes['text'] : '';
		$class     = isset( $attributes['className'] ) ? $attributes['className'] : '';

		// Build shortcode.
		$shortcode = '[f8m_link';
		if ( $link_id > 0 ) {
			$shortcode .= ' id="' . $link_id . '"';
		} elseif ( ! empty( $link_slug ) ) {
			$shortcode .= ' slug="' . $link_slug . '"';
		}
		if ( ! empty( $class ) ) {
			$shortcode .= ' class="' . esc_attr( $class ) . '"';
		}
		$shortcode .= ']';
		$shortcode .= ! empty( $text ) ? esc_html( $text ) : '';
		$shortcode .= '[/f8m_link]';

		return do_shortcode( $shortcode );
	}
}

