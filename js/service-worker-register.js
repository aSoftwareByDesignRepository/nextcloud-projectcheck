/**
 * Registers the ProjectCheck service worker (app-scoped) when supported.
 * Requires worker-src 'self' in CSP (CSPListener + CSPService).
 */
(function () {
	'use strict';

	if (typeof navigator === 'undefined' || !('serviceWorker' in navigator) || typeof OC === 'undefined') {
		return;
	}

	// Service workers require HTTPS or http://localhost / http://127.0.0.1
	var host = window.location.hostname || '';
	var isLocalHost = host === 'localhost' || host === '127.0.0.1' || host === '::1';
	if (window.location.protocol !== 'https:' && !isLocalHost) {
		return;
	}

	var path = window.location.pathname || '';
	// Worker route is registered under /apps/projectcheck/ only (not custom_apps asset URLs).
	if (path.indexOf('/apps/projectcheck') === -1) {
		return;
	}

	var webroot = typeof OC.webroot === 'string' ? OC.webroot : '';
	var scope = webroot + '/apps/projectcheck/';

	var useIndexPhp = path.indexOf('/index.php/') !== -1 || path === '/index.php';
	var scriptUrl = useIndexPhp
		? webroot + '/index.php/apps/projectcheck/service-worker.js'
		: webroot + '/apps/projectcheck/service-worker.js';

	navigator.serviceWorker
		.register(scriptUrl, { scope: scope, updateViaCache: 'none' })
		.then(function (registration) {
			if (registration.waiting) {
				registration.waiting.postMessage({ type: 'SKIP_WAITING' });
			}
		})
		.catch(function (err) {
			if (typeof console !== 'undefined' && console.debug) {
				console.debug('[projectcheck] service worker registration failed:', err);
			}
		});
})();
