'use strict';

const { describe, it, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const api = require('../../js/common/keep-focused-visible.js');

const {
	EDGE_PAD,
	PAD_ATTR,
	visibleBand,
	bottomChromeInset,
	resolvePadHost,
	ensureKeyboardScrollRoom,
	ensureFocusedVisible,
	install,
	_resetPadHostForTests,
} = api;

describe('keep-focused-visible helpers', () => {
	beforeEach(() => {
		_resetPadHostForTests();
		delete global.window;
		delete global.document;
	});

	it('uses visualViewport when present', () => {
		assert.deepEqual(
			visibleBand({
				visualViewport: { offsetTop: 40, height: 400 },
				innerHeight: 800,
			}),
			{ top: 40, bottom: 440 },
		);
	});

	it('falls back to innerHeight when visualViewport missing', () => {
		assert.deepEqual(visibleBand({ innerHeight: 640 }), { top: 0, bottom: 640 });
	});

	it('treats zero-height visualViewport as absent', () => {
		assert.deepEqual(
			visibleBand({
				visualViewport: { offsetTop: 0, height: 0 },
				innerHeight: 640,
			}),
			{ top: 0, bottom: 640 },
		);
	});

	it('resolvePadHost prefers form/main near the field', () => {
		const form = { style: {}, className: 'time-entry-form', closest: () => form };
		const doc = {
			querySelector: () => null,
		};
		assert.equal(resolvePadHost(doc, form), form);
	});

	it('resolvePadHost finds inventory / ticket main shells via querySelector', () => {
		const ivMain = { style: {}, className: 'iv-main' };
		const doc = {
			querySelector: (sel) => (sel === '.iv-main' ? ivMain : null),
		};
		assert.equal(resolvePadHost(doc, null), ivMain);

		const tcMain = { style: {}, id: 'tc-main-content' };
		const doc2 = {
			querySelector: (sel) => (sel === '#tc-main-content' ? tcMain : null),
		};
		assert.equal(resolvePadHost(doc2, null), tcMain);
	});

	it('resolvePadHost pads body-mounted dialogs, not the page main behind them', () => {
		const pageMain = {
			style: {},
			className: 'pc-main',
			contains: () => false,
		};
		const dialog = {
			style: { paddingBottom: '' },
			attrs: {},
			className: 'dc-dialog',
			querySelector: () => null,
			contains: () => true,
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
		};
		const field = {
			closest(sel) {
				if (String(sel).includes('.dc-dialog') || String(sel).includes('[role="dialog"]')) {
					return dialog;
				}
				return null;
			},
		};
		const doc = {
			querySelector: (sel) => (sel === '.pc-main' ? pageMain : null),
			documentElement: {},
		};
		assert.equal(resolvePadHost(doc, field), dialog);
		ensureKeyboardScrollRoom(doc, 280, field);
		assert.equal(dialog.style.paddingBottom, '280px');
		assert.notEqual(pageMain.style.paddingBottom, '280px');
	});

	it('bottomChromeInset measures sticky/fixed footers inside the visual band', () => {
		const footer = {
			getBoundingClientRect: () => ({ top: 340, bottom: 400, height: 60, left: 0, right: 390 }),
		};
		global.window = {
			getComputedStyle: () => ({ position: 'sticky', display: 'block', visibility: 'visible' }),
			innerHeight: 700,
			visualViewport: { offsetTop: 0, height: 400 },
		};
		const doc = {
			querySelectorAll: (sel) => (String(sel).includes('.modal-footer') ? [footer] : []),
			documentElement: {},
		};
		assert.equal(bottomChromeInset(doc, global.window, null), 60);
		assert.equal(bottomChromeInset(null, global.window, null), 0);
	});

	it('bottomChromeInset discovers sticky form-actions via ancestor walk (not only allowlist)', () => {
		const actions = {
			style: {},
			className: 'form-actions admin-user-detail__actions',
			children: [],
			getBoundingClientRect: () => ({ top: 330, bottom: 400, height: 70, left: 0, right: 390 }),
		};
		const form = {
			style: {},
			children: [actions],
			parentElement: null,
			getBoundingClientRect: () => ({ top: 0, bottom: 400, height: 400, left: 0, right: 390 }),
		};
		actions.parentElement = form;
		const field = {
			parentElement: form,
			getBoundingClientRect: () => ({ top: 200, bottom: 280, left: 0, right: 100 }),
		};
		form.children = [field, actions];
		global.window = {
			getComputedStyle: (node) => {
				if (node === actions) {
					return { position: 'sticky', display: 'block', visibility: 'visible' };
				}
				return { position: 'static', display: 'block', visibility: 'visible' };
			},
			innerHeight: 700,
			visualViewport: { offsetTop: 0, height: 400 },
		};
		const doc = {
			querySelectorAll: () => [], // allowlist empty — must discover via ancestors
			documentElement: {},
		};
		assert.equal(bottomChromeInset(doc, global.window, field), 70);
	});

	it('ensureKeyboardScrollRoom pads and restores the same host', () => {
		const host = {
			style: { paddingBottom: '8px' },
			attrs: {},
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
		};
		const doc = {
			querySelector: (sel) => (sel === '.time-entry-form' ? host : null),
			getElementById: () => null,
		};
		ensureKeyboardScrollRoom(doc, 320, null);
		assert.equal(host.style.paddingBottom, '320px');
		assert.equal(host.getAttribute(PAD_ATTR), '8px');
		ensureKeyboardScrollRoom(doc, 0, null);
		assert.equal(host.style.paddingBottom, '8px');
		assert.equal(host.getAttribute(PAD_ATTR), null);
	});

	it('scrolls a covered field up into the visible band', () => {
		const form = {
			className: 'time-entry-form',
			style: { paddingBottom: '' },
			attrs: {},
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
			closest: () => form,
		};
		const parent = {
			id: 'app-content',
			scrollHeight: 2000,
			clientHeight: 500,
			scrollTop: 0,
			parentElement: null,
			style: { paddingBottom: '' },
			getAttribute: () => null,
			setAttribute() {},
			removeAttribute() {},
		};
		let top = 500;
		let bottom = 620;
		const el = {
			getBoundingClientRect: () => ({ top, bottom, left: 0, right: 100 }),
			parentElement: parent,
			closest: () => form,
			scrollIntoView() {
				parent.scrollTop += 150;
				top -= 150;
				bottom -= 150;
			},
		};
		const original = global.getComputedStyle;
		global.window = {
			getComputedStyle: () => ({ overflowY: 'auto', overflowX: 'visible' }),
			scrollBy: (_x, y) => {
				parent.scrollTop += y;
				top -= y;
				bottom -= y;
			},
			innerHeight: 700,
		};
		global.document = {
			body: form,
			documentElement: {},
			querySelector: (sel) => {
				if (sel === '.time-entry-form') {
					return form;
				}
				if (sel === '#app-content') {
					return parent;
				}
				return null;
			},
			querySelectorAll: () => [],
			getElementById: () => null,
			scrollingElement: parent,
		};
		global.getComputedStyle = global.window.getComputedStyle;

		const result = ensureFocusedVisible(el, {
			visualViewport: { offsetTop: 0, height: 350 },
			innerHeight: 700,
			scrollBy: global.window.scrollBy,
		});

		assert.equal(result.moved, true);
		assert.ok(parent.scrollTop > 0);
		assert.equal(EDGE_PAD, 20);
		assert.equal(form.style.paddingBottom, '370px');

		global.getComputedStyle = original;
	});

	it('pads for sticky chrome even when soft keyboard height is already applied', () => {
		const form = {
			style: { paddingBottom: '' },
			attrs: {},
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
			closest: () => form,
		};
		const footer = {
			getBoundingClientRect: () => ({ top: 320, bottom: 400, height: 80, left: 0, right: 390 }),
		};
		const el = {
			getBoundingClientRect: () => ({ top: 280, bottom: 360, left: 0, right: 100 }),
			parentElement: null,
			closest: () => form,
			scrollIntoView() {},
		};
		global.window = {
			getComputedStyle: () => ({
				position: 'fixed',
				display: 'block',
				visibility: 'visible',
				overflowY: 'visible',
				overflowX: 'visible',
			}),
			innerHeight: 700,
		};
		global.document = {
			body: form,
			documentElement: {},
			querySelector: () => form,
			querySelectorAll: () => [footer],
			getElementById: () => null,
		};
		const win = {
			visualViewport: { offsetTop: 0, height: 400 },
			innerHeight: 700,
			scrollBy() {},
		};
		ensureFocusedVisible(el, win);
		assert.equal(form.style.paddingBottom, '320px');
		assert.ok(ensureFocusedVisible(el, win).moved);
	});

	it('no-ops when already visible', () => {
		const el = {
			getBoundingClientRect: () => ({ top: 80, bottom: 140, left: 0, right: 100 }),
			parentElement: null,
			closest: () => null,
		};
		global.document = {
			body: {
				style: {},
				getAttribute: () => null,
				setAttribute() {},
				removeAttribute() {},
			},
			querySelector: () => null,
			querySelectorAll: () => [],
			getElementById: () => null,
		};
		const result = ensureFocusedVisible(el, {
			visualViewport: { offsetTop: 0, height: 600 },
			innerHeight: 600,
			scrollBy: () => assert.fail('should not scroll'),
		});
		assert.deepEqual(result, { moved: false, delta: 0 });
	});

	it('install is idempotent and destroy cleans listeners', () => {
		const listeners = [];
		const doc = {
			documentElement: { dataset: {} },
			activeElement: null,
			addEventListener(type, fn, capture) {
				listeners.push({ type, fn, capture });
			},
			removeEventListener(type, fn, capture) {
				const i = listeners.findIndex((l) => l.type === type && l.fn === fn && l.capture === capture);
				if (i >= 0) {
					listeners.splice(i, 1);
				}
			},
			querySelector: () => null,
			querySelectorAll: () => [],
			getElementById: () => null,
		};
		const win = {
			innerHeight: 700,
			setTimeout: (fn) => {
				fn();
				return 1;
			},
			visualViewport: null,
		};
		const a = install(doc, win);
		const b = install(doc, win);
		assert.equal(doc.documentElement.dataset.pcKeepFocusedVisible, '1');
		assert.ok(listeners.some((l) => l.type === 'focusin'));
		b.destroy();
		a.destroy();
		assert.equal(doc.documentElement.dataset.pcKeepFocusedVisible, undefined);
		assert.equal(listeners.length, 0);
	});

	it('focusout after focusin does not clear pad while still focused (epoch race)', () => {
		const host = {
			style: { paddingBottom: '' },
			attrs: {},
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
		};
		const field = {
			matches: () => true,
			getBoundingClientRect: () => ({ top: 100, bottom: 200, left: 0, right: 40 }),
			parentElement: null,
			closest: () => host,
			scrollIntoView() {},
		};
		const doc = {
			documentElement: { dataset: {} },
			activeElement: field,
			addEventListener() {},
			removeEventListener() {},
			querySelector: (sel) => (sel === '.time-entry-form' ? host : null),
			querySelectorAll: () => [],
			getElementById: () => null,
		};
		const win = {
			innerHeight: 800,
			visualViewport: { offsetTop: 0, height: 400, addEventListener() {}, removeEventListener() {} },
			setTimeout(fn) {
				return 1;
			},
		};
		const handle = install(doc, win);
		ensureKeyboardScrollRoom(doc, 400, field);
		assert.equal(host.style.paddingBottom, '400px');
		ensureFocusedVisible(field, win);
		assert.ok(host.style.paddingBottom);
		handle.destroy();
	});
});
