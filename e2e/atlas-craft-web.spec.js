// @ts-check
/**
 * Atlas craft: capture ProjectCheck web surfaces into
 * .cursor/atlas-farm-v3/artifacts/projectcheck/craft/web/ — ds_chrome lane.
 *
 * Honesty rules:
 *  - Real theme persistence via the theming OCS API (light / dark /
 *    light-highcontrast / dark-highcontrast) — never a JS-painted
 *    data-theme attribute.
 *  - Locale-safe structural selectors only (ids/classes/data-attrs) — the
 *    i18n lint gate rejects EN|DE name/text regexes.
 *  - Serial, stateful: theme flips touch the probe account's theme config.
 *  - Filenames are descriptive: pc-<view>[-<state>][-dark|-hc|-dhc][-mobile].png
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { gotoApp } = require('./helpers/auth-guard');
const { setUserTheme, resetUserTheme } = require('./helpers/theming');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const APP = `${BASE}/index.php/apps/projectcheck`;
const OUT = path.resolve(
	__dirname,
	'../../../../.cursor/atlas-farm-v3/artifacts/projectcheck/craft/web',
);
const MANIFEST = [];

const PROJECT_ID = process.env.E2E_PROJECT_ID || '213';
const CUSTOMER_ID = process.env.E2E_CUSTOMER_ID || '184';
const TIME_ENTRY_ID = process.env.E2E_TIME_ENTRY_ID || '515';
const EMPLOYEE_UID = process.env.E2E_EMPLOYEE_UID || 'pc_doc_manager';

/** Every primary web page surface. */
const ROUTES = [
	['index', `${APP}/`],
	['dashboard', `${APP}/dashboard`],
	['projects', `${APP}/projects`],
	['project-create', `${APP}/projects/create`],
	['project-show', `${APP}/projects/${PROJECT_ID}`],
	['project-edit', `${APP}/projects/${PROJECT_ID}/edit`],
	['customers', `${APP}/customers`],
	['customer-create', `${APP}/customers/create`],
	['customer-show', `${APP}/customers/${CUSTOMER_ID}`],
	['customer-edit', `${APP}/customers/${CUSTOMER_ID}/edit`],
	['time-entries', `${APP}/time-entries`],
	['time-entry-create', `${APP}/time-entries/create`],
	['time-entry-show', `${APP}/time-entries/${TIME_ENTRY_ID}`],
	['time-entry-edit', `${APP}/time-entries/${TIME_ENTRY_ID}/edit`],
	['employees', `${APP}/employees`],
	['employee-show', `${APP}/employees/${EMPLOYEE_UID}`],
	['settings', `${APP}/settings`],
	['settings-access', `${APP}/settings/access`],
	['settings-admins', `${APP}/settings/admins`],
	['settings-defaults', `${APP}/settings/defaults`],
	['settings-license', `${APP}/settings/license`],
	['settings-support', `${APP}/settings/support`],
	['get-the-app', `${APP}/get-the-app`],
];

/** Representative surfaces re-captured under each non-default theme. */
const THEME_SUBSET = {
	dark: ['dashboard', 'projects', 'project-show', 'customers', 'time-entries', 'settings-license'],
	'light-highcontrast': ['dashboard', 'projects', 'project-show', 'settings'],
	'dark-highcontrast': ['dashboard', 'projects', 'project-show'],
};
const THEME_SUFFIX = { dark: 'dark', 'light-highcontrast': 'hc', 'dark-highcontrast': 'dhc' };

function note(id, rel, buf) {
	const sha = crypto.createHash('sha256').update(buf).digest('hex');
	MANIFEST.push({ id, path: `craft/web/${rel}`, bytes: buf.length, sha256: sha });
	// eslint-disable-next-line no-console
	console.log(`craft ${rel} ${buf.length}b sha=${sha.slice(0, 10)}`);
}

async function shot(page, rel, { fullPage = true } = {}) {
	const buf = await page.screenshot({ fullPage });
	fs.mkdirSync(OUT, { recursive: true });
	fs.writeFileSync(path.join(OUT, rel), buf);
	expect(buf.length, `${rel} too small — blank capture`).toBeGreaterThan(8000);
	note(rel.replace(/\.png$/, ''), rel, buf);
}

async function waitApp(page) {
	await page
		.waitForSelector('#pc-main-content, #projectcheck-org-main, main.pc-main', { timeout: 30000 })
		.catch(() => {});
	await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
	await page.waitForTimeout(350);
}

