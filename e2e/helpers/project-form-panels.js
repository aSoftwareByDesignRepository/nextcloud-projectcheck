/**
 * Create form keeps short description, pricing, and budget in closed <details>.
 * Open them before Playwright fill/check so actions are not blocked by visibility.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{ more?: boolean, pricing?: boolean, budget?: boolean }} [opts]
 */
async function openProjectFormPanels(page, opts = {}) {
	const {
		more = true,
		pricing = true,
		budget = true,
	} = opts;
	const ids = [];
	if (more) {
		ids.push('#pc-more-about-project');
	}
	if (pricing) {
		ids.push('#pc-advanced-pricing');
	}
	if (budget) {
		ids.push('#pc-advanced-budget');
	}
	for (const id of ids) {
		const panel = page.locator(id);
		if ((await panel.count()) === 0) {
			continue;
		}
		await panel.evaluate((el) => {
			if (el instanceof HTMLDetailsElement) {
				el.open = true;
			}
		});
	}
}

module.exports = { openProjectFormPanels };
