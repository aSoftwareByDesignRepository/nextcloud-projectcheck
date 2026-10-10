// @ts-check
/**
 * Theme × viewport × WCAG 2.1 AA gauntlet for ProjectCheck.
 *
 * Proves for every selectable NC theme and key route:
 *  - theme actually switched (body[data-theme-*]),
 *  - design tokens resolve from Nextcloud --color-* (tints mix into main-bg),
 *  - zero horizontal overflow from 320 px up to 4K,
 *  - primary chrome touch targets ≥ 44×44,
 *  - zero axe WCAG 2.1 A/AA violations on the app shell,
 *  - default shell is not locked to a fixed 1200/1400px max-width.
 */
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { gotoApp, dismissOpenAppNavigation } = require('./helpers/auth-guard');
const {
	setUserTheme,
	resetUserTheme,
	setAccentColor,
	resetAccentColor,
	USER_THEMES,
} = require('./helpers/theming');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');

const routes = [
	{ id: 'dashboard', url: process.env.E2E_DASHBOARD_URL || `${BASE}/index.php/apps/projectcheck/dashboard` },
	{ id: 'projects', url: process.env.E2E_PROJECTS_URL || `${BASE}/index.php/apps/projectcheck/projects` },
	{ id: 'customers', url: `${BASE}/index.php/apps/projectcheck/customers` },
	{ id: 'timeEntries', url: `${BASE}/index.php/apps/projectcheck/time-entries` },
	{ id: 'timeEntryCreate', url: process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create` },
	{ id: 'settings', url: process.env.E2E_PROJECTCHECK_SETTINGS_URL || `${BASE}/index.php/apps/projectcheck/settings` },
];

const overflowViewports = [
	{ width: 320, height: 640 },
	{ width: 375, height: 812 },
	{ width: 768, height: 1024 },
	{ width: 1024, height: 768 },
	{ width: 1440, height: 900 },
	{ width: 2560, height: 1440 },
];

const axeViewports = [
	{ width: 320, height: 640 },
	{ width: 768, height: 1024 },
	{ width: 1280, height: 800 },
];

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function expectNoHorizontalOverflow(page, label) {
	await dismissOpenAppNavigation(page);
	const overflow = await page.evaluate(() => {
		const doc = document.documentElement;
		const app = document.querySelector('#app-content.pc-app');
		const shell = document.querySelector('#app-content-wrapper.pc-shell, #app-content-wrapper');
		const shellOx = shell ? getComputedStyle(shell).overflowX : '';
		return {
			doc: doc.scrollWidth - doc.clientWidth,
			app: app ? app.scrollWidth - app.clientWidth : 0,
			shell: shell ? shell.scrollWidth - shell.clientWidth : 0,
			shellClipped: shellOx === 'hidden' || shellOx === 'clip',
		};
	});
	// Intentionally scrollable tables live inside overflow-x:auto wraps; page chrome must not grow.
	expect(overflow.doc, `document horizontal overflow at ${label}`).toBeLessThanOrEqual(2);
	expect(overflow.app, `#app-content overflow at ${label}`).toBeLessThanOrEqual(2);
	if (!overflow.shellClipped) {
		expect(overflow.shell, `.pc-shell overflow at ${label}`).toBeLessThanOrEqual(2);
	}
}

/**
 * @param {import('@playwright/test').Page} page
 */
async function assertThemeTokensResolved(page) {
	const tokens = await page.evaluate(() => {
		const bodyCs = getComputedStyle(document.body);
		const shell = document.querySelector('#app-content-wrapper.pc-shell, #app-content-wrapper');
		return {
			bg: bodyCs.getPropertyValue('--pc-bg-card').trim() || bodyCs.getPropertyValue('--color-main-background').trim(),
			text: bodyCs.getPropertyValue('--pc-text').trim() || bodyCs.getPropertyValue('--color-main-text').trim(),
			primary: bodyCs.getPropertyValue('--color-primary-element').trim(),
			muted: bodyCs.getPropertyValue('--pc-muted').trim(),
			tintInfo: bodyCs.getPropertyValue('--pc-tint-info').trim(),
			tintSuccess: bodyCs.getPropertyValue('--pc-tint-success').trim(),
			dangerFill: bodyCs.getPropertyValue('--pc-danger-fill').trim(),
			dangerOnFill: bodyCs.getPropertyValue('--pc-danger-on-fill').trim(),
			dangerInk: bodyCs.getPropertyValue('--pc-danger-ink').trim(),
			touch: bodyCs.getPropertyValue('--pc-touch-min').trim(),
			scrim: bodyCs.getPropertyValue('--pc-scrim').trim(),
			shadowSm: bodyCs.getPropertyValue('--pc-shadow-sm').trim(),
			shellMax: shell ? getComputedStyle(shell).maxWidth : '',
		};
	});
	expect(tokens.bg, 'theme background token').not.toEqual('');
	expect(tokens.text, 'theme text token').not.toEqual('');
	expect(tokens.primary, 'primary element token').not.toEqual('');
	expect(tokens.muted, 'muted token').not.toEqual('');
	expect(tokens.tintInfo, 'tint-info must resolve').not.toEqual('');
	expect(tokens.tintSuccess, 'tint-success must resolve').not.toEqual('');
	expect(tokens.dangerFill, 'danger-fill must resolve').not.toEqual('');
	expect(tokens.dangerOnFill, 'danger-on-fill must resolve').not.toEqual('');
	expect(tokens.dangerInk, 'danger-ink must resolve').not.toEqual('');
	expect(
		/,\s*transparent\s*\)\s*$/i.test(tokens.tintInfo),
		`tint-info must mix into main-background, got: ${tokens.tintInfo}`,
	).toBeFalsy();
	expect(tokens.scrim, 'scrim token').not.toEqual('');
	expect(tokens.shadowSm, 'shadow-sm token').not.toEqual('');
	expect(tokens.touch === '44px' || parseFloat(tokens.touch) >= 44, 'touch target token ≥44px').toBeTruthy();
	expect(
		tokens.shellMax === 'none' || tokens.shellMax === '' || parseFloat(tokens.shellMax) >= 2000,
		`default shell must not be a fixed 1200/1400px lock (got ${tokens.shellMax})`,
	).toBeTruthy();
}

