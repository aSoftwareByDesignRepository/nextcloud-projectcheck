'use strict';

/**
 * Unit coverage for project-form.js capacity normalization + single-Save UX.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const jsPath = path.join(__dirname, '../../js/project-form.js');
const source = fs.readFileSync(jsPath, 'utf8');
const tplPath = path.join(__dirname, '../../templates/project-form.php');
const tpl = fs.readFileSync(tplPath, 'utf8');

describe('project-form.js contracts', () => {
	it('coerces empty budget/rate before submit and keeps capacity numeric', () => {
		assert.match(source, /normalizeCapacityFieldsForSubmit/);
		assert.match(source, /fillShortDescriptionFromName/);
		assert.match(source, /details\.pc-advanced-details/);
		assert.match(source, /addEventListener\('invalid'/);
		assert.match(source, /total_budget/);
		assert.match(source, /hourly_rate/);
		assert.match(source, /available_hours/);
		assert.match(source, /el\.value = '0'/);
	});

	it('guides to the single footer Save after quick-add', () => {
		assert.match(source, /showSaveNextStep/);
		assert.match(source, /focusPrimarySave/);
		assert.match(source, /pc-quick-customer-goto-save/);
		assert.match(source, /pc-project-save/);
		assert.match(source, /Added to the list and selected\./);
		assert.match(source, /Save the project to finish\./);
		// After add, focusPrimarySave runs (not focus on Go to Save helper)
		assert.match(source, /focusPrimarySave\(\);\s*\}/);
		assert.match(source, /let creating = false/);
		assert.match(source, /let submitting = false/);
		assert.match(source, /validateDateRange/);
		assert.doesNotMatch(source, /pc-quick-customer-save/);
	});

	it('selects the created customer without navigating away', () => {
		assert.match(source, /selectCustomerOption/);
		assert.doesNotMatch(source, /window\.location\.href/);
	});
});

describe('project-form.php save hierarchy', () => {
	it('keeps Add to list secondary and exactly one primary submit', () => {
		assert.match(tpl, /id="pc-quick-customer-create" class="button pc-quick-customer__btn"/);
		assert.doesNotMatch(tpl, /id="pc-quick-customer-create"[^>]*primary/);
		assert.match(tpl, /id="pc-quick-customer-goto-save"/);
		assert.doesNotMatch(tpl, /id="pc-quick-customer-goto-save"[^>]*primary/);
		assert.match(tpl, /id="pc-project-save"/);
		assert.match(tpl, /pc-quick-customer-next/);
		const submits = tpl.match(/type="submit"/g) || [];
		assert.equal(submits.length, 1);
		const primaries = tpl.match(/class="[^"]*\bprimary\b[^"]*"/g) || [];
		assert.equal(primaries.length, 1);
	});

	it('does not submit readonly available_hours (display-only)', () => {
		assert.match(tpl, /id="available_hours"/);
		assert.doesNotMatch(tpl, /name="available_hours"/);
		assert.match(tpl, /pc-pricing-gate/);
		assert.match(tpl, /pc-project-form-error/);
	});

	it('uses a short tip instead of a multi-step create wizard', () => {
		assert.match(tpl, /pc-form-tip/);
		assert.doesNotMatch(tpl, /pc-create-workflow/);
		assert.match(tpl, /One button saves everything on this page\./);
		assert.match(tpl, /pc-advanced-classification/);
		assert.match(tpl, /pc-advanced-pricing/);
		assert.match(tpl, /pc-advanced-budget/);
		assert.match(tpl, /pc-advanced-schedule/);
		assert.match(tpl, /pc-more-about-project/);
		assert.match(tpl, /Leave blank to use the project name\./);
	});
});

describe('FormDecimal coerce mirror (JS defense)', () => {
	function coerceEmpty(value) {
		if (value === null || value === undefined) {
			return '0';
		}
		if (String(value).trim() === '') {
			return '0';
		}
		return String(value);
	}

	it('turns blank capacity into zero string', () => {
		assert.equal(coerceEmpty(''), '0');
		assert.equal(coerceEmpty('   '), '0');
		assert.equal(coerceEmpty('12.5'), '12.5');
	});
});

describe('project-form-cost-rates.js locked-mode contracts', () => {
	const ratesJs = fs.readFileSync(
		path.join(__dirname, '../../js/project-form-cost-rates.js'),
		'utf8',
	);
	const pricingTpl = fs.readFileSync(
		path.join(__dirname, '../../templates/parts/pricing-mode-cards.php'),
		'utf8',
	);

	it('resolves mode from hidden input when radios are absent (locked pricing)', () => {
		assert.match(ratesJs, /input\[name="cost_rate_mode"\]:checked/);
		assert.match(ratesJs, /input\[type="hidden"\]\[name="cost_rate_mode"\]/);
		assert.match(pricingTpl, /pc-pricing-locked/);
		assert.match(pricingTpl, /type="hidden" name="cost_rate_mode"/);
		assert.doesNotMatch(pricingTpl, /\$costRateModeLocked \? 'disabled'/);
	});
});
