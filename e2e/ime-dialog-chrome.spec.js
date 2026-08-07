/**
 * Cross-app integration: sticky chrome + dialog pad host survive a simulated IME.
 * Complements the unit suite with a multi-host journey (page main + body dialog).
 */
// @ts-check
const { test, expect } = require('@playwright/test');
const { gotoApp } = require('./helpers/auth-guard');

const BASE = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
const CREATE_URL =
	process.env.E2E_TIME_ENTRY_CREATE_URL || `${BASE}/index.php/apps/projectcheck/time-entries/create`;

test.describe('IME pad hosts — dialog vs page', () => {
	test.use({ viewport: { width: 390, height: 720 } });

	test('body-mounted dialog pads itself, not the page main behind it', async ({ page }) => {
		await gotoApp(page, CREATE_URL);
		await expect(page.locator('#description')).toBeVisible({ timeout: 20000 });

		const result = await page.evaluate(async () => {
			const api = /** @type {any} */ (window).PCKeepFocusedVisible;
			if (!api || typeof api.resolvePadHost !== 'function') {
				return { ok: false, reason: 'api-missing' };
			}

			const pageMain =
				document.querySelector('.pc-main') ||
				document.querySelector('#pc-main-content') ||
				document.querySelector('#app-content');
			if (!pageMain) {
				return { ok: false, reason: 'no-page-main' };
			}

			const dialog = document.createElement('div');
			dialog.setAttribute('role', 'dialog');
			dialog.className = 'pc-dialog';
			dialog.style.cssText = 'position:fixed;inset:0;display:flex;align-items:flex-end;z-index:10000;';
			const body = document.createElement('div');
			body.className = 'dialog__content';
			body.style.cssText = 'background:#fff;width:100%;padding:16px;';
			const ta = document.createElement('textarea');
			ta.id = 'e2e-dialog-note';
			ta.style.cssText = 'width:100%;min-height:96px;';
			body.appendChild(ta);
			dialog.appendChild(body);
			document.body.appendChild(dialog);

			const host = api.resolvePadHost(document, ta);
			api.ensureKeyboardScrollRoom(document, 280, ta);
			const paddedIsDialog = host === body || host === dialog;
			const pagePad = pageMain.style.paddingBottom || '';
			const hostPad = host && host.style ? host.style.paddingBottom : '';
			api.ensureKeyboardScrollRoom(document, 0, ta);
			dialog.remove();

			return {
				ok: paddedIsDialog && hostPad === '280px' && pagePad !== '280px',
				paddedIsDialog,
				hostPad,
				pagePad,
				hostTag: host && host.className,
			};
		});

		expect(result.ok, JSON.stringify(result)).toBeTruthy();
	});

	test('sticky footer chrome shrinks the usable band for a focused note', async ({ page }) => {
		await gotoApp(page, CREATE_URL);
		const note = page.locator('#description');
		await expect(note).toBeVisible({ timeout: 20000 });

		const inset = await page.evaluate(() => {
			const api = /** @type {any} */ (window).PCKeepFocusedVisible;
			if (!api || typeof api.bottomChromeInset !== 'function') {
				return -1;
			}
			const footer = document.createElement('div');
			footer.className = 'modal-footer';
			footer.setAttribute('data-ime-chrome', 'bottom');
			footer.style.cssText =
				'position:fixed;left:0;right:0;bottom:0;height:72px;background:#333;z-index:9999;';
			document.body.appendChild(footer);
			const win = {
				innerHeight: 720,
				visualViewport: { offsetTop: 0, height: 400 },
			};
			// Use real window visualViewport when present; otherwise inject via evaluate path.
			const real = window;
			const vv = real.visualViewport;
			let measured = 0;
			if (vv) {
				measured = api.bottomChromeInset(document, real, document.querySelector('#description'));
			} else {
				measured = api.bottomChromeInset(document, win, document.querySelector('#description'));
			}
			footer.remove();
			return measured;
		});

		expect(inset).toBeGreaterThan(0);
	});
});