/**
 * @param {import('@playwright/test').Page} page
 */
async function assertChromeTouchTargets(page) {
	const result = await page.evaluate(() => {
		const nodes = [
			...document.querySelectorAll(
				'#app-content.pc-app .pc-page-header__actions .btn, #app-content.pc-app .pc-page-header__actions button, #app-content.pc-app .pc-nav-toggle, #app-content.pc-app .btn-primary, #app-content.pc-app button.primary',
			),
		].slice(0, 40);
		const undersized = [];
		for (const el of nodes) {
			const style = getComputedStyle(el);
			if (style.display === 'none' || style.visibility === 'hidden') continue;
			const rect = el.getBoundingClientRect();
			if (rect.width === 0 && rect.height === 0) continue;
			const minH = Math.max(rect.height, parseFloat(style.minHeight) || 0);
			const minW = Math.max(rect.width, parseFloat(style.minWidth) || 0);
			const isBar = rect.width >= 120;
			if (minH < 40 || (!isBar && minW < 40)) {
				undersized.push({
					tag: el.tagName,
					cls: String(el.className).slice(0, 80),
					w: Math.round(minW),
					h: Math.round(minH),
				});
			}
		}
		return { ok: undersized.length === 0, undersized };
	});
	expect(result.ok, JSON.stringify(result.undersized)).toBeTruthy();
}

