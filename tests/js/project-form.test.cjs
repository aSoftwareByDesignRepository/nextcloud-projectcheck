'use strict';

/**
 * Unit coverage for project-form.js capacity normalization + quick-customer UX.
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
	it('coerces empty capacity fields before submit', () => {
		assert.match(source, /normalizeCapacityFieldsForSubmit/);
		assert.match(source, /available_hours/);
		assert.match(source, /el\.value = '0'/);
	});

	it('shows an in-place Save next step after quick-add', () => {
		assert.match(source, /showSaveNextStep/);
		assert.match(source, /pc-quick-customer-save/);
		assert.match(source, /Added to the list and selected\./);
		assert.match(source, /Save the project to finish\./);
	});

	it('selects the created customer without navigating away', () => {
		assert.match(source, /selectCustomerOption/);
		assert.doesNotMatch(source, /window\.location\.href/);
	});
});

describe('project-form.php save hierarchy', () => {
	it('keeps Add to list secondary and Save primary', () => {
		assert.match(tpl, /id="pc-quick-customer-create" class="button pc-quick-customer__btn"/);
		assert.doesNotMatch(tpl, /id="pc-quick-customer-create"[^>]*primary/);
		assert.match(tpl, /id="pc-quick-customer-save"/);
		assert.match(tpl, /id="pc-project-save"/);
		assert.match(tpl, /pc-quick-customer-next/);
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
