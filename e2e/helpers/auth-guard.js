/**
 * E2E auth: programmatic login, Nextcloud readiness checks.
 */
async function assertNcReady(page) {
	const { test } = require('@playwright/test');

	const upgradeHeading = page.getByRole('heading', { name: /app update required|app-aktualisierung erforderlich/i });
	if (await upgradeHeading.isVisible({ timeout: 2000 }).catch(() => false)) {
		test.skip(
			true,
			'Nextcloud requires occ upgrade. Run: docker compose exec -u www-data nextcloud php occ upgrade',
		);
	}

	const maintenance = page.getByText(/maintenance mode|wartungsmodus/i);
	if (await maintenance.isVisible({ timeout: 1000 }).catch(() => false)) {
		test.skip(true, 'Nextcloud is in maintenance mode.');
	}
}

async function tryProgrammaticLogin(page) {
	const user = process.env.E2E_USER;
	const pass = process.env.E2E_PASS || process.env.E2E_PASSWORD;
	if (!user || !pass) {
		return false;
	}

	const loginHeading = page.getByRole('heading', { name: /log in to nextcloud|bei nextcloud anmelden/i });
	const onLogin = await loginHeading.isVisible({ timeout: 3000 }).catch(() => false);
	if (!onLogin) {
		return true;
	}

	const accountField = page.getByRole('textbox', { name: /account name|email|kontoname|e-mail/i }).first();
	const passwordField = page.getByRole('textbox', { name: /password|passwort/i });
	await accountField.fill(user);
	await passwordField.fill(pass);
	await page.getByRole('button', { name: /^log in$|^anmelden$/i }).click();
	await page.waitForURL(
		(url) => !url.pathname.includes('/login'),
		{ timeout: 30_000 },
	).catch(() => {});

	const stillLogin = await loginHeading.isVisible({ timeout: 2000 }).catch(() => false);
	return !stillLogin;
}

async function ensureAuthenticated(page) {
	const loggedIn = await tryProgrammaticLogin(page);
	if (!loggedIn) {
		const loginHeading = page.getByRole('heading', { name: /log in to nextcloud|bei nextcloud anmelden/i });
		const onLogin = await loginHeading.isVisible({ timeout: 3000 }).catch(() => false);
		if (onLogin) {
			const { test } = require('@playwright/test');
			test.skip(
				true,
				'Not authenticated. Set E2E_USER + E2E_PASS in e2e/.env and run: npm run e2e:auth',
			);
		}
	}
	await assertNcReady(page);
}

/**
 * On narrow viewports the app navigation drawer can cover #app-content; toggle it closed.
 */
async function dismissOpenAppNavigation(page) {
	const narrow = (page.viewportSize()?.width ?? 1280) <= 480;
	if (!narrow) {
		return;
	}
	const appContent = page.locator('#app-content.pc-app, #app-content.projectcheck-app-content').first();
	const appBox = await appContent.boundingBox();
	if (appBox && appBox.width > 200) {
		return;
	}
	const toggle = page.locator('#app-navigation-toggle');
	if (await toggle.count()) {
		await toggle.click({ force: true }).catch(() => {});
		await page.waitForTimeout(300);
	}
	await appContent
		.evaluate((el) => {
			if (el.getBoundingClientRect().width > 200) {
				return;
			}
			const nav = document.getElementById('app-navigation');
			if (nav) {
				nav.classList.add('hidden');
			}
		})
		.catch(() => {});
}

async function gotoApp(page, url) {
	await page.goto(url, { waitUntil: 'domcontentloaded' });
	await ensureAuthenticated(page);
	await dismissOpenAppNavigation(page);
}

module.exports = {
	ensureAuthenticated,
	tryProgrammaticLogin,
	assertNcReady,
	dismissOpenAppNavigation,
	gotoApp,
};
