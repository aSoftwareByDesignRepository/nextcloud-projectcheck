# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.64 - 2026-05-30

### Fixed

- **Search field magnifier sat at the top of the input:** global `.lucide-icon-host { height: 1.125em }` overrode the search icon’s full-height positioning. The search icon host now uses `height: auto` with `top`/`bottom: 0` so it centres on the input. Moved the screen-reader hint outside `.employees-search` so it cannot affect layout.

## 2.0.63 - 2026-05-30

### Fixed

- **Employees table metric icons misaligned:** hours, revenue and rate cells now wrap icon + value in `.employees-metric` (inline-flex, centred on the cap-height). Replaces `inline-block` / `vertical-align: middle` rules that left clock, euro and trending-up glyphs high or low relative to the figures.

## 2.0.62 - 2026-05-30

### Fixed

- **Button icons sat below their labels:** hydrated icons render as `<i class="lucide-icon"><svg class="lucide-icon">…</svg></i>`. Global `vertical-align: baseline` left the SVG at the bottom of the host box (e.g. “View Details”). Icon hosts are now flex-centred (`lucide-icon-host` class from `icons.js`, with CSS fallbacks for direct-child `<i data-lucide>` in buttons).

## 2.0.61 - 2026-05-30

### Fixed

- **Revenue icon showed a flame, not a euro:** the shared `euro` icon used the Lucide *flame* path. Replaced it with the correct euro glyph, fixing the employees revenue column (and every other `icon-money-custom` usage).
- **Search magnifier misalignment:** the input icon inherited the global `-2px` icon nudge and was centred with a `transform`, leaving it visibly off. It now sits in a full-height flex box (perfectly centred on both axes) with the input's left padding matched to the icon box width.
- **Button icon/label alignment:** button glyphs now render as block SVGs with the `-2px` nudge neutralised, so the icon centres on the label cap-height.

### Changed

- **Search and table now share one panel:** the search toolbar, the ranked table and the pagination footer live in a single card (header → body → footer) instead of three stacked sections. The no-match empty state renders inside the same panel; when there is no data and no active search, a single clean empty card is shown without a pointless search box. Removed orphaned `.employees-filters--accessible` CSS.

## 2.0.60 - 2026-05-30

### Fixed

- **Employees page rendered unstyled:** the table and search CSS was scoped to an `.employees-page` wrapper that no longer existed in the DOM, so none of it applied. Rules are now scoped to the real `.pc-app--employees` shell class (matching the time-entries convention). The page-actions block also had an unclosed `<div>` that broke the layout; the markup is now balanced.
- **Team Overview totals were wrong with pagination:** the overview cards summed only the current page. Totals (employees, hours, revenue) are now computed server-side across the full, search-filtered result set.
- **Wrong empty state for no search matches:** searching with no results showed "No employees have logged time entries yet" instead of a dedicated "No employees match your search" state with a reset action.
- **Rank restarted on each page:** ranks are now continuous across paginated pages.
- **Search hardened:** non-string `search` params (e.g. `?search[]=`) are coerced safely before filtering.

### Changed

- **Employees page UX & accessibility (WCAG 2.1 AA):** native GET search `<form>` that works without JavaScript; labelled, keyboard-scrollable table with row headers and `scope` attributes; responsive stacked-card layout on small screens; decorative icons hidden from assistive technology; accessible per-row "View details for …" labels; visible focus styles and 44px touch targets. Added `search`, `search-x` and `rotate-ccw` icons to the shared icon catalog.

## 2.0.59 - 2026-05-30

### Fixed

- **Deletion modal close crash:** `ProjectCheckModalA11y.detach()` no longer re-enters `closeDeletionModal()` with a null `currentModal` reference.
- **Dependency load errors:** impact fetch handles non-JSON and HTTP error responses instead of throwing parse errors.
- **Customer delete with projects:** delete action available for editable customers; modal enforces cascade/reassign when projects exist; clearer server error messages.
- **Strategy radio layout:** overrides Nextcloud core `input { width: 130px }` so radios align inline with labels.

