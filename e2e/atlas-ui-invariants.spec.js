// @ts-check
/**
 * ATLAS_UI_INVARIANTS — shared-contract coverage for ProjectCheck web surfaces.
 *
 * Wires nextcloud/apps/_shared/e2e/atlas-ui-invariants.js onto representative
 * surfaces using a dedicated throwaway customer+project fixture (unique
 * `pcui-` mark — no shared fixture mutation, cleaned in afterAll):
 *   a11y-dom sweep, console errors, raw i18n keys, bounded (O(1)) list
 *   requests, stored-xss via project name, mutation→surface freshness,
 *   double-submit guard on the add-team-member dialog, and
 *   form-survival-on-5xx on the same dialog.
 */
const { test, expect } = require('@playwright/test');
const {
	ATLAS_XSS_PAYLOADS,
	assertA11yDom,
	assertNoConsoleErrors,
	assertNoDuplicateSubmit,
	assertFormSurvivesFailure,
	assertNoInjection,
	assertNoRawI18nKeys,
	assertSurfaceFresh,
	countApiRequests,
	trackConsoleErrors,
} = require('../../_shared/e2e/atlas-ui-invariants');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const APP = `${BASE}/index.php/apps/projectcheck`;
const CONTENT = '#app-content';
const MARK = `pcui-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
const E2E_USER = process.env.E2E_USER || 'admin';

/** session-cookie call into the app API (form or JSON). */
async function api(page, method, path, { body, form } = {}) {
	return page.evaluate(async ({ method: m, path: p, body: b, form: f }) => {
		const token = (typeof window.OC !== 'undefined' && window.OC.requestToken)
			|| document.querySelector('head[data-requesttoken]')?.getAttribute('data-requesttoken')
			|| document.querySelector('input[name="requesttoken"]')?.getAttribute('value')
			|| '';
		const res = await fetch(p, {
			method: m,
			credentials: 'same-origin',
			headers: {
				requesttoken: token,
				'OCS-APIRequest': 'true',
				Accept: 'application/json',
				...(f ? { 'Content-Type': 'application/x-www-form-urlencoded' } : b !== undefined ? { 'Content-Type': 'application/json' } : {}),
			},
			body: f !== undefined ? new URLSearchParams(f).toString() : b !== undefined ? JSON.stringify(b) : undefined,
		});
		return { status: res.status, body: await res.text() };
	}, { method, path: `${APP}${path}`, body, form });
}

let customerId = 0;
let projectId = 0;

test.describe('ProjectCheck UI invariants (atlas-ui-invariants)', () => {
	test.describe.configure({ mode: 'serial' });

	test.beforeEach(async () => {
		test.skip(!process.env.E2E_USER, 'Set E2E_USER + E2E_PASS in e2e/.env');
	});

	test.beforeAll(async ({ browser }) => {
		if (!process.env.E2E_USER) return;
		const page = await browser.newPage();
		try {
			await page.setViewportSize({ width: 1280, height: 800 });
			await gotoApp(page, `${APP}/dashboard`);
			const cust = await api(page, 'POST', '/customers', { form: { name: `${MARK} Kunde` } });
			customerId = JSON.parse(cust.body)?.customer?.id || 0;
			const proj = await api(page, 'POST', '/api/projects', {
				body: { name: `${MARK} Projekt`, short_description: 'pcui', customer_id: customerId },
			});
			projectId = JSON.parse(proj.body)?.project?.id || 0;
		} finally {
			await page.close();
		}
	});

	test.afterAll(async ({ browser }) => {
		if (!projectId && !customerId) return;
		const page = await browser.newPage();
		try {
			await gotoApp(page, `${APP}/dashboard`);
			if (projectId) await api(page, 'DELETE', `/api/projects/${projectId}`);
			if (customerId) await api(page, 'DELETE', `/customers/${customerId}`, { form: { strategy: 'cascade' } });
		} finally {
			await page.close();
		}
	});

	test('fixture customer+project created', async () => {
		expect(customerId, 'fixture customer create failed').toBeGreaterThan(0);
		expect(projectId, 'fixture project create failed').toBeGreaterThan(0);
	});

	for (const [label, path] of [
		['projects', '/projects'],
		['customers', '/customers'],
		['time-entries', '/time-entries'],
	]) {
		test(`a11y-dom sweep /${label}`, async ({ page }) => {
			await page.setViewportSize({ width: 1280, height: 800 });
			await gotoApp(page, `${APP}${path}`);
			await page.waitForLoadState('networkidle').catch(() => {});
			const findings = await assertA11yDom(page, { content: CONTENT });
			expect(findings, `a11y-dom findings on /${label}:\n${findings.join('\n')}`).toEqual([]);
		});

		test(`console errors + raw i18n keys /${label}`, async ({ page }) => {
			const errs = trackConsoleErrors(page);
			await gotoApp(page, `${APP}${path}`);
			await page.waitForLoadState('networkidle').catch(() => {});
			assertNoConsoleErrors(errs, { allow: [/favicon/i] });
			await assertNoRawI18nKeys(page, { content: CONTENT });
		});
	}

	test('n+1: /projects issues a bounded number of app API requests', async ({ page }) => {
		const hits = await countApiRequests(page, async () => {
			await gotoApp(page, `${APP}/projects`);
			await page.waitForLoadState('networkidle').catch(() => {});
		}, '/apps/projectcheck/api/');
		expect(
			hits.length,
			`projects fired ${hits.length} app API requests (N+1 suspect): ${hits.map((h) => `${h.method} ${h.url}`).join(' | ')}`,
		).toBeLessThanOrEqual(12);
	});

	test('stored-xss: project name renders escaped on /projects', async ({ page }) => {
		// api() fetches in-page: land on an app surface first so the call is
		// same-origin and a requesttoken exists.
		await gotoApp(page, `${APP}/projects`);
		const payload = `${MARK} ${ATLAS_XSS_PAYLOADS[0]}`;
		const created = await api(page, 'POST', '/api/projects', {
			body: { name: payload, short_description: 'pcui-xss', customer_id: customerId },
		});
		const xssId = JSON.parse(created.body)?.project?.id;
		test.skip(!xssId, `xss project create returned ${created.status}: ${created.body.slice(0, 160)}`);
		try {
			await gotoApp(page, `${APP}/projects`);
			await page.waitForLoadState('networkidle').catch(() => {});
			await assertNoInjection(page);
		} finally {
			await api(page, 'DELETE', `/api/projects/${xssId}`);
		}
	});

	test('mutation freshness: API-created customer shows on reload', async ({ page }) => {
		// api() fetches in-page: land on an app surface first (same-origin +
		// requesttoken), before assertSurfaceFresh's mutate step runs.
		await gotoApp(page, `${APP}/customers`);
		const catName = `${MARK} fresh`;
		let freshId = 0;
		try {
			await assertSurfaceFresh(page, {
				mutate: async () => {
					const created = await api(page, 'POST', '/customers', { form: { name: catName } });
					freshId = JSON.parse(created.body)?.customer?.id || 0;
					expect(freshId, `customer create failed: ${created.body.slice(0, 160)}`).toBeGreaterThan(0);
				},
				visit: () => page.goto(`${APP}/customers`, { waitUntil: 'networkidle' }),
				expect: { present: catName },
				content: CONTENT,
			});
		} finally {
			if (freshId) await api(page, 'DELETE', `/customers/${freshId}`, { form: { strategy: 'cascade' } });
		}
	});

	/** Open the add-team-member modal and select $E2E_USER in the combobox. */
	async function openMemberDialogWithUser(page) {
		await page.locator('#add-team-member-btn').click();
		const dialog = page.locator('#addTeamMemberModal');
		await expect(dialog).toBeVisible();
		const search = dialog.locator('#teamMemberSearch');
		await search.fill(E2E_USER.slice(0, 4));
		const option = dialog.locator('#teamMemberSearchResults li[role="option"]').first();
		await expect(option, `no user suggestion for "${E2E_USER.slice(0, 4)}"`).toBeVisible({ timeout: 10000 });
		await option.click();
		return dialog;
	}

	test('double-submit: add-team-member fires at most one write', async ({ page }) => {
		await gotoApp(page, `${APP}/projects/${projectId}`);
		await page.waitForLoadState('networkidle').catch(() => {});
		const dialog = await openMemberDialogWithUser(page);
		const submit = dialog.locator('#submit-add-team-member');
		await expect(submit).toBeEnabled();
		try {
			await assertNoDuplicateSubmit(page, {
				mutatingUrl: `/apps/projectcheck/projects/${projectId}/members`,
				method: 'POST',
				submit: () => submit.click({ force: true }),
			});
		} finally {
			// If the add went through the page reloads; remove admin again so the
			// next test starts from a clean member list.
			await api(page, 'DELETE', `/projects/${projectId}/members/${encodeURIComponent(E2E_USER)}`).catch(() => {});
		}
	});

	test('form survival: add-team-member keeps values on server 500', async ({ page }) => {
		await gotoApp(page, `${APP}/projects/${projectId}`);
		await page.waitForLoadState('networkidle').catch(() => {});
		const dialog = await openMemberDialogWithUser(page);
		const search = dialog.locator('#teamMemberSearch');
		const typed = await search.inputValue();
		await assertFormSurvivesFailure(page, {
			failUrl: `/apps/projectcheck/projects/${projectId}/members`,
			method: 'POST',
			fields: {},
			submit: () => dialog.locator('#submit-add-team-member').click({ force: true }),
			errorSel: '#add-team-member-error:not(:empty), .toastify, #pc-alert-region:not(:empty)',
		});
		// The picked-user label survives the failed submit (dialog stays open).
		await expect(search, 'user search field lost its value on server error').toHaveValue(typed);
	});
});
