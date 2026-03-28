# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.21 - 2026-03-27

### Added

- Standalone repository layout (private `nextcloud-projectcheck`): root `README.md`, `LICENSE`, `SECURITY.md`, `.github/FUNDING.yml`, and this changelog for App Store / SaaS-style publishing.

### Fixed

- Database API compatibility: use `executeQuery()` / `executeStatement()` instead of removed `QueryBuilder::execute()` on newer Nextcloud/DBAL.
- Safer date handling (`SafeDateTime`, services/mappers) to avoid `DateTime` construction from invalid or null values.
- JSON API error responses on time-entry routes when PHP throws `Error`/`Throwable` (avoid HTML 500 bodies on API calls).

### Changed

- `appinfo/info.xml` repository and bugs URLs now point at the **`nextcloud-projectcheck`** GitHub repository (private standalone app repo; see monorepo `ready2publish/REPOSITORY-LAYOUT.md`).
