/**
 * Contract: modal restore contract (modal_restore_contract_violation class).
 *
 *  - close() resolves the restore target LAZILY: a trigger node detached
 *    mid-open (list re-render after a confirmed mutation) must not strand
 *    focus on <body> — fall back through labelled page anchors:
 *    header actions → view heading → main landmark.
 *  - a still-connected trigger restores focus to itself.
 *
 * Behavioral test under jsdom (real attach/detach cycle).
 * Run: node --test tests/js/modal-restore-contract.test.mjs
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '../..');
const source = fs.readFileSync(path.join(root, 'js/common/modal-a11y.js'), 'utf8');

function makePage(html) {
	const dom = new JSDOM(`<!DOCTYPE html><html><body>${html}</body></html>`, { pretendToBeVisual: true });
	const { window } = dom;
	// jsdom does no layout — give every element a non-zero box so isVisible()
	// sees a rendered page.
	window.HTMLElement.prototype.getBoundingClientRect = function () {
		return { x: 0, y: 0, top: 0, left: 0, right: 10, bottom: 10, width: 10, height: 10 };
	};
	const g = {
		window,
		document: window.document,
		HTMLElement: window.HTMLElement,
		KeyboardEvent: window.KeyboardEvent,
		MouseEvent: window.MouseEvent,
		getComputedStyle: window.getComputedStyle,
		setTimeout,
	};
	// The module assigns to (window || globalThis).ProjectCheckModalA11y
	new Function('window', 'document', 'HTMLElement', 'KeyboardEvent', 'MouseEvent', 'getComputedStyle', 'setTimeout', source)(
		g.window, g.document, g.HTMLElement, g.KeyboardEvent, g.MouseEvent, g.getComputedStyle, g.setTimeout);
	return { window, api: window.ProjectCheckModalA11y };
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

test('connected trigger restores focus to itself', async () => {
	const { window, api } = makePage(`
		<main id="pc-main-content" tabindex="-1">
			<button id="trigger">Open</button>
			<div id="modal" aria-hidden="false"><button id="inside">In</button></div>
		</main>`);
	const doc = window.document;
	doc.getElementById('trigger').focus();
	assert.equal(doc.activeElement.id, 'trigger');
	api.attach(doc.getElementById('modal'));
	await sleep(5);
	api.detach(doc.getElementById('modal'), { reason: 'test' });
	assert.equal(doc.activeElement.id, 'trigger', 'connected trigger must regain focus');
});

test('detached trigger falls back to labelled anchor, never body', async () => {
	const { window, api } = makePage(`
		<header><div class="pc-page-header__actions"><button id="hdr-action">Back</button></div></header>
		<main id="pc-main-content" tabindex="-1">
			<h1 class="pc-page-header__title">Projects</h1>
			<button id="trigger">Delete</button>
			<div id="modal" aria-hidden="false"><button id="inside">In</button></div>
		</main>`);
	const doc = window.document;
	const trigger = doc.getElementById('trigger');
	trigger.focus();
	api.attach(doc.getElementById('modal'), { restoreTo: trigger });
	await sleep(5);
	trigger.remove(); // list re-rendered mid-open — trigger detached
	api.detach(doc.getElementById('modal'), { reason: 'confirm' });
	assert.notEqual(doc.activeElement, doc.body, 'focus must not strand on <body>');
	assert.equal(doc.activeElement.id, 'hdr-action', 'falls back to first header action');
});

test('no header actions → page title → main landmark', async () => {
	const { window, api } = makePage(`
		<main id="pc-main-content" tabindex="-1">
			<h1 class="pc-page-header__title">Projects</h1>
			<button id="trigger">Delete</button>
			<div id="modal" aria-hidden="false"><button id="inside">In</button></div>
		</main>`);
	const doc = window.document;
	const trigger = doc.getElementById('trigger');
	trigger.focus();
	api.attach(doc.getElementById('modal'), { restoreTo: trigger });
	await sleep(5);
	trigger.remove();
	api.detach(doc.getElementById('modal'), { reason: 'confirm' });
	assert.notEqual(doc.activeElement, doc.body, 'focus must not strand on <body>');
	assert.equal(doc.activeElement.className.includes('pc-page-header__title') || doc.activeElement.id === 'pc-page-title', true);
});

test('no title → main landmark gets focus', async () => {
	const { window, api } = makePage(`
		<main id="pc-main-content" tabindex="-1">
			<button id="trigger">Delete</button>
			<div id="modal" aria-hidden="false"><button id="inside">In</button></div>
		</main>`);
	const doc = window.document;
	const trigger = doc.getElementById('trigger');
	trigger.focus();
	api.attach(doc.getElementById('modal'), { restoreTo: trigger });
	await sleep(5);
	trigger.remove();
	api.detach(doc.getElementById('modal'), { reason: 'confirm' });
	assert.equal(doc.activeElement.id, 'pc-main-content');
});
