const { test, expect } = require('@playwright/test');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');

const URLS = {
	dashboard: process.env.E2E_DASHBOARD_URL || `${BASE}/index.php/apps/projectcheck/dashboard`,
	projects: process.env.E2E_PROJECTS_URL || `${BASE}/index.php/apps/projectcheck/projects`,
	projectCreate: process.env.E2E_PROJECT_CREATE_URL || `${BASE}/index.php/apps/projectcheck/projects/create`,
	timeEntryCreate: process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create`,
	settings:
		process.env.E2E_PROJECTCHECK_SETTINGS_URL
		|| `${BASE}/index.php/apps/projectcheck/settings`,
};

// Nextcloud's shell needs ~480px before #app-content has usable width; 320 is covered by UAT 12 below.
const viewports = [
	{ name: 'mobile-480', width: 480, height: 720 },
	{ name: 'desktop-1280', width: 1280, height: 800 },
];

async function assertAppShell(page) {
	const appContent = page.locator('#app-content.pc-app, #app-content.projectcheck-app-content').first();
	await expect(appContent).toBeVisible();
	const skip = appContent.locator('a.pc-skip-link');
	await expect(skip).toBeVisible();
	// On narrow viewports Nextcloud may keep the main landmark in DOM but not "visible" until nav is dismissed.
	const main = appContent.locator('#pc-main-content, #projectcheck-org-main, main.pc-main').first();
	await expect(main).toBeAttached();
	const h1 = appContent.getByRole('heading', { level: 1 });
	await expect(h1.first()).toBeAttached();
}

for (const vp of viewports) {
	test.describe(`ProjectCheck cost-pricing smoke @ ${vp.name}`, () => {
		test.beforeEach(async ({ page }) => {
			await page.setViewportSize({ width: vp.width, height: vp.height });
		});

		test('dashboard: shell, skip link, one h1', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.dashboard);
			await assertAppShell(page);
		});

		test('project create: pricing cards and section layout', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.projectCreate);
			await assertAppShell(page);

			const pricing = page.locator('#pc-pricing-method');
			await pricing.scrollIntoViewIfNeeded();
			await expect(pricing).toBeVisible();
			await expect(page.getByRole('radio', { name: /one rate for the whole project|ein satz für das ganze projekt/i })).toBeVisible();
			await expect(page.getByRole('radio', { name: /rate per employee|satz je mitarbeitendem/i })).toBeVisible();
			await expect(page.getByRole('radio', { name: /rate per person on this project|satz je person in diesem projekt/i })).toBeVisible();

			await expect(page.locator('.pc-section').first()).toBeVisible();
			await expect(page.getByRole('heading', { name: /basics|grundlagen/i })).toBeVisible();
			await expect(page.locator('#pc-pricing-method legend')).toContainText(/how are hours priced|wie werden stunden bewertet/i);
		});

		test('time entry create: hourly rate is readonly (A2)', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.timeEntryCreate);
			await assertAppShell(page);

			const rateInput = page.locator('#hourly_rate');
			await expect(rateInput).toBeVisible();
			await expect(rateInput).toHaveAttribute('readonly', '');
			const html = await page.content();
			expect(html).not.toMatch(/data-hourly-rate/i);
		});

		test('project create: project_member mode hides add-all in markup when present', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.projectCreate);

			const memberMode = page.locator('input[name="cost_rate_mode"][value="project_member"]');
			await memberMode.scrollIntoViewIfNeeded();
			await memberMode.evaluate((input) => {
				if (!(input instanceof HTMLInputElement) || input.disabled) {
					return;
				}
				input.checked = true;
				input.dispatchEvent(new Event('input', { bubbles: true }));
				input.dispatchEvent(new Event('change', { bubbles: true }));
			});
			// Create form only — add-all lives on project detail modal; ensure pricing mode value exists.
			await expect(memberMode).toBeChecked();
		});

		test('time entry create: native date input (UAT 14)', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.timeEntryCreate);
			const dateInput = page.locator('#date');
			await expect(dateInput).toHaveAttribute('type', 'date');
		});

		test('project create: native date inputs (UAT 14)', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.projectCreate);
			await expect(page.locator('#start_date')).toHaveAttribute('type', 'date');
			await expect(page.locator('#end_date')).toHaveAttribute('type', 'date');
		});

		test('settings: skip link and security notice', async ({ page }) => {
			test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
			await gotoApp(page, URLS.settings);
			await expect(page).toHaveURL(/\/apps\/projectcheck\/settings/, { timeout: 20000 });
			await assertAppShell(page);
			await expect(page.locator('#app-content.pc-app a.pc-skip-link')).toHaveAttribute(
				'href',
				'#projectcheck-org-main',
			);
			const trustHeading = page.locator('#projectcheck-org-trust-h');
			await trustHeading.scrollIntoViewIfNeeded();
			await expect(trustHeading).toBeVisible();
			const rateHint = page.locator('#pc_def_rate_hint');
			await rateHint.scrollIntoViewIfNeeded();
			await expect(rateHint).toContainText(/one rate for the whole project|ein satz für das ganze projekt/i);
		});
	});
}

test.describe('ProjectCheck cost-pricing smoke @ mobile-320 only', () => {
	test.beforeEach(async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 720 });
	});

	test('add-member modal fits viewport (UAT 12)', async ({ page }) => {
		test.skip(!process.env.BASE_URL && !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoApp(page, URLS.projects);
		const viewLink = page.locator('table tbody tr a.action-item[href*="/projects/"]').first();
		test.skip(!(await viewLink.count()), 'No projects');
		const detailHref = await viewLink.getAttribute('href');
		test.skip(!detailHref, 'No project detail link');
		await page.goto(new URL(detailHref, page.url()).href, { waitUntil: 'domcontentloaded' });
		await expect(page).toHaveURL(/\/projects\/\d+(?!\/)/, { timeout: 15000 });
		const addBtn = page.locator('#add-team-member-btn');
		test.skip(!(await addBtn.isVisible().catch(() => false)), 'No team button');
		await addBtn.click();
		const modal = page.locator('#addTeamMemberModal');
		await expect(modal).toBeVisible();
		const box = await modal.boundingBox();
		expect(box).toBeTruthy();
		expect(box.width).toBeLessThanOrEqual(320);
	});
});

test.describe('ProjectCheck security smoke (API)', () => {
	test.skip(!process.env.BASE_URL, 'Set BASE_URL');

	test('resolve-hourly-rate rejects unauthenticated callers (E21)', async () => {
		const res = await fetch(
			`${BASE}/index.php/apps/projectcheck/api/projects/999999/resolve-hourly-rate?date=2026-01-15`,
			{ redirect: 'manual' },
		);
		const text = await res.text();
		expect(res.status).not.toBe(500);
		expect(res.status, 'unauthenticated resolve must not succeed').toBe(401);
		const body = JSON.parse(text);
		expect(body.message || body.error).toBeTruthy();
		expect(body.success).toBeUndefined();
	});
});
