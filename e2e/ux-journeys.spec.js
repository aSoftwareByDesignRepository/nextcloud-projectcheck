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
	employees: `${BASE}/index.php/apps/projectcheck/employees`,
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

	test('projects list: all filters visible by default (no More filters)', async ({ page }) => {
		await gotoApp(page, URLS.projects);
		await expect(page.locator('#project-search')).toBeVisible();
		const portfolio = page.locator('#pc-portfolio-overview');
		await expect(portfolio).toBeAttached();
		expect(await portfolio.evaluate((el) => el instanceof HTMLDetailsElement && el.open)).toBe(false);
		await expect(page.locator('.pc-filters--all-visible')).toBeVisible();
		await expect(page.locator('.pc-filters__more')).toHaveCount(0);
		await expect(page.locator('#status-filter')).toBeVisible();
		await expect(page.locator('#priority-filter')).toBeVisible();
		await expect(page.locator('#project-type-filter')).toBeVisible();
		await expect(page.locator('#customer-filter')).toBeVisible();
		await expect(page.locator('#settlement-filter')).toBeVisible();
		await expect(page.locator('#apply-filters')).toBeVisible();
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
		await expect(page.locator('.pc-filters--all-visible')).toBeVisible();
		await expect(page.locator('.pc-filters__more')).toHaveCount(0);
		await expect(page.locator('#settlement-filter')).toBeVisible();
		const emptyOrTable = page.locator('.pc-empty-state, .customers-table, .pc-data-table');
		await expect(emptyOrTable.first()).toBeVisible();
		if (await page.locator('.pc-empty-state__cta').count()) {
			await expect(page.locator('.pc-empty-state__cta').first()).toHaveAttribute('href', /customers\/(create|new)/i);
		}
		await assertAxeClean(page);
	});

	test('time entries: all filters visible by default (no More filters)', async ({ page }) => {
		await gotoApp(page, URLS.timeEntries);
		await expect(page.locator('.pc-filters--all-visible')).toBeVisible();
		await expect(page.locator('.pc-filters__more')).toHaveCount(0);
		await expect(page.locator('#time-entry-search')).toBeVisible();
		await expect(page.locator('#project-filter')).toBeVisible();
		await expect(page.locator('#user-filter')).toBeVisible();
		await expect(page.locator('#time-entry-project-type-filter')).toBeVisible();
		await expect(page.locator('#billing-status-filter')).toBeVisible();
		await expect(page.locator('#date-from-filter')).toBeVisible();
		await expect(page.locator('#date-to-filter')).toBeVisible();
		await expect(page.locator('#apply-filters')).toBeVisible();
		await expect(page.locator('#clear-filters')).toBeVisible();
		await expect(page.locator('.pc-filters--all-visible .pc-filters__more, .pc-filters--all-visible .pc-filters__more-summary')).toHaveCount(0);
		await assertAxeClean(page);
	});

	test('time entries: user-filter toggles each value with honest empty results', async ({ page }) => {
		await gotoApp(page, URLS.timeEntries);
		const userFilter = page.locator('#user-filter');
		await expect(userFilter).toBeVisible();
		const optionValues = await userFilter.locator('option').evaluateAll((opts) =>
			opts.map((o) => /** @type {HTMLOptionElement} */ (o).value).filter((v) => v !== ''),
		);
		expect(optionValues.length, 'user-filter should expose at least one user option').toBeGreaterThan(0);

		for (const value of optionValues) {
			await userFilter.selectOption(value);
			await page.locator('#apply-filters').click();
			await page.waitForLoadState('networkidle').catch(() => {});
			await expect(page.locator('#user-filter')).toHaveValue(value);
			const emptyOrRows = page.locator(
				'.time-entries-empty, .pc-empty-state, #time-entries-table tbody tr, .time-entries-table tbody tr',
			);
			await expect(emptyOrRows.first()).toBeAttached();
		}

		// Impossible user id → honest empty (no fake KPI rows)
		await userFilter.selectOption({ index: 0 });
		await page.goto(`${URLS.timeEntries}?user_id=__atlas_no_such_user__`);
		await page.waitForLoadState('domcontentloaded');
		const emptyState = page.locator('.time-entries-empty, .pc-empty-state, .empty-content');
		const rows = page.locator('#time-entries-table tbody tr, .time-entries-table tbody tr');
		const emptyCount = await emptyState.count();
		const rowCount = await rows.count();
		expect(emptyCount > 0 || rowCount === 0, 'restrictive user filter must not invent rows').toBeTruthy();

		await page.locator('#clear-filters').click();
		await page.waitForLoadState('networkidle').catch(() => {});
		await expect(page.locator('#user-filter')).toBeVisible();
	});

	test('employees: search filter toggles populated and empty honesty', async ({ page }) => {
		await gotoApp(page, URLS.employees);
		const search = page.locator('#employee-search');
		await expect(search).toBeVisible();
		await expect(page.locator('.employees-search-form')).toBeVisible();

		await search.fill('a');
		await page.locator('#apply-filters').click();
		await page.waitForURL(/search=/);
		await expect(page.locator('#employee-search')).toBeVisible();
		const populatedOrEmpty = page.locator(
			'.employees-table tbody tr, .pc-data-table tbody tr, .emptycontent, .pc-empty-state, .employees-empty',
		);
		await expect(populatedOrEmpty.first()).toBeAttached();

		await search.fill('zzznomatch-atlas-xyz-999');
		await page.locator('#apply-filters').click();
		await page.waitForURL(/search=zzznomatch/);
		// Locale may be de (Keine Mitarbeitenden…) or en (No employees match…)
		await expect(page.locator('.emptycontent h2')).toBeVisible();
		await expect(page.locator('.emptycontent h2')).toContainText(/match|entsprechen|passen|matchar|correspon/i);
		await page.locator('#clear-filters, .emptycontent a.button').first().click();
		await page.waitForLoadState('networkidle').catch(() => {});
		await expect(page.locator('#employee-search')).toBeVisible();
		await expect(page.locator('#employee-search')).toHaveValue('');
		await assertAxeClean(page);
	});

	test('time entries: search filter toggles populated and empty honesty', async ({ page }) => {
		await gotoApp(page, URLS.timeEntries);
		const search = page.locator('#time-entry-search');
		await expect(search).toBeVisible();
		await expect(page.locator('.pc-filters--all-visible')).toBeVisible();

		await search.fill('a');
		await page.locator('#apply-filters').click();
		await page.waitForURL(/search=/);
		await expect(page.locator('#time-entry-search')).toBeVisible();
		const populatedOrEmpty = page.locator(
			'#time-entries-table tbody tr, .time-entries-table tbody tr, .time-entries-empty, .pc-empty-state',
		);
		await expect(populatedOrEmpty.first()).toBeAttached();

		await search.fill('zzznomatch-atlas-xyz-999');
		await page.locator('#apply-filters').click();
		await page.waitForURL(/search=zzznomatch/);
		await expect(page.locator('.time-entries-empty h2, .pc-empty-state h2').first()).toBeVisible();
		await expect(page.locator('.time-entries-empty h2, .pc-empty-state h2').first()).toContainText(
			/No time entries|Keine Zeiteintr|Ingen tid|Inga tids|Ninguna entrada|Aucun|Nessun/i,
		);
		await page.locator('#clear-filters').click();
		await page.waitForLoadState('networkidle').catch(() => {});
		await expect(page.locator('#time-entry-search')).toBeVisible();
		await expect(page.locator('#time-entry-search')).toHaveValue('');
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
			await expect(page.locator('#time-entry-form .form-row--metrics > .form-group')).toHaveCount(4);
			await expect(page.locator('#hourly_rate')).toBeVisible();
			await expect(page.locator('#total_cost')).toBeVisible();
			await expect(page.locator('#pc-te-pricing-summary')).toHaveCount(0);
		}
		await assertAxeClean(page);
	});

	test('time entry create: focusing selects/inputs must not jump the page (desktop)', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 800 });
		await gotoApp(page, URLS.timeEntryCreate);
		await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();

		const jumpProbe = async (locator) => {
			if ((await locator.count()) === 0) {
				return;
			}
			await locator.first().scrollIntoViewIfNeeded();
			const before = await page.evaluate(() => ({
				y: window.scrollY,
				app: document.querySelector('#app-content')?.scrollTop ?? 0,
			}));
			await locator.first().focus();
			await page.waitForTimeout(450);
			const after = await page.evaluate(() => ({
				y: window.scrollY,
				app: document.querySelector('#app-content')?.scrollTop ?? 0,
			}));
			expect(Math.abs(after.y - before.y), `window jump on ${await locator.first().evaluate((el) => el.id || el.name || el.tagName)}`).toBeLessThanOrEqual(2);
			expect(Math.abs(after.app - before.app), 'app-content jump').toBeLessThanOrEqual(2);
		};

		await jumpProbe(page.locator('#project_id, select[name="project_id"]').first());
		await jumpProbe(page.locator('#hours, input[name="hours"]').first());
		await jumpProbe(page.locator('#date, input[name="date"]').first());
		await jumpProbe(page.locator('#description, textarea[name="description"], #notes, textarea[name="notes"]').first());
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
		// Add to list is secondary; Save at the bottom is the only primary finish action
		await expect(page.locator('#pc-quick-customer-create')).not.toHaveClass(/primary/);
		await expect(page.locator('#project-form button.primary, #project-form .button.primary')).toHaveCount(1);
		await expect(page.locator('#pc-project-save')).toHaveClass(/primary/);
		await assertAxeClean(page);

		const customerName = `Bachus Customer ${Date.now()}`;
		await page.locator('#pc-quick-customer-name').fill(customerName);
		await page.locator('#pc-quick-customer-create').click();
		await expect(page.locator('#pc-quick-customer-status')).toContainText(/added|hinzugefügt|selected|ausgewählt/i, { timeout: 15_000 });
		await expect(page.locator('#pc-quick-customer-next')).toBeVisible();
		await expect(page.locator('#pc-quick-customer-goto-save')).toBeVisible();
		await expect(page.locator('#pc-quick-customer-goto-save')).not.toHaveClass(/primary/);
		// Bachus: after add, focus the single primary finish action (footer Save)
		await expect(page.locator('#pc-project-save')).toBeFocused({ timeout: 5_000 });
		await expect(page.locator('#project-form button.primary, #project-form .button.primary')).toHaveCount(1);

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
		await assertAxeClean(page, '#pc-project-form-actions');
	});

	test('project edit: quick-add customer then save persists reassignment (zero budget)', async ({ page }) => {
		await gotoApp(page, URLS.projectCreate);
		await expect(page.locator('#project-form')).toBeVisible();
		if ((await page.locator('.pc-quick-customer').count()) === 0) {
			test.skip(true, 'User cannot create customers — quick-add hidden by permission');
		}

		const projectName = `Bachus Reassign ${Date.now()}`;
		await page.locator('#name').fill(projectName);
		await page.locator('#pc-more-about-project').evaluate((el) => {
			if (el instanceof HTMLDetailsElement) {
				el.open = true;
			}
		});
		await page.locator('#short_description').fill('Reassign customer regression');
		const firstCustomer = `Bachus Cust A ${Date.now()}`;
		await page.locator('#pc-quick-customer-name').fill(firstCustomer);
		await page.locator('#pc-quick-customer-create').click();
		await expect(page.locator('#pc-quick-customer-next')).toBeVisible({ timeout: 15_000 });
		await expect(page.locator('#customer_id')).not.toHaveValue('');

		// Zero budget path (empty available_hours) previously broke UPDATE
		await page.locator('#pc-advanced-budget').evaluate((el) => {
			if (el instanceof HTMLDetailsElement) {
				el.open = true;
			}
		});
		const budget = page.locator('#total_budget');
		if (await budget.count()) {
			await budget.fill('0');
		}

		// Only the footer Save submits the form
		await Promise.all([
			page.waitForURL(/projects\/\d+/, { timeout: 25_000 }),
			page.locator('#pc-project-save').click(),
		]);
		const createdUrl = page.url();
		const idMatch = createdUrl.match(/projects\/(\d+)/);
		expect(idMatch).toBeTruthy();
		const projectId = idMatch[1];

		await gotoApp(page, `${BASE}/index.php/apps/projectcheck/projects/${projectId}/edit`);
		await expect(page.locator('#customer_id')).toBeVisible();
		await expect(page.locator('#pc-quick-customer-create')).toBeVisible();
		const secondCustomer = `Bachus Cust B ${Date.now()}`;
		await page.locator('#pc-quick-customer-name').fill(secondCustomer);
		await Promise.all([
			page.waitForResponse((r) => r.url().includes('/customers') && r.request().method() === 'POST'),
			page.locator('#pc-quick-customer-create').click(),
		]);
		await expect.poll(async () => {
			return page.locator('#customer_id').evaluate((el) => {
				const opt = el.options[el.selectedIndex];
				return opt ? opt.textContent.trim() : '';
			});
		}, { timeout: 15_000 }).toBe(secondCustomer);
		await expect(page.locator('#pc-quick-customer-goto-save')).toBeVisible();

		await Promise.all([
			page.waitForURL(/\/projects/, { timeout: 25_000 }),
			page.locator('#pc-project-save').click(),
		]);
		expect(page.url()).not.toMatch(/message=error/);

		await gotoApp(page, `${BASE}/index.php/apps/projectcheck/projects/${projectId}/edit`);
		await expect.poll(async () => {
			return page.locator('#customer_id').evaluate((el) => {
				const opt = el.options[el.selectedIndex];
				return opt ? opt.textContent.trim() : '';
			});
		}, { timeout: 15_000 }).toBe(secondCustomer);
	});

	test('project form: single primary Save and no competing primary CTAs', async ({ page }) => {
		await gotoApp(page, URLS.projectCreate);
		await expect(page.locator('#project-form')).toBeVisible();
		await expect(page.locator('#pc-form-tip, .pc-form-tip')).toBeVisible();
		await expect(page.locator('.pc-create-workflow')).toHaveCount(0);
		await expect(page.locator('#project-form .button.primary, #project-form button.primary')).toHaveCount(1);
		await expect(page.locator('#pc-project-save')).toHaveText(/Save project|Projekt speichern/i);
		await expect(page.locator('#project-form [type="submit"]')).toHaveCount(1);
		for (const id of ['#pc-advanced-classification', '#pc-advanced-pricing', '#pc-advanced-budget', '#pc-advanced-schedule', '#pc-more-about-project']) {
			const panel = page.locator(id);
			await expect(panel).toBeAttached();
			expect(await panel.evaluate((el) => el instanceof HTMLDetailsElement && el.open)).toBe(false);
		}
		await expect(page.locator('#short_description')).not.toHaveAttribute('required', '');
		await assertAxeClean(page, '#pc-project-form-actions');
	});

	test('project create: blank short description still saves via name fallback', async ({ page }) => {
		await gotoApp(page, URLS.projectCreate);
		await expect(page.locator('#project-form')).toBeVisible();
		if ((await page.locator('.pc-quick-customer').count()) === 0) {
			test.skip(true, 'User cannot create customers — quick-add hidden by permission');
		}
		const projectName = `Bachus AutoShort ${Date.now()}`;
		await page.locator('#name').fill(projectName);
		// Short description stays collapsed/blank on create — server auto-fills from name
		await expect(page.locator('#short_description')).toHaveValue('');
		const customerName = `Bachus AutoCust ${Date.now()}`;
		await page.locator('#pc-quick-customer-name').fill(customerName);
		await page.locator('#pc-quick-customer-create').click();
		await expect(page.locator('#pc-quick-customer-next')).toBeVisible({ timeout: 15_000 });
		await Promise.all([
			page.waitForURL(/projects\/\d+/, { timeout: 25_000 }),
			page.locator('#pc-project-save').click(),
		]);
		expect(page.url()).not.toMatch(/message=error/);
		await assertAxeClean(page);
	});

	test('project edit: capacity is display-only and pricing gate appears when rate missing', async ({ page }) => {
		await gotoApp(page, URLS.projectCreate);
		await expect(page.locator('#project-form')).toBeVisible();

		await page.locator('#pc-advanced-budget').evaluate((el) => {
			if (el instanceof HTMLDetailsElement) {
				el.open = true;
			}
		});
		const hours = page.locator('#available_hours');
		await expect(hours).toBeVisible();
		await expect(hours).toHaveAttribute('readonly', '');
		const hoursName = await hours.getAttribute('name');
		expect(hoursName === null || hoursName === '').toBeTruthy();

		await page.locator('#total_budget').fill('100');
		await page.locator('#hourly_rate').fill('0');
		await page.locator('#hourly_rate').dispatchEvent('input');
		await expect(page.locator('#pc-pricing-gate')).toBeVisible();
		await expect(page.locator('#available_hours')).toHaveValue('0');
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
