<?php
/**
 * File: dashboard-status-widget.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

add_action(
	'wp_dashboard_setup',
	function () {
		wp_add_dashboard_widget(
			'umami_connect_update_widget',
			'umami Connect',
			function () {
				$version_info = umami_connect_get_version_info();
				$has_update   = umami_connect_has_update();

				$events                = apply_filters( 'umami_connect_get_all_events', array() );
				$event_names_count     = 0;
				$event_key_value_count = 0;
				$event_names           = array();
				foreach ( $events as $row ) {
					if ( ! empty( $row['event'] ) ) {
						$event_names[ $row['event'] ] = true;
					}
					if ( ! empty( $row['data_pairs'] ) && is_array( $row['data_pairs'] ) ) {
						$event_key_value_count += count( $row['data_pairs'] );
					}
				}
				$event_names_count = count( $event_names );
				echo '<div class="umami-health-widget" style="display:flex; gap:0; align-items:stretch; max-width:520px; margin:auto;">';
				echo '<div style="flex:0.7; min-width:140px; max-width:180px; display:flex; flex-direction:column; align-items:center; justify-content:center;">';
				if ( $has_update ) {
					echo '<div style="margin-bottom:1px;">'
					. '<svg width="20" height="20" viewBox="0 0 204.688 186.621" xmlns="http://www.w3.org/2000/svg"><g><rect height="186.621" opacity="0" width="204.688" x="0" y="0"/><path d="M26.6602 185.547L178.027 185.547C194.629 185.547 204.688 174.023 204.688 159.082C204.688 154.492 203.32 149.707 200.879 145.41L125.098 13.3789C120.02 4.49219 111.328 0 102.344 0C93.3594 0 84.5703 4.49219 79.5898 13.3789L3.80859 145.41C1.17188 149.805 0 154.492 0 159.082C0 174.023 10.0586 185.547 26.6602 185.547ZM26.7578 170.215C19.9219 170.215 15.8203 164.941 15.8203 158.984C15.8203 157.129 16.2109 154.785 17.2852 152.734L92.9688 20.8008C95.0195 17.1875 98.7305 15.625 102.344 15.625C105.957 15.625 109.57 17.1875 111.621 20.8008L187.305 152.832C188.379 154.883 188.867 157.129 188.867 158.984C188.867 164.941 184.57 170.215 177.832 170.215Z" fill="#ff9800" fill-opacity="0.85"/><path d="M102.344 119.727C107.031 119.727 109.766 116.992 109.863 111.914L111.23 60.4492C111.328 55.4688 107.422 51.7578 102.246 51.7578C96.9727 51.7578 93.2617 55.3711 93.3594 60.3516L94.6289 111.914C94.7266 116.895 97.4609 119.727 102.344 119.727ZM102.344 151.465C108.008 151.465 112.891 146.973 112.891 141.309C112.891 135.547 108.105 131.152 102.344 131.152C96.582 131.152 91.7969 135.645 91.7969 141.309C91.7969 146.875 96.6797 151.465 102.344 151.465Z" fill="#ff9800" fill-opacity="0.85"/></g></svg>'
					. '</div>';
				} else {
					echo '<div style="margin-bottom:1px;">'
					. '<svg width="20" height="20" viewBox="0 0 199.219 199.316" xmlns="http://www.w3.org/2000/svg"><g><rect height="199.316" opacity="0" width="199.219" x="0" y="0"/><path d="M99.6094 199.219C154.59 199.219 199.219 154.59 199.219 99.6094C199.219 44.6289 154.59 0 99.6094 0C44.6289 0 0 44.6289 0 99.6094C0 154.59 44.6289 199.219 99.6094 199.219ZM99.6094 182.617C53.7109 182.617 16.6016 145.508 16.6016 99.6094C16.6016 53.7109 53.7109 16.6016 99.6094 16.6016C145.508 16.6016 182.617 53.7109 182.617 99.6094C182.617 145.508 145.508 182.617 99.6094 182.617Z" fill="#46b450" fill-opacity="0.85"/><path d="M88.8672 145.996C92.0898 145.996 94.8242 144.434 96.7773 141.406L141.406 71.1914C142.48 69.2383 143.75 67.0898 143.75 64.9414C143.75 60.5469 139.844 57.7148 135.742 57.7148C133.301 57.7148 130.859 59.2773 129.004 62.1094L88.4766 127.148L69.2383 102.246C66.8945 99.1211 64.7461 98.3398 62.0117 98.3398C57.8125 98.3398 54.4922 101.758 54.4922 106.055C54.4922 108.203 55.3711 110.254 56.7383 112.109L80.5664 141.406C83.0078 144.629 85.6445 145.996 88.8672 145.996Z" fill="#46b450" fill-opacity="0.85"/></g></svg>'
					. '</div>';
				}
				if ( $has_update ) {
					echo '<div style="margin-bottom:1px;"><b>Update available</b></div>';
					if ( current_user_can( 'update_plugins' ) ) {
						echo '<a href="' . esc_url( admin_url( 'admin.php?page=umami_connect_update' ) ) . '" style="text-decoration: none; font-size: 10px">Update now</a>';
					}
				} else {
					echo '<div style="margin-bottom:8px;"><b>You\'re up to date</b></div>';
				}
				echo '</div>';

				echo '<div style="width:1px; background:#e0e0e0; margin:0 16px; border-radius:1px;"></div>';

				echo '<div style="flex:1.3; min-width:180px; max-width:320px; display:flex; flex-direction:column; justify-content:center;">';
				echo '<table style="width:100%; border-collapse:collapse; font-size:14px;">';
				echo '<tr><td style="padding:6px 10px 6px 0; color:#666;">Event names</td><td style="padding:6px 0; font-weight:bold; text-align:right;">' . intval( $event_names_count ) . '</td></tr>';
				echo '<tr><td style="padding:6px 10px 6px 0; color:#666;">Event key-value pairs</td><td style="padding:6px 0; font-weight:bold; text-align:right;">' . intval( $event_key_value_count ) . '</td></tr>';
				echo '</table>';
				echo '</div>';
				echo '</div>';
				echo '<style>.umami-health-widget .umami-status-label.orange{color:#d63638;} .umami-health-widget .umami-status-label.green{color:#46b450;} .umami-progress-wrapper.orange svg circle:last-child{stroke:#d63638;} .umami-progress-wrapper.green svg circle:last-child{stroke:#46b450;}</style>';
			}
		);
	}
);
