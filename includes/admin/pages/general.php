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
		<script>
		(function(){
			var modeSelect = document.getElementById('umami_mode');
			var hostInput = document.getElementById('umami_host');
			var hostRow = hostInput ? hostInput.closest('tr') : null;
			function toggleHost() {
				if (!modeSelect || !hostRow) return;
				var isSelf = modeSelect.value === 'self';
				hostRow.style.display = isSelf ? '' : 'none';
				if (hostInput) {
					hostInput.required = isSelf;
					hostInput.disabled = !isSelf;
				}
			}
			toggleHost();
			if (modeSelect) modeSelect.addEventListener('change', toggleHost);

			var form = document.getElementById('umami-connect-form');
			var websiteIdInput = document.getElementById('umami_website_id');
			var errorDiv = document.getElementById('umami-website-id-error');
			if (form && websiteIdInput && errorDiv) {
				var uuidPattern = /^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;

				function validateUuidInput() {
					var uuid = websiteIdInput.value.trim();
					if (!uuidPattern.test(uuid)) {
						errorDiv.textContent = 'Please enter a valid Umami Website ID (UUID format).';
						errorDiv.style.display = 'block';
						websiteIdInput.classList.add('input-error');
					} else {
						errorDiv.textContent = '';
						errorDiv.style.display = 'none';
						websiteIdInput.classList.remove('input-error');
					}
				}

				websiteIdInput.addEventListener('input', validateUuidInput);

				form.addEventListener('submit', function(e){
					var uuid = websiteIdInput.value.trim();
					if (!uuidPattern.test(uuid)) {
						e.preventDefault();
						errorDiv.textContent = 'Please enter a valid Umami Website ID (UUID format).';
						errorDiv.style.display = 'block';
						websiteIdInput.focus();
						websiteIdInput.classList.add('input-error');
					} else {
						errorDiv.textContent = '';
						errorDiv.style.display = 'none';
						websiteIdInput.classList.remove('input-error');
					}
				});
			}
		})();
		</script>
		<style>
			#umami-connect-form table.form-table th { width: 200px; }
			.input-error { border-color: #b32d2e !important; box-shadow: 0 0 0 1.5px #b32d2e; }
		</style>
	</div>
	<?php
}
?>