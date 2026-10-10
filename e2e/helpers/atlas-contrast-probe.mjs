// @ts-check
/**
 * ATLAS ds_chrome live contrast probe (projectcheck).
 *
 * Walks representative pages as a probe user and measures the COMPUTED
 * WCAG 2.1 contrast of semantic chrome: badges/pills, primary + danger
 * CTAs, callout accents, control borders, aria-invalid painted borders,
 * field-error ink and tonal semantic fills — across light / dark /
 * light-highcontrast / dark-highcontrast user themes (server-pinned via
 * OCS theming, body[data-theme-*] marker asserted — never client-emulated).
 *
 *   text ink   >= 4.5:1  (WCAG 1.4.3 AA; >=3:1 for large icon glyphs)
 *   borders    >= 3.0:1  (WCAG 1.4.11 non-text contrast)
 *
 * Usage (from the app dir):
 *   node e2e/helpers/atlas-contrast-probe.mjs [--out <path.json>]
 *
 * Auth order: E2E_STORAGE_STATE (or the ds_chrome artifact storage file)
 * → programmatic login as DS_PROBE_USER/DS_PROBE_PASS → E2E_USER/E2E_PASS
 * from e2e/.env. NC canonicalizes origins — drive ONE consistent origin
 * (chromium_formaction_redirect class).
 */
import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const HERE = dirname(fileURLToPath(import.meta.url))

// Minimal .env loader (mirrors playwright.config.ts / global-setup.js).
function loadDotEnv(filePath) {
	if (!existsSync(filePath)) return
	for (const line of readFileSync(filePath, 'utf8').split('\n')) {
		const t = line.trim()
		if (!t || t.startsWith('#')) continue
		const eq = t.indexOf('=')
		if (eq === -1) continue
		const k = t.slice(0, eq).trim()
		let v = t.slice(eq + 1).trim()
		if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) v = v.slice(1, -1)
		if (process.env[k] === undefined) process.env[k] = v
	}
}
loadDotEnv(resolve(HERE, '..', '.env'))

const BASE = (process.env.NC_BASE_URL || process.env.E2E_BASE || process.env.BASE_URL || 'http://localhost:8081').replace(/\/$/, '')
const APP_PAGE = `${BASE}/index.php/apps/projectcheck/`
const USER = process.env.DS_PROBE_USER || process.env.E2E_USER || 'admin'
const PASS = process.env.DS_PROBE_PASS || process.env.E2E_PASS || process.env.E2E_PASSWORD || ''
const STORAGE_CANDIDATES = [
	process.env.E2E_STORAGE_STATE,
	resolve(HERE, '../../../../../.cursor/atlas-farm-v3/artifacts/projectcheck/ds_chrome/pc_ds_probe-storage.json'),
	resolve(HERE, '../../.auth/storage-state.json'),
].filter(Boolean)
const THEMES = ['light', 'dark', 'light-highcontrast', 'dark-highcontrast']

