# ProjectCheck

**IT project management, time tracking, and budgets — on your own Nextcloud.**

[![Nextcloud](https://img.shields.io/badge/Nextcloud-32–37-0082c9?logo=nextcloud&logoColor=white)](https://nextcloud.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2–8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![App Store](https://img.shields.io/badge/Install-Nextcloud%20App%20Store-0082c9)](https://apps.nextcloud.com/apps/projectcheck)

## About

**ProjectCheck** is a Nextcloud app for teams that run **IT projects** and need **time tracking** and **budget visibility** without sending data to another SaaS. You manage **customers**, **projects**, **team membership**, and **time entries** in one place; budgets and consumption update as people log work, so you can spot risk before the invoice does.

Typical flow: define **customers**, create **projects** (with status, dates, and optional budget lines), assign **team members** with roles, record **time** against projects, and use the **dashboard** and lists to filter, search, and export. **Administrative and personal settings** let you tune behaviour (including email notifications where configured). **Project files** can be attached to projects for lightweight document context.

Data stays on **your** server under **AGPL-3.0-or-later**. The app targets MySQL/MariaDB and PostgreSQL on common platforms (see `appinfo/info.xml`).

**[`nextcloud-projectcheck`](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)** is the official public home for source code, issues, and releases.

**Repository layout:** In the standalone Git repository, the **repository root is this app** — the same file tree as `apps/projectcheck/` beside a Nextcloud `apps/` directory. There is no multi-app workspace or server core in that repo; it is published with `git subtree split --prefix=apps/projectcheck` from some developers’ full checkouts. Paths in this README (`release/…`, `LICENSE`) are relative to the app root.

---

## Features

| Area | What you get |
|:-----|:-------------|
| **Customers** | Organise work by client; drill into related projects and activity. |
| **Projects** | Create and manage projects with status, dates, sorting, search, and filters; optional budgets and progress signals. |
| **Team & roles** | Add Nextcloud users to projects and manage permissions for collaboration. |
| **Time entries** | Log time against projects; list, search, and export for reporting. |
| **Budgets** | Track consumption against budgets so overrun risk stays visible. |
| **Dashboard** | Overview and statistics to see status at a glance. |
| **Files** | Upload and manage files attached to a project. |
| **Settings** | Admin and personal settings (see the Nextcloud settings integration for ProjectCheck). |

---

## Screenshots

Preview the UI on GitHub (same images as the App Store listing):

- [Screenshot 1](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-01.png) · [2](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-02.png) · [3](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-03.png) · [4](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-04.png) · [5](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-05.png) · [6](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-06.png) · [7](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-07.png) · [8](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-08.png) · [9](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-09.png)

Store assets for releases live in the **`screenshots/`** directory on the default branch of the public repository.

---

## Install

**Recommended:** In Nextcloud as an administrator, open **Apps**, search for **ProjectCheck**, then **Download / Enable**  
([App Store entry](https://apps.nextcloud.com/apps/projectcheck))

**From source** (development or custom installs):

```bash
git clone https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck.git /path/to/nextcloud/apps/projectcheck
cd /path/to/nextcloud/apps/projectcheck
composer install --no-dev
# Optional: build frontend assets
npm ci && npm run build
cd /path/to/nextcloud
php occ app:enable projectcheck
```

**Requirements:** See **`appinfo/info.xml`** — Nextcloud **32–37**, PHP **8.2–8.5**, supported databases as listed under `<release>`.

---

## Developers

```bash
composer install
composer test
```

Runs the PHPUnit unit suite (see `composer.json` and `tests/`). Frontend: `npm ci && npm run build` (or `npm run dev` with watch during development).

---

## Publishing to the App Store

Follow the **[App Developer Guide](https://nextcloudappstore.readthedocs.io/en/latest/developer.html)**. ProjectCheck-specific packaging and releases: **[`release/APPSTORE-RELEASE.md`](release/APPSTORE-RELEASE.md)**.

Build helper: [`release/build-appstore-archive.sh`](release/build-appstore-archive.sh). Run it from a checkout where the app is at the **repository root** (the standalone app repo), or from a private monorepo with the **same** tree under `apps/projectcheck/` — see the script’s comments.

`appinfo/info.xml` sets **`<repository>`** and **`<bugs>`** to the GitHub project above.

### Optional: syncing from a private monorepo

If you develop ProjectCheck inside a larger repository, see **[`release/STANDALONE_REPO.md`](release/STANDALONE_REPO.md)**. Publishing uses `git subtree` and a helper script run from the **monorepo root** (for example `scripts/push-public-app-subtree.sh`); that script is **not** part of the standalone app tree — only the workflow in `release/STANDALONE_REPO.md` applies. Most users and contributors only need the public **`nextcloud-projectcheck`** repository.

---

## Security

Do not report vulnerabilities in public issues. See **[`SECURITY.md`](SECURITY.md)** for responsible disclosure.

---

## Project & support

**Software by Design** — [software-by-design.de](https://software-by-design.de/) · [info@software-by-design.de](mailto:info@software-by-design.de)

---

## License

**AGPL-3.0-or-later** — see [`LICENSE`](LICENSE) and `appinfo/info.xml`.
