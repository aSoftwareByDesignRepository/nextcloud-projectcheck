import { test, expect } from '@playwright/test';

const base = process.env.BASE_URL;
const user = process.env.E2E_USER;
const pass = process.env.E2E_PASS;

test.describe('ProjectCheck mutation hardening (optional E2E)', () => {
	test.skip(!base, 'Set BASE_URL to run E2E');
	test.skip(!user || !pass, 'Set E2E_USER and E2E_PASS');

	test('authenticated preferences mutation enforces validation and never 500s', async ({ page }) => {
		const baseUrl = base!.replace(/\/$/, '');

		await page.goto(`${baseUrl}/index.php/login`);
		await page.fill('input[name="user"]', user!);
		await page.fill('input[name="password"]', pass!);
		await page.click('button[type="submit"]');
		const loginErrorVisible = await page
			.getByText('Wrong login or password.')
			.isVisible({ timeout: 4000 })
			.catch(() => false);
		test.skip(loginErrorVisible, 'E2E_USER/E2E_PASS are not valid for this instance');
		await page.waitForURL(/index\.php\/apps\/files|index\.php\/apps\/projectcheck/, { timeout: 20000 });

		await page.goto(`${baseUrl}/index.php/apps/projectcheck/settings`);
		await page.waitForLoadState('networkidle');

		const mutationResult = await page.evaluate(async () => {
			const tokenFromMeta = document.querySelector('meta[name="requesttoken"]')?.getAttribute('content') || '';
			const tokenFromWindow = (window as unknown as { oc_requesttoken?: string }).oc_requesttoken || '';
			const requestToken = tokenFromMeta || tokenFromWindow;

			const response = await fetch('/index.php/apps/projectcheck/api/preferences/save', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: requestToken,
				},
				body: JSON.stringify({
					budget_warning_threshold: '95',
					budget_critical_threshold: '90',
				}),
				credentials: 'same-origin',
			});

			let payload: unknown = null;
			try {
				payload = await response.json();
			} catch (error) {
				payload = null;
			}

			return {
				status: response.status,
				payload,
			};
		});

		expect(mutationResult.status).not.toBe(500);
		expect(mutationResult.status).toBe(400);
		expect(mutationResult.payload).toEqual(
			expect.objectContaining({
				success: false,
				error: 'validation',
			})
		);
	});
});