## 2.0.58 - 2026-05-30

### Fixed

- **Customer delete 403:** list template now substitutes `CUSTOMER_ID` in `data-delete-url` (same as show/edit links); JS resolves the placeholder before opening the deletion modal.

## 2.0.57 - 2026-05-30

### Fixed

- **Project delete returned 400** on active projects with team members: removed a guard that contradicted the deletion modal’s cascade (time entries and members are removed in the same transaction). Uploaded project files are purged before the project row is deleted.

## 2.0.56 - 2026-05-30

### Fixed

- **All destructive UI actions** now use `POST` + `FormData` `requesttoken` (files, employee project unassign, customers legacy path); removed remaining `DELETE` fetch paths in time-entries, project files fallback, and unreliable `_method=DELETE` on customer delete.
- **Deletion modal:** normalizes delete URLs for customers, files, and employee unassign; delete button gets an explicit `aria-label`.

### Added

- Routes/controllers: `projectfile#deletePost`, `employee#unassignProjectPost`.
- `ProjectCheckApi.postDelete()`; `del()` delegates to POST delete for CSP/CSRF safety.

## 2.0.55 - 2026-05-30

### Fixed

- **Project/time-entry/member deletion:** mutating deletes use `POST` + `FormData` `requesttoken` (`/projects/{id}/delete`, `/time-entries/{id}/delete`, `/members/{userId}/remove`) instead of `DELETE` with token in the query string (Nextcloud CSRF returned 400).
- **Deletion modal:** success path no longer shows a follow-up error toast after a successful delete.
- **Service worker:** register only on `/apps/projectcheck/` pages over HTTPS or localhost (avoids insecure registration when assets load from `custom_apps`).

### Changed

- **Templates:** project-detail team removal and time-entry detail use POST delete routes.

## 2.0.54 - 2026-05-30

### Added

- **Security tests:** controller tests ensure rate-resolution JSON never echoes raw `RateResolutionException` text (`ProjectController`, `EmployeeController`).

### Verified

- **221 PHPUnit** and **audit gates** (l10n, DB naming, Lucide) pass on host.
- **31 Playwright smoke/workflow tests** pass against Docker (`e2e/run-smoke.sh`).

## 2.0.53 - 2026-05-30

### Fixed

- **Feedback UI (XSS):** `messaging.js`, `components.js`, and `validation.js` build toasts, alerts, and modals with DOM APIs via shared `common/dom-ui.js` (no `innerHTML` for dynamic content).
- **Validation:** removed inline `onclick` dismiss handlers on form submission alerts (CSP-safe listeners).

### Changed

- **Modals:** `components.js` skips duplicate Escape/backdrop handlers when `ProjectCheckModalA11y` is loaded.

## 2.0.52 - 2026-05-30

### Fixed

- **Deletion modal:** rebuilt with DOM APIs only (no `innerHTML`); integrates `ProjectCheckModalA11y` focus trap; validates customer reassign strategy before submit.
- **Projects team modal:** member removal uses roster `id` for impact analysis URL (was incorrectly using `user_id` when impact fetch was enabled).
- **Projects list:** removed unused legacy `deleteProject()` path that surfaced raw API errors in `alert()`.

### Changed

- **Deletion modal UX:** `:focus-visible` styles, `prefers-reduced-motion`, `hidden` for reassign panel, `aria-live` on body.

## 2.0.51 - 2026-05-30

### Fixed

- **Security:** rate-resolution JSON APIs map errors by `code` via `RateResolutionMessage` (no raw exception text in responses).
- **XSS hardening:** team modal in `projects.js` builds roster rows with DOM APIs; shared `common/escape.js` for escaping.
- **Service worker:** removed unimplemented background-sync, IndexedDB queue, and push-notification stubs (cache + offline fallback only).
- **Offline page:** aligned with status-page layout, WCAG focus styles, and webroot-aware connectivity probe.

### Added

- **Tests:** `RateResolutionMessageTest`.

## 2.0.50 - 2026-05-30

### Fixed

