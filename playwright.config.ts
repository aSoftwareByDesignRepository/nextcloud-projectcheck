import { defineConfig, devices } from '@playwright/test';
import * as path from 'path';
import * as fs from 'fs';

function loadDotEnv(filePath: string): void {
	if (!fs.existsSync(filePath)) {
		return;
	}
	const content = fs.readFileSync(filePath, 'utf8');
	for (const line of content.split('\n')) {
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

loadDotEnv(path.join(__dirname, 'e2e', '.env'));

const baseURL = (process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '');

// Pin ProjectCheck routes so a parent-shell E2E_SETTINGS_URL (TicketCheck) cannot hijack tests.
process.env.E2E_DASHBOARD_URL = `${baseURL}/index.php/apps/projectcheck/dashboard`;
process.env.E2E_PROJECTS_URL = `${baseURL}/index.php/apps/projectcheck/projects`;
process.env.E2E_PROJECT_CREATE_URL = `${baseURL}/index.php/apps/projectcheck/projects/create`;
process.env.E2E_TIME_ENTRY_CREATE_URL = `${baseURL}/index.php/apps/projectcheck/time-entries/create`;
process.env.E2E_PROJECTCHECK_SETTINGS_URL = `${baseURL}/index.php/apps/projectcheck/settings`;
delete process.env.E2E_SETTINGS_URL;
const storageState = process.env.E2E_STORAGE_STATE
	? path.resolve(process.env.E2E_STORAGE_STATE)
	: path.join(__dirname, '.auth', 'storage-state.json');

export default defineConfig({
	testDir: './e2e',
	globalSetup: './e2e/global-setup.js',
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: [['list'], ['html', { open: 'never' }]],
	use: {
		baseURL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		...devices['Desktop Chrome'],
		...(process.env.E2E_USER && (process.env.E2E_PASS || process.env.E2E_PASSWORD)
			? { storageState }
			: {}),
	},
});
