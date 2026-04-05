# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.22 - 2026-04-05

### Added

- `release/build-appstore-archive.sh` to build a signed-upload-ready `.tar.gz` (npm + webpack + `composer install --no-dev`, excludes `node_modules`, tests, and extra release artifacts).
- PHPUnit layout under `tests/Unit/Controller` with `composer.json` / `nextcloud/ocp` dev tooling; `composer test` runs the controller unit suite.

### Changed

- `composer.json`: valid package name, reproducible `composer.lock`, dev dependencies for tests (PHPUnit, OCP stubs, Doctrine DBAL for interface constants).
- `appinfo/info.xml`: SPDX licence `AGPL-3.0-or-later`.
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

- `appinfo/info.xml` repository and bugs URLs now point at the **`nextcloud-projectcheck`** GitHub repository (private standalone app repo; see monorepo `ready2publish/REPOSITORY-LAYOUT.md`).
