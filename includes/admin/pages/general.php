<?php
/**
 * File: general.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

/**
 * Render the general settings page.
 */
function umami_connect_settings_page() {
	?>
	<div class="wrap">
		<h1><b>umami Connect</b></h1>
		<h3>General</h3>
		<form action="options.php" method="post" id="umami-connect-form">
	<?php
	settings_fields( 'umami_connect_general' );
	do_settings_sections( 'umami_connect' );
	submit_button();
	?>
		</form>
		<?php
		// Enqueue admin general settings JavaScript (CSP-compliant external file)
		wp_enqueue_script(
			'umami-admin-general',
			plugins_url( 'assets/js/admin-general.js', dirname( dirname( __FILE__ ) ) ),
			array(),
			'1.0.0',
			true
		);
		?>
		<style>
			#umami-connect-form table.form-table th { width: 200px; }
			.input-error { border-color: #b32d2e !important; box-shadow: 0 0 0 1.5px #b32d2e; }
		</style>
	</div>
	<?php
}
?>