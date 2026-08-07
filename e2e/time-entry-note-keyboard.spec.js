/**
 * Time-entry form: soft-keyboard / note field visibility journey.
 * Proves description textarea stays in the visual viewport band when focused
 * (simulated keyboard via visualViewport mock).
 */
// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const CREATE_URL =
	process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create`;

test.describe('Time entry note vs soft keyboard', () => {
	test.use({ viewport: { width: 390, height: 720 } });

	test('description note stays above a simulated on-screen keyboard', async ({ page }) => {
		await gotoApp(page, CREATE_URL);

		const note = page.locator('#description');
		await expect(note).toBeVisible({ timeout: 20000 });

		await page.evaluate(() => {
			const height = 720;
			const keyboard = 320;
			const vv = {
				offsetTop: 0,
				height: height - keyboard,
				width: 390,
				scale: 1,
				offsetLeft: 0,
				pageTop: 0,
				pageLeft: 0,
				addEventListener() {},
				removeEventListener() {},
				dispatchEvent() {
					return true;
				},
			};
			Object.defineProperty(window, 'visualViewport', {
				configurable: true,
				get: () => vv,
			});
			window.dispatchEvent(new Event('resize'));
		});

		await note.focus();
		await page.evaluate(() => {
			const api = window.PCKeepFocusedVisible;
			const el = document.getElementById('description');
			if (api && el) {
				api.ensureFocusedVisible(el, window);
			}
		});
		await page.waitForTimeout(450);

		const geometry = await note.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			const vv = window.visualViewport;
			const bottom = vv ? vv.offsetTop + vv.height : window.innerHeight;
			return {
				top: rect.top,
				bottom: rect.bottom,
				visibleBottom: bottom,
				covered: rect.bottom > bottom - 12,
				padHost: document.querySelector('[data-pc-ime-pad]')?.className || null,
			};
		});

		expect(geometry.covered, `note bottom ${geometry.bottom} vs visible ${geometry.visibleBottom}`).toBe(
			false,
		);
		expect(geometry.padHost, 'IME pad host should be the time-entry form / main').toBeTruthy();
	});

	test('tabbing away clears IME pad without leaving permanent bottom dead space', async ({ page }) => {
		await gotoApp(page, CREATE_URL);
		const note = page.locator('#description');
		await expect(note).toBeVisible({ timeout: 20000 });

		await page.evaluate(() => {
			Object.defineProperty(window, 'visualViewport', {
				configurable: true,
				get: () => ({
					offsetTop: 0,
					height: 400,
					width: 390,
					scale: 1,
					offsetLeft: 0,
					pageTop: 0,
					pageLeft: 0,
					addEventListener() {},
					removeEventListener() {},
					dispatchEvent() {
						return true;
					},
				}),
			});
		});

		await note.focus();
		await page.evaluate(() => {
			const api = window.PCKeepFocusedVisible;
			if (!api || typeof api.ensureFocusedVisible !== 'function') {
				throw new Error('PCKeepFocusedVisible API missing');
			}
			api.ensureFocusedVisible(document.getElementById('description'), window);
		});
		await expect.poll(async () => page.locator('[data-pc-ime-pad]').count()).toBeGreaterThan(0);

		// Blur the note; focusout handler (80ms) restores padding.
		await note.evaluate((el) => el.blur());
		await expect
			.poll(async () => page.locator('[data-pc-ime-pad]').count(), { timeout: 3000 })
			.toBe(0);
	});

	test('time-entry create form has no serious axe WCAG 2.1 AA violations', async ({ page }) => {
		await gotoApp(page, CREATE_URL);
		await expect(page.locator('#description')).toBeVisible({ timeout: 20000 });
		const results = await new AxeBuilder({ page })
			.withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
			.exclude('#header')
			.exclude('#content-vue')
			.analyze();
		const serious = results.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
		expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
	});
});
