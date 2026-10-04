'use strict';

/**
 * Toast dedup contract: identical toasts must not stack (learned defect
 * class vis-duplicate-toast-stacking). messaging.js show() must dedup on
 * kind+text and reset the existing toast's auto-dismiss timer.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const SRC = path.resolve(__dirname, '../../js/common/messaging.js');
const src = fs.readFileSync(SRC, 'utf8');

describe('toast dedup (kind+text+timer)', () => {
	it('show() consults findDuplicateToast before creating a toast', () => {
		assert.match(src, /findDuplicateToast\(/, 'missing dedup lookup in show()');
		assert.ok(
			src.indexOf('findDuplicateToast(') < src.indexOf('createToast(type, title, message, dismissible, actions);'),
			'dedup lookup must run before createToast'
		);
	});

	it('duplicate path resets the auto-dismiss timer', () => {
		assert.match(src, /_pcDismissTimer/, 'per-toast timer handle missing');
		assert.match(src, /clearTimeout\(duplicate\._pcDismissTimer\)/, 'timer reset missing');
	});

	it('findDuplicateToast matches kind class and message text, skips removing', () => {
		assert.match(src, /toast--\$\{type\}/, 'kind class check missing');
		assert.match(src, /toast--removing/, 'must skip removing toasts');
		assert.match(src, /\.toast-message/, 'message element selector missing');
	});
});

describe('toast dedup behaviour', () => {
	// Minimal DOM shim: enough surface for createToast/show/findDuplicateToast.
	function fakeEl(tag) {
		const el = {
			tagName: tag,
			className: '',
			children: [],
			attrs: {},
			parentNode: null,
			textContent: '',
			classList: {
				add: (...cs) => { for (const c of cs) el.className += ' ' + c; },
				remove: () => {},
				contains: (c) => el.className.split(/\s+/).includes(c),
			},
			setAttribute(k, v) { el.attrs[k] = v; },
			getAttribute(k) { return el.attrs[k]; },
			appendChild(c) { c.parentNode = el; el.children.push(c); return c; },
			removeChild(c) { el.children = el.children.filter(x => x !== c); c.parentNode = null; },
			addEventListener() {},
			querySelector(sel) {
				const cls = sel.replace('.', '');
				const walk = (e) => {
					for (const ch of e.children || []) {
						if ((ch.className || '').split(/\s+/).includes(cls)) return ch;
						const r = walk(ch); if (r) return r;
					}
					return null;
				};
				return walk(el);
			},
			querySelectorAll(sel) {
				const cls = sel.replace('.', '');
				const out = [];
				const walk = (e) => {
					for (const ch of e.children || []) {
						if ((ch.className || '').split(/\s+/).includes(cls)) out.push(ch);
						walk(ch);
					}
				};
				walk(el);
				return out;
			},
		};
		return el;
	}

	function loadMessaging() {
		const container = fakeEl('div');
		global.document = {
			createElement: (t) => fakeEl(t),
			getElementById: () => container,
			body: fakeEl('body'),
		};
		global.window = {
			ProjectCheckDom: {
				createEl: (t, cls) => { const e = fakeEl(t); if (cls) e.className = cls; return e; },
				textNode: (txt) => { const e = fakeEl('span'); e.textContent = txt; return e; },
				populateToast(toast, opts) {
					const content = fakeEl('div'); content.className = 'toast-content';
					const msg = fakeEl('div'); msg.className = 'toast-message';
					msg.textContent = opts.message;
					content.children.push(msg);
					toast.children.push(content);
				},
			},
			ProjectCheckIcons: null,
			dispatchEvent() {},
		};
		global.t = (app, s) => s;
		global.requestAnimationFrame = (fn) => fn();
		const M = require('../../js/common/messaging.js');
		M.toastContainer = container;
		return { M, container };
	}

	it('identical kind+text does not stack; different kind does', () => {
		const { M, container } = loadMessaging();
		const t1 = M.show('error', 'Save failed');
		const t2 = M.show('error', 'Save failed');
		assert.strictEqual(t1, t2, 'same kind+text must return existing toast');
		assert.equal(M.getToastCount(), 1, 'no duplicate stacked');
		const t3 = M.show('success', 'Save failed');
		assert.notStrictEqual(t1, t3, 'different kind must create new toast');
		assert.equal(M.getToastCount(), 2);
	});

	it('dedup resets the dismiss timer', () => {
		const { M } = loadMessaging();
		const t1 = M.show('info', 'hello', { duration: 5000 });
		const timer1 = t1._pcDismissTimer;
		assert.ok(timer1, 'toast has auto-dismiss timer');
		const t2 = M.show('info', 'hello', { duration: 5000 });
		assert.strictEqual(t1, t2);
		assert.ok(t2._pcDismissTimer && t2._pcDismissTimer !== timer1,
			'timer handle must be refreshed on dedup');
	});

	it('removing toasts are not dedup targets', () => {
		const { M, container } = loadMessaging();
		const t1 = M.show('info', 'bye');
		t1.classList.add('toast--removing');
		const t2 = M.show('info', 'bye');
		assert.notStrictEqual(t1, t2, 'a removing toast must not be reused');
	});
});
