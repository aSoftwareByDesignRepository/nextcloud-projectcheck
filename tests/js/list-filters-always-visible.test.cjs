'use strict';

/**
 * Unit coverage: projects + customers list filters are always visible.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '../..');
const projectsTpl = fs.readFileSync(path.join(root, 'templates/projects.php'), 'utf8');
const customersTpl = fs.readFileSync(path.join(root, 'templates/customers.php'), 'utf8');
const filtersCss = fs.readFileSync(path.join(root, 'css/common/filters.css'), 'utf8');

describe('list filters always visible (projects + customers)', () => {
	it('projects: one grid, all controls, no More filters disclosure', () => {
		assert.match(projectsTpl, /pc-filters--all-visible/);
		assert.doesNotMatch(projectsTpl, /pc-filters__more/);
		assert.doesNotMatch(projectsTpl, /More filters/);
		assert.doesNotMatch(projectsTpl, /\$projectsAdvancedOpen/);
		assert.equal((projectsTpl.match(/class="pc-filters__grid"/g) || []).length, 1);
		for (const id of [
			'project-search',
			'status-filter',
			'priority-filter',
			'project-type-filter',
			'customer-filter',
			'settlement-filter',
			'apply-filters',
			'clear-filters',
		]) {
			assert.match(projectsTpl, new RegExp(`id="${id}"`));
		}
	});

	it('customers: search + settlement always visible', () => {
		assert.match(customersTpl, /pc-filters--all-visible/);
		assert.doesNotMatch(customersTpl, /pc-filters__more/);
		assert.doesNotMatch(customersTpl, /More filters/);
		assert.doesNotMatch(customersTpl, /\$customersAdvancedOpen/);
		assert.equal((customersTpl.match(/class="pc-filters__grid"/g) || []).length, 1);
		assert.match(customersTpl, /id="customer-search"/);
		assert.match(customersTpl, /id="settlement-filter"/);
	});

	it('shared CSS keeps touch targets and search prominence', () => {
		assert.match(filtersCss, /\.pc-filters--all-visible/);
		assert.match(filtersCss, /grid-column:\s*span 2/);
		assert.match(filtersCss, /grid-column:\s*1\s*\/\s*-1/);
		assert.match(filtersCss, /--pc-touch-min,\s*44px/);
		assert.match(filtersCss, /:focus-visible/);
	});
});
