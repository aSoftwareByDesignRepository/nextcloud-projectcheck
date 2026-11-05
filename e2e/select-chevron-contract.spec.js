// @ts-check
/**
 * Rendered contract for projectcheck-intake-20261104T115525Z
 * (fail-tier class-gate required_field_or_step_undiscoverable):
 * css/deletion-modal.css painted `.projectcheck-deletion-modal__reassign-options
 * select` via the `background:` SHORTHAND, resetting the NC core select
 * chevron's background-image — the reassign-customer pick read as a plain
 * text field on the customer-deletion modal. Fixed via `background-color:`.
 *
 * Proof: the #reassign-customer select keeps a url() background-image,
 * no-repeat, a non-zero position in light AND dark — probed hidden (the
 * reassign section starts `hidden`; the cascade resolves anyway) and again
 * visible after the 'reassign' strategy radio is picked.
 * Mirrors whistlecheck e2e/select-chevron-contract.spec.js.
 */
const { test, expect } = require('@playwright/test');
const { gotoApp } = require('./helpers/auth-guard');
const { setUserTheme, resetUserTheme } = require('./helpers/theming');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const APP = `${BASE}/index.php/apps/projectcheck`;
const MARK = `pcchev-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;

let customerId = 0;
let projectId = 0;

/** session-cookie call into the app API (form or JSON). */
async function api(page, method, path, { body, form } = {}) {
	return page.evaluate(async ({ method: m, path: p, body: b, form: f }) => {
		const token = (typeof window.OC !== 'undefined' && window.OC.requestToken)
			|| document.querySelector('head[data-requesttoken]')?.getAttribute('data-requesttoken')
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

/**
 * Assert the resolved chevron cascade on the reassign select — the computed
 * background longhands resolve even while the section is display:none, which
 * is exactly the state the regressed layer lived in.
 * @param {import('@playwright/test').Locator} locator
 * @param {string} label
 */
async function expectChevron(locator, label) {
	await expect(locator, `${label}: select missing from DOM`).toHaveCount(1);
	const probe = await locator.evaluate((node) => {
		const cs = getComputedStyle(node);
		return {
			image: cs.backgroundImage,
			repeat: cs.backgroundRepeat,
			position: cs.backgroundPosition,
		};
	});
	expect(probe.image, `${label}: chevron background-image missing — select reads as a text field`).toContain('url(');
	expect(probe.repeat, `${label}: chevron tiles (caret-grid regression)`).toBe('no-repeat');
	expect(probe.position, `${label}: chevron unpositioned`).not.toBe('0% 0%');
}

test.describe('select chevron contract (pc deletion-modal reassign)', () => {
	test.describe.configure({ mode: 'serial' });
	test.setTimeout(240_000);

	test.beforeEach(async () => {
		test.skip(!process.env.E2E_USER, 'Set E2E_USER + E2E_PASS in e2e/.env');
	});

	test.beforeAll(async ({ browser }) => {
		if (!process.env.E2E_USER) return;
		const page = await browser.newPage();
		try {
			await gotoApp(page, `${APP}/dashboard`);
			const cust = await api(page, 'POST', '/customers', { form: { name: `${MARK} Kunde` } });
			customerId = JSON.parse(cust.body)?.customer?.id || 0;
			// The strategy section (with #reassign-customer) only renders when
			// the customer has dependent projects — seed one.
			const proj = await api(page, 'POST', '/api/projects', {
				body: { name: `${MARK} Projekt`, short_description: 'pcchev', customer_id: customerId },
			});
			projectId = JSON.parse(proj.body)?.project?.id || 0;
		} finally {
			await page.close();
		}
	});

	test.afterAll(async ({ browser }) => {
		if (projectId || customerId) {
			const page = await browser.newPage();
			try {
				await gotoApp(page, `${APP}/dashboard`);
				if (projectId) await api(page, 'DELETE', `/api/projects/${projectId}`);
				if (customerId) await api(page, 'DELETE', `/customers/${customerId}`, { form: { strategy: 'cascade' } });
			} finally {
				await page.close();
			}
		}
		const page = await browser.newPage();
		try {
			await gotoApp(page, `${APP}/dashboard`);
			await resetUserTheme(page);
		} finally {
			await page.close();
		}
	});

	test('reassign select keeps a computed chevron in light and dark', async ({ page }) => {
		test.skip(!customerId, 'fixture customer missing');
		await page.setViewportSize({ width: 1280, height: 800 });
		await gotoApp(page, `${APP}/customers`);

		for (const theme of ['light', 'dark']) {
			await setUserTheme(page, theme);
			await expect(
				page.locator(`body[data-theme-${theme}]`),
				`${theme}: theme leg did not apply`,
			).toHaveCount(1);

			const del = page.locator(`button.delete-customer-btn[data-customer-id="${customerId}"]`).first();
			await expect(del, `${theme}: delete trigger for fixture customer not visible`).toBeVisible({ timeout: 8000 });
			await del.scrollIntoViewIfNeeded();
			await del.click();
			await page.waitForSelector('#projectcheck-deletion-modal', { timeout: 10000 });

			const select = page.locator('#projectcheck-deletion-modal #reassign-customer');
			await expectChevron(select, `${theme} hidden`);
			await page.locator('#projectcheck-deletion-modal input[name="deletion-strategy"][value="reassign"]').check();
			await expect(select, `${theme}: reassign select still hidden after radio pick`).toBeVisible();
			await expectChevron(select, `${theme} visible`);

			const cancelBtn = page.locator('#projectcheck-deletion-modal .projectcheck-deletion-modal__btn--cancel');
			if (await cancelBtn.isVisible().catch(() => false)) {
				await cancelBtn.click();
			}
			await page.reload({ waitUntil: 'domcontentloaded' });
		}
	});
});
