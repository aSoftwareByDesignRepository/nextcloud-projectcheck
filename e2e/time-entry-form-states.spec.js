// @ts-check
/**
 * Interactive-state audit for the time-entry form (create).
 *
 * The static matrix in theme-responsive-a11y.spec.js only sees the initial
 * render. This spec reveals the surfaces that stay display:none until user
 * interaction — budget-impact <details>, live budget warning, admin-override
 * banner, field error states — then re-runs axe + overflow per NC theme.
 */
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { gotoApp, dismissOpenAppNavigation } = require('./helpers/auth-guard');
const { setUserTheme, resetUserTheme, USER_THEMES } = require('./helpers/theming');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const CREATE_URL = process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create`;

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function runAxe(page, label) {
	await page.locator('.toast, .toastify, #toast-container .toast').evaluateAll((nodes) => {
		nodes.forEach((n) => n.remove());
	}).catch(() => {});
	const results = await new AxeBuilder({ page })
		.include('#content')
		.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
		.exclude('#toast-container')
		.exclude('.toastify')
		.analyze();
	expect(
		results.violations,
		`axe violations at ${label}:\n${JSON.stringify(results.violations, null, 2)}`,
	).toEqual([]);
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function expectNoHorizontalOverflow(page, label) {
	const overflow = await page.evaluate(() => {
		const doc = document.documentElement;
		const app = document.querySelector('#app-content.pc-app');
		return {
			doc: doc.scrollWidth - doc.clientWidth,
			app: app ? app.scrollWidth - app.clientWidth : 0,
		};
	});
	expect(overflow.doc, `document horizontal overflow at ${label}`).toBeLessThanOrEqual(2);
	expect(overflow.app, `#app-content overflow at ${label}`).toBeLessThanOrEqual(2);
}

/**
 * Select a project that reveals #budget-info-section (i.e. has a budget).
 * @param {import('@playwright/test').Page} page
 */
async function selectBudgetedProject(page) {
	const options = page.locator('#project_id option[value]:not([disabled])');
	const count = await options.count();
	// Prefer the known budgeted seed project; fall back to scanning options.
	for (let i = 0; i < count; i++) {
		const text = (await options.nth(i).innerText()) || '';
		if (!/elbstrand|budget/i.test(text)) continue;
		await page.selectOption('#project_id', { index: i + 1 });
		const shown = await page.locator('#budget-info-section')
			.waitFor({ state: 'visible', timeout: 6_000 }).then(() => true).catch(() => false);
		if (shown) return true;
	}
	for (let i = 0; i < count; i++) {
		await page.selectOption('#project_id', { index: i + 1 });
		const shown = await page.locator('#budget-info-section')
			.waitFor({ state: 'visible', timeout: 6_000 }).then(() => true).catch(() => false);
		if (shown) return true;
	}
	return false;
}