/** Assert a route renders the app surface (HTTP 200 + app main), not an error shell. */
async function captureRoute(page, id, url, suffix = '') {
	const resp = await gotoAppAndResp(page, url);
	expect(resp, `${id}: no response`).toBeTruthy();
	expect(resp.status(), `${id}: HTTP ${resp.status()} for ${url}`).toBe(200);
	expect(page.url(), `${id}: bounced to login`).not.toContain('/login');
	await waitApp(page);
	const hasMain = await page
		.locator('#pc-main-content, #projectcheck-org-main, main.pc-main')
		.first()
		.isVisible({ timeout: 10000 })
		.catch(() => false);
	expect(hasMain, `${id}: app main surface not visible (error/deny shell?)`).toBe(true);
	await shot(page, `pc-${id}${suffix ? `-${suffix}` : ''}.png`);
}

async function gotoAppAndResp(page, url) {
	let resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
	// One retry on transient non-200 under concurrent farm load (shared instance
	// serves short-lived 404s while sibling lanes reset state mid-run).
	if (!resp || resp.status() !== 200) {
		await page.waitForTimeout(900);
		resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
	}
	return resp;
}

test.describe.configure({ mode: 'serial' });
test.setTimeout(420000);

test.describe('atlas craft: projectcheck web surfaces', () => {
	test.afterAll(async ({ browser }) => {
		// Leave the probe account back on default theme.
		const ctx = await browser.newContext();
		const page = await ctx.newPage();
		try {
			await page.goto(`${APP}/dashboard`, { waitUntil: 'domcontentloaded' });
			await resetUserTheme(page);
		} catch {
			/* best effort */
		}
		fs.mkdirSync(OUT, { recursive: true });
		// Merge with any previous manifest so partial (--grep) runs never
		// shrink the recorded inventory — same-path entries are replaced.
		const manifestPath = path.join(OUT, '_manifest.json');
		const merged = new Map();
		if (fs.existsSync(manifestPath)) {
			try {
				for (const f of JSON.parse(fs.readFileSync(manifestPath, 'utf8')).files || []) {
					merged.set(f.path, f);
				}
			} catch { /* rewrite on corrupt manifest */ }
		}
		for (const f of MANIFEST) {
			merged.set(f.path, f);
		}
		fs.writeFileSync(manifestPath, JSON.stringify({
			app: 'projectcheck',
			lane: 'ds_chrome',
			generated_at: new Date().toISOString(),
			files: [...merged.values()],
		}, null, 2));
		await ctx.close();
	});

	test('light theme — all page surfaces', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 });
		await gotoApp(page, `${APP}/dashboard`);
		await waitApp(page);
		await setUserTheme(page, 'light');
		for (const [id, url] of ROUTES) {
			await captureRoute(page, id, url);
		}
	});

	for (const theme of Object.keys(THEME_SUBSET)) {
		test(`${theme} theme — representative surfaces`, async ({ page }) => {
			await page.setViewportSize({ width: 1440, height: 900 });
			await gotoApp(page, `${APP}/dashboard`);
			await waitApp(page);
			await setUserTheme(page, theme);
			// prove the theme actually landed on the server-rendered shell
			await page.waitForSelector(`body[data-theme-${theme}]`, { timeout: 15000 });
			const urls = Object.fromEntries(ROUTES);
			for (const id of THEME_SUBSET[theme]) {
				await captureRoute(page, id, urls[id], THEME_SUFFIX[theme]);
			}
		});
	}

	test('dialogs + states', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 });
		await gotoApp(page, `${APP}/dashboard`);
		await waitApp(page);
		await setUserTheme(page, 'light');

		// project detail modals
		await gotoApp(page, `${APP}/projects/${PROJECT_ID}`);
		await waitApp(page);
		const modals = [
			['status-modal', '#open-status-modal-btn', '#statusChangeModal', '#cancel-status-change'],
			['settle-modal', '.pc-project-settle-btn[data-settle-action="invoice_open"]', '#projectSettleModal', '#cancel-project-settle'],
			['add-member-modal', '#add-team-member-btn', '#addTeamMemberModal', '#cancel-add-member'],
		];
		for (const [name, open, modal, cancel] of modals) {
			const btn = page.locator(open).first();
			expect(await btn.isVisible({ timeout: 8000 }).catch(() => false), `${name}: trigger ${open} not visible`).toBe(true);
			await btn.scrollIntoViewIfNeeded();
			await btn.click();
			await page.waitForSelector(`${modal}[aria-hidden="false"]`, { timeout: 8000 });
			await shot(page, `pc-dialog-${name}.png`, { fullPage: false });
			await page.locator(cancel).click();
			await page
				.waitForSelector(`${modal}[aria-hidden="true"], ${modal}[style*="none"]`, { timeout: 5000 })
				.catch(() => {});
		}

		// deletion modal (open + cancel — never confirm)
		await gotoApp(page, `${APP}/projects`);
		await waitApp(page);
		const del = page.locator('.delete-project-btn').first();
		expect(await del.isVisible({ timeout: 8000 }).catch(() => false), 'no .delete-project-btn on projects list').toBe(true);
		await del.scrollIntoViewIfNeeded();
		await del.click();
		await page.waitForSelector('#projectcheck-deletion-modal', { timeout: 10000 });
		await page.waitForTimeout(400);
		await shot(page, 'pc-dialog-deletion-modal.png', { fullPage: false });
		const cancelBtn = page.locator('#projectcheck-deletion-modal .projectcheck-deletion-modal__btn--cancel');
		if (await cancelBtn.isVisible().catch(() => false)) {
			await cancelBtn.click();
		}

		// field errors — customer create uses the app-rendered aria-invalid +
		// inline .error-message pattern
		await gotoApp(page, `${APP}/customers/create`);
		await waitApp(page);
		const csubmit = page.locator('button[type="submit"].primary, form button[type="submit"]').first();
		expect(await csubmit.isVisible({ timeout: 5000 }).catch(() => false), 'customer create: no submit').toBe(true);
		await csubmit.scrollIntoViewIfNeeded();
		await csubmit.click();
		await page.waitForTimeout(1200);
		const cerrs = await page.evaluate(() => ({
			ariaInvalid: document.querySelectorAll('[aria-invalid="true"]').length,
			visibleErr: Array.from(document.querySelectorAll('.error-message, [class*="field-error"]'))
				.filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; }).length,
		}));
		expect(cerrs.ariaInvalid, 'customer create: no aria-invalid after empty submit').toBeGreaterThan(0);
		expect(cerrs.visibleErr, 'customer create: no visible inline error after empty submit').toBeGreaterThan(0);
		await page.locator('[aria-invalid="true"]').first().scrollIntoViewIfNeeded().catch(() => {});
		await shot(page, 'pc-state-field-errors-customer.png', { fullPage: false });

		// field errors — project create uses native constraint validation by
		// design (required/min + setCustomValidity); submit must be blocked and
		// at least one control must signal invalidity.
		await gotoApp(page, `${APP}/projects/create`);
		await waitApp(page);
		const psubmit = page.locator('button[type="submit"].primary, button[type="submit"].btn-primary, button[type="submit"]').first();
		expect(await psubmit.isVisible({ timeout: 5000 }).catch(() => false), 'project create: no submit').toBe(true);
		await psubmit.scrollIntoViewIfNeeded();
		await psubmit.click();
		await page.waitForTimeout(1200);
		const perrs = await page.evaluate(() => {
			const invalidNative = Array.from(document.querySelectorAll('input,select,textarea'))
				.filter((el) => typeof el.checkValidity === 'function' && !el.checkValidity());
			return {
				ariaInvalid: document.querySelectorAll('[aria-invalid="true"]').length,
				errEls: document.querySelectorAll('.pc-field-error, .projectcheck-field-error, .field-error, .error-message, [class*="field-error"]').length,
				nativeInvalid: invalidNative.length,
				stillOnCreate: /\/projects\/create/.test(location.pathname),
			};
		});
		expect(perrs.stillOnCreate, 'project create: empty submit navigated away').toBe(true);
		expect(
			perrs.ariaInvalid + perrs.errEls + perrs.nativeInvalid,
			'project create: empty submit blocked but no invalid signal',
		).toBeGreaterThan(0);
		await page.locator('[aria-invalid="true"], input:invalid').first().scrollIntoViewIfNeeded().catch(() => {});
		await shot(page, 'pc-state-field-errors-project.png', { fullPage: false });

		// empty state — impossible search query on the page route
		await gotoApp(page, `${APP}/projects?search=zzzz-no-such-project-9999`);
		await waitApp(page);
		const empty = page.locator('.pc-empty-state, .empty-state, #pc-empty').first();
		expect(await empty.count(), 'empty-state surface missing for no-results search').toBeGreaterThan(0);
		await empty.scrollIntoViewIfNeeded().catch(() => {});
		await shot(page, 'pc-state-empty-search.png');

		// below-fold license tail — at a shorter viewport the tail must start
		// below the fold and reach the frame after scroll (non-vacuous proof:
		// a hidden element returns a zeroed rect and would fake `delta <= 1`).
		await page.setViewportSize({ width: 1440, height: 760 });
		await gotoApp(page, `${APP}/settings/license`);
		await waitApp(page);
		const pickTail = () => page.evaluate(() => {
			const sels = [
				'#pc-license-form #pc-license-save',
				'#pc-license-form',
				'#pc-license-key',
				'.pc-license-cta__link',
				'#pc-license-meter-text',
			];
			for (const sel of sels) {
				const el = document.querySelector(sel);
				if (!el) continue;
				const r = el.getBoundingClientRect();
				if (r.height > 0 && r.width > 0) {
					return { sel, top: Math.round(r.top), bottom: Math.round(r.bottom), vh: window.innerHeight };
				}
			}
			return null;
		});
		const tailBefore = await pickTail();
		expect(tailBefore, 'no visible license tail target on /settings/license').toBeTruthy();
		// eslint-disable-next-line no-console
		console.log(`license-tail pre-scroll ${tailBefore.sel} top=${tailBefore.top} bottom=${tailBefore.bottom} vh=${tailBefore.vh}`);
		expect(
			tailBefore.bottom,
			`license tail not below the fold at 760px viewport: ${tailBefore.sel} bottom=${tailBefore.bottom}`,
		).toBeGreaterThan(tailBefore.vh);
		const tail = page.locator(tailBefore.sel).first();
		await tail.scrollIntoViewIfNeeded();
		await page.waitForTimeout(300);
		const tailAfter = await pickTail();
		expect(tailAfter.bottom, 'license tail rect zeroed after scroll').toBeGreaterThan(0);
		expect(tailAfter.bottom, 'license tail did not move after scroll').toBeLessThan(tailBefore.bottom);
		expect(
			tailAfter.bottom - tailAfter.vh,
			`license tail still clipped after scroll: bottomDelta=${tailAfter.bottom - tailAfter.vh}`,
		).toBeLessThanOrEqual(1);
		await shot(page, 'pc-state-license-tail.png', { fullPage: false });
		await page.setViewportSize({ width: 1440, height: 900 });

		// toast dedup — identical notifications must not stack
		await gotoApp(page, `${APP}/dashboard`);
		await waitApp(page);
		const dedup = await page.evaluate(async () => {
			// Count toast roots only — inner .toast-message/-icon/-close children
			// and the .toast-container host itself must not inflate the count.
			const count = () => document.querySelectorAll(
				'#toast-container > .toast, .toast-container > .toast, .oc-toast',
			).length;
			const host = window.ProjectCheckMessaging || window.ProjectControlMessaging || null;
			// mirror api.js notifyUser: the container is lazy-initialized
			if (host && !host.toastContainer && typeof host.setupToastContainer === 'function') {
				host.setupToastContainer();
			}
			const notify = host && typeof host.error === 'function'
				? (m) => host.error(m)
				: (typeof window.ProjectCheckNotify?.error === 'function' ? window.ProjectCheckNotify.error : null);
			if (typeof notify === 'function') {
				notify('ATLAS-CRAFT-DEDUP');
				notify('ATLAS-CRAFT-DEDUP');
				await new Promise((r) => setTimeout(r, 400));
				const after2 = count();
				notify('ATLAS-CRAFT-DEDUP');
				await new Promise((r) => setTimeout(r, 300));
				return { via: 'app-messaging', after2, after3: count() };
			}
			return { via: 'none', after2: 0, after3: 0 };
		});
		expect(dedup.via, 'toast dedup probe: no app notification API').not.toBe('none');
		expect(dedup.after3, `identical toasts stacked: after3=${dedup.after3}`).toBeLessThanOrEqual(1);
		await shot(page, 'pc-state-toast-dedup.png', { fullPage: false });

		// uniform-404 / deny collapse — documented residual: entity misses
		// render the generic NC error page (existence-oracle hardening),
		// API answers a uniform deny. Captured as honest state evidence.
		const resp = await page.goto(`${APP}/projects/999999`, { waitUntil: 'domcontentloaded' });
		await page.waitForTimeout(500);
		const api = await page.evaluate(async () => {
			const token = (typeof window.OC !== 'undefined' && window.OC.requestToken)
				|| document.querySelector('head[data-requesttoken]')?.getAttribute('data-requesttoken') || '';
			const r = await fetch('/index.php/apps/projectcheck/api/projects/999999', {
				headers: { requesttoken: token, Accept: 'application/json' },
				credentials: 'same-origin',
			});
			return { status: r.status };
		});
		expect([403, 404], `api deny status ${api.status}`).toContain(api.status);
		await shot(page, 'pc-state-project-404.png');
		void resp;
	});

	test('mobile viewport — nav + content fit at 390px', async ({ page }) => {
		await page.setViewportSize({ width: 390, height: 844 });
		await gotoApp(page, `${APP}/dashboard`);
		await waitApp(page);
		await setUserTheme(page, 'light');
		for (const [id, url] of [
			['dashboard', `${APP}/dashboard`],
			['projects', `${APP}/projects`],
			['time-entries', `${APP}/time-entries`],
		]) {
			await gotoApp(page, url);
			await waitApp(page);
			const ox = await page.evaluate(() => {
				const doc = document.documentElement;
				return doc.scrollWidth - doc.clientWidth;
			});
			expect(ox, `mobile ${id}: horizontal overflow ${ox}px`).toBeLessThanOrEqual(1);
			await shot(page, `pc-mobile-${id}.png`);
		}
	});
});
