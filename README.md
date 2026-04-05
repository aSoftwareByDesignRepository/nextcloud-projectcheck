# ProjectCheck — IT project management for Nextcloud

**ProjectCheck** is a Nextcloud app for **IT project management** with **time tracking**, **team roles**, and **budget monitoring**. All data stays on your self-hosted instance.

### Features

- Projects and customers, status and date ranges
- Team membership and permissions
- Time entries with optional budget consumption and alerts
- Dashboard and search integration
- Activity and notifications where configured

### Installation

**From the Nextcloud App Store (recommended)**  
Search for **ProjectCheck** under Apps and enable it.

**From this repository (manual / development)**

```bash
git clone https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck.git /path/to/nextcloud/apps/projectcheck
cd /path/to/nextcloud/apps/projectcheck
composer install --no-dev
# If you build frontend assets from source:
# npm ci && npm run build
cd /path/to/nextcloud
php occ app:enable projectcheck
```

Requirements are declared in `appinfo/info.xml` (Nextcloud and PHP versions).

### Tests (developers)

```bash
composer install
composer test
```

This runs the PHPUnit **controller** unit suite (`tests/Unit/Controller`). Frontend: `npm ci && npm run build`.

### Publishing on the Nextcloud App Store

1. **Certificate:** Generate a key and CSR, open a PR on [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests), store `projectcheck.crt` next to your private key under `~/.nextcloud/certificates/`. Official guide: [App Developer Guide — Obtaining a Certificate](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate).
2. **Register the app id** once at [apps.nextcloud.com](https://apps.nextcloud.com) (developer account → register app) using your public certificate and the app-id signature from the docs.
3. **Ship a version:** bump `appinfo/info.xml` and `CHANGELOG.md`, then build and sign the tarball as described in **`release/APPSTORE-RELEASE.md`** (includes `./release/build-appstore-archive.sh` and upload steps).

**Public OSS source:** point `repository` / `bugs` in `info.xml` at a **public** GitHub repo. From this monorepo, push the app subtree with `scripts/push-public-app-subtree.sh` (see `release/STANDALONE_REPO.md`).

### Documentation

- **Release workflow** (App Store tarball, signatures, GitHub Releases): `release/APPSTORE-RELEASE.md`
- **Syncing this app from a private monorepo** (git subtree): `release/STANDALONE_REPO.md`

### Project & support

Developed and maintained by **Software by Design**.  
Website: https://software-by-design.de/ · E-mail: info@software-by-design.de

### License

SPDX: **AGPL-3.0-or-later** — see [`LICENSE`](LICENSE) and `appinfo/info.xml`.