- **Runtime schema repair:** `SchemaGuardMiddleware` and `SchemaGuardService` reconcile missing `pc_*` tables (including `pc_time_entries`) on the first app request, dashboard widget load, search, and cleanup job — fixes 500s when migrations were marked complete without creating every table.
- **Schema completeness:** `ProjectCheckTableCatalog` lists all runtime-required tables; repair also renames legacy rate tables and runs on fresh install (`repair-steps` install), matching BudgetCheck.
- **Schema repair concurrency:** exclusive lock prevents parallel DDL from concurrent requests; wait/retry when another request is repairing.
- **Security:** dashboard stats API no longer returns raw exception messages; schema-guard JSON uses translated user text only.
- **Error UX:** unified `templates/error.php` and `access-denied.php` with `status-pages.css`, CSP on schema errors, and `ErrorPageParams` helper.
- **Dashboard JS:** aligned with `overview-stat-compact` / `dashboard-card` DOM; live stat refresh via `data-dashboard-stat` attributes.
- **Dashboard widget:** “Add project” only when `canUserCreateProject`; schema failures logged instead of silent empty lists.
- **Error pages:** all controllers use `ErrorPageParams` / `ErrorPageTrait` (consistent layout, home + contextual back links, no raw exception text).
- **Customer pages:** untranslated error strings fixed; `EmployeeController` API errors no longer leak internal exception messages.

### Changed

- **Accessibility:** overview stats `aria-live="polite"`; recent project cards keyboard-activatable with visible `:focus-visible` styles.

### Added

- **Tests:** `ErrorPageParamsTest`, dashboard stats API security test (no SQL/internal leak on failure).

## 2.0.49 - 2026-05-27

### Added

- **Project edit — team callout:** Prominent callout on the project form deep-links to the team section on project detail, with permission-aware copy, member count, and secondary footer links.

### Changed

- **Time entries:** Clearer admin override messaging on project detail and the time-entry form; tighter server-side checks when adding time on behalf of others.
- **Team roster:** Improved rate-mode handling and project mapper hydration for member lists.

### Fixed

- **Tests:** `ProjectMapperHydrationTest`, `HourlyRateServiceTest`, and `ProjectServiceTest` cover team roster and rate-mode edge cases.

## 2.0.48 - 2026-05-26

### Fixed

- **Pricing mode cards:** disabled cards no longer show the primary focus ring; styles use `:has(:disabled)` so locked modes stay visually inactive over `:checked` / `:hover`.
- **Project files:** refreshed upload/dropzone layout and project-detail files UX.

### Changed

- **Rate history migrations:** consolidated rate-table bootstrap into `RateHistoryTables` / `RateTableRenamer`; added migration `Version2011Date20260526120000`.
- **`info.xml` dependencies:** declare `mysql` and `pgsql` under `<dependencies>` for DB standards linting.

## 2.0.47 - 2026-05-25

### Changed

- **DB-standards alignment (Oracle/MySQL/PostgreSQL portability):** every migration declares `: ?ISchemaWrapper` and wraps `createTable` / column additions in `hasTable` / `hasColumn` guards (idempotent across replays). Primary keys and indexes use explicit short names (`pc_*_pk`, `pc_*_idx`). `PcCoreSchemaBootstrap` helpers now return `false` instead of bailing early so re-runs after partial failures finish the schema.
- **Budget display: "Over by X" instead of negative remaining.** `BudgetService` now returns `over_budget_amount` alongside `is_over_budget` (computed via Money fixed-point so display never drifts). Project detail and budget bar both flip to "Over by X" via the new shared `templates/parts/budget-remaining-line.php` partial; the duplicate "Over Budget" breakdown row is removed.
- **Time entries:** moved the filter container CSS out of an inline `<style>` block (with CSP nonce) into `css/time-entries.css`.

### Added

