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

								<script>
								(function() {
									var radios = document.querySelectorAll('input[name="umami_tracker_before_send_mode"]');
									var fnField = document.getElementById('before_send_function_name_field');
									var fnInput = document.getElementById('umami_tracker_before_send');
									var fnCheckBtn = document.getElementById('umami_fn_check');
									var fnResultEl = document.getElementById('umami_fn_check_result');
									var inlineField = document.getElementById('before_send_inline_field');
									var inlineInput = document.getElementById('umami_tracker_before_send_inline');
									var insertBtn = document.getElementById('umami_inline_insert_example');
									var clearBtn = document.getElementById('umami_inline_clear');
									var testBtn = document.getElementById('umami_inline_test');
									var resultEl = document.getElementById('umami_inline_test_result');
									var submitBtn = document.getElementById('submit') || document.querySelector('input[type="submit"].button-primary');
									var inlineTestPassed = false;

									var siteUrl = <?php echo wp_json_encode( home_url( '/' ) ); ?>;
									var siteOrigin = (function(u){ try { return new URL(u).origin; } catch(e) { return window.location.origin; } })(siteUrl);

									function updateSubmitState(mode) {
										if ( ! submitBtn ) { return; }
										if ( mode === 'inline' ) {
											submitBtn.disabled = ! inlineTestPassed;
										} else {
											submitBtn.disabled = false;
										}
									}

									function resetInlineTestState() {
										inlineTestPassed = false;
										if ( resultEl ) {
											resultEl.style.display = 'none';
											resultEl.textContent = '';
											resultEl.style.color = '';
										}
									}

									function toggle() {
										var checked = document.querySelector('input[name="umami_tracker_before_send_mode"]:checked');
										var mode = checked ? checked.value : 'disabled';
										var disabledField = document.getElementById('before_send_disabled_field');
										
										if ( mode === 'disabled' ) {
											fnField.style.display = 'none';
											inlineField.style.display = 'none';
											disabledField.style.display = 'block';
											fnInput.disabled = true;
											inlineInput.disabled = true;
										} else if ( mode === 'function_name' ) {
											fnField.style.display = 'block';
											inlineField.style.display = 'none';
											disabledField.style.display = 'none';
											fnInput.disabled = false;
											inlineInput.disabled = true;
										} else {
											fnField.style.display = 'none';
											inlineField.style.display = 'block';
											disabledField.style.display = 'none';
											fnInput.disabled = true;
											inlineInput.disabled = false;
										}

										updateSubmitState( mode );
									}

									radios.forEach( function( r ) { r.addEventListener( 'change', toggle ); } );
									if ( insertBtn ) {
										insertBtn.addEventListener( 'click', function() {
											var example = "function(payload, url) {\n  // Block events for preview URLs\n  if (url.includes('preview=true')) return false;\n  // Attach custom info\n  payload.locale = document.documentElement.lang || 'en';\n  return payload;\n}";
											inlineInput.value = example;
											resetInlineTestState();
											updateSubmitState( 'inline' );
										} );
									}
									if ( clearBtn ) {
										clearBtn.addEventListener( 'click', function() {
											inlineInput.value = '';
											inlineInput.focus();
											resetInlineTestState();
											updateSubmitState( 'inline' );
										} );
									}

									if ( inlineInput ) {
										inlineInput.addEventListener( 'input', function() {
											resetInlineTestState();
											var checked = document.querySelector('input[name="umami_tracker_before_send_mode"]:checked');
											var mode = checked ? checked.value : 'function_name';
											updateSubmitState( mode );
										} );
									}

									function runInlineTest() {
										resetInlineTestState();
										if ( ! inlineInput ) { return; }
										var code = ( inlineInput.value || '' ).trim();
										if ( ! code ) {
											if ( resultEl ) {
												resultEl.style.display = 'block';
												resultEl.style.color = '#cc1818';
												resultEl.textContent = 'Please enter a function first.';
											}
											updateSubmitState( 'inline' );
											return;
										}
										if ( ! /^function\s*\(/.test( code ) ) {
											if ( resultEl ) {
												resultEl.style.display = 'block';
												resultEl.style.color = '#cc1818';
												resultEl.textContent = 'Code must start with "function(".';
											}
											updateSubmitState( 'inline' );
											return;
										}

										try {
											var fnFactory = new Function( 'return (' + code + ');' );
											var fn = fnFactory();
											if ( typeof fn !== 'function' ) {
												throw new Error( 'Provided code did not evaluate to a function.' );
											}
											var payload = { __test__: true };
											void fn( payload, 'https://example.com/test' );

											inlineTestPassed = true;
											if ( resultEl ) {
												resultEl.style.display = 'block';
												resultEl.style.color = '#138a07';
												resultEl.textContent = '✓ Test successful. You can save now.';
											}
										} catch ( e ) {
											inlineTestPassed = false;
											if ( resultEl ) {
												resultEl.style.display = 'block';
												resultEl.style.color = '#cc1818';
												resultEl.textContent = 'Test failed: ' + ( e && e.message ? e.message : e );
											}
										}

										updateSubmitState( 'inline' );
									}

									if ( testBtn ) {
										testBtn.addEventListener( 'click', runInlineTest );
									}

									function validateFunctionPath(path) {
										return /^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$/.test(path);
									}

									function runFunctionNameCheck() {
										if ( ! fnInput ) { return; }
										var val = ( fnInput.value || '' ).trim();
										if ( ! val ) {
											if ( fnResultEl ) {
												fnResultEl.style.display = 'block';
												fnResultEl.style.color = '#cc1818';
												fnResultEl.textContent = 'Please enter a function name first.';
											}
											return;
										}
										if ( ! validateFunctionPath( val ) ) {
											if ( fnResultEl ) {
												fnResultEl.style.display = 'block';
												fnResultEl.style.color = '#cc1818';
												fnResultEl.textContent = 'Invalid name. Use dot-separated JavaScript identifiers.';
											}
											return;
										}

										var token = Math.random().toString(36).slice(2) + String(Date.now());
										var url = siteUrl + (siteUrl.indexOf('?') === -1 ? '?' : '&') + 'umami_check_before_send=1' + '&path=' + encodeURIComponent(val) + '&token=' + encodeURIComponent(token) + '&t=' + Date.now();

										if ( fnResultEl ) {
											fnResultEl.style.display = 'block';
											fnResultEl.style.color = '#444';
											fnResultEl.textContent = 'Checking frontend availability…';
										}

										var iframe = document.createElement('iframe');
										iframe.style.width = '0';
										iframe.style.height = '0';
										iframe.style.border = '0';
										iframe.style.position = 'absolute';
										iframe.style.left = '-9999px';
										iframe.src = url;
										document.body.appendChild( iframe );

										var done = false;
										var timeoutId = setTimeout(function(){
											if ( done ) { return; }
											done = true;
											try { document.body.removeChild( iframe ); } catch(e){}
											if ( fnResultEl ) {
												fnResultEl.style.display = 'block';
												fnResultEl.style.color = '#cc1818';
												fnResultEl.textContent = 'No response received from the frontend (timeout).';
											}
										}, 10000);

										function onMessage(ev) {
											if ( done ) { return; }
											if ( ev.origin !== siteOrigin ) { return; }
											var data = ev.data || {};
											if ( !data || data.type !== 'umami-before-send-check' ) { return; }
											if ( data.token !== token ) { return; }
											done = true;
											clearTimeout( timeoutId );
											try { document.body.removeChild( iframe ); } catch(e){}
											window.removeEventListener('message', onMessage);

											var msg = '';
											var okColor = '#138a07';
											var errorColor = '#cc1818';
											if ( data.exists && data.isFunction ) {
												msg = '✓ Found and available as a function on the public site.';
												fnResultEl.style.color = okColor;
											} else if ( data.exists && ! data.isFunction ) {
												msg = 'Found, but it is not a function. Please provide a function name.';
												fnResultEl.style.color = errorColor;
											} else {
												msg = 'Not found on the public site. Ensure your script/theme exposes it globally.';
												fnResultEl.style.color = errorColor;
											}
											if ( fnResultEl ) {
												fnResultEl.style.display = 'block';
												fnResultEl.textContent = msg;
											}
										}

										window.addEventListener('message', onMessage);
									}

									if ( fnCheckBtn ) {
										fnCheckBtn.addEventListener('click', runFunctionNameCheck);
									}

									var formEl = document.querySelector('form[action="options.php"]');
									if ( formEl ) {
										formEl.addEventListener( 'submit', function( ev ) {
											var checked = document.querySelector('input[name="umami_tracker_before_send_mode"]:checked');
											var mode = checked ? checked.value : 'disabled';
											if ( mode === 'inline' && ! inlineTestPassed ) {
												ev.preventDefault();
												if ( resultEl ) {
													resultEl.style.display = 'block';
													resultEl.style.color = '#cc1818';
													resultEl.textContent = 'Please run and pass the test before saving.';
												}
												inlineInput && inlineInput.focus();
											}
										} );
									}

									toggle();
								})();
								</script>
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
