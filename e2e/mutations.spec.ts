import { test, expect } from '@playwright/test';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { gotoApp } = require('./helpers/auth-guard');

const base = process.env.BASE_URL;

test.describe('ProjectCheck mutation hardening (optional E2E)', () => {
	test.skip(!base, 'Set BASE_URL to run E2E');
	test.skip(!process.env.E2E_USER, 'Set E2E_USER and run npm run e2e:auth (uses storage state)');

	test('authenticated preferences mutation enforces validation and never 500s', async ({ page }) => {
		const baseUrl = base!.replace(/\/$/, '');

		// Personal preferences API — org settings page does not host this form.
		await gotoApp(page, `${baseUrl}/index.php/settings/user/projectcheck`);
		await page.waitForURL(/settings\/user\/projectcheck/, { timeout: 20000 });

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
			} catch {
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
			}),
		);
	});
});
