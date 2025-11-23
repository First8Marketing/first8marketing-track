/**
 * Admin Advanced Settings JavaScript
 * Handles beforeSend configuration UI and validation
 * 
 * @package First8MarketingTrack
 */

(function() {
	'use strict';

	// Wait for DOM to be ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAdvancedSettings);
	} else {
		initAdvancedSettings();
	}

	function initAdvancedSettings() {
		// Get all DOM elements
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

		// Get site URL from global data attribute or fallback
		var dataEl = document.getElementById('umami-advanced-data');
		var siteUrl = dataEl ? dataEl.getAttribute('data-site-url') : window.location.origin + '/';
		var siteOrigin = (function(u){ 
			try { 
				return new URL(u).origin; 
			} catch(e) { 
				return window.location.origin; 
			} 
		})(siteUrl);

		/**
		 * Update submit button state based on validation
		 */
		function updateSubmitState(mode) {
			if (!submitBtn) return;
			
			if (mode === 'inline') {
				submitBtn.disabled = !inlineTestPassed;
			} else {
				submitBtn.disabled = false;
			}
		}

		/**
		 * Reset inline test state
		 */
		function resetInlineTestState() {
			inlineTestPassed = false;
			if (resultEl) {
				resultEl.style.display = 'none';
				resultEl.textContent = '';
				resultEl.style.color = '';
			}
		}

		/**
		 * Toggle visibility of beforeSend configuration sections
		 */
		function toggle() {
			var checked = document.querySelector('input[name="umami_tracker_before_send_mode"]:checked');
			var mode = checked ? checked.value : 'disabled';
			var disabledField = document.getElementById('before_send_disabled_field');
			
			if (mode === 'disabled') {
				if (fnField) fnField.style.display = 'none';
				if (inlineField) inlineField.style.display = 'none';
				if (disabledField) disabledField.style.display = 'block';
				if (fnInput) fnInput.disabled = true;
				if (inlineInput) inlineInput.disabled = true;
			} else if (mode === 'function_name') {
				if (fnField) fnField.style.display = 'block';
				if (inlineField) inlineField.style.display = 'none';
				if (disabledField) disabledField.style.display = 'none';
				if (fnInput) fnInput.disabled = false;
				if (inlineInput) inlineInput.disabled = true;
			} else {
				if (fnField) fnField.style.display = 'none';
				if (inlineField) inlineField.style.display = 'block';
				if (disabledField) disabledField.style.display = 'none';
				if (fnInput) fnInput.disabled = true;
				if (inlineInput) inlineInput.disabled = false;
			}

			updateSubmitState(mode);
		}

		/**
		 * Insert example inline function
		 */
		function insertExample() {
			if (!inlineInput) return;
			
			var example = "function(payload, url) {\n  // Block events for preview URLs\n  if (url.includes('preview=true')) return false;\n  // Attach custom info\n  payload.locale = document.documentElement.lang || 'en';\n  return payload;\n}";
			inlineInput.value = example;
			resetInlineTestState();
			updateSubmitState('inline');
		}

		/**
		 * Clear inline function
		 */
		function clearInline() {
			if (!inlineInput) return;
			
			inlineInput.value = '';
			inlineInput.focus();
			resetInlineTestState();
			updateSubmitState('inline');
		}

		/**
		 * Handle inline input changes
		 */
		function onInlineInputChange() {
			resetInlineTestState();
			var checked = document.querySelector('input[name="umami_tracker_before_send_mode"]:checked');
			var mode = checked ? checked.value : 'function_name';
			updateSubmitState(mode);
		}

		/**
		 * Test inline function code
		 */
		function runInlineTest() {
			resetInlineTestState();
			if (!inlineInput) return;
			
			var code = (inlineInput.value || '').trim();
			
			if (!code) {
				showTestResult('Please enter a function first.', 'error');
				updateSubmitState('inline');
				return;
			}
			
			if (!/^function\s*\(/.test(code)) {
				showTestResult('Code must start with "function(".', 'error');
				updateSubmitState('inline');
				return;
			}

			try {
				var fnFactory = new Function('return (' + code + ');');
				var fn = fnFactory();
				
				if (typeof fn !== 'function') {
					throw new Error('Provided code did not evaluate to a function.');
				}
				
				var payload = { __test__: true };
				void fn(payload, 'https://example.com/test');

				inlineTestPassed = true;
				showTestResult('✓ Test successful. You can save now.', 'success');
			} catch (e) {
				inlineTestPassed = false;
				showTestResult('Test failed: ' + (e && e.message ? e.message : e), 'error');
			}

			updateSubmitState('inline');
		}

		/**
		 * Show test result message
		 */
		function showTestResult(message, type) {
			if (!resultEl) return;
			
			resultEl.style.display = 'block';
			resultEl.textContent = message;
			resultEl.style.color = type === 'success' ? '#138a07' : '#cc1818';
		}

		/**
		 * Validate function path format
		 */
		function validateFunctionPath(path) {
			return /^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$/.test(path);
		}

		/**
		 * Check if function exists on frontend
		 */
		function runFunctionNameCheck() {
			if (!fnInput) return;
			
			var val = (fnInput.value || '').trim();
			
			if (!val) {
				showFnCheckResult('Please enter a function name first.', 'error');
				return;
			}
			
			if (!validateFunctionPath(val)) {
				showFnCheckResult('Invalid name. Use dot-separated JavaScript identifiers.', 'error');
				return;
			}

			var token = Math.random().toString(36).slice(2) + String(Date.now());
			var url = siteUrl + (siteUrl.indexOf('?') === -1 ? '?' : '&') + 
				'umami_check_before_send=1' + 
				'&path=' + encodeURIComponent(val) + 
				'&token=' + encodeURIComponent(token) + 
				'&t=' + Date.now();

			showFnCheckResult('Checking frontend availability…', 'info');

			var iframe = document.createElement('iframe');
			iframe.style.width = '0';
			iframe.style.height = '0';
			iframe.style.border = '0';
			iframe.style.position = 'absolute';
			iframe.style.left = '-9999px';
			iframe.src = url;
			document.body.appendChild(iframe);

			var done = false;
			var timeoutId = setTimeout(function(){
				if (done) return;
				done = true;
				try { document.body.removeChild(iframe); } catch(e){}
				showFnCheckResult('No response received from the frontend (timeout).', 'error');
			}, 10000);

			function onMessage(ev) {
				if (done) return;
				if (ev.origin !== siteOrigin) return;
				
				var data = ev.data || {};
				if (!data || data.type !== 'umami-before-send-check') return;
				if (data.token !== token) return;
				
				done = true;
				clearTimeout(timeoutId);
				try { document.body.removeChild(iframe); } catch(e){}
				window.removeEventListener('message', onMessage);

				var msg = '';
				var msgType = 'error';
				
				if (data.exists && data.isFunction) {
					msg = '✓ Found and available as a function on the public site.';
					msgType = 'success';
				} else if (data.exists && !data.isFunction) {
					msg = 'Found, but it is not a function. Please provide a function name.';
				} else {
					msg = 'Not found on the public site. Ensure your script/theme exposes it globally.';
				}
				
				showFnCheckResult(msg, msgType);
			}

			window.addEventListener('message', onMessage);
		}

		/**
		 * Show function check result
		 */
		function showFnCheckResult(message, type) {
			if (!fnResultEl) return;
			
			fnResultEl.style.display = 'block';
			fnResultEl.textContent = message;
			
			if (type === 'success') {
				fnResultEl.style.color = '#138a07';
			} else if (type === 'error') {
				fnResultEl.style.color = '#cc1818';
			} else {
				fnResultEl.style.color = '#444';
			}
		}

		/**
		 * Handle form submission
		 */
		function onFormSubmit(ev) {
			var checked = document.querySelector('input[name="umami_tracker_before_send_mode"]:checked');
			var mode = checked ? checked.value : 'disabled';
			
			if (mode === 'inline' && !inlineTestPassed) {
				ev.preventDefault();
				showTestResult('Please run and pass the test before saving.', 'error');
				if (inlineInput) inlineInput.focus();
			}
		}

		// Attach event listeners
		if (radios) {
			radios.forEach(function(r) { 
				r.addEventListener('change', toggle); 
			});
		}
		
		if (insertBtn) {
			insertBtn.addEventListener('click', insertExample);
		}
		
		if (clearBtn) {
			clearBtn.addEventListener('click', clearInline);
		}
		
		if (inlineInput) {
			inlineInput.addEventListener('input', onInlineInputChange);
		}
		
		if (testBtn) {
			testBtn.addEventListener('click', runInlineTest);
		}
		
		if (fnCheckBtn) {
			fnCheckBtn.addEventListener('click', runFunctionNameCheck);
		}
		
		var formEl = document.querySelector('form[action="options.php"]');
		if (formEl) {
			formEl.addEventListener('submit', onFormSubmit);
		}

		// Initialize visibility
		toggle();
	}
})();