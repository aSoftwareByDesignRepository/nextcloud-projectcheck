// @ts-check
/**
 * ATLAS_RENDERED_SURFACE_CONTRACT — rendered truth, not DOM presence.
 *
 * Asserts list markers, select vertical centring, non-zero icon boxes and
 * non-inherited control centering across the app's main surfaces. Lists that
 * are intentionally marker-less (chip rows, swatch legends, icon feature
 * grids) are allow-listed per selector below — each entry must be a real
 * visual substitute (chips/swatch/icon), never a content list whose bullets
 * a CSS reset silently ate.
 */
const { test } = require('@playwright/test');
const { assertAtlasRenderedSurface } = require('../../_shared/e2e/atlas-rendered-surface-contract');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const APP = `${BASE}/index.php/apps/projectcheck`;
const PROJECT_ID = process.env.E2E_PROJECT_ID || '213';

/**
 * Intentionally marker-less lists (visual substitute replaces bullets):
 *  - .pc-settle-strip__list            horizontally scrollable metric strip (time entries)
 *  - .pc-stl-progress__legend          colored swatch legend (settlement progress)
 *  - .pc-productivity__legend          colored swatch legend (dashboard chart)
 *  - .projectcheck-entity-picker__chips removable user/group chips (entity picker)
 *  - .pc-get-app__features             feature grid with icon wells (get-the-app)
 */
const LIST_ALLOW = [
	'.pc-settle-strip__list',
	'.pc-stl-progress__legend',
	'.pc-productivity__legend',
	'.projectcheck-entity-picker__chips',
	'.pc-get-app__features',
].join(', ');

const SURFACES = {
	dashboard: `${APP}/dashboard`,
	projects: `${APP}/projects`,
	customers: `${APP}/customers`,
	'time-entries': `${APP}/time-entries`,
	settings: `${APP}/settings/access`,
	'project-show': `${APP}/projects/${PROJECT_ID}`,
	'project-create': `${APP}/projects/create`,
	'get-the-app': `${APP}/get-the-app`,
};

test.describe('ATLAS_RENDERED_SURFACE_CONTRACT', () => {
	test.beforeEach(async ({ page }) => {
		test.skip(!process.env.E2E_USER && !process.env.BASE_URL, 'Set BASE_URL and E2E_USER in e2e/.env');
		await page.setViewportSize({ width: 1280, height: 800 });
	});

	for (const [name, url] of Object.entries(SURFACES)) {
		test(`${name}: lists/selects/icons/centering render correctly`, async ({ page }) => {
			await gotoApp(page, url);
			await assertAtlasRenderedSurface(page, {
				content: '#pc-main-content, #projectcheck-org-main, main.pc-main',
				listAllow: LIST_ALLOW,
			});
		});
	}
});
