<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- Legacy filename.
/**
 * File: self_protection.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

/**
 * Render the self protection settings page.
 */
function umami_connect_self_protection_page() {
	?>
	<div class="wrap">
		<h1><b>umami Connect</b></h1>
		<h3>Self protection</h3>
		<form action="options.php" method="post">
	<?php
	settings_fields( 'umami_connect_self' );
	do_settings_sections( 'umami_connect_self_protection' );

	submit_button();
	?>
		</form>
	</div>
	<?php
}
?>