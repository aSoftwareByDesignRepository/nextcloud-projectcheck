/**
 * Behavioral tests for js/settings-legacy-redirect.js — executed for real in
 * Node (no browser). The PHP side of the anchor contract is pinned by
 * tests/Unit/Controller/SettingsPagesContractTest.
 *
 * Run: node --test tests/js/settings-pages.test.mjs
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '../..');
const source = fs.readFileSync(path.join(root, 'js/settings-legacy-redirect.js'), 'utf8');

function loadModule() {
	const fakeWindow = {};
	new Function('window', source)(fakeWindow);
	const api = fakeWindow.ProjectCheckSettingsLegacyRedirect;
	assert.ok(api, 'module must export window.ProjectCheckSettingsLegacyRedirect');
	return api;
}

const SECTION_URLS = Object.freeze({
	access: '/apps/projectcheck/settings/access',
	admins: '/apps/projectcheck/settings/admins',
	defaults: '/apps/projectcheck/settings/defaults',
	license: '/apps/projectcheck/settings/license',
	support: '/apps/projectcheck/settings/support',
});

/**
 * @param {object} [overrides]
 */
function docStub({ section = 'access', urls = { settingsSections: SECTION_URLS }, attrs = null } = {}) {
	const attributes = attrs || {
		'data-pc-settings-section': section,
		'data-pc-urls': JSON.stringify(urls),
	};
	return {
		getElementById(id) {
			if (id !== 'app-content') {
				return null;
			}
			return {
				getAttribute(name) {
					return Object.prototype.hasOwnProperty.call(attributes, name) ? attributes[name] : null;
				},
			};
		},
	};
}

test('exports a frozen api with a frozen anchor map', () => {
	const api = loadModule();
	assert.ok(Object.isFrozen(api), 'api object must be frozen');
	assert.ok(Object.isFrozen(api.ANCHOR_SECTIONS), 'ANCHOR_SECTIONS must be frozen');
	assert.throws(() => {
		'use strict';
		api.ANCHOR_SECTIONS['pc-evil'] = 'access';
	}, TypeError);
	assert.equal(typeof api.resolve, 'function');
});

test('every anchor forwards to its owning sub-page and keeps the fragment', () => {
	const api = loadModule();
	const anchors = Object.entries(api.ANCHOR_SECTIONS);
	assert.ok(anchors.length >= 15, 'anchor map must cover the full legacy page');
	for (const [anchor, section] of anchors) {
		const current = section === 'access' ? 'license' : 'access';
		const url = api.resolve(docStub({ section: current }), { hash: '#' + anchor });
		assert.equal(
			url,
			SECTION_URLS[section] + '#' + anchor,
			`#${anchor} must forward to the ${section} page with the fragment preserved`,
		);
	}
});

test('does not forward when the anchor already lives on the current page', () => {
	const api = loadModule();
	for (const [anchor, section] of Object.entries(api.ANCHOR_SECTIONS)) {
		assert.equal(api.resolve(docStub({ section }), { hash: '#' + anchor }), null, `#${anchor} on its own page must not loop`);
	}
});

test('ignores unknown, empty, and prototype-polluting hashes', () => {
	const api = loadModule();
	for (const hash of ['', '#', '#unknown-anchor', '#constructor', '#__proto__', '#hasOwnProperty', '#toString']) {
		assert.equal(api.resolve(docStub(), { hash }), null, `hash '${hash}' must not redirect`);
	}
});

test('does nothing outside a settings sub-page (empty section attribute)', () => {
	const api = loadModule();
	assert.equal(api.resolve(docStub({ section: '' }), { hash: '#projectcheck-license' }), null);
});

test('fails closed on missing DOM, malformed urls, and missing section urls', () => {
	const api = loadModule();
	const hash = '#projectcheck-license';
	assert.equal(api.resolve(null, { hash }), null, 'no document');
	assert.equal(api.resolve({}, { hash }), null, 'document without getElementById');
	assert.equal(api.resolve({ getElementById: () => null }, { hash }), null, 'no #app-content');
	assert.equal(
		api.resolve(docStub({ attrs: { 'data-pc-settings-section': 'access', 'data-pc-urls': '{broken json' } }), { hash }),
		null,
		'malformed data-pc-urls JSON',
	);
	assert.equal(
		api.resolve(docStub({ urls: {} }), { hash }),
		null,
		'urls payload without settingsSections',
	);
	assert.equal(
		api.resolve(docStub({ urls: { settingsSections: { license: '' } } }), { hash }),
		null,
		'empty target url',
	);
	assert.equal(
		api.resolve(docStub({ urls: { settingsSections: { license: 42 } } }), { hash }),
		null,
		'non-string target url',
	);
	assert.equal(api.resolve(docStub(), null), null, 'no location');
	assert.equal(api.resolve(docStub(), {}), null, 'location without hash');
});

test('module tolerates a null global root (non-browser environments)', () => {
	assert.doesNotThrow(() => new Function('window', source)(null));
});