// ── WCAG contrast helpers (injected into the page for computed colors) ──
const EVAL_FN = String.raw`
function hexToRgb(c) {
  c = c.trim()
  if (c.startsWith('color(')) {
    const m = c.match(/[\d.]+/g)
    if (m && m.length >= 3) {
      const s = m.map(parseFloat)
      const scale = s.every((v) => v <= 1) ? 255 : 1
      return [s[0] * scale, s[1] * scale, s[2] * scale]
    }
    return null
  }
  if (c.startsWith('rgb')) {
    const m = c.match(/[\d.]+/g)
    if (m && m.length >= 3) return [parseFloat(m[0]), parseFloat(m[1]), parseFloat(m[2])]
    return null
  }
  if (c.startsWith('#')) {
    let h = c.slice(1)
    if (h.length === 3) h = h.split('').map(x => x + x).join('')
    if (h.length === 4) h = h.split('').map(x => x + x).join('')
    if (h.length === 6 || h.length === 8) {
      return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)]
    }
  }
  return null
}
function lum(rgb) {
  const f = v => {
    v /= 255
    return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
  }
  return 0.2126 * f(rgb[0]) + 0.7152 * f(rgb[1]) + 0.0722 * f(rgb[2])
}
function effBgFrom(n) {
  while (n && n !== document.documentElement) {
    const bg = getComputedStyle(n).backgroundColor
    const m = bg && bg.match(/[\d.]+/g)
    if (m && m.length >= 4 && parseFloat(m[3]) > 0) return bg
    if (m && m.length === 3 && !bg.includes('transparent')) return bg
    n = n.parentElement
  }
  return getComputedStyle(document.body).backgroundColor
}
function effBg(el) { return effBgFrom(el) }
function effBgParent(el) { return effBgFrom(el && el.parentElement) }
/* Gradient fills: background shorthand paints a background-IMAGE while
   backgroundColor stays transparent — effBg() alone misreads those controls
   as 'text on page bg'. gradientStops() returns the resolved stop colors so
   callers can measure ink/fill against the real painted surface (worst stop). */
function gradientStops(el) {
  const bi = getComputedStyle(el).backgroundImage
  if (!bi || !bi.includes('gradient')) return null
  const cols = bi.match(/rgba?\([^)]*\)|color\([^)]*\)|#[0-9a-fA-F]{3,8}/g)
  return cols && cols.length ? cols : null
}
function effFills(el) {
  const stops = gradientStops(el)
  if (stops) return stops
  const bg = getComputedStyle(el).backgroundColor
  const m = bg && bg.match(/[\d.]+/g)
  if (m && ((m.length >= 4 && parseFloat(m[3]) > 0) || (m.length === 3 && !bg.includes('transparent')))) return [bg]
  return null
}
function alphaOf(c) {
  const m = c && c.match(/[\d.]+/g)
  if (m && m.length >= 4) return parseFloat(m[3])
  if (c && c.startsWith('color(')) {
    const parts = c.match(/[\d.]+/g)
    if (parts && parts.length >= 4) return parseFloat(parts[3])
  }
  return 1
}
function blend(fgRgb, bgRgb, a) {
  return [
    a * fgRgb[0] + (1 - a) * bgRgb[0],
    a * fgRgb[1] + (1 - a) * bgRgb[1],
    a * fgRgb[2] + (1 - a) * bgRgb[2],
  ]
}
function ratio(fg, bg) {
  const a = hexToRgb(fg), b = hexToRgb(bg)
  if (!a || !b) return null
  const l1 = lum(a), l2 = lum(b)
  const hi = Math.max(l1, l2), lo = Math.min(l1, l2)
  return (hi + 0.05) / (lo + 0.05)
}
function borderRatio(border, bg) {
  const f = hexToRgb(border), b = hexToRgb(bg)
  if (!f || !b) return null
  const alpha = alphaOf(border)
  const eff = alpha >= 1 ? f : blend(f, b, alpha)
  const l1 = lum(eff), l2 = lum(b)
  const hi = Math.max(l1, l2), lo = Math.min(l1, l2)
  return (hi + 0.05) / (lo + 0.05)
}
window.__pcProbe = { effBg, effBgParent, effFills, gradientStops, ratio, alphaOf, borderRatio }
`

/** Programmatic login — NC canonicalizes origins; stay on BASE. */
async function login(page) {
	for (let attempt = 1; attempt <= 3; attempt++) {
		await page.goto(`${BASE}/index.php/login`, { waitUntil: 'domcontentloaded', timeout: 45_000 })
		const html = await page.content()
		if (/maintenance mode|update is in progress|needs to be updated/i.test(html)) {
			throw new Error('Nextcloud is in maintenance/upgrade mode')
		}
		if (!page.url().includes('/login')) return
		const user = page.locator('input[name="user"], #user')
		const pass = page.locator('input[name="password"], #password')
		await user.first().waitFor({ state: 'visible', timeout: 30_000 })
		await user.first().fill(USER)
		await pass.first().fill(PASS)
		await page.locator('button[type="submit"], input[type="submit"], #submit-form').first().click()
		await page.waitForTimeout(1500)
		await page.goto(APP_PAGE, { waitUntil: 'domcontentloaded', timeout: 45_000 })
		if (!page.url().includes('/login')) return
	}
	throw new Error(`login_failed for ${USER} — check DS_PROBE_USER/DS_PROBE_PASS or e2e/.env`)
}

