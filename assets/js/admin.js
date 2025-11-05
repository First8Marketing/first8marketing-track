/**
 * Umami WordPress Connector - Admin JavaScript
 *
 * @package UmamiWPConnect
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {

			/**
			 * Test connection button
			 */
			$( '#umami-test-connection' ).on(
				'click',
				function (e) {
					e.preventDefault();

					const $button = $( this );
					const $status = $( '#umami-connection-status' );

					$button.prop( 'disabled', true ).text( 'Testing...' );
					$status.html( '<span class="umami-status-indicator unknown"></span> Testing connection...' );

					$.ajax(
						{
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'umami_test_connection',
								nonce: umamiAdmin.nonce,
								api_url: $( '#umami_api_url' ).val(),
								api_key: $( '#umami_api_key' ).val()
							},
							success: function (response) {
								if (response.success) {
									$status.html( '<span class="umami-status-indicator connected"></span> ' + response.data.message );
								} else {
									$status.html( '<span class="umami-status-indicator disconnected"></span> ' + response.data.message );
								}
							},
							error: function () {
								$status.html( '<span class="umami-status-indicator disconnected"></span> Connection failed' );
							},
							complete: function () {
								$button.prop( 'disabled', false ).text( 'Test Connection' );
							}
						}
					);
				}
			);

			/**
			 * Clear event queue button
			 */
			$( '#umami-clear-queue' ).on(
				'click',
				function (e) {
					e.preventDefault();

					if ( ! confirm( 'Are you sure you want to clear the event queue? This cannot be undone.' )) {
						return;
					}

					const $button = $( this );

					$button.prop( 'disabled', true ).text( 'Clearing...' );

					$.ajax(
						{
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'umami_clear_queue',
								nonce: umamiAdmin.nonce
							},
							success: function (response) {
								if (response.success) {
									alert( response.data.message );
									location.reload();
								} else {
									alert( 'Error: ' + response.data.message );
								}
							},
							error: function () {
								alert( 'Failed to clear queue' );
							},
							complete: function () {
								$button.prop( 'disabled', false ).text( 'Clear Queue' );
							}
						}
					);
				}
			);

			/**
			 * Toggle advanced settings
			 */
			$( '#umami-toggle-advanced' ).on(
				'click',
				function (e) {
					e.preventDefault();
					$( '#umami-advanced-settings' ).slideToggle();
					$( this ).text(
						function (i, text) {
							return text === 'Show Advanced Settings' ? 'Hide Advanced Settings' : 'Show Advanced Settings';
						}
					);
				}
			);

			/**
			 * Copy tracking code
			 */
			$( '#umami-copy-code' ).on(
				'click',
				function (e) {
					e.preventDefault();

					const code = $( '#umami-tracking-code' ).text();

					navigator.clipboard.writeText( code ).then(
						function () {
							const $button      = $( '#umami-copy-code' );
							const originalText = $button.text();
							$button.text( 'Copied!' );
							setTimeout(
								function () {
									$button.text( originalText );
								},
								2000
							);
						}
					).catch(
						function () {
							alert( 'Failed to copy code' );
						}
					);
				}
			);

			/**
			 * Form validation
			 */
			$( 'form' ).on(
				'submit',
				function (e) {
					const websiteId = $( '#umami_website_id' ).val();
					const scriptUrl = $( '#umami_script_url' ).val();

					if ( ! websiteId || ! scriptUrl) {
						e.preventDefault();
						alert( 'Please fill in all required fields (Website ID and Script URL)' );
						return false;
					}
				}
			);

			/**
			 * Auto-save settings
			 */
			let saveTimer;
			$( '.umami-settings-field input, .umami-settings-field textarea' ).on(
				'change',
				function () {
					clearTimeout( saveTimer );
					saveTimer = setTimeout(
						function () {
							$( '#umami-auto-save-indicator' ).fadeIn().text( 'Saving...' ).delay( 1000 ).fadeOut();
						},
						500
					);
				}
			);

		}
	);

})( jQuery );
