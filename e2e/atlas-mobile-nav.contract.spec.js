// @ts-check
/** ATLAS_MOBILE_NAV_CONTRACT — phone Menu → open nav + no h-scroll */
const { test } = require('@playwright/test');
const { assertAtlasMobileNav } = require('../../_shared/e2e/atlas-mobile-nav-contract');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const DASHBOARD = process.env.E2E_DASHBOARD_URL || `${BASE}/index.php/apps/projectcheck/dashboard`;

test('ATLAS_MOBILE_NAV_CONTRACT in-page Menu opens drawer', async ({ page }) => {
	test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER');
	await page.setViewportSize({ width: 375, height: 812 });
	await gotoApp(page, DASHBOARD);
	await page.waitForSelector('[data-pc-nav-toggle], #pc-nav-toggle', { timeout: 30000 });
	await assertAtlasMobileNav(page, {
		toggle: page.locator('[data-pc-nav-toggle], #pc-nav-toggle').first(),
		nav: page.locator('#app-navigation'),
		openClass: /pc-nav--open/,
	});
});
