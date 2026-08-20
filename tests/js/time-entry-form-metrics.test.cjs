'use strict';

/**
 * Unit coverage: time-entry metrics strip layout + server-authoritative rate.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '../..');
const tpl = fs.readFileSync(path.join(root, 'templates/time-entry-form.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'css/time-entry-form.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'js/time-entry-form.js'), 'utf8');

describe('time-entry-form metrics layout', () => {
	it('exposes four peer form groups instead of a collapsed pricing details', () => {
		assert.match(tpl, /form-row--metrics/);
		assert.doesNotMatch(tpl, /form-row--split-4/);
		assert.doesNotMatch(tpl, /id="pc-te-pricing-summary"/);
		assert.match(tpl, /form-group--date/);
		assert.match(tpl, /form-group--hours/);
		assert.match(tpl, /form-group--rate/);
		assert.match(tpl, /form-group--total/);
		assert.match(tpl, /form-row--metrics__date-hint/);
		assert.match(tpl, /id="date-hint"/);
	});

	it('keeps hourly rate readonly and total cost non-submittable', () => {
		assert.match(tpl, /id="hourly_rate"[^>]*\breadonly\b|readonly[^>]*id="hourly_rate"/);
		assert.match(tpl, /name="hourly_rate"/);
		assert.match(tpl, /id="total_cost"/);
		assert.doesNotMatch(tpl, /id="total_cost"[^>]*\bname=/);
		assert.match(js, /resolveRateFromServer/);
		assert.match(js, /resolve-hourly-rate/);
		assert.match(js, /rateInput\.readOnly = true/);
		assert.match(js, /calculateTotalCost/);
	});

	it('CSS uses a four-track metrics grid with touch-sized controls', () => {
		assert.match(css, /\.form-row--metrics\s*\{/);
		assert.doesNotMatch(css, /form-row--split-4/);
		assert.match(
			css,
			/minmax\(0,\s*1\.2fr\)\s+minmax\(0,\s*0\.7fr\)\s+minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/,
		);
		assert.match(css, /--pc-touch-min,\s*44px/);
		assert.match(css, /form-row--metrics__date-hint/);
	});
});
