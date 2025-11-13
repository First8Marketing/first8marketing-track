<?php
/**
 * File: update.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

/**
 * Render the update page.
 */
function umami_connect_update_page() {
	if ( isset( $_POST['umami_connect_self_update'] ) && check_admin_referer( 'umami_connect_self_update', 'umami_connect_self_update_nonce' ) ) {
		$update_version  = isset( $_POST['umami_update_version'] ) ? sanitize_text_field( wp_unslash( $_POST['umami_update_version'] ) ) : '';
		$release_api_url = 'https://api.github.com/repos/' . UMAMI_CONNECT_GITHUB_USER . '/' . UMAMI_CONNECT_GITHUB_REPO . '/releases/tags/' . urlencode( $update_version ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- GitHub API compatibility.
		$args            = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'umami-wp-connect-plugin',
			),
			'timeout' => 10,
		);
		$response        = wp_remote_get( $release_api_url, $args );
		$zip_url         = '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_urlencode -- Legacy code.
		if ( ! is_wp_error( $response ) && isset( $response['response']['code'] ) && 200 === $response['response']['code'] ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
				foreach ( $body['assets'] as $asset ) {
					if ( ! empty( $asset['name'] ) && ! empty( $asset['browser_download_url'] )
						&& preg_match( '/^umami-wp-connect-.*\\.zip$/', $asset['name'] )
					) {
						$zip_url = $asset['browser_download_url'];
						break;
					}
				}
			}
			if ( ! $zip_url && ! empty( $body['zipball_url'] ) ) {
				$zip_url = $body['zipball_url'];
			}
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD']
			&& isset( $_POST['umami_connect_self_update'] )
			&& check_admin_referer( 'umami_connect_self_update', 'umami_connect_self_update_nonce' )
		) {
			if ( $zip_url ) {
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					include_once ABSPATH . 'wp-admin/includes/file.php';
				}
				WP_Filesystem();
				global $wp_filesystem;
				$tmp_zip  = wp_tempnam( $zip_url );
				$zip_data = wp_remote_get( $zip_url, array( 'timeout' => 30 ) );
				if ( is_wp_error( $zip_data ) || empty( $zip_data['body'] ) ) {
					echo '<div class="notice notice-error"><b>Error downloading ZIP:</b> ' . esc_html( $zip_data->get_error_message() ) . '</div>';
					return;
				}
				if ( ! $wp_filesystem->put_contents( $tmp_zip, $zip_data['body'], FS_CHMOD_FILE ) ) {
					echo '<div class="notice notice-error"><b>Error:</b> Could not write temporary file.</div>';
					return;
				}
				$tmp_dir = WP_CONTENT_DIR . '/upgrade/umami-wp-connect-update';
				if ( is_dir( $tmp_dir ) ) {
					$wp_filesystem->rmdir( $tmp_dir, true );
				}
				$wp_filesystem->mkdir( $tmp_dir );
				$unzip = unzip_file( $tmp_zip, $tmp_dir );
				if ( is_wp_error( $unzip ) ) {
					echo '<div class="notice notice-error"><b>Error extracting ZIP:</b> ' . esc_html( $unzip->get_error_message() ) . '</div>';
					return;
				}
				$plugin_dir = WP_PLUGIN_DIR . '/umami-wp-connect';
				$src_dir    = $tmp_dir . '/umami-wp-connect';
				if ( ! is_dir( $src_dir ) ) {
					$dirs = glob( $tmp_dir . '/*', GLOB_ONLYDIR );
					if ( ! empty( $dirs ) ) {
						$src_dir = $dirs[0];
					}
				}
				$result = copy_dir( $src_dir, $plugin_dir );
				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><b>Error copying files:</b> ' . esc_html( $result->get_error_message() ) . '</div>';
					return;
				}
				$main_plugin_file_rel = 'umami-wp-connect/umami-connect.php';
				if ( ! is_plugin_active( $main_plugin_file_rel ) ) {
					activate_plugin( $main_plugin_file_rel );
				}
				delete_transient( 'umami_connect_latest_release' );
				echo '<div class="notice notice-success"><b>Update successful!</b> The plugin was updated via WP_Filesystem and reactivated.</div>';
			} else {
				echo '<div class="notice notice-error"><b>Error:</b> ZIP-URL could not be determined.</div>';
			}
		}
	}

	echo '<div class="wrap">';
	echo '<h1><b>umami Connect</b></h1>';
	echo '<h3>Update</h3>';

	$version_info    = umami_connect_get_version_info();
	$current_version = $version_info['current'];
	$github_url      = $version_info['github_url'];

	$github_api_url = 'https://api.github.com/repos/' . UMAMI_CONNECT_GITHUB_USER . '/' . UMAMI_CONNECT_GITHUB_REPO . '/releases';
	$args           = array(
		'headers' => array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'umami-wp-connect-plugin',
		),
		'timeout' => 8,
	);
	$releases       = array();

	$latest_version = $version_info['latest'];
	$latest_body    = '';

	$response = wp_remote_get( $github_api_url, $args );
	if ( ! is_wp_error( $response ) && isset( $response['response']['code'] ) && 200 === $response['response']['code'] ) {
		$releases = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $releases ) && is_array( $releases ) ) {
			$latest_body = $releases[0]['body'] ?? '';
		}
	}

	echo '<p><b>Current Version:</b> ' . esc_html( $current_version ) . '</p>';
	if ( $latest_version && '–' !== $latest_version ) {
		echo '<p><b>Latest Release:</b> ' . esc_html( $latest_version ) . ' ';
		echo '<a href="' . esc_url( $github_url ) . '" target="_blank">(Releases on GitHub)</a></p>';
	} else {
		$error_code = '';
		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
		} elseif ( isset( $response['response']['code'] ) ) {
			$error_code = $response['response']['code'];
		}
		echo '<div style="background:#fff;border:1px solid #e3e3e3;border-radius:8px;padding:16px 32px;margin-bottom:24px;max-width:600px;color:#b00;font-weight:500;">Could not fetch the latest version information from GitHub.<br>Please try again later.';
		if ( $error_code ) {
			echo '<br><span style="color:#333;font-size:13px;">Error code: ' . esc_html( $error_code ) . '</span>';
		}
		echo '</div>';
	}

	if ( $latest_body ) {
		/**
		 * Simple markdown parser.
		 *
		 * @param string $text The text to parse.
		 */
		function umami_simple_markdown( $text ) {
			$text             = preg_replace_callback(
				'/`([^`]+)`/',
				function ( $m ) {
					return '`' . str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $m[1] ) . '`';
				},
				$text
			);
			$text             = preg_replace_callback(
				'/```([\s\S]*?)```/',
				function ( $m ) {
						return '```' . str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $m[1] ) . '```';
				},
				$text
			);
			$blockquote_types = array(
				'NOTE'      => array(
					'color'  => '#eaf5ff',
					'border' => '#007cba',
					'svg'    => '<svg class="octicon octicon-info mr-2" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8Zm8-6.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM6.5 7.75A.75.75 0 0 1 7.25 7h1a.75.75 0 0 1 .75.75v2.75h.25a.75.75 0 0 1 0 1.5h-2a.75.75 0 0 1 0-1.5h.25v-2h-.25a.75.75 0 0 1-.75-.75ZM8 6a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path></svg>',
				),
				'TIP'       => array(
					'color'  => '#eaffea',
					'border' => '#28a745',
					'svg'    => '<svg class="octicon octicon-light-bulb mr-2" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M8 1.5c-2.363 0-4 1.69-4 3.75 0 .984.424 1.625.984 2.304l.214.253c.223.264.47.556.673.848.284.411.537.896.621 1.49a.75.75 0 0 1-1.484.211c-.04-.282-.163-.547-.37-.847a8.456 8.456 0 0 0-.542-.68c-.084-.1-.173-.205-.268-.32C3.201 7.75 2.5 6.766 2.5 5.25 2.5 2.31 4.863 0 8 0s5.5 2.31 5.5 5.25c0 1.516-.701 2.5-1.328 3.259-.095.115-.184.22-.268.319-.207.245-.383.453-.541.681-.208.3-.33.565-.37.847a.751.751 0 0 1-1.485-.212c.084-.593.337-1.078.621-1.489.203-.292.45-.584.673-.848.075-.088.147-.173.213-.253.561-.679.985-1.32.985-2.304 0-2.06-1.637-3.75-4-3.75ZM5.75 12h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5ZM6 15.25a.75.75 0 0 1 .75-.75h2.5a.75.75 0 0 1 0 1.5h-2.5a.75.75 0 0 1-.75-.75Z"></path></svg>',
				),
				'IMPORTANT' => array(
					'color'  => '#fff4e5',
					'border' => '#ff9800',
					'svg'    => '<svg class="octicon octicon-report mr-2" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M0 1.75C0 .784.784 0 1.75 0h12.5C15.216 0 16 .784 16 1.75v9.5A1.75 1.75 0 0 1 14.25 13H8.06l-2.573 2.573A1.458 1.458 0 0 1 3 14.543V13H1.75A1.75 1.75 0 0 1 0 11.25Zm1.75-.25a.25.25 0 0 0-.25.25v9.5c0 .138.112.25.25.25h2a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h6.5a.25.25 0 0 0 .25-.25v-9.5a.25.25 0 0 0-.25-.25Zm7 2.25v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 9a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"></path></svg>',
				),
				'WARNING'   => array(
					'color'  => '#fff3cd',
					'border' => '#ffc107',
					'svg'    => '<svg class="octicon octicon-alert mr-2" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0 1 14.082 15H1.918a1.75 1.75 0 0 1-1.543-2.575Zm1.763.707a.25.25 0 0 0-.44 0L1.698 13.132a.25.25 0 0 0 .22.368h12.164a.25.25 0 0 0 .22-.368Zm.53 3.996v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"></path></svg>',
				),
				'CAUTION'   => array(
					'color'  => '#fdecea',
					'border' => '#d32f2f',
					'svg'    => '<svg class="octicon octicon-stop mr-2" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M4.47.22A.749.749 0 0 1 5 0h6c.199 0 .389.079.53.22l4.25 4.25c.141.14.22.331.22.53v6a.749.749 0 0 1-.22.53l-4.25 4.25A.749.749 0 0 1 11 16H5a.749.749 0 0 1-.53-.22L.22 11.53A.749.749 0 0 1 0 11V5c0-.199.079-.389.22-.53Zm.84 1.28L1.5 5.31v5.38l3.81 3.81h5.38l3.81-3.81V5.31L10.69 1.5ZM8 4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path></svg>',
				),
			);
			foreach ( $blockquote_types as $type => $style ) {
				$pattern = '/^>\s*\[!' . $type . '\]\s*\n?((?:>.*\n?)*)/mi';
				$text    = preg_replace_callback(
					$pattern,
					function ( $matches ) use ( $type, $style ) {
						$content = preg_replace( '/^> ?/m', '', $matches[1] );
						$content = nl2br( trim( $content ) );
						$svg     = preg_replace( '/(<svg[^>]*)(>)/', '$1 style="fill:' . $style['border'] . ';color:' . $style['border'] . '"$2', $style['svg'] );
						$title   = '<span style="font-weight:600;font-size:15px;display:flex;align-items:center;gap:8px;margin-bottom:9px;color:' . $style['border'] . ';">' . $svg . '<span>' . ucfirst( strtolower( $type ) ) . '</span></span>';
						return '<div style="background:#fff;border-left:4px solid ' . $style['border'] . ';padding:12px 18px 12px 18px;margin:12px 0 16px 0;border-radius:6px;box-shadow:0 1px 2px #eee;font-size:15px;">' . $title . '<div style="margin-top:2px">' . $content . '</div></div>';
					},
					$text
				);
			}
			$text = preg_replace( '/^---+\s*$/m', '<hr style="border:none;border-top:1px solid #e3e3e3;margin:16px 0;">', $text );
			$text = preg_replace( '/^### (.*)$/m', '<h4>$1</h4>', $text );
			$text = preg_replace( '/^## (.*)$/m', '<h3>$1</h3>', $text );
			$text = preg_replace( '/^# (.*)$/m', '<h2>$1</h2>', $text );
			$text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );
			$text = preg_replace( '/\[(.*?)\]\(([^\s\)]+)\)/', '<a href="$2" target="_blank">$1</a>', $text );
			$text = preg_replace( '/^\- (.*)$/m', '<li>$1</li>', $text );
			$text = preg_replace_callback(
				'/(<li>.*?<\/li>\n?)+/s',
				function ( $matches ) {
					return '<ul>' . str_replace( "\n", '', $matches[0] ) . '</ul>';
				},
				$text
			);
			return $text;
		}

		$has_update    = umami_connect_has_update();
		$version_label = $has_update
		? '<span style="display:inline-block;background:#f0f0f0;color:#666;border-radius:12px;padding:2px 12px;font-size:13px;font-weight:500;">' . esc_html( $current_version ) . '</span> <span style="color:#999;font-size:14px;margin:0 6px;">→</span> <span style="display:inline-block;background:#007cba;color:#fff;border-radius:12px;padding:2px 12px;font-size:13px;font-weight:500;">' . esc_html( $latest_version ) . '</span>'
		: '<span style="display:inline-block;background:#28a745;color:#fff;border-radius:12px;padding:2px 12px;font-size:13px;font-weight:500;">' . esc_html( $current_version ) . '</span> <span style="color:#999;font-size:13px;margin-left:8px;">Current Version</span>';
		echo '<div style="background:#fff;border:1px solid #e3e3e3;border-radius:8px;padding:0;margin-bottom:24px;max-width:700px;">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $version_label contains pre-escaped HTML.
		echo '<div style="background:#f8f8f8;border-bottom:1px solid #e3e3e3;padding:12px 32px 10px 32px;border-radius:8px 8px 0 0;font-weight:600;font-size:16px;display:flex;align-items:center;gap:12px;">Release Notes ' . $version_label . '</div>';
		echo '<style>.umami-changelog ul { list-style: disc inside; margin-left: 1em; } .umami-changelog li { margin-bottom: 2px; }</style>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- umami_simple_markdown() returns sanitized HTML.
		echo '<div class="umami-changelog" style="padding:18px 32px 18px 32px;">' . umami_simple_markdown( $latest_body ) . '</div>';
		echo '</div>';
	}

	if ( ! empty( $releases ) && is_array( $releases ) && current_user_can( 'activate_plugins' ) ) {
		$is_localhost = ( isset( $_SERVER['HTTP_HOST'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ), 'localhost:8080' ) !== false );
		if ( $is_localhost ) {
			echo '<div style="background:#fff3cd;border:1px solid #ffeeba;border-radius:8px;padding:14px 32px;margin-bottom:24px;max-width:600px;color:#856404;font-weight:500;">Update is disabled during development (localhost:8080).</div>';
		} else {
			/**
			 * Compare two version strings.
			 *
			 * @param string $v1 First version.
			 * @param string $v2 Second version.
			 * @return int Comparison result.
			 */
			function umami_version_compare( $v1, $v2 ) {
				return version_compare( preg_replace( '/[^0-9.]/', '', $v1 ), preg_replace( '/[^0-9.]/', '', $v2 ) );
			}
			$cmp = umami_version_compare( $current_version, $latest_version );
			if ( $cmp < 0 || 0 === $cmp ) {
				$is_newer = ( $cmp < 0 );
				$btn_text = $is_newer
				? 'Update to version ' . esc_html( $latest_version )
				: 'Re-Install version ' . esc_html( $latest_version );
				echo '<form method="post" style="margin-top:24px;">';
				echo '<input type="hidden" name="umami_connect_self_update" value="1">';
				echo '<input type="hidden" name="umami_update_version" value="' . esc_attr( $latest_version ) . '">';
				wp_nonce_field( 'umami_connect_self_update', 'umami_connect_self_update_nonce' );
				echo '<button type="submit" class="button button-primary">' . esc_html( $btn_text ) . '</button>';
				echo '</form>';
			}
		}
	}

	echo '</div>';
}
