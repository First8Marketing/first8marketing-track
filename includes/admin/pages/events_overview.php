<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- Legacy filename.
/**
 * File: events_overview.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table queries required.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time data display.
 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin page with GET parameters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UMAMI_CONNECT_MAX_BLOCK_NESTING_DEPTH', 15 );

/**
 * Render the events overview page.
 */
function umami_connect_render_events_overview_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'umami-connect' ) );
	}

	if ( isset( $_POST['umami_delete_event'] ) && check_admin_referer( 'umami_delete_event', 'umami_delete_nonce' ) ) {
		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$block_index = isset( $_POST['block_index'] ) ? sanitize_text_field( wp_unslash( $_POST['block_index'] ) ) : '';
		$event_type  = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : 'button';

		if ( ! $post_id || $post_id <= 0 ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Error: Invalid post ID.</strong></p></div>';
		} elseif ( empty( $block_index ) || ! is_string( $block_index ) ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Error: Invalid block index.</strong></p></div>';
		} elseif ( ! in_array( $event_type, array( 'button', 'link', true ), true ) ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Error: Invalid event type.</strong></p></div>';
		} else {

			$result = umami_connect_delete_event_from_block( $post_id, $block_index, $event_type );
			if ( $result ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>Event deleted successfully.</strong></p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Could not delete event.</p></div>';
			}
		}
	}

	echo '<div class="wrap">';
	echo '<h1><b>umami Connect</b></h1>
        <h3>Event overview</h3>';
	echo '<p>Overview of all configured tracking events. Use "Edit Page/Post" to manage events in Gutenberg.</p>';

	$screen   = get_current_screen();
	$per_page = get_user_meta( get_current_user_id(), 'events_per_page', true );
	if ( empty( $per_page ) || $per_page < 1 ) {
		$per_page = $screen->get_option( 'per_page', 'default' );
	}

	$hidden_columns = get_hidden_columns( $screen );
	$columns        = umami_connect_events_overview_columns( array() );

	$events = apply_filters( 'umami_connect_get_all_events', array(), $per_page );

	$all_count        = count( $events );
	$events_count     = 0;
	$candidates_count = 0;

	foreach ( $events as $event ) {
		if ( isset( $event['is_tracked'] ) && $event['is_tracked'] ) {
			++$events_count;
		} else {
			++$candidates_count;
		}
	}

	$current_filter  = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all';
	$original_filter = $current_filter;
	$filter_switched = false;

	if ( 'events' === $current_filter && 0 === $events_count ) {
		$current_filter  = 'all';
		$filter_switched = ( 'events' === $original_filter );
	}
	if ( 'candidates' === $current_filter && 0 === $candidates_count ) {
		$current_filter  = 'all';
		$filter_switched = ( 'candidates' === $original_filter );
	}

	if ( $filter_switched ) {
		$switched_from = 'events' === $original_filter ? 'Events' : 'Candidates';
		echo '<div class="notice notice-warning is-dismissible" style="margin:15px 0;">';
		echo '<p><strong>Filter switched to "All":</strong> The "' . esc_html( $switched_from ) . '" filter shows no results, so all items are displayed instead.</p>';
		echo '</div>';
	}

	if ( empty( $events ) ) {
		echo '<div class="notice notice-info" style="margin:20px 0; padding:12px 16px 12px 12px; display:flex; align-items:center; gap:12px;">';
		echo '<span class="dashicons dashicons-info" style="font-size:22px; color:#2271b1;"></span>';
		echo '<div>';
		echo '<strong style="color:#2271b1; font-size:15px;">No events found</strong>';
		echo '<p style="margin:4px 0 0 0;">Start adding custom events by editing blocks in the Gutenberg editor and configuring Umami tracking in the block inspector.</p>';
		echo '</div>';
		echo '</div>';
	} else {
		echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; min-height: 32px;">';

		echo '<ul class="subsubsub" style="margin: 0;">';

		$all_class = 'all' === $current_filter ? 'current' : '';
		$all_url   = remove_query_arg( array( 'filter', 'paged' ) );
		echo '<li class="all"><a href="' . esc_url( $all_url ) . '" class="' . esc_attr( $all_class ) . '">All <span class="count">(' . esc_html( $all_count ) . ')</span></a>';

		if ( $events_count > 0 || $candidates_count > 0 ) {
			echo ' | ';
		}
		echo '</li>';

		if ( $events_count > 0 ) {
			$events_class = 'events' === $current_filter ? 'current' : '';
			$events_url   = add_query_arg( 'filter', 'events', remove_query_arg( 'paged' ) );
			echo '<li class="events"><a href="' . esc_url( $events_url ) . '" class="' . esc_attr( $events_class ) . '">Events <span class="count">(' . esc_html( $events_count ) . ')</span></a>';

			if ( $candidates_count > 0 ) {
				echo ' | ';
			}
			echo '</li>';
		}

		if ( $candidates_count > 0 ) {
			$candidates_class = 'candidates' === $current_filter ? 'current' : '';
			$candidates_url   = add_query_arg( 'filter', 'candidates', remove_query_arg( 'paged' ) );
			echo '<li class="candidates"><a href="' . esc_url( $candidates_url ) . '" class="' . esc_attr( $candidates_class ) . '">Candidates <span class="count">(' . esc_html( $candidates_count ) . ')</span></a></li>';
		}

		echo '</ul>';

		echo '<form method="get" style="margin: 0;">';
		echo '<input type="hidden" name="page" value="umami_connect_events_overview">';
		if ( 'all' !== $current_filter ) {
			echo '<input type="hidden" name="filter" value="' . esc_attr( $current_filter ) . '">';
		}
		echo '<div class="search-box" style="display: flex; align-items: center; gap: 8px;">';
		echo '<label class="screen-reader-text" for="event-search-input">Search Events:</label>';
		echo '<input type="search" id="event-search-input" name="s" value="' . esc_attr( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ) . '" placeholder="Search events..." style="width: 200px;" />';
		echo '<input type="submit" id="search-submit" class="button" value="Search">';
		echo '</div>';
		echo '</form>';

		echo '</div>';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$search   = trim( strtolower( $search ) );
		$filtered = array();

		foreach ( $events as $row ) {
			$rowtext = strtolower( $row['event'] . ' ' . $row['post_title'] . ' ' . $row['label'] );
			if ( '' !== $search && strpos( $rowtext, $search ) === false ) {
				continue;
			}

			$is_tracked = isset( $row['is_tracked'] ) ? $row['is_tracked'] : true;
			if ( 'events' === $current_filter && ! $is_tracked ) {
				continue;
			}
			if ( 'candidates' === $current_filter && $is_tracked ) {
				continue;
			}

			$filtered[] = $row;
		}
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'event';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc';
		$order   = strtolower( $order );
		usort(
			$filtered,
			function ( $a, $b ) use ( $orderby, $order ) {
				if ( 'block_type' === $orderby ) {
						$block_labels = array(
							'core/button'       => 'Button',
							'core/paragraph'    => 'Paragraph',
							'core/post-excerpt' => 'Excerpt',
							'core/heading'      => 'Heading',
							'core/quote'        => 'Quote',
							'core/pullquote'    => 'Pullquote',
							'core/list'         => 'List',
							'core/list-item'    => 'List Item',
							'core/columns'      => 'Columns',
							'core/cover'        => 'Cover',
							'core/group'        => 'Group',
						);
						$a_val        = strtolower( $block_labels[ $a['block_type'] ?? '' ] ?? ( $a['block_type'] ?? '' ) );
						$b_val        = strtolower( $block_labels[ $b['block_type'] ?? '' ] ?? ( $b['block_type'] ?? '' ) );
				} else {
					$a_val = strtolower( $a[ $orderby ] ?? '' );
					$b_val = strtolower( $b[ $orderby ] ?? '' );
				}
				if ( $a_val === $b_val ) {
					return 0;
				}
				if ( 'desc' === $order ) {
					return ( $a_val < $b_val ) ? 1 : -1;
				}
				return ( $a_val < $b_val ) ? -1 : 1;
			}
		);
		/**
		 * Generate a sortable column link.
		 *
		 * @param string $label Column label.
		 * @param string $col   Column key.
		 * @return string Escaped HTML for sortable link.
		 */
		function sort_link( $label, $col ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET params for display sorting only.
			$current = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'event';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET params for display sorting only.
			$order = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc';
			$order = strtolower( $order );
			$next  = ( $col === $current && 'asc' === $order ) ? 'desc' : 'asc';
			$url   = add_query_arg(
				array(
					'orderby' => $col,
					'order'   => $next,
				)
			);
			$arrow = ( $col === $current ) ? ( ' <span style="font-size:0.9em;">' . ( 'asc' === $order ? '▲' : '▼' ) . '</span>' ) : '';
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
		}
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ( $columns as $column_key => $column_name ) {
			if ( in_array( $column_key, $hidden_columns, true ) ) {
				continue;
			}
			if ( in_array( $column_key, array( 'event', 'post', 'block_type', 'label', true ), true ) ) {
				$sort_key = 'post' === $column_key ? 'post_title' : $column_key;
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sort_link() returns escaped HTML.
				echo '<th scope="col" class="manage-column column-' . esc_attr( $column_key ) . '">' . sort_link( $column_name, $sort_key ) . '</th>';
			} else {
				echo '<th scope="col" class="manage-column column-' . esc_attr( $column_key ) . '">' . esc_html( $column_name ) . '</th>';
			}
		}
		echo '</tr></thead>';
		echo '<tfoot><tr>';
		foreach ( $columns as $column_key => $column_name ) {
			if ( in_array( $column_key, $hidden_columns, true ) ) {
				continue;
			}
			if ( in_array( $column_key, array( 'event', 'post', 'block_type', 'label', true ), true ) ) {
				$sort_key = 'post' === $column_key ? 'post_title' : $column_key;
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sort_link() returns escaped HTML.
				echo '<th scope="col" class="manage-column column-' . esc_attr( $column_key ) . '">' . sort_link( $column_name, $sort_key ) . '</th>';
			} else {
				echo '<th scope="col" class="manage-column column-' . esc_attr( $column_key ) . '">' . esc_html( $column_name ) . '</th>';
			}
		}
		echo '</tr></tfoot>';
		echo '<tbody>';
		$block_labels = array(
			'core/button'       => 'Button',
			'core/paragraph'    => 'Paragraph',
			'core/post-excerpt' => 'Excerpt',
			'core/heading'      => 'Heading',
			'core/quote'        => 'Quote',
			'core/pullquote'    => 'Pullquote',
			'core/list'         => 'List',
			'core/list-item'    => 'List Item',
			'core/columns'      => 'Columns',
			'core/cover'        => 'Cover',
			'core/group'        => 'Group',
		);
		foreach ( $filtered as $row ) {
			$block_type  = $row['block_type'] ?? '';
			$block_label = $block_labels[ $block_type ] ?? $block_type;
			$block_index = $row['block_index'] ?? '';
			$event_type  = $row['event_type'] ?? 'button';
			$is_tracked  = isset( $row['is_tracked'] ) ? $row['is_tracked'] : true;
			$post_type   = get_post_type( $row['post_id'] );

			echo '<tr>';

			if ( ! in_array( 'event', $hidden_columns, true ) ) {
				echo '<td class="title column-title has-row-actions column-primary">';

				if ( $is_tracked ) {
					echo '<strong><code>' . esc_html( $row['event'] ) . '</code></strong>';
				} else {
					echo '<strong style="color:#999;"><em>' . esc_html( $row['event'] ) . '</em></strong>';
				}

				if ( $block_index ) {
					echo '<div class="row-actions">';

					if ( 'page' === $post_type ) {
						$edit_label = 'Edit Page';
					} else {
						$edit_label = 'Edit Post';
					}
					echo '<span class="edit">';
					echo '<a href="' . esc_url( get_edit_post_link( $row['post_id'] ) ) . '" target="_blank">' . esc_html( $edit_label ) . '</a>';

					if ( $is_tracked && 'none' !== $event_type ) {
						echo ' | </span>';
						echo '<span class="trash">';
						echo '<a href="#" class="delete-event submitdelete" data-post-id="' . esc_attr( $row['post_id'] ) . '" data-block-index="' . esc_attr( $block_index ) . '" data-event-type="' . esc_attr( $event_type ) . '" style="color:#b32d2e;">Delete</a>';
					}
					echo '</span>';
					echo '</div>';
				}
				echo '<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>';
				echo '</td>';
			}

			if ( ! in_array( 'post', $hidden_columns, true ) ) {
				echo '<td class="column-post">';
				echo '<a href="' . esc_url( get_edit_post_link( $row['post_id'] ) ) . '" target="_blank">' . esc_html( $row['post_title'] ) . '</a>';
				echo '</td>';
			}

			if ( ! in_array( 'block_type', $hidden_columns, true ) ) {
				echo '<td class="column-block_type">' . esc_html( $block_label ) . '</td>';
			}

			if ( ! in_array( 'label', $hidden_columns, true ) ) {
				echo '<td class="column-label">' . esc_html( $row['label'] ) . '</td>';
			}

			if ( ! in_array( 'data_pairs', $hidden_columns, true ) ) {
				echo '<td class="column-data_pairs">';
				if ( ! empty( $row['data_pairs'] ) && is_array( $row['data_pairs'] ) ) {
					$pairs = array();
					foreach ( $row['data_pairs'] as $pair ) {
						if ( ! empty( $pair['key'] ) ) {
							$pairs[] = '<li><span>' . esc_html( $pair['key'] ) . '</span>: <b>' . esc_html( $pair['value'] ) . '</b></li>';
						}
					}
					if ( ! empty( $pairs ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $pairs contains pre-escaped HTML.
						echo '<ul style="margin:0 0 0 18px; padding:0; list-style:disc;">' . implode( '', $pairs ) . '</ul>';
					} else {
						echo '<span style="color:#888;">–</span>';
					}
				} else {
					echo '<span style="color:#888;">–</span>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		global $wpdb;
		$total_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND (post_type = %s OR post_type = %s)",
				'publish',
				'post',
				'page'
			)
		);

		$total_pages = ceil( $total_posts / $per_page );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET param for pagination display only.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		if ( $total_pages > 1 ) {
			echo '<div class="tablenav bottom">';
			echo '<div class="alignleft actions bulkactions">';
			echo '</div>';
			echo '<div class="tablenav-pages">';

			echo '<span class="displaying-num">' . esc_html(
				sprintf(
					/* translators: %s is the number of items found. */
					_n( '%s item', '%s items', $total_posts, 'first8marketing-track' ),
					number_format_i18n( $total_posts )
				)
			) . '</span>';

			$base_url = remove_query_arg( 'paged' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET param for filter display only.
			$current_filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all';
			if ( 'all' !== $current_filter ) {
				$base_url = add_query_arg( 'filter', $current_filter, $base_url );
			}

			echo '<span class="pagination-links">';

			if ( $current_page > 1 ) {
				echo '<a class="first-page button" href="' . esc_url( $base_url ) . '">';
				echo '<span class="screen-reader-text">' . esc_html__( 'First page', 'first8marketing-track' ) . '</span>';
				echo '<span aria-hidden="true">&laquo;</span>';
				echo '</a>';
			} else {
				echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
			}

			if ( $current_page > 1 ) {
				$prev_url = add_query_arg( 'paged', $current_page - 1, $base_url );
				echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">';
				echo '<span class="screen-reader-text">' . esc_html__( 'Previous page', 'first8marketing-track' ) . '</span>';
				echo '<span aria-hidden="true">‹</span>';
				echo '</a>';
			} else {
				echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
			}

			echo '<span class="paging-input">';
			echo '<label for="current-page-selector" class="screen-reader-text">' . esc_html__( 'Current Page', 'first8marketing-track' ) . '</label>';
			echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . esc_attr( $current_page ) . '" size="' . esc_attr( strlen( $total_pages ) ) . '" aria-describedby="table-paging" />';
			echo '<span class="tablenav-paging-text"> ' . esc_html__( 'of', 'first8marketing-track' ) . ' ';
			echo '<span class="total-pages">' . esc_html( number_format_i18n( $total_pages ) ) . '</span></span>';
			echo '</span>';

			if ( $current_page < $total_pages ) {
				$next_url = add_query_arg( 'paged', $current_page + 1, $base_url );
				echo '<a class="next-page button" href="' . esc_url( $next_url ) . '">';
				echo '<span class="screen-reader-text">' . esc_html__( 'Next page', 'first8marketing-track' ) . '</span>';
				echo '<span aria-hidden="true">›</span>';
				echo '</a>';
			} else {
				echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
			}

			if ( $current_page < $total_pages ) {
				$last_url = add_query_arg( 'paged', $total_pages, $base_url );
				echo '<a class="last-page button" href="' . esc_url( $last_url ) . '">';
				echo '<span class="screen-reader-text">' . esc_html__( 'Last page', 'first8marketing-track' ) . '</span>';
				echo '<span aria-hidden="true">&raquo;</span>';
				echo '</a>';
			} else {
				echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
			}

			echo '</span>';
			echo '</div>';
			echo '<br class="clear" />';
			echo '</div>';
		}

		echo '<form id="delete-event-form" method="post" style="display:none;">';
		wp_nonce_field( 'umami_delete_event', 'umami_delete_nonce' );
		echo '<input type="hidden" id="delete-post-id" name="post_id" value="">';
		echo '<input type="hidden" id="delete-block-index" name="block_index" value="">';
		echo '<input type="hidden" id="delete-event-type" name="event_type" value="">';
		echo '<input type="hidden" name="umami_delete_event" value="1">';
		echo '</form>';

		wp_enqueue_script( 'wp-a11y' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		?>

		<div id="umami-delete-dialog" title="<?php esc_attr_e( 'Confirm Event Deletion', 'umami-connect' ); ?>" style="display: none;">
			<div style="padding: 8px 0;">
				<p style="margin: 0 0 12px; font-size: 14px; line-height: 1.5;">
					<?php esc_html_e( 'Are you sure you want to delete this event tracking configuration?', 'umami-connect' ); ?>
				</p>
				<p style="margin: 0; color: #d63638; font-weight: 600; font-size: 13px;">
					<?php esc_html_e( 'This action cannot be undone.', 'umami-connect' ); ?>
				</p>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var currentDeleteData = {};

			$('#umami-delete-dialog').dialog({
				autoOpen: false,
				modal: true,
				draggable: false,
				resizable: false,
				width: 450,
				height: 'auto',
				dialogClass: 'wp-dialog umami-delete-dialog',
				closeOnEscape: true,
				buttons: [
					{
						text: '<?php echo esc_js( __( 'Cancel', 'umami-connect' ) ); ?>',
						class: 'button',
						style: 'background: #f6f7f7; border-color: #50575e; color: #50575e;',
						click: function() {
							$(this).dialog('close');
						}
					},
					{
						text: '<?php echo esc_js( __( 'Delete Event', 'umami-connect' ) ); ?>',
						class: 'button button-primary',
						style: 'background: #d63638; border-color: #d63638;',
						click: function() {
							$('#delete-post-id').val(currentDeleteData.postId);
							$('#delete-block-index').val(currentDeleteData.blockIndex);
							$('#delete-event-type').val(currentDeleteData.eventType);

							$(this).dialog('close');
							$('#delete-event-form').submit();
						}
					}
				],
				open: function() {
					$('.ui-dialog-buttonset .button:first').focus();

					$('.umami-delete-dialog .ui-dialog-titlebar').css({
						'background': '#f9f9f9',
						'border-bottom': '1px solid #e2e4e7',
						'padding': '12px 16px'
					});

					$('.umami-delete-dialog .ui-dialog-buttonpane').css({
						'background': '#f9f9f9',
						'border-top': '1px solid #e2e4e7',
						'padding': '12px 16px'
					});
				}
			});

			$('.delete-event').on('click', function(e) {
				e.preventDefault();

				currentDeleteData = {
					postId: $(this).data('post-id'),
					blockIndex: $(this).data('block-index'),
					eventType: $(this).data('event-type')
				};

				$('#umami-delete-dialog').dialog('open');
			});

			$('#current-page-selector').on('keydown', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					var page = parseInt($(this).val(), 10);
					var totalPages = parseInt($('.total-pages').text().replace(/,/g, ''), 10);
					
					if (page && page > 0 && page <= totalPages) {
						var currentUrl = window.location.href;
						var newUrl = currentUrl.replace(/([?&])paged=\d+/, '$1paged=' + page);
						if (newUrl === currentUrl) {
							var separator = currentUrl.indexOf('?') !== -1 ? '&' : '?';
							newUrl = currentUrl + separator + 'paged=' + page;
						}
						window.location.href = newUrl;
					} else {
						$(this).val('<?php echo esc_js( $current_page ); ?>');
					}
				}
			});
		});
		</script>
		<?php
	}
	echo '</div>';
}

