#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

function loadDotEnv(filePath) {
	if (!fs.existsSync(filePath)) {
		return;
	}
	for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
		const trimmed = line.trim();
		if (trimmed === '' || trimmed.startsWith('#')) {
			continue;
		}
		const eq = trimmed.indexOf('=');
		if (eq === -1) {
			continue;
		}
		const key = trimmed.slice(0, eq).trim();
		let value = trimmed.slice(eq + 1).trim();
		if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
			value = value.slice(1, -1);
		}
		if (process.env[key] === undefined) {
			process.env[key] = value;
		}
	}
}

loadDotEnv(path.join(__dirname, '.env'));

module.exports = async function globalSetup() {
	const user = process.env.E2E_USER;
	const pass = process.env.E2E_PASS || process.env.E2E_PASSWORD;
	if (!user || !pass) {
		console.warn('[projectcheck:e2e] Skipping auth setup: set E2E_USER and E2E_PASS in e2e/.env');
		return;
	}

	const base = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');
	const loginUrl = process.env.E2E_LOGIN_URL || `${base}/index.php/login`;
	const outputPath = process.env.E2E_STORAGE_STATE
		? path.resolve(process.env.E2E_STORAGE_STATE)
		: path.resolve(__dirname, '..', '.auth', 'storage-state.json');

	fs.mkdirSync(path.dirname(outputPath), { recursive: true });
	if (fs.existsSync(outputPath)) {
		fs.unlinkSync(outputPath);
	}

	const browser = await chromium.launch({ headless: true });
	const context = await browser.newContext();
	const page = await context.newPage();

	await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

	const upgrade = page.getByRole('heading', { name: /app update required|app-aktualisierung erforderlich/i });
	if (await upgrade.isVisible({ timeout: 3000 }).catch(() => false)) {
		await browser.close();
		throw new Error(
			'[projectcheck:e2e] Nextcloud needs occ upgrade before E2E. Run: docker compose exec -u www-data nextcloud php occ upgrade',
		);
	}

	const accountField = page.getByRole('textbox', { name: /account name|email|kontoname|e-mail/i }).first();
	const passwordField = page.getByRole('textbox', { name: /password|passwort/i });
	await accountField.fill(user);
	await passwordField.fill(pass);
	await page.getByRole('button', { name: /^log in$|^anmelden$/i }).click();

	try {
		await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 });
	} catch {
		await browser.close();
		throw new Error('[projectcheck:e2e] Login failed — check E2E_USER / E2E_PASS in e2e/.env');
	}

	// Verify ProjectCheck is reachable while authenticated.
	await page.goto(`${base}/index.php/apps/projectcheck/dashboard`, { waitUntil: 'domcontentloaded' });
	const appContent = page.locator('#app-content');
	await appContent.waitFor({ state: 'visible', timeout: 15_000 }).catch(async () => {
		await browser.close();
		throw new Error('[projectcheck:e2e] ProjectCheck dashboard did not load after login');
	});

	await context.storageState({ path: outputPath });
	await browser.close();

	process.env.E2E_STORAGE_STATE = outputPath;
	console.log(`[projectcheck:e2e] Fresh storage state: ${outputPath}`);
};
