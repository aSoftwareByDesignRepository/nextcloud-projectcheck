'use strict';

/**
 * Fleet contract: every Check-suite web app ships and wires soft-keyboard IME helpers.
 * Fails hard if a sibling app regresses (missing file or missing Util::addScript).
 */

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const APPS_ROOT = path.resolve(__dirname, '../../..');

/** @type {Array<{ id: string; prefix: string; wireGlobs: string[] }>} */
const FLEET = [
	{ id: 'projectcheck', prefix: 'PC', wireGlobs: ['templates/common/navigation.php'] },
	{ id: 'arbeitszeitcheck', prefix: 'AZC', wireGlobs: ['templates/common/navigation.php'] },
	{ id: 'dutycheck', prefix: 'DC', wireGlobs: ['lib/Controller/PageController.php'] },
	{ id: 'budgetcheck', prefix: 'BC', wireGlobs: ['lib/Controller/PageController.php'] },
	{ id: 'customercheck', prefix: 'CRM', wireGlobs: ['templates/common/navigation.php'] },
	{ id: 'inventorycheck', prefix: 'IV', wireGlobs: ['lib/Controller/PageController.php'] },
	{ id: 'maintenancecheck', prefix: 'MN', wireGlobs: ['templates/common/navigation.php'] },
	{ id: 'mobilitycheck', prefix: 'MC', wireGlobs: ['lib/Controller/PageController.php'] },
	{ id: 'invoicecheck', prefix: 'IC', wireGlobs: ['templates/common/navigation.php'] },
	{ id: 'ticketcheck', prefix: 'TC', wireGlobs: ['lib/Service/FrontEndAssetService.php'] },
	{ id: 'deskcheck', prefix: 'DK', wireGlobs: ['templates/common/navigation.php'] },
];

const REQUIRED_PAD_HINTS = [
	'.iv-main',
	'.ic-main',
	'.mn-main',
	'.mc-main',
	'.tc-main',
	'.dc-main',
	'#tc-main-content',
	'#mn-main-content',
	'.dc-dialog',
	'[role="dialog"]',
	'.tc-banner',
	'.projectcheck-admin',
	'.admin-user-detail__actions',
	'chromeCandidates',
];

describe('keep-focused-visible fleet contract', () => {
	for (const app of FLEET) {
		it(`${app.id}: ships keep-focused-visible.js with ${app.prefix} global`, () => {
			const file = path.join(APPS_ROOT, app.id, 'js/common/keep-focused-visible.js');
			assert.ok(fs.existsSync(file), `missing ${file}`);
			const src = fs.readFileSync(file, 'utf8');
			assert.match(src, new RegExp(`${app.prefix}KeepFocusedVisible`));
			assert.match(src, /visualViewport/);
			assert.match(src, /resolvePadHost/);
			assert.match(src, /focusEpoch/);
			for (const hint of REQUIRED_PAD_HINTS) {
				assert.ok(src.includes(hint), `${app.id} missing pad hint ${hint}`);
			}
		});

		it(`${app.id}: registers common/keep-focused-visible`, () => {
			let wired = false;
			const wireFiles = [...app.wireGlobs];
			if (app.id === 'projectcheck') {
				wireFiles.push('templates/admin-settings.php');
			}
			for (const rel of wireFiles) {
				const file = path.join(APPS_ROOT, app.id, rel);
				assert.ok(fs.existsSync(file), `missing wire file ${file}`);
				const src = fs.readFileSync(file, 'utf8');
				if (src.includes('keep-focused-visible')) {
					wired = true;
					assert.match(src, /addScript\([^)]*keep-focused-visible/);
				}
			}
			assert.equal(wired, true, `${app.id} not wired`);
		});
	}
});
