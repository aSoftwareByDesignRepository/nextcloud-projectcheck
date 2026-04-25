/**
 * Registers the ProjectCheck service worker (app-scoped) when supported.
 * Skips incompatibles: no SW API, or non-secure contexts (except local dev).
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

	var webroot = typeof OC.webroot === 'string' ? OC.webroot : '';
	var path = webroot + '/apps/projectcheck/sw.js';
	var scope = webroot + '/apps/projectcheck/';

	// Single registration; updates handled by SW (skip waiting / message handler)
	navigator.serviceWorker
		.register(path, { scope: scope, updateViaCache: 'none' })
		.catch(function () {
			// Intentionally silent: broken SW must not break the app
		});
})();
