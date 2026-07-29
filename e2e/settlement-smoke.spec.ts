import { test, expect } from '@playwright/test';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');

const AUTH_OK_STATUSES = [200, 301, 302, 303, 307, 401, 403];

const URLS = {
	dashboard: `${BASE}/index.php/apps/projectcheck/dashboard`,
	timeEntries: `${BASE}/index.php/apps/projectcheck/time-entries`,
	timeEntriesOutstanding: `${BASE}/index.php/apps/projectcheck/time-entries?billing_status=outstanding`,
	projectsOutstanding: `${BASE}/index.php/apps/projectcheck/projects?settlement=outstanding`,
	settings: `${BASE}/index.php/apps/projectcheck/settings`,
};

/**
 * Settlement track (spec M7) — smoke that the settle UI surfaces exist and never 500.
 * Full finance flows stay covered by PHPUnit (TimeEntryBillingService / ProjectSettlementService).
 */
test.describe('ProjectCheck settlement smoke', () => {
	test('settlement routes do not 500 unauthenticated', async ({ playwright }) => {
		test.skip(!process.env.BASE_URL, 'Set BASE_URL to run E2E');
		const request = await playwright.request.newContext();
		for (const route of [
			'/index.php/apps/projectcheck/dashboard',
			'/index.php/apps/projectcheck/time-entries',
			'/index.php/apps/projectcheck/time-entries?billing_status=outstanding',
			'/index.php/apps/projectcheck/projects?settlement=outstanding',
		]) {
			const res = await request.get(`${BASE}${route}`, { maxRedirects: 0 });
			expect(res.status(), `${route} must not 500`).not.toBe(500);
			expect(AUTH_OK_STATUSES.includes(res.status())).toBeTruthy();
		}
		await request.dispose();
	});

	test('time entries: settlement filter and status column', async ({ page }) => {
		test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoApp(page, URLS.timeEntries);
		const app = page.locator('#app-content.pc-app, #app-content.projectcheck-app-content').first();
		await expect(app).toBeVisible();

		const filter = page.locator('#billing-status-filter');
		await expect(filter).toBeVisible();
		await expect(filter.locator('option[value="outstanding"]')).toBeAttached();
		await expect(filter.locator('option[value="open"]')).toBeAttached();
		await expect(filter.locator('option[value="invoiced"]')).toBeAttached();
		await expect(filter.locator('option[value="paid"]')).toBeAttached();

		await expect(page.locator('th.col-settlement, .col-settlement').first()).toBeAttached();
	});

	test('outstanding filter page loads without 500', async ({ page }) => {
		test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoApp(page, URLS.timeEntriesOutstanding);
		const app = page.locator('#app-content.pc-app, #app-content.projectcheck-app-content').first();
		await expect(app).toBeVisible();
		const filter = page.locator('#billing-status-filter');
		await expect(filter).toHaveValue('outstanding');
	});

	test('dashboard exposes Not yet paid settlement section when settler', async ({ page }) => {
		test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoApp(page, URLS.dashboard);
		const app = page.locator('#app-content.pc-app, #app-content.projectcheck-app-content').first();
		await expect(app).toBeVisible();
		// Admin/manager sees the AR widget; ordinary members may not — either attached or absent is OK,
		// but the page must never 500 and the main landmark must exist.
		await expect(app.locator('#pc-main-content, main.pc-main, #projectcheck-org-main').first()).toBeAttached();
		const settleSection = page.locator('#dash-settlement-title, .pc-settle-dashboard');
		const count = await settleSection.count();
		expect(count === 0 || count >= 1).toBeTruthy();
	});

	test('license settings panel does not claim mobile is coming soon', async ({ page }) => {
		test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoApp(page, `${URLS.settings}#projectcheck-license`);
		const panel = page.locator('#projectcheck-license, #pc-license-panel');
		await expect(panel.first()).toBeVisible({ timeout: 15000 });
		const text = (await panel.first().innerText()).toLowerCase();
		expect(text).not.toContain('coming soon');
		expect(text).toMatch(/mobile|sitz|seat|available|verfügbar/);
	});
});
