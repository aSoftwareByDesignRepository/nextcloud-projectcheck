'use strict';

/**
 * Soft-keyboard IME helper — must never yank desktop forms on focus/select.
 */

const { describe, it, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const api = require('../../js/common/keep-focused-visible.js');

const {
	KEYBOARD_SHRINK_PX,
	PAD_ATTR,
	needsImeReveal,
	softKeyboardLikelyOpen,
	shouldAutoReveal,
	resolvePadHost,
	ensureFocusedVisible,
	ensureKeyboardScrollRoom,
	_resetPadHostForTests,
} = api;

function stubElementTypes() {
	function Element() {}
	function HTMLButtonElement() {}
	function HTMLInputElement() {}
	function HTMLSelectElement() {}
	global.Element = Element;
	global.HTMLButtonElement = HTMLButtonElement;
	global.HTMLInputElement = HTMLInputElement;
	global.HTMLSelectElement = HTMLSelectElement;
	Object.setPrototypeOf(HTMLButtonElement.prototype, Element.prototype);
	Object.setPrototypeOf(HTMLInputElement.prototype, Element.prototype);
	Object.setPrototypeOf(HTMLSelectElement.prototype, Element.prototype);
	return { Element, HTMLButtonElement, HTMLInputElement, HTMLSelectElement };
}

describe('projectcheck keep-focused-visible', () => {
	beforeEach(() => {
		_resetPadHostForTests();
		delete global.window;
		delete global.document;
		delete global.Element;
		delete global.HTMLButtonElement;
		delete global.HTMLInputElement;
		delete global.HTMLSelectElement;
	});

	it('exports soft-keyboard gate constants', () => {
		assert.equal(KEYBOARD_SHRINK_PX, 120);
		assert.equal(typeof needsImeReveal, 'function');
		assert.equal(typeof softKeyboardLikelyOpen, 'function');
		assert.equal(typeof shouldAutoReveal, 'function');
	});

	it('softKeyboardLikelyOpen requires a large visualViewport shrink', () => {
		assert.equal(
			softKeyboardLikelyOpen({
				innerHeight: 900,
				visualViewport: { height: 820, offsetTop: 0 },
			}),
			false,
		);
		assert.equal(
			softKeyboardLikelyOpen({
				innerHeight: 900,
				visualViewport: { height: 500, offsetTop: 0 },
			}),
			true,
		);
	});

	it('needsImeReveal ignores buttons, checkboxes, selects, and date pickers', () => {
		stubElementTypes();

		const btn = new global.HTMLButtonElement();
		btn.matches = () => true;
		assert.equal(needsImeReveal(btn), false);

		const checkbox = new global.HTMLInputElement();
		checkbox.type = 'checkbox';
		checkbox.matches = () => true;
		assert.equal(needsImeReveal(checkbox), false);

		const select = new global.HTMLSelectElement();
		select.matches = () => true;
		assert.equal(needsImeReveal(select), false);

		const date = new global.HTMLInputElement();
		date.type = 'date';
		date.matches = () => true;
		assert.equal(needsImeReveal(date), false);

		const search = new global.HTMLInputElement();
		search.type = 'search';
		search.matches = () => true;
		assert.equal(needsImeReveal(search), true);
	});

	it('resolvePadHost prefers .pc-dialog body when present, else dialog shell', () => {
		const body = { style: { paddingBottom: '' }, className: 'modal-body' };
		const dialog = {
			style: { paddingBottom: '' },
			className: 'pc-dialog',
			querySelector: (sel) => (String(sel).includes('.modal-body') ? body : null),
		};
		const field = {
			closest(sel) {
				if (String(sel).includes('.pc-dialog') || String(sel).includes('[role="dialog"]')) {
					return dialog;
				}
				return null;
			},
		};
		const doc = { querySelector: () => null, documentElement: {} };
		assert.equal(resolvePadHost(doc, field), body);
	});

	it('desktop focus without soft keyboard never scrolls or pads (select + text)', () => {
		stubElementTypes();

		const host = {
			style: { paddingBottom: '' },
			attrs: {},
			className: 'time-entry-form',
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
			getBoundingClientRect: () => ({ top: 0, bottom: 800, height: 800, left: 0, right: 300 }),
		};

		const text = new global.HTMLInputElement();
		text.type = 'text';
		text.matches = () => true;
		text.closest = (sel) => (String(sel).includes('time-entry-form') || String(sel).includes('form') ? host : null);
		text.getBoundingClientRect = () => ({ top: 100, bottom: 140, height: 40, left: 0, right: 300 });
		text.scrollIntoView = () => {
			throw new Error('scrollIntoView must not run on desktop');
		};

		const select = new global.HTMLSelectElement();
		select.matches = () => true;
		select.closest = text.closest;
		select.getBoundingClientRect = text.getBoundingClientRect;
		select.scrollIntoView = text.scrollIntoView;

		global.document = {
			querySelector: () => null,
			querySelectorAll: () => [],
			documentElement: {},
			getElementById: () => null,
			body: host,
		};
		global.window = {
			innerHeight: 900,
			visualViewport: { height: 900, offsetTop: 0 },
			getComputedStyle: () => ({
				position: 'static',
				display: 'block',
				visibility: 'visible',
				overflowY: 'visible',
				overflowX: 'visible',
			}),
		};

		assert.equal(shouldAutoReveal(text, global.window), false);
		assert.equal(shouldAutoReveal(select, global.window), false);
		assert.deepEqual(ensureFocusedVisible(text, global.window), { moved: false, delta: 0 });
		assert.deepEqual(ensureFocusedVisible(select, global.window), { moved: false, delta: 0 });
		assert.equal(host.style.paddingBottom, '');
		assert.equal(host.getAttribute(PAD_ATTR), null);
	});

	it('select never auto-reveals even when soft keyboard is open', () => {
		stubElementTypes();
		const select = new global.HTMLSelectElement();
		select.matches = () => true;
		global.window = {
			innerHeight: 900,
			visualViewport: { height: 400, offsetTop: 0 },
		};
		assert.equal(needsImeReveal(select), false);
		assert.equal(shouldAutoReveal(select, global.window), false);
	});

	it('soft-keyboard open: pads host and scrollIntoView uses nearest (never center)', () => {
		stubElementTypes();

		const host = {
			style: { paddingBottom: '' },
			attrs: {},
			className: 'time-entry-form',
			scrollHeight: 2000,
			clientHeight: 400,
			scrollTop: 0,
			getAttribute(k) {
				return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
			},
			setAttribute(k, v) {
				this.attrs[k] = v;
			},
			removeAttribute(k) {
				delete this.attrs[k];
			},
			getBoundingClientRect: () => ({ top: 0, bottom: 400, height: 400, left: 0, right: 300 }),
			matches: () => false,
		};

		const scrollOpts = [];
		const text = new global.HTMLInputElement();
		text.type = 'text';
		text.matches = () => true;
		text.parentElement = host;
		text.closest = (sel) =>
			String(sel).includes('time-entry-form') || String(sel).includes('form') ? host : null;
		// Sit below usableBottom - EDGE_PAD (400 - 20) so reveal must scroll.
		text.getBoundingClientRect = () => ({ top: 360, bottom: 400, height: 40, left: 0, right: 300 });
		text.scrollIntoView = (opts) => {
			scrollOpts.push(opts);
		};

		global.document = {
			querySelector: () => null,
			querySelectorAll: () => [],
			documentElement: {},
			body: host,
			getElementById: () => null,
			scrollingElement: host,
		};
		global.window = {
			innerHeight: 900,
			visualViewport: { height: 400, offsetTop: 0 },
			getComputedStyle: () => ({
				position: 'static',
				display: 'block',
				visibility: 'visible',
				overflowY: 'auto',
				overflowX: 'visible',
			}),
			scrollBy: () => {},
		};

		assert.equal(shouldAutoReveal(text, global.window), true);
		const result = ensureFocusedVisible(text, global.window);
		assert.equal(result.moved, true);
		assert.ok(String(host.style.paddingBottom).endsWith('px'));
		assert.notEqual(host.style.paddingBottom, '0px');
		assert.ok(scrollOpts.length >= 1, 'expected scrollIntoView');
		for (const opts of scrollOpts) {
			assert.equal(opts.block, 'nearest');
			assert.notEqual(opts.block, 'center');
		}
	});

	it('install wires focusin and skips select targets', () => {
		stubElementTypes();
		const { install } = api;
		const listeners = [];
		const doc = {
			documentElement: { dataset: {} },
			activeElement: null,
			addEventListener(type, fn, capture) {
				listeners.push({ type, fn, capture });
			},
			removeEventListener() {},
			querySelector: () => null,
			getElementById: () => null,
		};
		const win = {
			innerHeight: 900,
			visualViewport: {
				height: 900,
				offsetTop: 0,
				addEventListener() {},
				removeEventListener() {},
			},
			setTimeout: (fn) => {
				fn();
				return 1;
			},
		};
		const handle = install(doc, win);
		assert.ok(listeners.some((l) => l.type === 'focusin' && l.capture === true));

		const select = new global.HTMLSelectElement();
		select.matches = () => true;
		select.scrollIntoView = () => {
			throw new Error('select focusin must not scroll');
		};
		const focusin = listeners.find((l) => l.type === 'focusin');
		doc.activeElement = select;
		focusin.fn({ target: select });
		handle.destroy();
	});

	it('visualViewport resize without keyboard clears leftover IME pad', () => {
		stubElementTypes();
		const { install } = api;

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
			querySelector: () => null,
		};

		const vvListeners = {};
		const doc = {
			documentElement: { dataset: {} },
			activeElement: null,
			body: host,
			addEventListener() {},
			removeEventListener() {},
			querySelector: () => null,
			getElementById: () => null,
		};
		const win = {
			innerHeight: 900,
			visualViewport: {
				height: 400,
				offsetTop: 0,
				addEventListener(type, fn) {
					vvListeners[type] = fn;
				},
				removeEventListener() {},
			},
			setTimeout: (fn) => {
				fn();
				return 1;
			},
		};

		ensureKeyboardScrollRoom(doc, 220, {
			closest: () => host,
		});
		assert.equal(host.style.paddingBottom, '220px');

		const handle = install(doc, win);
		assert.equal(typeof vvListeners.resize, 'function');

		// Keyboard closed → resize must drop pad (no sticky stretch).
		win.visualViewport.height = 900;
		vvListeners.resize();
		assert.equal(host.style.paddingBottom, '');
		assert.equal(host.getAttribute(PAD_ATTR), null);
		handle.destroy();
	});

	it('ensureKeyboardScrollRoom can clear an existing pad', () => {
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
			querySelector: () => null,
		};
		const field = {
			closest(sel) {
				if (
					String(sel).includes('.pc-dialog') ||
					String(sel).includes('[role="dialog"]') ||
					String(sel).includes('.modal') ||
					String(sel).includes('time-entry-form')
				) {
					return host;
				}
				return null;
			},
		};
		global.document = {
			querySelector: () => null,
			getElementById: () => null,
			documentElement: {},
		};

		ensureKeyboardScrollRoom(global.document, 200, field);
		assert.equal(host.style.paddingBottom, '200px');
		assert.equal(host.getAttribute(PAD_ATTR), '');
		ensureKeyboardScrollRoom(global.document, 0, field);
		assert.equal(host.style.paddingBottom, '');
		assert.equal(host.getAttribute(PAD_ATTR), null);
	});
});