add_filter(
	'umami_connect_get_all_events',
	function ( $events, $per_page = 25 ) {
		global $wpdb;
		$result = array();

		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		$total_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND (post_type = %s OR post_type = %s)",
				'publish',
				'post',
				'page'
			)
		);

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = %s AND (post_type = %s OR post_type = %s) ORDER BY post_date DESC LIMIT %d OFFSET %d",
				'publish',
				'post',
				'page',
				$per_page,
				$offset
			)
		);

		/**
		 * Find Umami events in blocks.
		 *
		 * @param array  $blocks      The blocks to search.
		 * @param array  $result      The result array (passed by reference).
		 * @param int    $post_id     The post ID.
		 * @param string $post_title  The post title.
		 * @param string $parent_path The parent path.
		 */
		function find_umami_events( $blocks, &$result, $post_id, $post_title, $parent_path = '' ) {
			foreach ( $blocks as $idx => $block ) {
				$block_path = '' === $parent_path ? (string) $idx : $parent_path . '.' . $idx;
				$block_name = $block['blockName'] ?? '';

				$trackable_blocks = array( 'core/button', 'core/paragraph', 'core/heading', 'core/quote', 'core/pullquote' );
				$is_trackable     = in_array( $block_name, $trackable_blocks, true );

				if ( ! empty( $block['attrs']['umamiEvent'] ) ) {
					$event = trim( $block['attrs']['umamiEvent'] );
					$label = '';
					if ( ! empty( $block['attrs']['text'] ) ) {
						$label = $block['attrs']['text'];
					} elseif ( ! empty( $block['innerHTML'] ) ) {
						$label = wp_strip_all_tags( $block['innerHTML'] );
					}
					$data_pairs = array();
					if ( ! empty( $block['attrs']['umamiDataPairs'] ) && is_array( $block['attrs']['umamiDataPairs'] ) ) {
						$data_pairs = $block['attrs']['umamiDataPairs'];
					}
					$result[] = array(
						'event'       => $event,
						'post_id'     => $post_id,
						'post_title'  => $post_title,
						'block_type'  => $block_name,
						'label'       => $label,
						'data_pairs'  => $data_pairs,
						'block_index' => $block_path,
						'event_type'  => 'button',
						'is_tracked'  => true,
					);
				}
				if ( ! empty( $block['attrs']['umamiLinkEvents'] ) && is_array( $block['attrs']['umamiLinkEvents'] ) ) {
					foreach ( $block['attrs']['umamiLinkEvents'] as $ev ) {
						$event_name = isset( $ev['event'] ) ? trim( (string) $ev['event'] ) : '';
						$pairs      = array();
						if ( ! empty( $ev['pairs'] ) && is_array( $ev['pairs'] ) ) {
									$pairs = $ev['pairs'];
						}
						if ( '' !== $event_name || ! empty( $pairs ) ) {
								$link_text = isset( $ev['linkText'] ) ? (string) $ev['linkText'] : '';
								$link_url  = isset( $ev['linkUrl'] ) ? (string) $ev['linkUrl'] : '';
								$label     = trim( $link_text ) . ( $link_url ? ' → ' . $link_url : '' );
								$result[]  = array(
									'event'       => '' !== $event_name ? $event_name : 'link_click',
									'post_id'     => $post_id,
									'post_title'  => $post_title,
									'block_type'  => $block_name,
									'label'       => $label,
									'data_pairs'  => $pairs,
									'block_index' => $block_path,
									'event_type'  => 'link',
									'is_tracked'  => true,
								);
						}
					}
				}

				if ( $is_trackable && empty( $block['attrs']['umamiEvent'] ) && empty( $block['attrs']['umamiLinkEvents'] ) ) {
					$label = '';
					if ( ! empty( $block['attrs']['text'] ) ) {
						$label = $block['attrs']['text'];
					} elseif ( ! empty( $block['innerHTML'] ) ) {
						$label = wp_strip_all_tags( $block['innerHTML'] );
					}

					if ( 'core/button' === $block_name && ! empty( $label ) ) {
						$result[] = array(
							'event'       => '(Candidate)',
							'post_id'     => $post_id,
							'post_title'  => $post_title,
							'block_type'  => $block_name,
							'label'       => $label,
							'data_pairs'  => array(),
							'block_index' => $block_path,
							'event_type'  => 'none',
							'is_tracked'  => false,
						);
					} elseif ( 'core/button' !== $block_name && ! empty( $block['innerHTML'] ) && strpos( $block['innerHTML'], '<a ' ) !== false ) {
						$clean_html = wp_kses_post( $block['innerHTML'] );
						if ( preg_match_all( '/<a\s+[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i', $clean_html, $matches, PREG_SET_ORDER ) ) {
							foreach ( $matches as $match ) {
								$link_url   = esc_url_raw( $match[1] );
								$link_text  = wp_strip_all_tags( $match[2] );
								$link_label = trim( $link_text ) . ( $link_url ? ' → ' . $link_url : '' );

								if ( ! empty( $link_text ) ) {
									$result[] = array(
										'event'       => '(Candidate)',
										'post_id'     => $post_id,
										'post_title'  => $post_title,
										'block_type'  => $block_name,
										'label'       => $link_label,
										'data_pairs'  => array(),
										'block_index' => $block_path,
										'event_type'  => 'none',
										'is_tracked'  => false,
									);
								}
							}
						}
					}
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					find_umami_events( $block['innerBlocks'], $result, $post_id, $post_title, $block_path );
				}
			}
		}

		foreach ( $posts as $post ) {
			$blocks = parse_blocks( $post->post_content );
			find_umami_events( $blocks, $result, $post->ID, $post->post_title );
		}
		return $result;
	},
	10,
	2
);

