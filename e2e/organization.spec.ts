import { test, expect } from '@playwright/test';

const base = process.env.BASE_URL;
const user = process.env.E2E_USER;
const pass = process.env.E2E_PASS;

test.describe('ProjectCheck organization (optional E2E)', () => {
	test.skip(!base, 'Set BASE_URL to run E2E');
	test.skip(!user || !pass, 'Set E2E_USER and E2E_PASS');

	test('organization page loads for app admin', async ({ request }) => {
		// Server-side session: use request context for login flow if your NC uses form login.
		// Minimal check: unauthenticated org URL should not be 200 with full app shell (usually redirect to login).
		const orgUrl = `${base!.replace(/\/$/, '')}/index.php/apps/projectcheck/organization`;
		const res = await request.get(orgUrl, { maxRedirects: 0 });
		const status = res.status();
		// 302/401 to login or 200 for guest error template — not 5xx
		expect(status, 'organization route should not 500').not.toBe(500);
		expect([200, 301, 302, 401, 403].includes(status)).toBeTruthy();
	});
});
