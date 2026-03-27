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

### Documentation

- **Release workflow** (App Store tarball, signatures, GitHub Releases): `release/APPSTORE-RELEASE.md`
- **Syncing this app from a private monorepo** (git subtree): `release/STANDALONE_REPO.md`

### Project & support

Developed and maintained by **Software by Design**.  
Website: https://software-by-design.de/ · E-mail: info@software-by-design.de

### License

SPDX: **AGPL-3.0-or-later** — see [`LICENSE`](LICENSE) and `appinfo/info.xml`.