- **`OCA\ProjectCheck\Repair\UninstallDropTables` + `<repair-steps><uninstall>` in `appinfo/info.xml`:** auto-generated drop list keeps every projectcheck table ever created in sync. Disabling the app now drops all `pc_*` (and legacy) tables, `migrations` rows, and app config — no orphan data on remove. Regenerate with `php scripts/check-nextcloud-db-standards.php sync-uninstall --app=projectcheck`.
- **l10n:** `Over by %s` (en: `Over by %s`, de: `Überschreitung um %s`).
- **Tests:** `BudgetServiceTest` covers the new `over_budget_amount` field.

### Bumped

- **Nextcloud `max-version`:** `33` (latest stable major).

## 2.0.46 - 2026-05-23

### Fixed

- **Automatic schema repair on every upgrade:** Post-migration repair step `EnsureProjectCheckSchema` runs on each `occ upgrade` (idempotent), reconciling legacy→`pc_*` renames, missing tables, and columns skipped when migrations 2007/2009 ran before `pc_projects` existed.
- **Centralized guard:** `ProjectCheckSchemaEnsurer` shared by migration `Version2010` and the repair step; fails loudly if core tables are still missing after repair.

## 2.0.45 - 2026-05-23

### Fixed

- **PostgreSQL / fresh installs:** Repair migration `Version2010` bootstraps core `pc_*` tables when neither legacy nor prefixed schema exists, and re-runs legacy→`pc_*` renames when only `oc_projects` (etc.) are present — fixes `relation "oc_pc_projects" does not exist` on Hub/AIO after upgrade from 2.0.36.
- **PostgreSQL:** Project list/detail queries no longer use `p.*` (invalid SQL `"p".,` on PostgreSQL); explicit column list via `ProjectQueryColumns`.

### Changed

- **Migrations:** Table rename logic extracted to `LegacyTableRenamer` (shared by `Version2006` and repair step).

## 2.0.44 - 2026-05-23

### Fixed

- **Team add (`project_member`):** Server error message matches client l10n (`Enter an hourly rate for this person before adding them to the project.`).

### Added

- **`scripts/run-all-release-gates.sh`:** One command for audit gates + Docker PHPUnit + E2E (when configured).

## 2.0.43 - 2026-05-23

### Security / UX

- **Team add (`project_member`):** Client validates hourly rate before POST (matches server); clear error in `#add-team-member-error`.

### Added

- **E2E:** UAT 5 (add member without rate); project form `type="date"` (UAT 14); mobile modal in dedicated describe (no desktop skip).

## 2.0.42 - 2026-05-23

### Security / WCAG

- **`ProjectCheckNotify`:** Centralized non-blocking feedback in `js/common/api.js` (`OC.Notification` + `#pc-alert-region`); removed `window.alert` fallbacks from project detail, files, rates, and employee assign flows.

### Added

- **E2E:** Employee-mode project create (UAT 2); native `type="date"` check (UAT 14); mobile add-member modal width (UAT 12); Escape closes team picker (UAT 4).
- **Release:** `release/AUDIT-EVIDENCE.md` for external audit.

## 2.0.41 - 2026-05-23

### Changed

- **Org settings:** Uses shared `page-start` / `page-end` shell (skip link, live regions, one `h1`, `#projectcheck-org-main` preserved for anchors/E2E).
- **Shell:** `page-start.php` supports optional kicker, custom main id, and wrapper classes.

### Added

- **E2E:** Project-mode create with rate (UAT 1); pricing mode locked on edit after time entry (UAT 9 / E6).

## 2.0.40 - 2026-05-23

### Fixed

- **Employee detail assign:** `project_member` projects show a proactive hint and an accessible **Open project team page** link (not a raw URL in notifications); assign `<option>` elements expose `data-cost-rate-mode` and team URL.

### Added

- **E2E:** `cost-pricing-uat.spec.js` — keyboard team picker (UAT 4), employee `project_member` assign API + UI hint (UAT 11), `prefers-reduced-motion` scroll (UAT 15); full suite **22 tests**.
- **PHPUnit:** `EmployeeControllerTest::testAssignProjectRejectedForProjectMemberMode`.

## 2.0.39 - 2026-05-23

### Fixed

