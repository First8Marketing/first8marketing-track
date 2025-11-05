(function () {
	var CFG = (window.__UMAMI_CONNECT__) || {};

	function log()
	{
		if (CFG.debug && typeof console !== 'undefined') {
			console.log.apply( console, ['[UmamiConnect]'].concat( [].slice.call( arguments ) ) );
		}
	}

	function getByPath(root, path)
	{
		try {
			if ( ! path) {
				return undefined;
			}
			var parts = String( path ).split( '.' );
			var cur   = root;
			for (var i = 0;i < parts.length;i++) {
				if (cur == null) {
					return undefined;
				}
				cur = cur[parts[i]];
			}
			return cur;
		} catch (e) {
			return undefined; }
	}

	function consentGranted()
	{
		if ( ! CFG.consentRequired) {
			return true;
		}
		return ! ! getByPath( window, CFG.consentFlag || 'umamiConsentGranted' );
	}

	function canTrack()
	{
		return typeof window.umami === 'object' &&
			typeof window.umami.track === 'function' &&
			consentGranted() &&
			localStorage.getItem( 'umami.disabled' ) !== 'true';
	}

	var readyCheckCount = 0;
	var readyIv         = setInterval(
		function () {
			readyCheckCount++;
			if (canTrack()) {
				clearInterval( readyIv );
				log( 'umami ready' );
			}
			if (readyCheckCount > 60) {
				clearInterval( readyIv );
			}
		},
		500
	);

	window.umamiConnect = {
		track: function (eventName, data) {
			if ( ! canTrack()) {
				log( 'skip track (not ready or no consent)', eventName, data );
				return;
			}
			try {
				if (data && typeof data === 'object') {
					window.umami.track( eventName, data );
				} else {
					window.umami.track( eventName );
				}
				log( 'track', eventName, data || null );
			} catch (e) {
				log( 'track error', e );
			}
		},
		canTrack: canTrack,
		log: log
	};
})();