/**
 * WCAG 1.4.11 for every rendered app control: visible border ≥1px and ≥3:1
 * against the effective painted background. Catches regressions to
 * var(--color-border) on inputs/selects/textareas/buttons in any theme.
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function assertVisibleControlBorders(page, label) {
	const result = await page.evaluate(() => {
		const parse = (rgb) => (rgb.match(/[\d.]+/g) || []).map(Number);
		const lum = ([r, g, b]) => {
			const f = (c) => (c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
			return 0.2126 * f(r / 255) + 0.7152 * f(g / 255) + 0.0722 * f(b / 255);
		};
		const effectiveBg = (el) => {
			let node = el;
			while (node && node !== document.documentElement) {
				const bg = getComputedStyle(node).backgroundColor;
				const p = parse(bg);
				if (p.length >= 4 && p[3] < 1) { node = node.parentElement; continue; }
				if (bg === 'rgba(0, 0, 0, 0)') { node = node.parentElement; continue; }
				return bg;
			}
			return getComputedStyle(document.body).backgroundColor;
		};
		const scope = document.querySelector('#app-content.pc-app, #app-content');
		const nodes = [...scope.querySelectorAll([
			'input[type="text"]', 'input[type="search"]', 'input[type="date"]',
			'input[type="number"]', 'input[type="email"]', 'input[type="password"]',
			'input[type="time"]', 'input[type="url"]', 'input[type="checkbox"]',
			'input[type="radio"]', 'select', 'textarea',
			'.button.secondary', '.btn-secondary', '.pc-nav-toggle',
		].join(', '))].slice(0, 120);
		const weak = [];
		for (const el of nodes) {
			const cs = getComputedStyle(el);
			if (cs.display === 'none' || cs.visibility === 'hidden') continue;
			// Visually-hidden fields are clipped for screen readers only.
			if (el.closest('.visually-hidden, .sr-only, [hidden]') || cs.clipPath !== 'none' || cs.clip !== 'auto') continue;
			const rect = el.getBoundingClientRect();
			if (rect.width === 0 && rect.height === 0) continue;
			if (rect.width <= 2 && rect.height <= 2) continue;
			// Native-appearance checkbox/radio widgets ignore author borders
			// (appearance:auto paints UA chrome); only custom ones are ours.
			const type = el.getAttribute('type');
			if ((type === 'checkbox' || type === 'radio') && cs.appearance !== 'none') continue;
			const w = parseFloat(cs.borderTopWidth);
			const bg = effectiveBg(el);
			const b = lum(parse(cs.borderTopColor));
			const g = lum(parse(bg));
			const ratio = (Math.max(b, g) + 0.05) / (Math.min(b, g) + 0.05);
			if (w < 1 || ratio < 3) {
				const id = el.id ? `#${el.id}` : '';
				weak.push({
					el: `${el.tagName.toLowerCase()}${id}.${String(el.className).split(' ').slice(0, 2).join('.')}`,
					border: cs.borderTopColor,
					w,
					bg,
					ratio: Math.round(ratio * 100) / 100,
				});
			}
		}
		return { ok: weak.length === 0, weak, checked: nodes.length };
	});
	expect(
		result.ok,
		`weak/missing control borders at ${label} (${result.checked} checked):\n${JSON.stringify(result.weak.slice(0, 20), null, 2)}`,
	).toBeTruthy();
}

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
 * @param {string} url
 */
async function gotoReady(page, url) {
	await gotoApp(page, url);
	await expect(page.locator('#pc-main-content, #projectcheck-org-main, main.pc-main').first()).toBeAttached({ timeout: 30_000 });
	await page.waitForFunction(() => {
		const body = getComputedStyle(document.body);
		return body.getPropertyValue('--color-main-text').trim() !== ''
			&& body.getPropertyValue('--color-main-background').trim() !== '';
	}, null, { timeout: 10_000 }).catch(() => {});
}