- **Time entry resolve API:** `GET /api/projects/{id}/resolve-hourly-rate` marked `NoCSRFRequired` (read-only, session + access checks) so the time-entry form can load rates without “CSRF check failed”.
- **Time entry form:** Project `<option>` elements expose `data-cost-rate-mode` for clients and E2E.
- **E2E harness:** `run-smoke.sh` runs `occ upgrade` when `needsDbUpgrade`, refreshes auth cookies every run, and detects the Nextcloud “App update required” screen; `mutations.spec.ts` targets personal settings (`/settings/user/projectcheck`) so validation returns 400, not stale HTML 200.

### Added

- **E2E:** `cost-pricing-workflows.spec.js` (create `project_member`, detail pricing badge, resolve rate poll); `auth-guard.js` `gotoApp()` helper; 19 Playwright tests in `bash e2e/run-smoke.sh` (smoke + workflows + mutations + route guards).

## 2.0.38 - 2026-05-23

### Added

- **E2E:** Playwright `e2e/cost-pricing-smoke.spec.js` (320px + desktop): app shell, pricing cards, readonly hourly rate, settings hint, unauthenticated resolve API (E21). Run: `bash e2e/run-smoke.sh`.

### Fixed

- **Icons:** All legacy `icon-*-custom` classes (calendar, user, chart, add, edit, delete, folder, file, …) hydrate via `js/common/icons.js` without loading `custom-icons.css`.
- **WCAG:** Destructive actions use `projectcheckDeletionModal` only — removed remaining `window.confirm()` on time entry detail, team removal, file delete, and project list fallbacks.
- **Time entry form:** Hourly rate field uses `aria-required` instead of HTML `required` so the browser does not block submit before server resolve completes.
- **Org settings:** Skip link and `pc-live-region` / `pc-alert-region` for screen-reader parity with other pages.

## 2.0.37 - 2026-05-22

### Added

- **Cost pricing (M1–M5):** `cost_rate_mode` on projects (`project`, `employee`, `project_member`); append-only `pc_employee_hourly_rates` and `pc_project_member_hourly_rates`; `HourlyRateService` resolves bill rates on the server for time entries; GET `/api/projects/{id}/resolve-hourly-rate`; employee rate admin API.
- **UX:** Project form pricing cards, post-create banner → team section, per-person rate on add-member modal and inline team list, employee rate history (admin), locked hourly rate on time entry form with live resolve, budget preview includes current entry cost (B7).
- **Shell (M6):** `page-start` / `page-end`, `scope-strip-project`, `css/app.css` (tokens + shell + colors); primary pages use one `h1` + skip link + live regions; customer detail + employees use page shell; extracted `project-detail.js`, `project-detail-files.js`, `project-detail-rates.js`; `js/common/entity-picker.js` for accessible team user search; `scripts/validate-lucide-icons.sh`, `scripts/sync-l10n-missing.php`.
- **Tests:** PHPUnit suite includes all `tests/Unit/Service/*`; resolve API (A6), `projectHasLoggedTime`, E6 mode lock on update, employee rates, E15/E16 re-resolve, `addAll` blocked for `project_member` (T2.07); **170 tests** green.
- **Release:** `scripts/run-audit-gates.sh` for §15.3 gates; `release/COST-PRICING-MIGRATION.md` for operators/upgrades.
- **Dates (WCAG §10):** Native `<input type="date">` with `lang` on time entry form, project form (start/end), and time entries list filters; `htmlLang` injected on all enriched templates.
- **l10n:** 97+ pricing/security strings added to `en.json` / `de.json` with parity check.

### Security

- **P0 A1–A6:** Time entries no longer trust client `hourly_rate`; tamper check (±0.009); re-resolve on project/date change; project cost totals use `Money` row sums; resolve API requires project access; IDOR checks on rate APIs.

### Changed