test.describe('ProjectCheck time-entry form — interactive states × themes', () => {
	test.describe.configure({ mode: 'serial' });
	test.setTimeout(300_000);

	for (const theme of USER_THEMES) {
		test(`${theme}: budget panel, live warning, error states`, async ({ page }) => {
			test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');

			await page.setViewportSize({ width: 375, height: 812 });
			await gotoApp(page, CREATE_URL);
			await setUserTheme(page, theme);
			await dismissOpenAppNavigation(page);
			await expect(page.locator('#time-entry-form')).toBeAttached({ timeout: 30_000 });
			await page.waitForFunction(
				() => typeof window.jQuery !== 'undefined' || document.readyState === 'complete',
				null, { timeout: 10_000 },
			).catch(() => {});

			const hasProjects = await page.locator('#project_id option[value]:not([disabled])').count();
			test.skip(hasProjects === 0, 'No selectable projects in this instance');

			// 0) Control borders must be visible — WCAG 1.4.11 (≥3:1 vs background).
			// NC core inputs use --color-border-maxcontrast; --color-border is invisible on main-bg.
			const borderCheck = await page.evaluate(() => {
				const parse = (rgb) => (rgb.match(/[\d.]+/g) || []).map(Number);
				const lum = ([r, g, b]) => {
					const f = (c) => (c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
					return 0.2126 * f(r / 255) + 0.7152 * f(g / 255) + 0.0722 * f(b / 255);
				};
				const out = {};
				const targets = ['project_id', 'date', 'hours', 'hourly_rate', 'total_cost', 'description'];
				for (const id of targets) {
					const el = document.getElementById(id);
					if (!el) continue;
					const cs = getComputedStyle(el);
					const b = lum(parse(cs.borderTopColor));
					const bg = lum(parse(cs.backgroundColor));
					const ratio = (Math.max(b, bg) + 0.05) / (Math.min(b, bg) + 0.05);
					out[id] = {
						border: cs.borderTopColor,
						bg: cs.backgroundColor,
						width: cs.borderTopWidth,
						ratio: Math.round(ratio * 100) / 100,
					};
				}
				// Secondary/ghost buttons (Cancel in page header) must show a real frame.
				const cancel = document.querySelector('.pc-page-header__actions .button.secondary');
				if (cancel) {
					const cs = getComputedStyle(cancel);
					const b = lum(parse(cs.borderTopColor));
					const bg = lum(parse(cs.backgroundColor));
					out['cancel-button'] = {
						border: cs.borderTopColor,
						bg: cs.backgroundColor,
						width: cs.borderTopWidth,
						ratio: Math.round(((Math.max(b, bg) + 0.05) / (Math.min(b, bg) + 0.05)) * 100) / 100,
					};
				}
				return out;
			});
			for (const [id, m] of Object.entries(borderCheck)) {
				expect(parseFloat(m.width), `${theme}: #${id} border width`).toBeGreaterThanOrEqual(1);
				expect(m.ratio, `${theme}: #${id} border contrast ${m.border} vs ${m.bg}`).toBeGreaterThanOrEqual(3);
			}

			// 1) Reveal budget-impact <details> + expand it.
			const budgetShown = await selectBudgetedProject(page);
			if (budgetShown) {
				await page.locator('#pc-budget-impact > summary').click();
				await expect(page.locator('#pc-budget-impact')).toHaveJSProperty('open', true);
				await expect(page.locator('.budget-progress-fill')).toBeAttached();
			}

			// 2) Fill date + hours → live budget warning box renders.
			const todayIso = new Date().toISOString().slice(0, 10);
			await page.fill('#date', todayIso);
			await page.fill('#hours', '2');
			await page.locator('#description').click(); // blur hours → validation + budget check
			if (budgetShown) {
				await page.locator('#budget-warning-container .pc-budget-impact')
					.waitFor({ state: 'visible', timeout: 8_000 }).catch(() => {});
			}

			// 3) Force field error states (invalid hours, overlong description).
			await page.fill('#hours', '99');
			await page.locator('#description').evaluate((el) => {
				el.value = 'x'.repeat(1100);
				el.dispatchEvent(new Event('input', { bubbles: true }));
				el.dispatchEvent(new Event('blur', { bubbles: true }));
			});
			await page.locator('#date').click(); // blur hours → error text renders
			await expect(page.locator('#hours-error')).not.toBeEmpty();
			await expect(page.locator('#description-error')).not.toBeEmpty();
			await expect(page.locator('#hours')).toHaveClass(/has-error/);

			// 4) Admin-override banner when an override-only project exists.
			const overrideOption = page.locator('#project_id option[data-admin-override="1"]');
			if (await overrideOption.count()) {
				await page.selectOption('#project_id', { value: await overrideOption.first().getAttribute('value') });
				await expect(page.locator('#pc-admin-override-banner')).toBeVisible();
			}

			await dismissOpenAppNavigation(page);
			await expectNoHorizontalOverflow(page, `${theme}/timeEntryCreate:states@375`);
			await runAxe(page, `${theme}/timeEntryCreate:states@375`);

			await page.setViewportSize({ width: 1280, height: 800 });
			await dismissOpenAppNavigation(page);
			await expectNoHorizontalOverflow(page, `${theme}/timeEntryCreate:states@1280`);
			await runAxe(page, `${theme}/timeEntryCreate:states@1280`);

			await resetUserTheme(page).catch(() => {});
		});
	}
});
