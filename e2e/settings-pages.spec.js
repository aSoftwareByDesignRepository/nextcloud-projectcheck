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
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/apps/projectcheck/settings/admins', { waitUntil: 'domcontentloaded' });
    await ensureAuthenticated(page);
    await page.waitForSelector('#pc-settings-pages, .pc-nav__sublink, .projectcheck-nav__sublink', { timeout: 30000 });

    // Desktop: sidebar sublinks are the primary settings nav (chip bar is CSS-hidden ≥1024px).
    const sublinks = page.locator('.pc-nav__sublink, .projectcheck-nav__sublink');
    await expect(sublinks).toHaveCount(settingsSections.length);
    await expect(page.locator('.pc-nav__sublink[aria-current="page"], .projectcheck-nav__sublink[aria-current="page"]')).toHaveCount(1);

    // Active sublink: soft tint + readable ink (never white/primary-text on light fill).
    const activeSub = page.locator('.pc-nav__sublink[aria-current="page"], .projectcheck-nav__sublink[aria-current="page"]').first();
    const contrast = await activeSub.evaluate((el) => {
      const s = getComputedStyle(el);
      const parse = (v) => {
        const raw = String(v).trim();
        const m = raw.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/i);
        if (m) {
          return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
        }
        const srgb = raw.match(/color\(\s*srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+))?/i);
        if (srgb) {
          return {
            r: Math.round(+srgb[1] * 255),
            g: Math.round(+srgb[2] * 255),
            b: Math.round(+srgb[3] * 255),
            a: srgb[4] === undefined ? 1 : +srgb[4],
          };
        }
        return null;
      };
      const rel = (c) => {
        const n = c / 255;
        return n <= 0.03928 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4;
      };
      const lum = (rgb) => 0.2126 * rel(rgb.r) + 0.7152 * rel(rgb.g) + 0.0722 * rel(rgb.b);
      let bgEl = el;
      let bg = parse(s.backgroundColor);
      while (bg && bg.a < 0.05 && bgEl.parentElement) {
        bgEl = bgEl.parentElement;
        bg = parse(getComputedStyle(bgEl).backgroundColor);
      }
      const fg = parse(s.color);
      if (!fg || !bg) return { ratio: 0, color: s.color, backgroundColor: s.backgroundColor };
      const L1 = lum(fg);
      const L2 = lum(bg);
      const lighter = Math.max(L1, L2);
      const darker = Math.min(L1, L2);
      return {
        ratio: (lighter + 0.05) / (darker + 0.05),
        color: s.color,
        backgroundColor: s.backgroundColor,
      };
    });
    expect(contrast.ratio, JSON.stringify(contrast)).toBeGreaterThanOrEqual(4.5);

    await page.locator('.pc-nav__sublink, .projectcheck-nav__sublink', { hasText: /license|lizenz/i }).first().click();
    await page.waitForURL(/\/apps\/projectcheck\/settings\/license/, { timeout: 30000 });
    await expect(page.locator('.pc-nav__sublink[aria-current="page"], .projectcheck-nav__sublink[aria-current="page"]')).toContainText(/license|lizenz/i);

    // Mobile / tablet drawer width: chip bar is the reachable sibling nav.
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/apps/projectcheck/settings/admins', { waitUntil: 'domcontentloaded' });
    await ensureAuthenticated(page);
    await page.waitForSelector('#pc-settings-pages', { timeout: 30000 });
    const chips = page.locator('#pc-settings-pages .pc-settings-nav__link');
    await expect(chips).toHaveCount(settingsSections.length);
    await expect(page.locator('#pc-settings-pages .pc-settings-nav__link[aria-current="page"]')).toHaveCount(1);
    await expect(page.locator('#pc-settings-pages .pc-settings-nav__link.is-active')).toContainText(/admin|admins|verwalter/i);
    await page.locator('#pc-settings-pages .pc-settings-nav__link', { hasText: /license|lizenz/i }).click();
    await page.waitForURL(/\/apps\/projectcheck\/settings\/license/, { timeout: 30000 });
    await expect(page.locator('#pc-settings-pages .pc-settings-nav__link[aria-current="page"]')).toContainText(/license|lizenz/i);
  });
});
