/**
 * Deeper cost-pricing workflows (authenticated). Requires customers in DB.
 * Run via: bash e2e/run-smoke.sh (includes all e2e/*.spec.js)
 */
const { test, expect } = require('@playwright/test');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const PROJECTS_URL = process.env.E2E_PROJECTS_URL || `${BASE}/index.php/apps/projectcheck/projects`;
const PROJECT_CREATE_URL = process.env.E2E_PROJECT_CREATE_URL || `${BASE}/index.php/apps/projectcheck/projects/create`;
const EMPLOYEES_URL = `${BASE}/index.php/apps/projectcheck/employees`;
const TIME_ENTRY_CREATE_URL = process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create`;

test.describe('ProjectCheck cost-pricing workflows', () => {
	test.beforeEach(async ({ page }) => {
		test.skip(!process.env.BASE_URL || !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await page.setViewportSize({ width: 1280, height: 800 });
	});

	test('projects list and employees index load with app shell', async ({ page }) => {
		await gotoApp(page, PROJECTS_URL);
		await expect(page.locator('#pc-main-content')).toBeVisible();
		await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();

		await gotoApp(page, EMPLOYEES_URL);
		await expect(page.locator('#pc-main-content')).toBeVisible();
	});

	test('project mode detail: pricing summary and add-all visible in team modal', async ({ page }) => {
		await gotoApp(page, PROJECTS_URL);

		const viewLink = page.locator('table tbody tr a.action-item').first();
		const hasProject = await viewLink.isVisible({ timeout: 5000 }).catch(() => false);
		test.skip(!hasProject, 'No projects in database to open');

		await viewLink.click();
		await page.waitForURL(/\/projects\/\d+(?!\/)/, { timeout: 15000 });

		await expect(page.locator('.pc-pricing-badge-label')).toBeVisible();

		const addMemberBtn = page.locator('#add-team-member-btn');
		if (await addMemberBtn.isVisible().catch(() => false)) {
			await addMemberBtn.click();
			const modal = page.locator('#addTeamMemberModal');
			await expect(modal).toBeVisible();
			const addAll = page.locator('#submit-add-all-team-members');
			const mode = await page.locator('body').textContent();
			if (/per person on this project|je person in diesem projekt/i.test(mode || '')) {
				await expect(addAll).toHaveCount(0);
			} else {
				await expect(addAll).toBeVisible();
			}
			await page.locator('#close-add-member-modal').click();
		}
	});

	test('create project_member project: banner, team section, add-all hidden', async ({ page }) => {
		await gotoApp(page, PROJECT_CREATE_URL);

		const customerSelect = page.locator('#customer_id');
		const optionCount = await customerSelect.locator('option').count();
		test.skip(optionCount < 2, 'Need at least one customer to create a project');

		const unique = `E2E member pricing ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Playwright smoke — per-person project rates.');
		await customerSelect.selectOption({ index: 1 });

		await page.getByRole('radio', { name: /rate per person on this project|satz je person in diesem projekt/i }).check();

		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/\d+.*message=created|\/projects\/\d+(?!\/)/, { timeout: 30000 });

		await expect(page.locator('.pc-created-banner')).toBeVisible({ timeout: 10000 });
		await expect(page.locator('#team-section')).toBeVisible();

		const addMemberBtn = page.locator('#add-team-member-btn');
		if (await addMemberBtn.isVisible().catch(() => false)) {
			await addMemberBtn.click();
			await expect(page.locator('#addTeamMemberModal')).toBeVisible();
			await expect(page.locator('#teamMemberRateGroup')).toBeVisible();
			await expect(page.locator('#submit-add-all-team-members')).toHaveCount(0);
			await page.locator('#close-add-member-modal').click();
		}
	});

	test('project_member: add without rate shows clear error (UAT 5)', async ({ page }) => {
		await gotoApp(page, PROJECT_CREATE_URL);
		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');
		const unique = `E2E no rate ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Per-person rate required on add.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="project_member"]').check({ force: true });
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/(\d+)/, { timeout: 30000 });
		const projectId = page.url().match(/\/projects\/(\d+)/)?.[1];
		test.skip(!projectId, 'Missing project id');

		const addBtn = page.locator('#add-team-member-btn');
		test.skip(!(await addBtn.isVisible().catch(() => false)), 'No team button');
		await addBtn.click();
		await expect(page.locator('#addTeamMemberModal')).toBeVisible();
		await expect(page.locator('#teamMemberRateGroup')).toBeVisible();

		const assignable = await page.evaluate(async (pid) => {
			const token = document.querySelector('meta[name="requesttoken"]')?.getAttribute('content')
				|| (typeof OC !== 'undefined' ? OC.requestToken : '');
			for (const q of ['e2e', 'admin']) {
				const res = await fetch(
					`/index.php/apps/projectcheck/projects/${pid}/members/search-users?q=${encodeURIComponent(q)}`,
					{ headers: { requesttoken: token, Accept: 'application/json' }, credentials: 'same-origin' },
				);
				const data = await res.json().catch(() => ({}));
				if (data.success && data.items?.length) {
					return { query: q, uid: data.items[0].uid };
				}
			}
			return null;
		}, projectId);
		test.skip(!assignable, 'No assignable users');

		const search = page.locator('#teamMemberSearch');
		await search.fill(assignable.query);
		const option = page.locator('#teamMemberSearchResults li[role="option"]').first();
		await expect(option).toBeVisible({ timeout: 15000 });
		await search.press('ArrowDown');
		await search.press('Enter');
		await expect(page.locator('#teamMemberUserId')).not.toHaveValue('');

		await page.locator('#teamMemberHourlyRate').fill('');
		const submit = page.locator('#submit-add-team-member');
		await expect(submit).toBeDisabled();
		await expect(submit).toHaveAttribute('title', /hourly rate|stundensatz/i);
		await page.locator('#addTeamMemberForm').evaluate((form) => form.requestSubmit());
		await expect(page.locator('#add-team-member-error')).toContainText(/hourly rate|stundensatz/i, {
			timeout: 5000,
		});
		await expect(page.locator('#teamMemberHourlyRate')).toHaveAttribute('aria-invalid', 'true');
	});

	test('time entry: resolve rate after project and date selected', async ({ page }) => {
		await gotoApp(page, TIME_ENTRY_CREATE_URL);

		const projectSelect = page.locator('#project_id');
		const optionCount = await projectSelect.locator('option[value]:not([value=""])').count();
		test.skip(optionCount < 1, 'No projects available for time entry');

		// Prefer a project-mode project (fixed project rate) over project_member (needs member rate).
		const projectModeOption = projectSelect.locator('option[data-cost-rate-mode="project"]').first();
		if (await projectModeOption.count() > 0) {
			await projectSelect.selectOption(await projectModeOption.getAttribute('value'));
		} else {
			await projectSelect.selectOption({ index: 1 });
		}

		const dateInput = page.locator('#date');
		const today = new Date();
		const iso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
		await dateInput.fill(iso);

		const rateInput = page.locator('#hourly_rate');
		await expect(rateInput).toHaveAttribute('readonly', '');

		await expect(page.locator('#hourly_rate-hint')).not.toContainText(/csrf check failed/i, { timeout: 5000 });

		await expect.poll(async () => {
			const v = await rateInput.inputValue();
			return parseFloat(v) > 0;
		}, { timeout: 15000, message: 'Server should resolve hourly rate' }).toBe(true);
	});

	test('create employee mode project without planning rate (UAT 2)', async ({ page }) => {
		await gotoApp(page, PROJECT_CREATE_URL);

		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');

		const unique = `E2E employee mode ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Employee master-data pricing.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="employee"]').check({ force: true });
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/\d+/, { timeout: 30000 });

		await expect(page.locator('.pc-scope-strip')).toContainText(/employee|mitarbeitend/i);
	});

	test('create project mode with rate and log time (UAT 1)', async ({ page }) => {
		await gotoApp(page, PROJECT_CREATE_URL);

		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');

		const unique = `E2E project rate ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Playwright — one project rate.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="project"]').check({ force: true });
		await page.locator('#hourly_rate').fill('120');
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/\d+/, { timeout: 30000 });

		await expect(page.locator('.pc-scope-strip')).toBeVisible();
		await expect(page.locator('.pc-pricing-badge-label').first()).toBeVisible();
	});

	test('pricing mode locked on edit after time logged (UAT 9 / E6)', async ({ page }) => {
		await gotoApp(page, PROJECT_CREATE_URL);

		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');

		const unique = `E2E mode lock ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Mode lock after time entry.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="project"]').check({ force: true });
		await page.locator('#hourly_rate').fill('90');
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/(\d+)/, { timeout: 30000 });
		const projectId = page.url().match(/\/projects\/(\d+)/)?.[1];
		test.skip(!projectId, 'Missing project id');

		await gotoApp(page, TIME_ENTRY_CREATE_URL);
		await page.locator('#project_id').selectOption(projectId);
		const today = new Date();
		const iso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
		await page.locator('#date').fill(iso);
		await page.locator('#hours').fill('1');
		const rateInput = page.locator('#hourly_rate');
		await expect.poll(async () => parseFloat(await rateInput.inputValue()) > 0, {
			timeout: 15000,
			message: 'Rate should resolve before submit',
		}).toBe(true);
		await page.locator('#time-entry-form button[type="submit"]').click();
		await page.waitForURL(/time-entries/, { timeout: 30000 });

		await gotoApp(page, `${BASE}/index.php/apps/projectcheck/projects/${projectId}/edit`);
		await expect(page.locator('#pc-pricing-method-help')).toContainText(/locked|gesperrt/i);
		await expect(page.locator('#pc-pricing-method input[type="radio"]').first()).toBeDisabled();
		await expect(page.locator('input[type="hidden"][name="cost_rate_mode"]')).toHaveCount(1);
	});

	test('authenticated resolve API: 403 for inaccessible project (E21)', async ({ page }) => {
		await gotoApp(page, PROJECTS_URL);

		const result = await page.evaluate(async () => {
			const token = document.querySelector('meta[name="requesttoken"]')?.getAttribute('content')
				|| (typeof OC !== 'undefined' ? OC.requestToken : '');
			const res = await fetch('/index.php/apps/projectcheck/api/projects/999999/resolve-hourly-rate?date=2026-01-15', {
				headers: { requesttoken: token },
				credentials: 'same-origin',
			});
			return { status: res.status, body: await res.json().catch(() => ({})) };
		});

		expect(result.status).not.toBe(500);
		expect([403, 404].includes(result.status)).toBeTruthy();
		expect(result.body?.success).not.toBe(true);
	});
});
