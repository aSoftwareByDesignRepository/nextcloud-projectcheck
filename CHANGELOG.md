# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
