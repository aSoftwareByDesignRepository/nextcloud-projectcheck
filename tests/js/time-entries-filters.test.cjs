'use strict';

/**
 * Unit coverage: time-entries list filters are always visible (no More filters).
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '../..');
const tpl = fs.readFileSync(path.join(root, 'templates/time-entries.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'css/common/filters.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'js/time-entries.js'), 'utf8');
const filtersCss = css;

describe('time-entries filters always visible', () => {
	it('renders one filter grid with all controls and no disclosure', () => {
		assert.match(tpl, /pc-filters--all-visible/);
		assert.doesNotMatch(tpl, /pc-filters__more/);
		assert.doesNotMatch(tpl, /More filters/);
		assert.doesNotMatch(tpl, /\$teAdvancedOpen/);
		assert.doesNotMatch(tpl, /pc-filters__grid--advanced/);
		assert.equal((tpl.match(/class="pc-filters__grid"/g) || []).length, 1);

		for (const id of [
			'time-entry-search',
			'project-filter',
			'time-entry-project-type-filter',
			'billing-status-filter',
			'date-from-filter',
			'date-to-filter',
			'apply-filters',
			'clear-filters',
		]) {
			assert.match(tpl, new RegExp(`id="${id}"`));
		}
	});

	it('keeps labels and search landmark for WCAG 2.1 AA', () => {
		assert.match(tpl, /role="search"/);
		assert.match(tpl, /for="time-entry-search"/);
		assert.match(tpl, /for="billing-status-filter"/);
		assert.match(tpl, /for="date-from-filter"/);
		assert.match(tpl, /for="date-to-filter"/);
		assert.match(filtersCss, /--pc-touch-min,\s*44px/);
		assert.match(filtersCss, /:focus-visible/);
	});

	it('CSS promotes search width when all filters are shown', () => {
		assert.match(css, /pc-filters--all-visible/);
		assert.match(css, /\.pc-filters--all-visible\s+\.pc-filters__field--search/);
		assert.match(css, /grid-column:\s*span 2/);
		assert.match(css, /grid-column:\s*1\s*\/\s*-1/);
	});

	it('JS still reads the same filter IDs and rejects inverted date ranges', () => {
		assert.match(js, /getElementById\('time-entry-search'\)|time-entry-search/);
		assert.match(js, /billing-status-filter/);
		assert.match(js, /date-from-filter/);
		assert.match(js, /date-to-filter/);
		assert.match(js, /if \(dateFrom && dateTo && dateFrom > dateTo\)/);
		assert.doesNotMatch(js, /if \(false && dateFrom && dateTo && dateFrom > dateTo\)/);
		assert.match(js, /applyFilters/);
		assert.match(js, /clearFilters/);
	});
});