- **Team:** `add-all` disabled for `project_member` mode; member rate updates append history rows instead of remove/re-add.
- **Mode lock:** `cost_rate_mode` locked after first time entry on a project (unlocks when all entries removed); locked-mode hidden field moved outside disabled fieldset so HTML submit is reliable.
- **Icons:** Legacy `icon-time-custom` / `icon-money-custom` hydrated centrally in `js/common/icons.js` (removed per-page inline scripts and `custom-icons.css` on customer detail).

## 2.0.36 - 2026-05-12

### Fixed

- **App Store:** `donation`, `website`, and author `homepage` in `appinfo/info.xml` use `https://` URLs so they validate against the store schema (`donation` requires pattern `https://.+`).

### Changed

- **Release:** Nextcloud `max-version` set to current stable major **33** (upstream lookup during release prep).

## 2.0.35 - 2026-05-12

### Fixed

- **App Store:** `donation`, `website`, and author `homepage` in `appinfo/info.xml` use `https://` URLs so they validate against the store schema (`donation` requires pattern `https://.+`).

## 2.0.34 - 2026-05-12

### Fixed

- **PostgreSQL:** Yearly and project-type statistics no longer use MySQL-only `YEAR()`; portable `EXTRACT(YEAR FROM …)` (and SQLite `strftime`) via `SqlPortableExpressions` so dashboard and analytics load on PostgreSQL.
- **PostgreSQL:** Raw SQL in `createFunction()` no longer uses MySQL identifier backticks for display-name `COALESCE` fragments; migration `Version2008` name-trim `UPDATE` is ANSI-quoted (`*PREFIX*` only) so it runs on PostgreSQL.
- **Time entries list:** `findWithProjectInfo` uses `selectAlias` + portable coalesce; `count()` applies the same joins and filters as the list when searching or filtering by `project_type`, so pagination totals stay consistent.
- **Search:** `findAll` uses case-insensitive `iLike` with `escapeLikeParameter`, matching list/count behaviour on PostgreSQL.

### Changed

- **`project_type`:** Runtime assumes migration `Version2007` has run (column always present); defensive `columnExists` / insert-retry paths removed from `ProjectService` and `TimeEntryMapper` in favour of a single clear schema contract after `occ upgrade`.
- **Release metadata:** App Store prep script aligned `package.json` and `info.xml` to **2.0.34**; Nextcloud `max-version` set to current stable major **33** (per upstream lookup). PHPUnit suite includes `tests/Unit/Db`.

### Removed

- `ColumnIntrospectionTrait` (superseded by the migration-backed `project_type` column).

## 2.0.32 - 2026-05-08

### Fixed

- Budget overview: clearer UI state handling when loading or when data is empty.
- Organization currency settings: stricter validation and safer persistence of the default currency.

### Changed

- Dashboard widgets: prefer dark-themed app icons where the server exposes them, for better contrast on the dashboard.
- Release metadata for App Store: version `2.0.32`, package alignment, and Nextcloud `32–33` compatibility (`max-version` aligned with current stable server major `33`).

## 2.0.31 - 2026-05-06

### Fixed

- Time entries page (`/apps/projectcheck/time-entries`) crashed with
  `SQLSTATE[42S22] Unknown column ':dcValue1'` on installs whose
  `pc_projects.project_type` column was missing. The fallback `SELECT`
  branch in `TimeEntryMapper::findWithProjectInfo()` was emitting a named
  parameter placeholder as if it were a column identifier; that branch is
  now removed in favour of the existing PHP-level fallback to `'client'`.

### Added

- New migration `Version2007Date20260506180000` ensures that
  `pc_projects.project_type` always exists (`varchar(32) NOT NULL DEFAULT
  'client'`) and is indexed (`pc_proj_type_idx`) on every install,
  including fresh installs and partial upgrades from the legacy
  `projectcontrol` schema. Idempotent and cross-database safe.

### Changed

- Consolidated the two divergent `columnExists()` helpers in
  `TimeEntryMapper` and `ProjectService` into a shared
  `ColumnIntrospectionTrait`. The new helper is portable across MySQL,
  PostgreSQL and SQLite, immune to false negatives on empty tables, and
  caches results for the request lifetime so a typical request now
  performs at most one introspection probe per `(table, column)` pair.