/** Server-pinned OCS theme switch (learned: client flips fake HC readings). */
async function setUserTheme(page, themeId) {
	const failures = await page.evaluate(async ({ target, all }) => {
		const token = (window.OC && window.OC.requestToken)
			|| document.querySelector('head[data-requesttoken]')?.getAttribute('data-requesttoken') || ''
		const headers = { requesttoken: token, 'OCS-APIRequest': 'true', Accept: 'application/json' }
		const problems = []
		for (const id of all.filter((t) => t !== target)) {
			const res = await fetch(`/ocs/v2.php/apps/theming/api/v1/theme/${id}`, { method: 'DELETE', credentials: 'same-origin', headers })
			if (!res.ok && res.status !== 400) problems.push(`disable ${id}: HTTP ${res.status}`)
		}
		const res = await fetch(`/ocs/v2.php/apps/theming/api/v1/theme/${target}/enable`, { method: 'PUT', credentials: 'same-origin', headers })
		if (!res.ok && res.status !== 400) problems.push(`enable ${target}: HTTP ${res.status}`)
		return problems
	}, { target: themeId, all: THEMES })
	if (failures.length) throw new Error(`theme ${themeId}: ${failures.join(';')}`)
}

async function themeMarkerOk(page, themeId) {
	return page.evaluate((t) => {
		const attr = t === 'light' ? 'data-theme-light' : `data-theme-${t}`
		const dataThemes = document.body.getAttribute('data-themes') || ''
		return document.body.hasAttribute(attr) || dataThemes.split(/\s+/).includes(t)
			|| (t === 'light' && /default|light/.test(dataThemes))
	}, themeId)
}

const ANCHOR = '#pc-main-content, #projectcheck-org-main, main.pc-main, #app-content.pc-app'
const CONTROLS = '#app-content input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]), #app-content select, #app-content textarea'