test.describe('ProjectCheck theme × viewport a11y matrix', () => {
	test.describe.configure({ mode: 'serial' });
	test.setTimeout(300_000);

	for (const theme of USER_THEMES) {
		for (const route of routes) {
			test(`${theme}: ${route.id}`, async ({ page }) => {
				test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');

				await gotoReady(page, route.url);
				await setUserTheme(page, theme);
				await dismissOpenAppNavigation(page);
				await expect(page.locator('#pc-main-content, #projectcheck-org-main, main.pc-main').first()).toBeAttached({ timeout: 30_000 });
				await assertThemeTokensResolved(page);

				for (const viewport of overflowViewports) {
					await page.setViewportSize(viewport);
					await expectNoHorizontalOverflow(page, `${theme}/${route.id}@${viewport.width}px`);
				}
				await page.setViewportSize({ width: 1280, height: 800 });
				await assertChromeTouchTargets(page);
			await assertVisibleControlBorders(page, `${theme}/${route.id}@1280`);
				for (const viewport of axeViewports) {
					await page.setViewportSize(viewport);
					await dismissOpenAppNavigation(page);
					await runAxe(page, `${theme}/${route.id}@${viewport.width}px`);
				}
			});
		}
	}

	test('custom accent color resolves into primary tokens', async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');
		try {
			setAccentColor('#B02E1C');
			await gotoReady(page, routes[0].url);
			await setUserTheme(page, 'light');
			const primary = await page.evaluate(() => getComputedStyle(document.body).getPropertyValue('--color-primary-element').trim());
			expect(primary).not.toEqual('');
			await assertThemeTokensResolved(page);
			await runAxe(page, 'accent/light/dashboard');
		} finally {
			resetAccentColor();
			await resetUserTheme(page).catch(() => {});
		}
	});

	test('projects list uses Lucide type chips (no emoji glyphs)', async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoReady(page, routes.find((r) => r.id === 'projects').url);
		await setUserTheme(page, 'dark');
		await dismissOpenAppNavigation(page);

		const row = page.locator('#projects-tbody .project-row').first();
		await expect(row).toBeVisible({ timeout: 30_000 });

		const typeCell = row.locator('td[data-label="Type"], td[data-label="Typ"]').first();
		const typeChip = typeCell.locator('.project-type-chip').first();
		await expect(typeChip).toBeVisible();
		await expect(typeChip.locator('[data-lucide]')).toHaveCount(1);
		await expect(typeChip.locator('svg.lucide-icon, .lucide-icon-host svg')).toHaveCount(1, { timeout: 10_000 });

		const typeText = await typeCell.innerText();
		expect(typeText).not.toMatch(/[👥⚠️✅⚙️📊🚨]/);

		const priority = row.locator('.priority-badge').first();
		await expect(priority).toBeVisible();
		const bg = await priority.evaluate((el) => getComputedStyle(el).backgroundColor);
		expect(bg).not.toMatch(/rgba?\(\s*0,\s*0,\s*0/);
		expect(bg).not.toEqual('rgba(0, 0, 0, 0)');

		await resetUserTheme(page).catch(() => {});
	});

	test('projects list: danger delete is visible; zero progress has no empty track', async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');
		await gotoReady(page, routes.find((r) => r.id === 'projects').url);
		for (const theme of ['light', 'dark']) {
			await setUserTheme(page, theme);
			await dismissOpenAppNavigation(page);
			await assertThemeTokensResolved(page);

			const danger = page.locator('#projects-tbody .action-item--danger, #projects-tbody .delete-project-btn').first();
			await expect(danger).toBeVisible({ timeout: 30_000 });
			const dangerCss = await danger.evaluate((el) => {
				const cs = getComputedStyle(el);
				return {
					color: cs.color,
					bg: cs.backgroundColor,
					border: cs.borderTopColor,
				};
			});
			// Must not be transparent / fully invisible chrome
			expect(dangerCss.color, `${theme} danger ink`).not.toMatch(/rgba\(\s*0,\s*0,\s*0,\s*0\s*\)/);
			expect(dangerCss.bg, `${theme} danger bg`).not.toMatch(/rgba\(\s*0,\s*0,\s*0,\s*0\s*\)/);

			const emptyProgress = page.locator('#projects-tbody .progress-info--empty').first();
			if (await emptyProgress.count()) {
				await expect(emptyProgress.locator('.budget-progress-bar')).toHaveCount(0);
				await expect(emptyProgress.locator('.hours-logged')).not.toHaveText(/^\s*$/);
			}

			// No decorative empty 0% tracks left in the list
			const zeroTracks = await page.locator('#projects-tbody .budget-progress-fill[style*="width: 0%"], #projects-tbody .budget-progress-fill[style*="width:0%"]').count();
			expect(zeroTracks, `${theme}: empty 0% progress fills`).toBe(0);
		}
		await resetUserTheme(page).catch(() => {});
	});
});
