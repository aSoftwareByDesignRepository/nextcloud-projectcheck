import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
	testDir: './e2e',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: 'list',
	use: {
		baseURL: process.env.BASE_URL || 'http://127.0.0.1:1',
		trace: 'on-first-retry',
		...devices['Desktop Chrome'],
	},
});
