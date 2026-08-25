#!/usr/bin/env node
'use strict';

/**
 * Invoke Playwright globalSetup outside `playwright test`.
 * `node e2e/global-setup.js` alone only loads the module and does nothing.
 */
require('./global-setup.js')()
	.then(() => process.exit(0))
	.catch((err) => {
		console.error(err && err.message ? err.message : err);
		process.exit(1);
	});
