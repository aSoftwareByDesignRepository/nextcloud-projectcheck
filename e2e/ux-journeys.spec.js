// @ts-check
/**
 * Bachus UX gauntlet: core journeys, dead-end rescues, simplified chrome, axe coverage.
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
	timeEntryCreate: process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create`,
	projectCreate: process.env.E2E_PROJECT_CREATE_URL || `${BASE}/index.php/apps/projectcheck/projects/create`,
	settings: process.env.E2E_PROJECTCHECK_SETTINGS_URL || `${BASE}/index.php/apps/projectcheck/settings`,
};

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} [include]
 */
async function assertAxeClean(page, include = '#content') {
	const builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
	if (include) {
		builder.include(include);
	}
	const results = await builder.analyze();
	expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
}

test.describe('ProjectCheck UX journeys (Bachus gauntlet)', () => {
	test.beforeEach(async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');
		await page.setViewportSize({ width: 1280, height: 800 });
	});

	test('dashboard: no duplicate quick-nav chrome; primary CTAs remain', async ({ page }) => {
		await gotoApp(page, URLS.dashboard);
		await expect(page.locator('.quick-actions-toolbar')).toHaveCount(0);
		await expect(page.locator('#app-navigation.pc-nav')).toBeVisible();
		await expect(page.locator('.pc-nav__group-title').first()).toBeVisible();
		const headerActions = page.locator('.pc-page-header__actions a.button, .pc-page-header__actions .button');
		await expect(headerActions.first()).toBeVisible();
		await assertAxeClean(page);
	});

	test('projects list: search first, advanced filters collapsed by default', async ({ page }) => {
		await gotoApp(page, URLS.projects);
		await expect(page.locator('#project-search')).toBeVisible();
		const more = page.locator('.pc-filters__more').first();
		await expect(more).toBeAttached();
		const open = await more.evaluate((el) => el instanceof HTMLDetailsElement && el.open);
		// Default Active status alone should keep disclosure closed
		expect(open).toBe(false);
		await more.locator('summary').click();
		await expect(page.locator('#status-filter')).toBeVisible();
		const emptyOrTable = page.locator('.pc-empty-state, .projects-table, .pc-data-table');
		await expect(emptyOrTable.first()).toBeVisible();
		const emptyCta = page.locator('.pc-empty-state__cta');
		if (await emptyCta.count()) {
			await expect(emptyCta.first()).toBeVisible();
			await expect(emptyCta.first()).toHaveAttribute('href', /projects\/create|projects\/new/i);
		}
		await assertAxeClean(page);
	});

	test('customers list: empty state has CTA or table is present', async ({ page }) => {
		await gotoApp(page, URLS.customers);
		await expect(page.locator('#customer-search')).toBeVisible();
		const emptyOrTable = page.locator('.pc-empty-state, .customers-table, .pc-data-table');
		await expect(emptyOrTable.first()).toBeVisible();
		if (await page.locator('.pc-empty-state__cta').count()) {
			await expect(page.locator('.pc-empty-state__cta').first()).toHaveAttribute('href', /customers\/(create|new)/i);
		}
		await assertAxeClean(page);
	});

	test('time entries: list and create form are reachable without dead ends', async ({ page }) => {
		await gotoApp(page, URLS.timeEntries);
		await expect(page.locator('#time-entry-search, .time-entries-empty, .pc-empty-state').first()).toBeAttached();
		await assertAxeClean(page);

		await gotoApp(page, URLS.timeEntryCreate);
		await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
		const projectSelect = page.locator('#project_id');
		await expect(projectSelect).toBeAttached();
		const options = projectSelect.locator('option:not([disabled])');
		const selectable = await options.count();
		if (selectable === 0) {
			const rescue = page.locator('.pc-empty-state__cta');
			await expect(rescue).toBeVisible();
			await expect(rescue).toHaveAttribute('href', /projects/i);
		} else {
			await expect(page.locator('#hours, #date, input[name="hours"], input[name="date"]').first()).toBeAttached();
		}
		await assertAxeClean(page);
	});

	test('project create: inline customer quick-add without losing form draft', async ({ page }) => {
		await gotoApp(page, URLS.projectCreate);
		const nameField = page.locator('#name, input[name="name"]').first();
		await expect(nameField).toBeVisible();
		const draftName = `Bachus Draft ${Date.now()}`;
		await nameField.fill(draftName);

		const quick = page.locator('.pc-quick-customer');
		if ((await quick.count()) === 0) {
			test.skip(true, 'User cannot create customers — quick-add hidden by permission');
		}
		await expect(quick).toBeVisible();
		await assertAxeClean(page);

		const customerName = `Bachus Customer ${Date.now()}`;
		await page.locator('#pc-quick-customer-name').fill(customerName);
		await page.locator('#pc-quick-customer-create').click();
		await expect(page.locator('#pc-quick-customer-status')).toContainText(/added|angelegt|selected|ausgewählt/i, { timeout: 15_000 });

		const selected = page.locator('#customer_id');
		await expect(selected).not.toHaveValue('');
		const selectedLabel = await selected.evaluate((el) => {
			const opt = el.options[el.selectedIndex];
			return opt ? opt.textContent.trim() : '';
		});
		expect(selectedLabel).toBe(customerName);

		// Project draft must still be intact (no navigation away)
		await expect(page).toHaveURL(/projects\/(create|new)/i);
		await expect(nameField).toHaveValue(draftName);
	});

	test('project create: customer quick-add is always available when permitted', async ({ page }) => {
		await gotoApp(page, URLS.projectCreate);
		await expect(page.locator('#customer_id')).toBeAttached();
		const quick = page.locator('#pc-quick-customer-create, .pc-quick-customer');
		const help = page.locator('#customer_id-help');
		await expect(help).toBeVisible();
		if (await page.locator('.pc-quick-customer').count()) {
			await expect(page.locator('#pc-quick-customer-name')).toBeVisible();
			await expect(page.locator('#pc-quick-customer-create')).toBeVisible();
		}
		await assertAxeClean(page);
	});

	test('mobile drawer: Menu opens pc-nav and Escape closes it', async ({ page }) => {
		await page.setViewportSize({ width: 390, height: 844 });
		await gotoApp(page, URLS.dashboard);
		const toggle = page.locator('[data-pc-nav-toggle]').first();
		await expect(toggle).toBeVisible();
		await toggle.click();
		const nav = page.locator('#app-navigation.pc-nav');
		await expect(nav).toHaveClass(/pc-nav--open/);
		// Hints must not take layout space on narrow viewports
		const hintVisible = await page.locator('.pc-nav__hint').first().evaluate((el) => {
			const cs = getComputedStyle(el);
			return cs.display !== 'none' && cs.visibility !== 'hidden';
		}).catch(() => false);
		expect(hintVisible).toBe(false);
		await page.keyboard.press('Escape');
		await expect(nav).not.toHaveClass(/pc-nav--open/);
	});

	test('nav groups expose Overview / Management structure', async ({ page }) => {
		await gotoApp(page, URLS.dashboard);
		await expect(page.locator('.pc-nav__group-title', { hasText: /overview|überblick/i })).toBeVisible();
		await expect(page.locator('.pc-nav__name', { hasText: /dashboard|übersicht/i }).first()).toBeVisible();
		await expect(page.locator('.pc-nav__link[href*="time-entries"]').first()).toBeVisible();
	});
});