## 2.0.30 - 2026-05-05

### Changed

- Database migration hardening for production upgrades: legacy generic table and
  identifier names are normalized to `pc_*` names with idempotent upgrade-safe
  migrations and cross-database compatibility safeguards.

## 2.0.29 - 2026-05-03

### Changed

- Prepared ProjectCheck `2.0.29` release metadata for App Store publishing with aligned package versioning and Nextcloud `32-33` compatibility.

## 2.0.28 - 2026-04-30

### Changed

- Finalized organization-scoped settings and deletion workflow hardening across controllers, templates, listener wiring, and localization payloads.
- Release operator guidance now explicitly requires a new app version for each published update to ensure upgrade visibility in Nextcloud instances.

## 2.0.27 - 2026-04-26

### Changed

- Release metadata alignment for App Store publishing: bumped `appinfo/info.xml` and `package.json` to `2.0.27`, and aligned declared Nextcloud compatibility to `32-33` for this validated release line.
- Release documentation refreshed (`README.md`, `release/APPSTORE-RELEASE.md`) to match the current support policy and current release placeholder examples.

## 2.0.25 - 2026-04-05

### Changed

- Documentation: public-facing bilingual **README** (German / English), aligned with ArbeitszeitCheck-style structure; screenshots and install/support sections for the standalone repository.

## 2.0.24 - 2026-04-05

### Fixed

- `info.xml` screenshot URLs: use `refs/heads/main` (standalone repo default branch), not `master`, so `raw.githubusercontent.com` links resolve.

## 2.0.23 - 2026-04-05

### Added

- Nine App Store screenshots (`screenshots/projectcheck-screenshot-01.png` … `09.png`), referenced in `info.xml` with HTTPS `raw.githubusercontent.com` URLs (same pattern as ArbeitszeitCheck).

## 2.0.22 - 2026-04-05

### Added

- `appinfo/info.xml`: `<donation>` (same destination as ArbeitszeitCheck `.github/FUNDING.yml` custom link) for App Store listing parity.
- `release/build-appstore-archive.sh` to build a signed-upload-ready `.tar.gz` (npm + webpack + `composer install --no-dev`, excludes `node_modules`, tests, and extra release artifacts).
- PHPUnit layout under `tests/Unit/Controller` with `composer.json` / `nextcloud/ocp` dev tooling; `composer test` runs the controller unit suite.

### Changed

- `composer.json`: valid package name, reproducible `composer.lock`, dev dependencies for tests (PHPUnit, OCP stubs, Doctrine DBAL for interface constants).
- `appinfo/info.xml`: SPDX licence `AGPL-3.0-or-later`; author `mail` / `homepage` aligned with ArbeitszeitCheck.
- `README.md` and `release/APPSTORE-RELEASE.md`: aligned with the official [App Developer Guide](https://nextcloudappstore.readthedocs.io/en/latest/developer.html) (certificate, register app, upload release, metadata, blacklisted files).

### Removed

- Unused CSP debug `TestController` and `templates/test.php`; tests moved out of `lib/` into `tests/`.

## 2.0.21 - 2026-03-27

### Added

- Standalone repository layout (private `nextcloud-projectcheck`): root `README.md`, `LICENSE`, `SECURITY.md`, `.github/FUNDING.yml`, and this changelog for App Store / SaaS-style publishing.

### Fixed

- Database API compatibility: use `executeQuery()` / `executeStatement()` instead of removed `QueryBuilder::execute()` on newer Nextcloud/DBAL.
- Safer date handling (`SafeDateTime`, services/mappers) to avoid `DateTime` construction from invalid or null values.
- JSON API error responses on time-entry routes when PHP throws `Error`/`Throwable` (avoid HTML 500 bodies on API calls).

### Changed

- `appinfo/info.xml` repository and bugs URLs now point at the **`nextcloud-projectcheck`** GitHub repository (canonical public home for ProjectCheck only; optional monorepo layout: `ready2publish/REPOSITORY-LAYOUT.md`).
