<?php
/**
 * File: filter-render-block.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API properties use camelCase.
 */

add_filter(
	'render_block',
	function ( $block_content, $block ) {
		if ( empty( $block['attrs'] ) ) {
			return $block_content;
		}
		$attrs = $block['attrs'];

		$event     = isset( $attrs['umamiEvent'] ) ? trim( $attrs['umamiEvent'] ) : '';
		$data_keys = array();
		if ( ! empty( $attrs['umamiDataPairs'] ) && is_array( $attrs['umamiDataPairs'] ) ) {
			foreach ( $attrs['umamiDataPairs'] as $pair ) {
				if ( ! empty( $pair['key'] ) && isset( $pair['value'] ) ) {
					$key               = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $pair['key'] ) );
					$data_keys[ $key ] = $pair['value'];
				}
			}
		}

		if ( 'core/button' === $block['blockName'] ) {
			if ( ! $event && empty( $data_keys ) ) {
				return $block_content;
			}
			$block_content = preg_replace( '/(<div[^>]*class="[^"]*wp-block-button[^"]*")([^>]*data-umami-event="[^"]*")?([^>]*data-umami-data-[^=]*="[^"]*")*/i', '$1', $block_content );
			$block_content = preg_replace_callback(
				'/<a([^>]*class="[^\"]*wp-block-button__link[^\"]*")([^>]*)>/i',
				function ( $m ) use ( $event, $data_keys ) {
					$attr     = '';
					$href_val = null;
					if ( preg_match( '/href\s*=\s*([\"\"])\s*(.*?)\s*\1/i', $m[2], $hm ) ) {
							$href_val = trim( $hm[2] );
					} elseif ( preg_match( '/href\s*=\s*([^\s>]+)/i', $m[2], $hm2 ) ) {
						$href_val = trim( $hm2[1] );
					}
					$has_valid_href = ( is_string( $href_val ) && '' !== $href_val );

					if ( $event ) {
						$attr .= ' data-umami-event="' . esc_attr( $event ) . '"';
					}
					foreach ( $data_keys as $key => $val ) {
						$attr .= ' data-umami-event-' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
					}
					if ( ! $has_valid_href ) {
						$attr .= ' href="javascript:void(0)"';
					}
					return '<a' . $m[1] . $m[2] . $attr . '>';
				},
				$block_content
			);
			return $block_content;
		}

		$supports_link_events = in_array( $block['blockName'], array( 'core/paragraph', 'core/heading', 'core/post-excerpt', 'core/quote', 'core/pullquote', 'core/list', 'core/list-item', 'core/columns', 'core/cover', 'core/group', true ), true );
		$link_events          = isset( $attrs['umamiLinkEvents'] ) && is_array( $attrs['umamiLinkEvents'] ) ? $attrs['umamiLinkEvents'] : array();

		if ( ! $supports_link_events && ! $event && empty( $data_keys ) ) {
			return $block_content;
		}

		if ( $supports_link_events || $event || ! empty( $data_keys ) ) {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$wrapped = '<div>' . $block_content . '</div>';
			$dom->loadHTML( '<?xml encoding="UTF-8"?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();

			$xpath = new DOMXPath( $dom );

			$by_id = array();
			foreach ( $link_events as $ev ) {
				if ( ! empty( $ev['id'] ) ) {
					$by_id[ $ev['id'] ] = $ev;
				}
			}

			$norm = function ( $s ) {
				$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$s = preg_replace( '/\s+/u', ' ', $s ?? '' );
				$s = trim( $s );
				return mb_strtolower( $s, 'UTF-8' );
			};

			$anchor_nodes = $xpath->query( '//a' );
			if ( $anchor_nodes && $anchor_nodes->length ) {
				foreach ( $anchor_nodes as $a ) {
						$matched = null;

						$rel = $a->hasAttribute( 'rel' ) ? $a->getAttribute( 'rel' ) : '';
					if ( $rel && preg_match( '/(^|\s)umami:([a-z0-9\-]+)/i', $rel, $mm ) ) {
						$rid = $mm[2];
						if ( isset( $by_id[ $rid ] ) ) {
										$matched = $by_id[ $rid ];
						}
					}

					if ( ! $matched && ! empty( $link_events ) ) {
						$href = $a->hasAttribute( 'href' ) ? $a->getAttribute( 'href' ) : '';
						$text = $norm( $a->textContent );
						foreach ( $link_events as $ev ) {
							$ev_url  = isset( $ev['linkUrl'] ) ? (string) $ev['linkUrl'] : '';
							$ev_text = $norm( isset( $ev['linkText'] ) ? (string) $ev['linkText'] : '' );
							if ( '' !== $ev_url && '' !== $href && $href === $ev_url && '' !== $ev_text && $text === $ev_text ) {
								$matched = $ev;
								break;
							}
						}
					}

					if ( ! $matched && ! empty( $link_events ) ) {
						$href = $a->hasAttribute( 'href' ) ? $a->getAttribute( 'href' ) : '';
						if ( '' !== $href ) {
								$candidates = array_values(
									array_filter(
										$link_events,
										function ( $ev ) use ( $href ) {
											return isset( $ev['linkUrl'] ) && (string) $ev['linkUrl'] === $href;
										}
									)
								);
							if ( count( $candidates ) === 1 ) {
								$matched = $candidates[0];
							}
						}
					}

					if ( $matched && ( ! empty( $matched['event'] ) || ( ! empty( $matched['pairs'] ) && is_array( $matched['pairs'] ) ) ) ) {
						if ( ! empty( $matched['event'] ) ) {
							$a->setAttribute( 'data-umami-event', (string) $matched['event'] );
						}
						if ( ! empty( $matched['pairs'] ) && is_array( $matched['pairs'] ) ) {
							foreach ( $matched['pairs'] as $pair ) {
								if ( ! empty( $pair['key'] ) && isset( $pair['value'] ) ) {
									$key = strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $pair['key'] ) );
									$a->setAttribute( 'data-umami-event-' . $key, (string) $pair['value'] );
								}
							}
						}
						continue;
					}

					if ( $event || ! empty( $data_keys ) ) {
						if ( $event && ! $a->hasAttribute( 'data-umami-event' ) ) {
							$a->setAttribute( 'data-umami-event', esc_attr( $event ) );
						}
						foreach ( $data_keys as $k => $v ) {
							$attr_name = 'data-umami-event-' . $k;
							if ( ! $a->hasAttribute( $attr_name ) ) {
										$a->setAttribute( $attr_name, esc_attr( $v ) );
							}
						}
					}
				}
			}

			$container = $dom->getElementsByTagName( 'div' )->item( 0 );
			$html      = '';
			if ( $container ) {
				foreach ( $container->childNodes as $node ) {
					$html .= $dom->saveHTML( $node );
				}
				return $html;
			}
		}

		return $block_content;
	},
	20,
	2
);
