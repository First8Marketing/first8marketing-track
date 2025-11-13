<?php
/**
 * File: automation.php
 *
 * @package First8MarketingTrack
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename.
 */

/**
 * Render the automation settings page.
 */
function umami_connect_automation_page() {
	?>
	<div class="wrap">
		<h1><b>umami Connect</b></h1>
		<h3>Automation</h3>
		<form action="options.php" method="post">
	<?php
	settings_fields( 'umami_connect_automation' );
	do_settings_sections( 'umami_connect_automation' );
	submit_button();
	?>
		</form>
	</div>
	<?php
}
?>