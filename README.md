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

The **canonical** steps are the **[App Developer Guide](https://nextcloudappstore.readthedocs.io/en/latest/developer.html)** (App Store docs / Read the Docs): *Obtaining a Certificate* → *Registering an App* → *Uploading an App Release*, plus *App metadata* (`info.xml`, `CHANGELOG.md`) and *Blacklisted files* (archives must not contain `.git/`).

ProjectCheck-specific commands, tarball layout, and GitHub Release notes: **`release/APPSTORE-RELEASE.md`**.

Summary:

1. **Certificate:** [Obtaining a Certificate](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate) — CSR PR to [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests); keep `~/.nextcloud/certificates/projectcheck.key` private.
2. **Register app id:** [Registering an App](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#registering-an-app) — paste `projectcheck.crt` and the `echo -n "projectcheck" | openssl dgst …` signature.
3. **Release:** [Uploading an App Release](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#uploading-an-app-release) — host `projectcheck-X.Y.Z.tar.gz` at an **HTTPS** URL, then submit that URL and the `openssl dgst -sha512 -sign …` signature over the **same** file. Build helpers: `./release/build-appstore-archive.sh`.

**Public OSS source:** point `repository` / `bugs` in `info.xml` at a **public** GitHub repo. From this monorepo, push the app subtree with `scripts/push-public-app-subtree.sh` (see `release/STANDALONE_REPO.md`).

### Documentation

- **Release workflow** (aligned with the official App Developer Guide): `release/APPSTORE-RELEASE.md`
- **Syncing this app from a private monorepo** (git subtree): `release/STANDALONE_REPO.md`

### Project & support

Developed and maintained by **Software by Design**.  
Website: https://software-by-design.de/ · E-mail: info@software-by-design.de

### License

SPDX: **AGPL-3.0-or-later** — see [`LICENSE`](LICENSE) and `appinfo/info.xml`.
