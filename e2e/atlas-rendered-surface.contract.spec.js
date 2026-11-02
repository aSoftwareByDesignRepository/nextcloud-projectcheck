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
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
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

	// LEARNED-REGRESSIONS probe (pc-intake-20261102T193907Z —
	// workflow_step_text_clip sibling sweep from mobilitycheck,
	// hardened by pc-critic-r12-workflow-stepper-sub720-live-clip): the
	// .pc-create-workflow__* stepper block floors its columns via grid
	// minmax(0,1fr) at every width, so neither a step nor a single
	// unbreakable compound ever paints past the card edge. The markup
	// is dormant in templates (pinned absent by
	// tests/js/project-form.test.cjs), so the leg mounts the shipped DOM
	// shape onto a real styled surface. Canonical oracle: each step's
	// rect must stay inside its list/card edge — a step-relative text
	// check alone is tautologically clean when the step overflows
	// together with its text.
	test('workflow stepper words never clip across the step card edge', async ({ page }) => {
		const probe = () => {
			const bad = [];
			document.querySelectorAll('.pc-create-workflow__step').forEach((step) => {
				const sRect = step.getBoundingClientRect();
				const list = step.closest('.pc-create-workflow__list');
				const card = step.closest('.pc-create-workflow');
				const edgeRight = Math.min(
					list ? list.getBoundingClientRect().right : Infinity,
					card ? card.getBoundingClientRect().right : Infinity,
				);
				if (sRect.right > edgeRight + 0.5) {
					bad.push(`card-edge: step paints to right=${sRect.right.toFixed(1)} past list/card edge=${edgeRight.toFixed(1)}`);
				}
				step.querySelectorAll(':scope > :not(.pc-create-workflow__num)').forEach((el) => {
					const label = (el.textContent || '').trim().slice(0, 48);
					if (el.scrollWidth > el.clientWidth + 0.5) {
						bad.push(`overflow: "${label}" scrollW=${el.scrollWidth} > clientW=${el.clientWidth}`);
					}
					const r = el.getBoundingClientRect();
					if (r.width > 0 && r.left + el.scrollWidth > sRect.right + 0.5) {
						bad.push(`step-edge: "${label}" paints to ${(r.left + el.scrollWidth).toFixed(1)} past step.right=${sRect.right.toFixed(1)}`);
					}
				});
			});
			return bad;
		};

		await gotoApp(page, SURFACES['project-create']);
		const mounted = await page.evaluate(() => {
			const main = document.querySelector('#pc-main-content, #projectcheck-org-main, main.pc-main');
			if (!main) return false;
			const nav = document.createElement('nav');
			nav.className = 'pc-create-workflow pc-section';
			nav.id = 'pc-e2e-create-workflow';
			const title = document.createElement('h2');
			title.className = 'pc-create-workflow__title';
			title.textContent = 'Setup steps';
			const list = document.createElement('ol');
			list.className = 'pc-create-workflow__list';
			[
				'Enter name, customer, and description',
				'Set schedule and status',
				'Choose how hours are priced',
				'After saving, add your team so people can log time',
			].forEach((text, i) => {
				const li = document.createElement('li');
				li.className = 'pc-create-workflow__step';
				const num = document.createElement('span');
				num.className = 'pc-create-workflow__num';
				num.setAttribute('aria-hidden', 'true');
				num.textContent = String(i + 1);
				const body = document.createElement('span');
				body.textContent = text;
				li.appendChild(num);
				li.appendChild(body);
				list.appendChild(li);
			});
			nav.appendChild(title);
			nav.appendChild(list);
			main.prepend(nav);
			return true;
		});
		expect(mounted, 'app main surface missing — cannot mount stepper').toBe(true);

		const flow = page.locator('#pc-e2e-create-workflow');
		await expect(flow).toBeVisible();
		const outDir = path.resolve(
			__dirname,
			'../../../../.cursor/atlas-farm-v3/artifacts/projectcheck',
		);
		fs.mkdirSync(outDir, { recursive: true });

		// Two-column grid band at 1280/768 (>=720px) — the tightest the
		// columns get on desktop widths.
		for (const width of [1280, 768]) {
			await page.setViewportSize({ width, height: 900 });
			const violations = await page.evaluate(probe);
			expect(violations, `create-workflow stepper @${width}px: step text overflow`).toEqual([]);
		}

		// Synthesized long-compound step — locale-independent stress. The
		// token must be wider than the ~450px floored column to reproduce
		// the clip class; normal words and short compounds always fit.
		const synth = await page.evaluate((probeSrc) => {
			const list = document.querySelector('.pc-create-workflow__list');
			if (!list) return { bad: ['no .pc-create-workflow__list mounted'] };
			const li = document.createElement('li');
			li.className = 'pc-create-workflow__step';
			const num = document.createElement('span');
			num.className = 'pc-create-workflow__num';
			num.setAttribute('aria-hidden', 'true');
			num.textContent = '9';
			const body = document.createElement('span');
			body.textContent = 'Zeiterfassungsfreigabeworkflowkonfigurationsverwaltungseinstellungenübersichtsseite';
			li.appendChild(num);
			li.appendChild(body);
			list.appendChild(li);
			const bad = new Function(`return (${probeSrc})()`)();
			return { bad };
		}, probe.toString());
		for (const width of [1280, 768]) {
			await page.setViewportSize({ width, height: 900 });
			const violations = await page.evaluate(probe);
			expect(violations, `synthesized long-compound step @${width}px`).toEqual([]);
		}
		expect(synth.bad, 'synthesized long-compound step @1280px').toEqual([]);

		await flow.screenshot({ path: path.join(outDir, 'pc-workflow-1280px.png') });
		await page.setViewportSize({ width: 375, height: 900 });
		const singleCol = await page.evaluate(probe);
		expect(singleCol, 'create-workflow stepper @375px: step text overflow').toEqual([]);
		await flow.screenshot({ path: path.join(outDir, 'pc-workflow-375px.png') });
	});
});