/**
 * Delete an event from a specific block - HTML cleaning approach.
 *
 * @param int    $post_id     The post ID.
 * @param string $block_index The block index.
 * @param string $event_type  The event type.
 */
function umami_connect_delete_event_from_block( $post_id, $block_index, $event_type = 'button' ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$content = $post->post_content;
	$blocks  = parse_blocks( $content );
	$changed = false;

	remove_event_from_block_by_path( $blocks, $block_index, $event_type, $changed );

	if ( $changed ) {
		$new_content = serialize_blocks( $blocks );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( ! is_wp_error( $result ) ) {
			clean_post_cache( $post_id );

			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );

			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'blocks' );
			}

			return true;
		}
	}

	return false;
}



/**
 * Recursively find and remove event from block by path.
 *
 * @param array  $blocks       The blocks array (passed by reference).
 * @param string $target_path  The target path.
 * @param string $event_type   The event type.
 * @param bool   $changed      Whether the block was changed (passed by reference).
 * @param string $current_path The current path.
 * @param int    $depth        The current depth.
 */
function remove_event_from_block_by_path( &$blocks, $target_path, $event_type, &$changed, $current_path = '', $depth = 0 ) {
	if ( $depth > UMAMI_CONNECT_MAX_BLOCK_NESTING_DEPTH ) {
		return;
	}

	if ( ! is_array( $blocks ) ) {
		return;
	}

	foreach ( $blocks as $idx => &$block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		$block_path = '' === $current_path ? (string) $idx : $current_path . '.' . $idx;

		if ( $block_path === $target_path ) {
			if ( 'button' === $event_type ) {
				$attrs_changed = false;
				if ( isset( $block['attrs']['umamiEvent'] ) ) {
					unset( $block['attrs']['umamiEvent'] );
					$attrs_changed = true;
				}
				if ( isset( $block['attrs']['umamiDataPairs'] ) ) {
					unset( $block['attrs']['umamiDataPairs'] );
					$attrs_changed = true;
				}

				if ( $attrs_changed ) {
					if ( isset( $block['innerHTML'] ) ) {
						$clean_html         = $block['innerHTML'];
						$clean_html         = preg_replace( '/\s+data-umami-event="[^"]*"/', '', $clean_html );
						$clean_html         = preg_replace( '/\s+data-umami-event-[^=\s]*="[^"]*"/', '', $clean_html );
						$block['innerHTML'] = $clean_html;
					}

					if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
						$inner_content_count = count( $block['innerContent'] );
						for ( $i = 0; $i < $inner_content_count; $i++ ) {
							if ( is_string( $block['innerContent'][ $i ] ) ) {
								$clean_content               = $block['innerContent'][ $i ];
								$clean_content               = preg_replace( '/\s+data-umami-event="[^"]*"/', '', $clean_content );
								$clean_content               = preg_replace( '/\s+data-umami-event-[^=\s]*="[^"]*"/', '', $clean_content );
								$block['innerContent'][ $i ] = $clean_content;
							}
						}
					}
					$changed = true;
				}
			} elseif ( 'link' === $event_type ) {
				$attrs_changed = false;
				if ( isset( $block['attrs']['umamiLinkEvents'] ) ) {
					unset( $block['attrs']['umamiLinkEvents'] );
					$attrs_changed = true;
				}

				if ( $attrs_changed ) {
					if ( isset( $block['innerHTML'] ) ) {
						$clean_html = $block['innerHTML'];

						$clean_html = preg_replace( '/\s+rel="[^"]*umami:[^"]*"/', '', $clean_html );
						$clean_html = preg_replace( '/\s+rel="[^"]*umami--event--[^"]*"/', '', $clean_html );
						$clean_html = preg_replace( '/\s+data-umami-event="[^"]*"/', '', $clean_html );
						$clean_html = preg_replace( '/\s+data-umami-event-[^=\s]*="[^"]*"/', '', $clean_html );

						$clean_html = preg_replace_callback(
							'/\s+rel="([^"]*)"/',
							function ( $matches ) {
								$rel_value    = $matches[1];
								$tokens       = preg_split( '/\s+/', trim( $rel_value ) );
								$clean_tokens = array_filter(
									$tokens,
									function ( $token ) {
										return ! preg_match( '/^umami[:\-]/', $token );
									}
								);
								$clean_rel    = implode( ' ', $clean_tokens );

								if ( empty( trim( $clean_rel ) ) ) {
									return '';
								}
								return ' rel="' . $clean_rel . '"';
							},
							$clean_html
						);

						$clean_html = preg_replace( '/\s+rel=""/', '', $clean_html );
						$clean_html = preg_replace( '/\s+rel="\s*"/', '', $clean_html );

						$block['innerHTML'] = $clean_html;
					}

					if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
						$inner_content_count = count( $block['innerContent'] );
						for ( $i = 0; $i < $inner_content_count; $i++ ) {
							if ( is_string( $block['innerContent'][ $i ] ) ) {
								$clean_content = $block['innerContent'][ $i ];

								$clean_content = preg_replace( '/\s+rel="[^"]*umami:[^"]*"/', '', $clean_content );
								$clean_content = preg_replace( '/\s+rel="[^"]*umami--event--[^"]*"/', '', $clean_content );
								$clean_content = preg_replace( '/\s+data-umami-event="[^"]*"/', '', $clean_content );
								$clean_content = preg_replace( '/\s+data-umami-event-[^=\s]*="[^"]*"/', '', $clean_content );

								$clean_content = preg_replace_callback(
									'/\s+rel="([^"]*)"/',
									function ( $matches ) {
										$rel_value    = $matches[1];
										$tokens       = preg_split( '/\s+/', trim( $rel_value ) );
										$clean_tokens = array_filter(
											$tokens,
											function ( $token ) {
												return ! preg_match( '/^umami[:\-]/', $token );
											}
										);
										$clean_rel    = implode( ' ', $clean_tokens );

										if ( empty( trim( $clean_rel ) ) ) {
											return '';
										}
										return ' rel="' . $clean_rel . '"';
									},
									$clean_content
								);

								$clean_content = preg_replace( '/\s+rel=""/', '', $clean_content );
								$clean_content = preg_replace( '/\s+rel="\s*"/', '', $clean_content );

								$block['innerContent'][ $i ] = $clean_content;
							}
						}
					}
					$changed = true;
				}
			}
			return;
		}

		if ( ! $changed && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			if ( strpos( $target_path, $block_path . '.' ) === 0 ) {
				remove_event_from_block_by_path( $block['innerBlocks'], $target_path, $event_type, $changed, $block_path, $depth + 1 );
			}
		}
	}
}
