/**
 * General Settings Page JavaScript
 * Handles mode toggle and UUID validation
 * 
 * @package First8Marketing_Track
 * @since 1.0.0
 */
(function() {
	'use strict';
	
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