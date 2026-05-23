import { test, expect } from '@playwright/test';

const base = process.env.BASE_URL;

const AUTH_OK_STATUSES = [200, 301, 302, 303, 307, 401, 403];

test.describe('ProjectCheck settings routes (optional E2E)', () => {
	test.skip(!base, 'Set BASE_URL to run E2E');

	test('settings page is canonical and organization remains compatible', async ({ playwright }) => {
		const baseUrl = base!.replace(/\/$/, '');
		const request = await playwright.request.newContext();

		const settingsRes = await request.get(`${baseUrl}/index.php/apps/projectcheck/settings`, { maxRedirects: 0 });
		expect(settingsRes.status(), 'settings route should not 500').not.toBe(500);
		expect(AUTH_OK_STATUSES.includes(settingsRes.status())).toBeTruthy();

		const orgRes = await request.get(`${baseUrl}/index.php/apps/projectcheck/organization`, { maxRedirects: 0 });
		expect(orgRes.status(), 'organization route should not 500').not.toBe(500);
		expect(AUTH_OK_STATUSES.includes(orgRes.status())).toBeTruthy();

		await request.dispose();
	});

	test('critical permission routes do not 500 unauthenticated', async ({ playwright }) => {
		const baseUrl = base!.replace(/\/$/, '');
		const request = await playwright.request.newContext();
		const candidateRoutes = [
			'/index.php/apps/projectcheck/settings',
			'/index.php/apps/projectcheck/organization',
			'/index.php/apps/projectcheck/projects',
			'/index.php/apps/projectcheck/customers',
			'/index.php/apps/projectcheck/time-entries',
			'/index.php/apps/projectcheck/employees',
		];

		for (const route of candidateRoutes) {
			const res = await request.get(`${baseUrl}${route}`, { maxRedirects: 0 });
			const status = res.status();
			expect(status, `${route} should not 500`).not.toBe(500);
			expect(AUTH_OK_STATUSES.includes(status), `${route} should return expected auth status (got ${status})`).toBeTruthy();
		}

		await request.dispose();
	});
});
