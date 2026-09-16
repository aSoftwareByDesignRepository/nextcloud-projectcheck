// @ts-check
/**
 * ATLAS_VERTICAL_SCROLL_CONTRACT — tall settings/license page must scroll to the end.
 * Guards CSS Overflow L3 unpaired overflow-x:clip truncating bottom license CTAs.
 */
const { test } = require('@playwright/test');
const { assertAtlasVerticalScrollReachable } = require('../../_shared/e2e/atlas-vertical-scroll-contract');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const LICENSE = process.env.E2E_PC_LICENSE_URL || `${BASE}/apps/projectcheck/settings/license`;

test.describe('ATLAS_VERTICAL_SCROLL_CONTRACT', () => {
	test('settings license scrolls to key textarea / purchase CTA', async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER');
		await page.setViewportSize({ width: 1280, height: 640 });
		await gotoApp(page, LICENSE);
		await page.waitForSelector('#projectcheck-license, #pc-license-key, #pc-license-heading', {
			timeout: 45_000,
		});

		await assertAtlasVerticalScrollReachable(page, {
			scrollport: '#app-content',
			target: '#pc-license-key, .pc-license-cta__link, #pc-license-meter-text',
			bottomSlopPx: 12,
		});
	});

	test('settings license stays reachable at phone height', async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER');
		await page.setViewportSize({ width: 390, height: 667 });
		await gotoApp(page, LICENSE);
		await page.waitForSelector('#projectcheck-license, #pc-license-key, #pc-license-heading', {
			timeout: 45_000,
		});

		await assertAtlasVerticalScrollReachable(page, {
			scrollport: '#app-content',
			target: '#pc-license-key, .pc-license-cta__link, #pc-license-meter-text',
			bottomSlopPx: 16,
		});
	});
});
