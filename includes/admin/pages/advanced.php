<?php
/**
 * File: advanced.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

/**
 * Render the advanced settings page.
 */
function umami_connect_advanced_page() {
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'host-url'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tabs = array(
		'host-url'       => 'Host URL',
		'auto-track'     => 'Auto track',
		'domains'        => 'Domains',
		'tag'            => 'Tag',
		'exclude-search' => 'Exclude search',
		'exclude-hash'   => 'Exclude hash',
		'dnt'            => 'Do Not Track',
		'before-send'    => 'Before send',
	);
	?>
	<div class="wrap">
		<h1><b>umami Connect</b></h1>
		<h3>Advanced</h3>
		<h2 class="nav-tab-wrapper" style="margin-top:12px;">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=umami_connect_advanced&tab=' . $key ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<div style="margin-top:16px; max-width: 760px;">
			<form action="options.php" method="post">
				<?php settings_fields( 'umami_connect_advanced' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php if ( 'host-url' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_tracker_host_url">Host URL override</label></th>
							<td>
								<input type="url" class="regular-text" id="umami_tracker_host_url" name="umami_tracker_host_url" value="<?php echo esc_attr( get_option( 'umami_tracker_host_url', '' ) ); ?>" placeholder="https://analytics.example.com" />
								<p class="description">Sets <code>data-host-url</code> on the tracker script. Leave empty to use the script host.</p>
							</td>
						</tr>
					<?php elseif ( 'auto-track' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_disable_auto_track">Disable auto tracking</label></th>
							<td>
								<?php $v = get_option( 'umami_disable_auto_track', '0' ); ?>
								<label><input type="checkbox" id="umami_disable_auto_track" name="umami_disable_auto_track" value="1" <?php checked( $v, '1' ); ?> /> Set <code>data-auto-track="false"</code> to disable Umami's built-in auto tracking.</label>
								<p class="description">Note: The plugin's Automation settings are separate and can still emit events.</p>
							</td>
						</tr>
					<?php elseif ( 'domains' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_tracker_domains">Allowed domains</label></th>
							<td>
								<input type="text" class="regular-text" id="umami_tracker_domains" name="umami_tracker_domains" value="<?php echo esc_attr( get_option( 'umami_tracker_domains', '' ) ); ?>" placeholder="example.com,example.org" />
								<p class="description">Comma separated. Sets <code>data-domains</code> to restrict where the tracker runs.</p>
							</td>
						</tr>
					<?php elseif ( 'tag' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_tracker_tag">Event tag</label></th>
							<td>
								<input type="text" class="regular-text" id="umami_tracker_tag" name="umami_tracker_tag" value="<?php echo esc_attr( get_option( 'umami_tracker_tag', '' ) ); ?>" placeholder="umami-eu" />
								<p class="description">Sets <code>data-tag</code> so you can filter events by tag in Umami.</p>
							</td>
						</tr>
					<?php elseif ( 'exclude-search' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_tracker_exclude_search">Exclude search</label></th>
							<td>
								<?php $v = get_option( 'umami_tracker_exclude_search', '0' ); ?>
								<label><input type="checkbox" id="umami_tracker_exclude_search" name="umami_tracker_exclude_search" value="1" <?php checked( $v, '1' ); ?> /> Set <code>data-exclude-search="true"</code> to ignore URL query parameters.</label>

							</td>
						</tr>
					<?php elseif ( 'exclude-hash' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_tracker_exclude_hash">Exclude hash</label></th>
							<td>
								<?php $v = get_option( 'umami_tracker_exclude_hash', '0' ); ?>
								<label><input type="checkbox" id="umami_tracker_exclude_hash" name="umami_tracker_exclude_hash" value="1" <?php checked( $v, '1' ); ?> /> Set <code>data-exclude-hash="true"</code> to ignore URL hash fragments.</label>
							</td>
						</tr>
					<?php elseif ( 'dnt' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="umami_tracker_do_not_track">Respect Do Not Track</label></th>
							<td>
								<?php $v = get_option( 'umami_tracker_do_not_track', '0' ); ?>
								<label><input type="checkbox" id="umami_tracker_do_not_track" name="umami_tracker_do_not_track" value="1" <?php checked( $v, '1' ); ?> /> Set <code>data-do-not-track="true"</code> to respect the browser setting.</label>
							</td>
						</tr>
					<?php elseif ( 'before-send' === $tab ) : ?>
						<tr>
							<th scope="row"><label>beforeSend</label></th>
							<td>
								<?php
								$mode          = get_option( 'umami_tracker_before_send_mode', 'function_name' );
								$function_name = get_option( 'umami_tracker_before_send', '' );
								$inline_code   = get_option( 'umami_tracker_before_send_inline', '' );
								?>
								<fieldset>
									<p style="margin: 0 0 8px;">Choose how to provide <code>beforeSend</code>:</p>
									<div style="display:flex; gap:16px; align-items:center; margin-bottom:8px;">
										<label style="display:inline-flex; align-items:center; gap:6px;">
											<input type="radio" name="umami_tracker_before_send_mode" value="disabled" <?php checked( $mode, 'disabled' ); ?> />
											<strong>Disabled</strong>
										</label>
										<label style="display:inline-flex; align-items:center; gap:6px;">
											<input type="radio" name="umami_tracker_before_send_mode" value="function_name" <?php checked( $mode, 'function_name' ); ?> />
											<strong>Function name</strong>
										</label>
										<label style="display:inline-flex; align-items:center; gap:6px;">
											<input type="radio" name="umami_tracker_before_send_mode" value="inline" <?php checked( $mode, 'inline' ); ?> />
											<strong>Inline script</strong>
										</label>
									</div></fieldset>

								<fieldset id="before_send_disabled_field" style="margin:8px 0 0;">
									<p class="description">beforeSend hook is disabled. No function will be called before events are sent.</p>
								</fieldset>

								<fieldset>

									<div style="margin:8px 0 0;" id="before_send_function_name_field">
										<label for="umami_tracker_before_send" style="display:block; font-weight:600;">Global function name</label>
										<input type="text" class="regular-text" id="umami_tracker_before_send" name="umami_tracker_before_send" value="<?php echo esc_attr( $function_name ); ?>" placeholder="beforeSendHandler" pattern="^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$" title="Valid JS function name, e.g. beforeSendHandler or MyApp.handlers.beforeSend" />
										<p>
											<button type="button" class="button" id="umami_fn_check">Check function</button>
										</p>
										<p id="umami_fn_check_result" class="description" style="display:none; margin-top:6px;"></p>
										<p class="description">Reference an existing global function (available on the frontend), e.g. <code>beforeSendHandler</code>.</p>
									</div>

									<div style="margin:16px 0 0;" id="before_send_inline_field">
										<label for="umami_tracker_before_send_inline" style="display:block; font-weight:600;">Inline function</label>
										<textarea class="large-text code" rows="10" id="umami_tracker_before_send_inline" name="umami_tracker_before_send_inline" placeholder="function(payload, url) {&#10;  // Inspect or modify payload&#10;  return payload;&#10;}"><?php echo esc_textarea( $inline_code ); ?></textarea>
										<p>
											<button type="button" class="button button-primary" id="umami_inline_test">Test function</button>
											<button type="button" class="button" id="umami_inline_insert_example">Insert example</button>
											<button type="button" class="button button-secondary" id="umami_inline_clear">Clear</button>
										</p>
										<p id="umami_inline_test_result" class="description" style="display:none; margin-top:6px;"></p>
										<p class="description">Provide a JavaScript function that starts with <code>function(</code>. Return the payload or a falsy value (e.g. <code>false</code>) to cancel sending.</p>
										<details style="margin-top:6px;">
											<summary>Example</summary>
											<pre style="background:#f6f7f7; padding:8px; overflow:auto;">function(payload, url) {
	// Block events for preview URLs.
	if (url.includes('preview=true')) return false;
	// Attach custom info.
	payload.locale = document.documentElement.lang || 'en';
	return payload;
}</pre>
										</details>
									</div>
								</fieldset>
								<?php
								// Enqueue external JavaScript (CSP-compliant)
								wp_enqueue_script(
									'umami-admin-advanced',
									plugins_url( 'assets/js/admin-advanced.js', dirname( dirname( __FILE__ ) ) ),
									array(),
									'1.0.0',
									true
								);
								?>
								<!-- Data element for JavaScript to access site URL -->
								<div id="umami-advanced-data" data-site-url="<?php echo esc_attr( home_url( '/' ) ); ?>" style="display:none;"></div>
							</td>
						</tr>
					<?php endif; ?>
					</tbody>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
}
?>
