/* eslint-env node */
/** Minimal ESLint config for the app’s plain browser JS (no bundler for most files). */
module.exports = {
	root: true,
	env: {
		browser: true,
		es2020: true,
	},
	globals: {
		OC: 'readonly',
		OCA: 'readonly',
		t: 'readonly',
		escapeHTML: 'readonly',
		ProjectCheckDatepicker: 'readonly',
		ProjectCheckDeletionModal: 'readonly',
	},
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'script',
	},
	overrides: [
		{
			files: ['js/common/index.js', 'js/common/cache.js', 'js/common/performance.js'],
			parserOptions: { sourceType: 'module' },
		},
	],
	rules: {
		'no-unused-vars': ['warn', { args: 'none', caughtErrors: 'none', varsIgnorePattern: '^_' }],
		'no-console': ['warn', { allow: ['warn', 'error'] }],
	},
	ignorePatterns: ['dist/', 'node_modules/', 'js/dist/', 'sw.js'],
};
