# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
