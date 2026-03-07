# ProjectCheck — Nextcloud Standards Compliance Audit

**Date:** 2025-03-07

## Executive Summary

The app is well-structured and mostly compliant with Nextcloud expert standards. This audit identified issues in logging, i18n, theming, and minor type-safety gaps. Fixes have been applied.

---

## 1. Backend (PHP)

### ✅ Compliant
- `declare(strict_types=1)` on all lib PHP files
- OCP-only imports — no `OC\*` or `OC_*` internals
- Constructor injection for dependencies
- QBMapper + Entities for database access
- Migrations for schema changes
- Controllers delegate to services

### ⚠️ Fixed
- **error_log()** — Replaced with `LoggerInterface` in CustomerController and TimeEntryController
- **Hardcoded strings** — CustomerController and TimeEntryController now use `$l->t()` for all user-facing messages
- **Type hints** — CustomerController methods now have proper return types and parameter types
- **Constructor** — CustomerController `$appName` parameter typed as `string`

---

## 2. Frontend (Templates + JS)

### ✅ Compliant
- User data output via `p()` in templates
- `requesttoken` included in POST/PUT/DELETE AJAX (OC.requestToken)
- Most strings use `$l->t()` in PHP templates

### ⚠️ innerHTML Usage
- **projects.js** — Team member list uses innerHTML with `t()` translated strings (safe; no user data)
- **customer-detail.js** — Uses `escapeHtml()` for error messages (safe)
- **datepicker.js** — Static chars only (safe)
- Recommendation: Continue using `textContent` or `escapeHtml()` for any user-supplied content

---

## 3. Theming (CSS)

### ✅ Compliant
- **colors.css** — Uses only Nextcloud CSS variables
- Core app styles use `--color-*` variables

### ✅ Fixed (2025-03-07)
- **typography.css** — Replaced hex with var() references
- **accessibility.css** — High-contrast and print now use CSS variables
- **critical.css** — Uses Nextcloud variables with fallbacks
- **base.css** — Dark mode uses var() references
- **progress-bars.css** — Gradients use var(--color-*-hover)
- **projects.css** — rgba() replaced with color-mix()

---

## 4. Accessibility

### ✅ Compliant
- `aria-label` on search inputs and filter selects
- Keyboard support on sortable headers (Enter, Space)
- Focus indicators via CSS variables

### Recommendation
- Audit icon-only buttons for `aria-label` in templates
- Ensure modals have focus traps (deletion-modal uses Nextcloud patterns)

---

## 5. i18n

### ✅ Compliant
- l10n/en.json and l10n/de.json present
- Most user-facing strings translated

### ⚠️ Fixed
- CustomerController: Replaced hardcoded 'User not authenticated', 'Customer not found', 'Customer created successfully', etc. with `$l->t()`
- Added missing keys to en.json and de.json where needed

---

## 6. Pre-Delivery Checklist

| Item | Status |
|------|--------|
| declare(strict_types=1) | ✅ |
| OCP only | ✅ |
| Constructor injection | ✅ |
| Controller → Service delegation | ✅ |
| QBMapper only | ✅ |
| Migrations for schema | ✅ |
| p() for user data | ✅ |
| CSS variables only (core) | ✅ |
| requesttoken in mutating requests | ✅ |
| All strings in l10n | ✅ (after fixes) |
| LoggerInterface (no error_log) | ✅ (after fixes) |