/** Elements to measure per page. Anchor = fabricated-capture guard. */
const PROBES = [
	{
		page: '/index.php/apps/projectcheck/dashboard',
		anchor: ANCHOR,
		label: 'dashboard',
		rows: [
			{ sel: '.pc-badge, .status-badge, .budget-status-badge, .budget-warning-badge, .yearly-stat-badge', kind: 'badge', what: 'text', optional: true },
			{ sel: '#app-content button.primary, #app-content .button.primary, #app-content .btn--primary, #app-content .pc-btn--primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: CONTROLS, kind: 'control-border', what: 'border', optional: true },
			{ sel: '.projectcheck-callout, .pc-form-callout', kind: 'callout-accent-border', what: 'border', borderSide: 'borderInlineStartColor', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/projects',
		anchor: ANCHOR,
		label: 'projects',
		rows: [
			{ sel: '.status-badge, .pc-badge, .project-status-badges .badge', kind: 'badge', what: 'text', optional: true },
			{ sel: '#app-content button.primary, #app-content .button.primary, a.button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: CONTROLS + ', .pc-filter-field input, .pc-filter-field select', kind: 'control-border', what: 'border', optional: true },
			{ sel: '.project-row.budget-status-warning, .project-row.budget-status-critical, .project-row.budget-status-safe', kind: 'row-status-accent', what: 'border', borderSide: 'borderInlineStartColor', optional: true },
			{ sel: '.delete-project-btn, button.error, .button.error, .btn--danger', kind: 'danger-cta', what: 'text', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/customers',
		anchor: ANCHOR,
		label: 'customers',
		rows: [
			{ sel: '.pc-badge, .status-badge', kind: 'badge', what: 'text', optional: true },
			{ sel: '#app-content button.primary, #app-content .button.primary, a.button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: CONTROLS, kind: 'control-border', what: 'border', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/customers/create',
		anchor: ANCHOR,
		label: 'customer-create',
		rows: [
			{ sel: CONTROLS, kind: 'control-border', what: 'border' },
			{ sel: '#app-content button[type="submit"].primary, #app-content button.primary', kind: 'primary-cta', what: 'text', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/time-entries',
		anchor: ANCHOR,
		label: 'time-entries',
		rows: [
			{ sel: '.pc-badge, .status-badge', kind: 'badge', what: 'text', optional: true },
			{ sel: '#app-content button.primary, #app-content .button.primary, a.button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: CONTROLS, kind: 'control-border', what: 'border', optional: true },
			{ sel: '.pc-settle-strip__list .pc-badge, .pc-stl-progress__legend, .pc-productivity__legend', kind: 'settle-strip-ink', what: 'text', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/time-entries/create',
		anchor: ANCHOR,
		label: 'time-entry-create',
		rows: [
			{ sel: CONTROLS + ', .time-entry-form input:not([type="hidden"]), .time-entry-form select', kind: 'control-border', what: 'border' },
			{ sel: '.time-entry-form button[type="submit"], #app-content button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: '.pc-form-callout, .projectcheck-callout', kind: 'callout-accent-border', what: 'border', borderSide: 'borderInlineStartColor', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/settings',
		anchor: ANCHOR,
		label: 'settings',
		rows: [
			{ sel: CONTROLS, kind: 'control-border', what: 'border', optional: true },
			{ sel: '#app-content button.primary, #app-content .button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: '.pc-license-badge, .pc-badge', kind: 'badge', what: 'text', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/settings/license',
		anchor: ANCHOR,
		label: 'settings-license',
		rows: [
			{ sel: '#pc-license-key, #app-content textarea, #app-content input:not([type="hidden"])', kind: 'control-border', what: 'border', optional: true },
			{ sel: '.pc-license-cta__link, .pc-license-cta a.button, #app-content button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: '.pc-license-badge, .pc-badge', kind: 'badge', what: 'text', optional: true },
			{ sel: '#pc-license-meter-text', kind: 'meter-ink', what: 'text', optional: true },
		],
	},
	{
		page: '/index.php/apps/projectcheck/employees',
		anchor: ANCHOR,
		label: 'employees',
		rows: [
			{ sel: '.pc-role-badge, .pc-badge, .status-badge', kind: 'badge', what: 'text', optional: true },
			{ sel: '#app-content button.primary, #app-content .button.primary', kind: 'primary-cta', what: 'text', optional: true },
			{ sel: CONTROLS, kind: 'control-border', what: 'border', optional: true },
		],
	},
]

async function settle(page) {
	await page.waitForLoadState('domcontentloaded').catch(() => {})
	try { await page.waitForLoadState('networkidle', { timeout: 5000 }) } catch { /* long-polls */ }
	await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))))
}

async function measure(page, probes) {
	const results = []
	for (const p of probes) {
		const resp = await page.goto(`${BASE}${p.page}`, { waitUntil: 'domcontentloaded' })
		if (!resp || resp.status() >= 400) {
			results.push({ page: p.label, error: `http ${resp ? resp.status() : 'nav-fail'}`, rows: [] })
			continue
		}
		// Surface anchor assert BEFORE any measurement (fabricated-capture guard).
		await page.waitForSelector(p.anchor, { timeout: 30_000 })
		await settle(page)
		const pageRes = { page: p.label, rows: [] }
		for (const row of p.rows) {
			const found = await page.evaluate(
				async ({ sel, what, borderSide }) => {
					const els = Array.from(document.querySelectorAll(sel)).filter(
						(n) => n.offsetParent !== null
							// NC skip links are offscreen until :focus — not a painted surface.
							&& !n.closest('.skip-navigation'),
					)
					const out = []
					for (const el of els.slice(0, 6)) {
						const cs = getComputedStyle(el)
						const bg = window.__pcProbe.effBg(el)
						const fills = window.__pcProbe.effFills(el)
						const surface = fills && fills.length ? fills : [bg]
						const border = borderSide ? cs[borderSide] : cs.borderColor
						const bWidth = borderSide ? cs.borderInlineStartWidth : cs.borderWidth
						const worstOf = (fn) => Math.min(...surface.map((s) => fn(s) ?? 99))
						const item = {
							tag: el.tagName.toLowerCase(),
							cls: (el.getAttribute('class') || '').slice(0, 80),
							fg: cs.color,
							bg,
							fillStops: fills,
							borderColor: border,
							borderWidth: bWidth,
							selfBg: cs.backgroundColor,
						}
						if (what !== 'border') {
							// ink vs worst gradient stop / painted fill
							item.textRatio = worstOf((s) => window.__pcProbe.ratio(cs.color, s))
						}
						if (what !== 'text' && parseFloat(bWidth) > 0) {
							/* Boundary contrast (WCAG 1.4.11): the control's outer edge
							   faces the SURROUNDING surface — parent bg for filled
							   controls — not its own fill. A control passes when
							   EITHER its border OR its fill reads >=3:1 vs outside. */
							const parentBg = window.__pcProbe.effBgParent(el)
							const surrounding = fills ? parentBg : bg
							item.borderRatio = window.__pcProbe.borderRatio(border, surrounding)
							item.borderAlpha = window.__pcProbe.alphaOf(border)
							const fillRs = (fills || [cs.backgroundColor]).map(
								(s) => window.__pcProbe.ratio(s, parentBg),
							).filter((x) => x !== null)
							item.fillRatio = fillRs.length ? Math.min(...fillRs) : null
							const meas = [item.borderRatio, item.fillRatio].filter((x) => x !== null)
							item.boundaryRatio = meas.length ? Math.max(...meas) : null
						}
						out.push(item)
					}
					return out
				},
				{ sel: row.sel, what: row.what, borderSide: row.borderSide || null },
			)
			pageRes.rows.push({ kind: row.kind, selector: row.sel, what: row.what, found: found.length, optional: !!row.optional, samples: found })
		}
		results.push(pageRes)
	}
	return results
}

/**
 * Dialog controls + validation-negative invalid state. Deletion modal on
 * /projects mounts an overlay on document.body — measure input borders
 * inside it, then confirm the cancel path does not delete.
 */
async function measureDialogAndFieldError(page) {
	const out = {}
	// 1) Deletion modal chrome (open + cancel only — never confirm).
	try {
		await page.goto(`${BASE}/index.php/apps/projectcheck/projects`, { waitUntil: 'domcontentloaded' })
		await page.waitForSelector(ANCHOR, { timeout: 30_000 })
		await settle(page)
		const del = page.locator('.delete-project-btn').first()
		if (await del.isVisible({ timeout: 8000 }).catch(() => false)) {
			await del.scrollIntoViewIfNeeded()
			await del.click()
			await page.waitForSelector('#projectcheck-deletion-modal', { timeout: 10_000 })
			await page.waitForTimeout(400)
			out.dialogControls = await page.evaluate(() => {
				const dlg = document.querySelector('#projectcheck-deletion-modal')
				if (!dlg) return []
				const els = [...dlg.querySelectorAll('button, .button, input, select, textarea')]
					.filter((n) => n.offsetParent !== null)
				return els.slice(0, 8).map((el) => {
					const cs = getComputedStyle(el)
					const bg = window.__pcProbe.effBg(el)
					const fills = window.__pcProbe.effFills(el)
					const surface = fills && fills.length ? fills : [bg]
					const parentBg = window.__pcProbe.effBgParent(el)
					const bordered = parseFloat(cs.borderWidth) > 0
					const borderR = bordered ? window.__pcProbe.borderRatio(cs.borderColor, fills ? parentBg : bg) : null
					const fr = surface.map((s) => window.__pcProbe.ratio(s, parentBg)).filter((x) => x !== null)
					const fillR = bordered && fr.length ? Math.min(...fr) : null
					const meas = [borderR, fillR].filter((x) => x !== null)
					return {
						tag: el.tagName.toLowerCase(),
						cls: (el.getAttribute('class') || '').slice(0, 60),
						fg: cs.color,
						borderColor: cs.borderColor,
						borderWidth: cs.borderWidth,
						bg,
						textRatio: Math.min(...surface.map((s) => window.__pcProbe.ratio(cs.color, s) ?? 99)),
						borderRatio: borderR,
						fillRatio: fillR,
						boundaryRatio: meas.length ? Math.max(...meas) : null,
					}
				})
			})
			const cancelBtn = page.locator('#projectcheck-deletion-modal .projectcheck-deletion-modal__btn--cancel')
			if (await cancelBtn.isVisible().catch(() => false)) await cancelBtn.click()
		} else {
			out.dialogSkipped = 'no .delete-project-btn on projects list'
		}
	} catch (e) {
		out.dialogError = String(e.message || e).split('\n')[0]
	}

	// 2) Validation negative: customer-create empty submit → aria-invalid +
	//    painted error border + readable inline .error-message.
	try {
		await page.goto(`${BASE}/index.php/apps/projectcheck/customers/create`, { waitUntil: 'domcontentloaded' })
		await page.waitForSelector(ANCHOR, { timeout: 30_000 })
		await settle(page)
		const submit = page.locator('button[type="submit"].primary, form button[type="submit"]').first()
		if (await submit.isVisible({ timeout: 5000 }).catch(() => false)) {
			await submit.click()
			await page.waitForTimeout(1200)
			out.invalidState = await page.evaluate(() => {
				const input = document.querySelector('#app-content [aria-invalid="true"]')
				const err = [...document.querySelectorAll('.error-message, [class*="field-error"]')]
					.find((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 })
				if (!input) return { ariaInvalid: null, errorShown: !!err }
				const cs = getComputedStyle(input)
				const bg = window.__pcProbe.effBg(input)
				const res = {
					ariaInvalid: input.getAttribute('aria-invalid'),
					ariaDescribedby: input.getAttribute('aria-describedby'),
					errorShown: !!err,
					border: {
						borderColor: cs.borderColor,
						borderWidth: cs.borderWidth,
						borderRatio: window.__pcProbe.borderRatio(cs.borderColor, bg),
					},
				}
				if (err) {
					const ecs = getComputedStyle(err)
					res.errorText = {
						text: (err.textContent || '').slice(0, 120),
						textRatio: window.__pcProbe.ratio(ecs.color, window.__pcProbe.effBg(err)),
					}
				}
				return res
			})
		} else {
			out.invalidSkipped = 'customer create: submit button not found'
		}
	} catch (e) {
		out.invalidError = String(e.message || e).split('\n')[0]
	}
	return out
}

/**
 * Semantic accent surfaces not reachable from every fixture: inject
 * representative nodes and measure COMPUTED token resolution — class-level
 * stylesheet truth for toast accents, badges and tonal fills.
 *
 * IMPORTANT: each spec declares the page whose templates ship the
 * component's stylesheet — budget-alerts.css only loads on the dashboard,
 * admin-settings.css only on settings. Injecting a class on a page that
 * never enqueues it fabricates an unstyled state (0px borders) — that is
 * probe error, not an app defect.
 */
const ACCENT_SPECS = [
	{
		page: '/index.php/apps/projectcheck/dashboard',
		label: 'accents:dashboard',
		specs: [
			{ cls: 'pc-badge', tag: 'span' },
			{ cls: 'pc-badge pc-badge--info', tag: 'span' },
			{ cls: 'pc-badge pc-badge--neutral', tag: 'span' },
			// variant classes as rendered by templates/dashboard.php — the
			// bare base class alone ships no semantic accent.
			{ cls: 'status-badge status-active', tag: 'span' },
			{ cls: 'status-badge status-cancelled', tag: 'span' },
			{ cls: 'budget-status-badge warning', tag: 'span' },
			{ cls: 'budget-status-badge critical', tag: 'span' },
			{ cls: 'budget-warning-badge warning', tag: 'span' },
			{ cls: 'pc-form-callout pc-form-callout--error', tag: 'div', side: 'borderInlineStartColor' },
		],
	},
	{
		page: '/index.php/apps/projectcheck/settings',
		label: 'accents:settings',
		specs: [
			// bare callout = neutral info block (no accent required);
			// --caution is the state-bearing variant.
			{ cls: 'projectcheck-callout', tag: 'div', side: 'borderInlineStartColor', accentOptional: true },
			{ cls: 'projectcheck-callout projectcheck-callout--caution', tag: 'div', side: 'borderInlineStartColor' },
		],
	},
]

async function measureAccentSurfaces(page, specGroup) {
	return page.evaluate((specs) => {
		const host = document.querySelector('#app-content') || document.body
		const out = []
		for (const spec of specs) {
			const el = document.createElement(spec.tag || 'span')
			el.className = spec.cls
			el.textContent = 'probe'
			host.appendChild(el)
			const cs = getComputedStyle(el)
			const bg = window.__pcProbe.effBg(el)
			const fills = window.__pcProbe.effFills(el)
			const surface = fills && fills.length ? fills : [bg]
			const parentBg = window.__pcProbe.effBgParent(el)
			const b = spec.side ? cs[spec.side] : cs.borderColor
			const w = spec.side ? cs.borderInlineStartWidth : cs.borderWidth
			const textRatio = Math.min(...surface.map((s) => window.__pcProbe.ratio(cs.color, s) ?? 99))
			let borderRatio = null
			let fillRatio = null
			let accentRatio = null
			if (parseFloat(w) > 0) {
				borderRatio = window.__pcProbe.borderRatio(b, fills ? parentBg : bg)
				const fr = surface.map((s) => window.__pcProbe.ratio(s, parentBg)).filter((x) => x !== null)
				fillRatio = fr.length ? Math.min(...fr) : null
			}
			/* Semantic accent strips (status badges carry state on a 3px
			   border-inline-start, not the decorative all-side ring). */
			if (!spec.side && parseFloat(cs.borderInlineStartWidth) > 0
				&& cs.borderInlineStartColor !== cs.borderTopColor) {
				accentRatio = window.__pcProbe.borderRatio(cs.borderInlineStartColor, fills ? parentBg : bg)
			}
			const meas = [borderRatio, fillRatio, accentRatio].filter((x) => x !== null)
			out.push({
				kind: `accent:${spec.cls}`,
				cls: (el.getAttribute('class') || '').slice(0, 80),
				accentOptional: !!spec.accentOptional,
				fg: cs.color,
				bg,
				selfBg: cs.backgroundColor,
				borderColor: b,
				borderWidth: w,
				textRatio,
				borderRatio,
				fillRatio,
				accentRatio,
				boundaryRatio: meas.length ? Math.max(...meas) : null,
			})
			el.remove()
		}
		return out
	}, specGroup.specs)
}

async function main() {
	const browser = await chromium.launch({ headless: true })
	const storagePath = STORAGE_CANDIDATES.find((p) => existsSync(p))
	const context = await browser.newContext({
		baseURL: BASE,
		viewport: { width: 1440, height: 900 },
		...(storagePath ? { storageState: storagePath } : {}),
	})
	const page = await context.newPage()
	const report = {
		app: 'projectcheck', probe: 'live-computed-contrast', base: BASE,
		auth: storagePath ? `storageState:${storagePath}` : `login:${USER}`,
		generated_at: new Date().toISOString(), themes: {},
	}

	try {
		// Verify session; fall back to programmatic login on /login bounce.
		await page.goto(APP_PAGE, { waitUntil: 'domcontentloaded', timeout: 45_000 })
		if (page.url().includes('/login')) {
			report.auth = `login:${USER}`
			await login(page)
		}
		await context.addInitScript(EVAL_FN)
		await page.goto(APP_PAGE, { waitUntil: 'domcontentloaded' })
		await page.evaluate(new Function(EVAL_FN))

		for (const theme of THEMES) {
			await page.goto(APP_PAGE, { waitUntil: 'domcontentloaded' })
			await setUserTheme(page, theme)
			// Marker assert AFTER a real navigation — OCS persistence is only
			// painted on the next render (never trust pre-nav body attrs).
			await page.goto(APP_PAGE, { waitUntil: 'domcontentloaded' })
			const markerOk = await themeMarkerOk(page, theme)
			const themeRes = { themeMarker: markerOk, pages: await measure(page, PROBES) }
			if (theme === 'light' || theme === 'dark') {
				themeRes.dialogAndFieldError = await measureDialogAndFieldError(page)
			}
			themeRes.accents = []
			for (const group of ACCENT_SPECS) {
				await page.goto(`${BASE}${group.page}`, { waitUntil: 'domcontentloaded' })
				await page.waitForSelector(ANCHOR, { timeout: 30_000 })
				await settle(page)
				const rows = await measureAccentSurfaces(page, group)
				themeRes.accents.push(...rows.map((r) => ({ ...r, hostPage: group.label })))
			}
			report.themes[theme] = themeRes
		}
		// Restore probe user to light for subsequent lanes.
		await page.goto(APP_PAGE, { waitUntil: 'domcontentloaded' })
		await setUserTheme(page, 'light')
	} finally {
		await context.close()
		await browser.close()
	}

	const TEXT_MIN = 4.5
	const BORDER_MIN = 3.0
	const findings = []
	for (const [theme, t] of Object.entries(report.themes)) {
		if (t.themeMarker === false) {
			findings.push({ theme, kind: 'theme-marker-missing', ratio: null, min: null })
		}
		for (const pr of t.pages || []) {
			if (pr.error) {
				findings.push({ theme, page: pr.page, kind: 'page-error', detail: pr.error })
				continue
			}
			for (const row of pr.rows || []) {
				if (row.found === 0 && !row.optional) {
					findings.push({ theme, page: pr.page, kind: row.kind + '-absent', detail: `selector matched nothing: ${row.selector}` })
					continue
				}
				for (const s of row.samples || []) {
					if (s.textRatio !== undefined && s.textRatio !== null && s.textRatio < TEXT_MIN) {
						findings.push({ theme, page: pr.page, kind: row.kind, cls: s.cls, ratio: s.textRatio, min: TEXT_MIN })
					}
					// boundary = best of border-vs-outside / fill-vs-outside (WCAG 1.4.11)
					const boundary = s.boundaryRatio ?? s.borderRatio ?? s.fillRatio ?? null
					if (boundary !== null && boundary < BORDER_MIN) {
						findings.push({ theme, page: pr.page, kind: row.kind + '-boundary', cls: s.cls, ratio: boundary, min: BORDER_MIN })
					}
				}
			}
		}
		for (const s of t.accents || []) {
			const ap = s.hostPage || 'accents'
			if (s.textRatio !== null && s.textRatio !== undefined && s.textRatio < TEXT_MIN) {
				findings.push({ theme, page: ap, kind: s.kind + '-ink', cls: s.cls, ratio: s.textRatio, min: TEXT_MIN })
			}
			if (s.boundaryRatio === null || s.boundaryRatio === undefined) {
				// Injected node painted no measurable accent — stylesheet not
				// loaded here or class ships no accent. Real states need one.
				if (!s.accentOptional) {
					findings.push({ theme, page: ap, kind: s.kind + '-accent-missing', cls: s.cls, detail: 'no border/fill/accent painted' })
				}
			} else if (s.boundaryRatio < BORDER_MIN) {
				findings.push({ theme, page: ap, kind: s.kind + '-boundary', cls: s.cls, ratio: s.boundaryRatio, min: BORDER_MIN })
			}
		}
		const m = t.dialogAndFieldError
		if (m) {
			for (const s of m.dialogControls || []) {
				if (s.textRatio !== null && s.textRatio < TEXT_MIN) {
					findings.push({ theme, page: 'dialog', kind: 'dialog-button-ink', cls: s.cls, ratio: s.textRatio, min: TEXT_MIN })
				}
				const boundary = s.boundaryRatio ?? s.borderRatio ?? s.fillRatio ?? null
				if (boundary !== null && boundary < BORDER_MIN) {
					findings.push({ theme, page: 'dialog', kind: 'dialog-control-boundary', cls: s.cls, ratio: boundary, min: BORDER_MIN })
				}
			}
			const inv = m.invalidState || {}
			if (inv.errorShown) {
				if (inv.ariaInvalid !== 'true') {
					findings.push({ theme, page: 'customer-create', kind: 'aria-invalid-missing', detail: 'field error shown without aria-invalid on control' })
				}
				if (inv.border && inv.border.borderRatio !== null && inv.border.borderRatio < BORDER_MIN) {
					findings.push({ theme, page: 'customer-create', kind: 'invalid-border', ratio: inv.border.borderRatio, min: BORDER_MIN })
				}
				if (inv.errorText && inv.errorText.textRatio !== null && inv.errorText.textRatio < TEXT_MIN) {
					findings.push({ theme, page: 'customer-create', kind: 'field-error-text', ratio: inv.errorText.textRatio, min: TEXT_MIN })
				}
			} else if (inv.ariaInvalid === null) {
				findings.push({ theme, page: 'customer-create', kind: 'invalid-state-absent', detail: 'empty submit produced neither aria-invalid nor visible error' })
			}
		}
	}
	report.findings = findings
	report.verdict = findings.length === 0 ? 'PASS' : 'FAIL'

	const outIdx = process.argv.indexOf('--out')
	const outPath = outIdx > 0 ? process.argv[outIdx + 1] : null
	if (outPath) {
		mkdirSync(dirname(outPath), { recursive: true })
		writeFileSync(outPath, JSON.stringify(report, null, 2))
		console.log(`wrote ${outPath}`)
	} else {
		console.log(JSON.stringify(report, null, 2).slice(0, 4000))
	}
	console.log(`contrast probe: ${report.verdict} (${findings.length} findings)`)
	process.exit(findings.length > 0 ? 1 : 0)
}

main().catch((e) => {
	console.error(e)
	process.exit(2)
})
