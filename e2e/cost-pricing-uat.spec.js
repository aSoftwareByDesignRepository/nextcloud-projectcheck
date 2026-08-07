/**
 * UAT items not fully covered by smoke: keyboard team picker (4), employee assign hint (11).
 */
const { test, expect } = require('@playwright/test');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const PROJECTS_URL = `${BASE}/index.php/apps/projectcheck/projects`;
const EMPLOYEES_URL = `${BASE}/index.php/apps/projectcheck/employees`;

test.describe('ProjectCheck UAT gaps', () => {
	test.beforeEach(async ({ page }) => {
		test.skip(!process.env.BASE_URL || !process.env.E2E_USER, 'Set BASE_URL and E2E_USER in e2e/.env');
		await page.setViewportSize({ width: 1280, height: 800 });
	});

	test('team picker: ArrowDown and Enter select a user (UAT 4)', async ({ page }) => {
		const createUrl = `${BASE}/index.php/apps/projectcheck/projects/create`;
		await gotoApp(page, createUrl);
		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');
		const unique = `E2E team picker ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Empty team for picker test.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="project"]').check({ force: true });
		await page.locator('#hourly_rate').fill('75');
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/\d+/, { timeout: 30000 });

		const addBtn = page.locator('#add-team-member-btn');
		test.skip(!(await addBtn.isVisible().catch(() => false)), 'Team management not available');
		await addBtn.click();
		await expect(page.locator('#addTeamMemberModal')).toBeVisible();

		const search = page.locator('#teamMemberSearch');
		const projectId = page.url().match(/\/projects\/(\d+)/)?.[1];
		const assignable = await page.evaluate(async (pid) => {
			const token = document.querySelector('meta[name="requesttoken"]')?.getAttribute('content')
				|| (typeof OC !== 'undefined' ? OC.requestToken : '');
			for (const q of ['e2e', 'admin', 'guest']) {
				const res = await fetch(
					`/index.php/apps/projectcheck/projects/${pid}/members/search-users?q=${encodeURIComponent(q)}`,
					{ headers: { requesttoken: token, Accept: 'application/json' }, credentials: 'same-origin' },
				);
				const data = await res.json().catch(() => ({}));
				if (data.success && Array.isArray(data.items) && data.items.length > 0) {
					return { query: q, uid: data.items[0].uid };
				}
			}
			return null;
		}, projectId);
		test.skip(!assignable, 'No assignable users for team search');

		await search.fill(assignable.query);
		const option = page.locator('#teamMemberSearchResults li[role="option"]').first();
		await expect(option).toBeVisible({ timeout: 15000 });
		await search.press('ArrowDown');
		await search.press('Enter');
		await expect(page.locator('#teamMemberUserId')).not.toHaveValue('');
		await search.press('Escape');
		await expect(page.locator('#teamMemberSearchResults')).toBeHidden();
	});

	test('employee assign on project_member: API + UI hint (UAT 11)', async ({ page }) => {
		const createUrl = `${BASE}/index.php/apps/projectcheck/projects/create`;
		await gotoApp(page, createUrl);
		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');
		const unique = `E2E assign hint ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('UAT 11 — per-person assign hint.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="project_member"]').check({ force: true });
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/(\d+)/, { timeout: 30000 });
		const projectId = page.url().match(/\/projects\/(\d+)/)?.[1];
		test.skip(!projectId, 'Could not read new project id');

		const targetUser = process.env.E2E_ASSIGN_USER || 'e2e_employee';

		const apiResult = await page.evaluate(async ({ employeeId, pid }) => {
			const token = document.querySelector('meta[name="requesttoken"]')?.getAttribute('content')
				|| (typeof OC !== 'undefined' ? OC.requestToken : '');
			const body = new URLSearchParams({ project_id: String(pid) });
			const res = await fetch(`/index.php/apps/projectcheck/employees/${encodeURIComponent(employeeId)}/projects`, {
				method: 'POST',
				headers: {
					requesttoken: token,
					'X-Requested-With': 'XMLHttpRequest',
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: body.toString(),
				credentials: 'same-origin',
			});
			return { status: res.status, body: await res.json().catch(() => ({})) };
		}, { employeeId: targetUser, pid: projectId });

		expect(apiResult.status).toBe(400);
		expect(apiResult.body.code).toBe('project_member_mode');
		expect(apiResult.body.project_url).toMatch(/team-section/);

		await gotoApp(page, `${BASE}/index.php/apps/projectcheck/employees/${encodeURIComponent(targetUser)}`);
		const assignSelect = page.locator('#assignProjectId');
		if (await assignSelect.isVisible({ timeout: 8000 }).catch(() => false)) {
			await assignSelect.selectOption(projectId);
			const proactiveLink = page.locator('#assign-project-member-hint a.pc-inline-action-link');
			await expect(proactiveLink).toBeVisible();
			await expect(proactiveLink).toHaveAttribute('href', /team-section/);

			await page.locator('#assign-project-submit').click();
			const errorLink = page.locator('#assign-project-error a.pc-inline-action-link');
			await expect(errorLink).toBeVisible({ timeout: 5000 });
			await expect(errorLink).toHaveAttribute('href', /team-section/);
		}
	});

	test('created banner scroll respects reduced motion (UAT 15)', async ({ page }) => {
		await page.emulateMedia({ reducedMotion: 'reduce' });
		await gotoApp(page, PROJECTS_URL);

		const createUrl = `${BASE}/index.php/apps/projectcheck/projects/create`;
		await gotoApp(page, createUrl);

		const customerSelect = page.locator('#customer_id');
		test.skip((await customerSelect.locator('option').count()) < 2, 'Need a customer');

		const unique = `E2E motion ${Date.now()}`;
		await page.locator('#name').fill(unique);
		await page.locator('#short_description').fill('Reduced motion scroll check.');
		await customerSelect.selectOption({ index: 1 });
		await page.locator('input[name="cost_rate_mode"][value="project_member"]').check({ force: true });
		await page.locator('form#project-form button[type="submit"]').click();
		await page.waitForURL(/\/projects\/\d+/, { timeout: 30000 });

		const scrollBehavior = await page.evaluate(async () => {
			const team = document.getElementById('team-section');
			const link = document.querySelector('.pc-created-banner a[href="#team-section"]');
			if (!team || !link) {
				return null;
			}
			let captured = null;
			const original = team.scrollIntoView.bind(team);
			team.scrollIntoView = function (opts) {
				captured = opts && opts.behavior ? opts.behavior : 'default';
			};
			link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
			team.scrollIntoView = original;
			return captured;
		});

		expect(scrollBehavior).toBe('auto');
	});
});
