// @ts-check
/**
 * Shell chrome + layout smoke for ProjectCheck (design-system contract).
 */
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');

const URLS = {
	dashboard: process.env.E2E_DASHBOARD_URL || `${BASE}/index.php/apps/projectcheck/dashboard`,
	projects: process.env.E2E_PROJECTS_URL || `${BASE}/index.php/apps/projectcheck/projects`,
	customers: `${BASE}/index.php/apps/projectcheck/customers`,
	timeEntries: `${BASE}/index.php/apps/projectcheck/time-entries`,
	settings: process.env.E2E_PROJECTCHECK_SETTINGS_URL || `${BASE}/index.php/apps/projectcheck/settings`,
};

test.describe('ProjectCheck shell chrome a11y smoke', () => {
	test.beforeEach(async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');
		await page.setViewportSize({ width: 1280, height: 800 });
	});

	for (const [name, url] of Object.entries(URLS)) {
		test(`${name}: skip links, live regions, tokens, axe`, async ({ page }) => {
			await gotoApp(page, url);
			const app = page.locator('#app-content.pc-app').first();
			await expect(app).toBeVisible();
			await expect(app.locator('a.pc-skip-link[href="#pc-main-content"], a.pc-skip-link[href^="#pc-"], a.pc-skip-link').first()).toBeAttached();
			await expect(app.locator('a.pc-skip-link[href="#app-navigation"]')).toBeAttached();
			await expect(page.locator('#pc-live-region')).toBeAttached();
			await expect(page.locator('#pc-alert-region')).toBeAttached();
			await expect(page.locator('#pc-main-content, #projectcheck-org-main, main.pc-main').first()).toBeAttached();
			await expect(app.getByRole('heading', { level: 1 }).first()).toBeAttached();

			const tokens = await page.evaluate(() => {
				const cs = getComputedStyle(document.body);
				return {
					tintInfo: cs.getPropertyValue('--pc-tint-info').trim(),
					touch: cs.getPropertyValue('--pc-touch-min').trim(),
					shellMax: (() => {
						const shell = document.querySelector('#app-content-wrapper.pc-shell');
						return shell ? getComputedStyle(shell).maxWidth : '';
					})(),
				};
			});
			expect(tokens.tintInfo).not.toEqual('');
			expect(tokens.touch).toBe('44px');
			expect(
				tokens.shellMax === 'none' || tokens.shellMax === '' || parseFloat(tokens.shellMax) >= 2000,
				`shell max-width must be unconstrained by default (got ${tokens.shellMax})`,
			).toBeTruthy();

			const results = await new AxeBuilder({ page })
				.include('#content')
				.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
				.analyze();
			expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
		});
	}

	test('mobile 375: no horizontal document overflow on dashboard', async ({ page }) => {
		await page.setViewportSize({ width: 375, height: 812 });
		await gotoApp(page, URLS.dashboard);
		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
		expect(overflow).toBeLessThanOrEqual(2);
	});
});
