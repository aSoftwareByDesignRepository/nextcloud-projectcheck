// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { ensureAuthenticated } = require('./helpers/auth-guard');

/**
 * Multipage settings: axe smoke per section, redirect, hash forward, nav active state.
 * Uses storageState / E2E_USER when available; skips cleanly on login wall.
 */
const settingsSections = [
  'access',
  'admins',
  'defaults',
  'license',
  'support',
];

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} path
 */
async function assertA11y(page, path) {
  await page.goto(path, { waitUntil: 'domcontentloaded' });
  await ensureAuthenticated(page);
  await page.waitForSelector('#projectcheck-org-main, #pc-main-content, main.pc-main', { timeout: 30000 });
  await page.waitForFunction(() => {
    const body = getComputedStyle(document.body);
    return Boolean(body.getPropertyValue('--color-main-text') || body.color);
  });
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .analyze();
  expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
}

test.describe('ProjectCheck multipage settings', () => {
  for (const section of settingsSections) {
    test(`axe: /settings/${section}`, async ({ page }) => {
      await assertA11y(page, `/apps/projectcheck/settings/${section}`);
    });
  }

  test('/settings redirects to /settings/access', async ({ page }) => {
    await page.goto('/apps/projectcheck/settings', { waitUntil: 'domcontentloaded' });
    await ensureAuthenticated(page);
    await page.waitForURL(/\/apps\/projectcheck\/settings\/access/, { timeout: 30000 });
    await expect(page.locator('#projectcheck-org-main, main.pc-main').first()).toBeVisible();
  });

  test('legacy hash forwards to owning section', async ({ page }) => {
    await page.goto('/apps/projectcheck/settings/access#projectcheck-license', { waitUntil: 'domcontentloaded' });
    await ensureAuthenticated(page);
    await page.waitForURL(/\/apps\/projectcheck\/settings\/license/, { timeout: 30000 });
    expect(page.url()).toContain('#projectcheck-license');
  });

  test('sidebar subnav and chip bar mark active section', async ({ page }) => {
    await page.goto('/apps/projectcheck/settings/admins', { waitUntil: 'domcontentloaded' });
    await ensureAuthenticated(page);
    await page.waitForSelector('#pc-settings-pages', { timeout: 30000 });

    const chips = page.locator('#pc-settings-pages .pc-settings-nav__link');
    await expect(chips).toHaveCount(settingsSections.length);
    await expect(page.locator('#pc-settings-pages .pc-settings-nav__link[aria-current="page"]')).toHaveCount(1);
    await expect(page.locator('#pc-settings-pages .pc-settings-nav__link.is-active')).toContainText(/admin|admins|verwalter/i);

    const sublinks = page.locator('.projectcheck-nav__sublink');
    await expect(sublinks).toHaveCount(settingsSections.length);
    await expect(page.locator('.projectcheck-nav__sublink[aria-current="page"]')).toHaveCount(1);

    await page.locator('#pc-settings-pages .pc-settings-nav__link', { hasText: /license|lizenz/i }).click();
    await page.waitForURL(/\/apps\/projectcheck\/settings\/license/, { timeout: 30000 });
    await expect(page.locator('#pc-settings-pages .pc-settings-nav__link[aria-current="page"]')).toContainText(/license|lizenz/i);
  });
});
