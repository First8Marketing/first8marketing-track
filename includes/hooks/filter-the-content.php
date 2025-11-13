<?php
/**
 * File: filter-the-content.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API properties use camelCase.
 */

add_filter(
	'the_content',
	function ( $content ) {
		if ( is_admin() ) {
			return $content;
		}

		$autotrack_links   = get_option( 'umami_autotrack_links', '1' ) === '1';
		$autotrack_buttons = get_option( 'umami_autotrack_buttons', '1' ) === '1';

		if ( ! $autotrack_links && ! $autotrack_buttons ) {
			return $content;
		}
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );

		$wrapped = '<?xml encoding="UTF-8"?><div>' . $content . '</div>';
		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		$anchors_with_markers = $xpath->query( '//a[descendant::span[contains(concat(" ", normalize-space(@class), " "), " umami-event ")] or descendant::*[@data-umami-event or @*[starts-with(name(), "data-umami-event-")]]]' );
		if ( $anchors_with_markers && $anchors_with_markers->length ) {
			foreach ( $anchors_with_markers as $a ) {
				$marker = null;
				foreach ( $a->getElementsByTagName( '*' ) as $child ) {
					$is_umami_span      = ( strcasecmp( $child->nodeName, 'span' ) === 0 && $child->hasAttribute( 'class' ) && strpos( ' ' . $child->getAttribute( 'class' ) . ' ', ' umami-event ' ) !== false );
					$has_any_umami_attr = $child->hasAttribute( 'data-umami-event' );
					if ( ! $has_any_umami_attr ) {
						foreach ( $child->attributes as $attr ) {
							if ( strpos( $attr->name, 'data-umami-event-' ) === 0 ) {
								$has_any_umami_attr = true;
								break;
							}
						}
					}
					if ( $is_umami_span || $has_any_umami_attr ) {
						$marker = $child;
						break;
					}
				}
				if ( $marker ) {
					if ( $marker->hasAttribute( 'data-umami-event' ) && ! $a->hasAttribute( 'data-umami-event' ) ) {
						$a->setAttribute( 'data-umami-event', $marker->getAttribute( 'data-umami-event' ) );
					}
					foreach ( $marker->attributes as $attr ) {
						$name = $attr->name;
						if ( strpos( $name, 'data-umami-event-' ) === 0 && ! $a->hasAttribute( $name ) ) {
							$a->setAttribute( $name, $attr->value );
						}
					}
					$parent = $marker->parentNode;
					if ( $parent ) {
						while ( $marker->firstChild ) {
							$parent->insertBefore( $marker->firstChild, $marker );
						}
						$parent->removeChild( $marker );
					}
				}
			}
		}

		if ( $autotrack_buttons ) {
			$buttons = $xpath->query( '//a[contains(@class, "wp-block-button__link") or contains(@class, "wp-element-button")]' );
			foreach ( $buttons as $button ) {
				if ( $button->hasAttribute( 'data-umami-event' ) || $button->hasAttribute( 'data-umami-skip' ) ) {
					continue;
				}

				$text       = trim( $button->textContent );
				$event_name = $text ? 'button:' . $text : 'button_click';

				$button->setAttribute( 'data-umami-event', $event_name );

				if ( ! $button->hasAttribute( 'href' ) || trim( $button->getAttribute( 'href' ) ) === '' ) {
					$button->setAttribute( 'href', 'javascript:void(0)' );
				}
			}
		}

     // phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
		$get_own_text = function ( \DOMNode $node ) {
			$txt = '';
			foreach ( $node->childNodes as $child ) {
				if ( XML_TEXT_NODE === $child->nodeType ) {
						$txt .= $child->nodeValue;
				} elseif ( XML_CDATA_SECTION_NODE === $child->nodeType ) {
					$txt .= $child->data;
				}
			}
			$txt = preg_replace( '/\s+/u', ' ', $txt );
			return trim( $txt );
		};
     // phpcs:enable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar

		if ( $autotrack_links ) {
			$links = $xpath->query( '//a[not(contains(@class, "wp-block-button__link")) and not(contains(@class, "wp-element-button"))]' );
			foreach ( $links as $link ) {
				if ( $link->hasAttribute( 'data-umami-skip' ) ) {
					continue;
				}
				$rel_val = $link->hasAttribute( 'rel' ) ? $link->getAttribute( 'rel' ) : '';
				if ( $rel_val && preg_match( '/(^|\s)umami:[a-z0-9\-]+/i', $rel_val ) ) {
					continue;
				}
				if ( $link->hasAttribute( 'data-umami-event' ) ) {
					continue;
				}
				$has_any_pair = false;
				foreach ( $link->attributes as $attr ) {
					if ( strpos( $attr->name, 'data-umami-event-' ) === 0 ) {
						$has_any_pair = true;
						break;
					}
				}
				if ( $has_any_pair ) {
					continue;
				}

				$text = $get_own_text( $link );
				if ( '' === $text ) {
                 // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
					$text = trim( preg_replace( '/\s+/u', ' ', $link->textContent ) );
				}
				$href = $link->hasAttribute( 'href' ) ? $link->getAttribute( 'href' ) : '';

				$event_name = $text ? ( 'link:' . $text ) : 'link_click';

				$link->setAttribute( 'data-umami-event', $event_name );

				if ( $href && '#' !== $href && '' !== $href ) {
					$link->setAttribute( 'data-umami-event-url', $href );
				}

				if ( ! $link->hasAttribute( 'href' ) || trim( $href ) === '' ) {
					$link->setAttribute( 'href', 'javascript:void(0)' );
				}
			}
		}

		$container = $dom->getElementsByTagName( 'div' )->item( 0 );
		$html      = '';
		if ( $container ) {
            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
			foreach ( $container->childNodes as $node ) {
				$html .= $dom->saveHTML( $node );
			}
		}

		return $html ? $html : $content;
	},
	20
);

add_action(
	'wp_footer',
	function () {
		if ( is_admin() ) {
			return;
		}

		$autotrack_forms = get_option( 'umami_autotrack_forms', '1' ) === '1';
		if ( ! $autotrack_forms ) {
			return;
		}

		?>
	<script>
	(function() {
		document.addEventListener('DOMContentLoaded', function() {
			var forms = document.querySelectorAll('form:not([data-umami-event]):not([data-umami-skip])');
			forms.forEach(function(form) {
				var id = form.getAttribute('id');
				var name = form.getAttribute('name');
				var eventName = 'form_submit';

				if (id) {
					eventName = 'form:' + id;
				} else if (name) {
					eventName = 'form:' + name;
				}

				form.setAttribute('data-umami-event', eventName);
			});
		});
	})();
	</script>
		<?php
	},
	100
);

