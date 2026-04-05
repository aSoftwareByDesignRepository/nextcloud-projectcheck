# ProjectCheck

[![Nextcloud](https://img.shields.io/badge/Nextcloud-32–37-0082c9?logo=nextcloud&logoColor=white)](https://nextcloud.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2–8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL--3.0-blue.svg)](LICENSE)
[![App Store](https://img.shields.io/badge/Install-Nextcloud%20App%20Store-0082c9)](https://apps.nextcloud.com/apps/projectcheck)

**[Deutsch](#deutsch)** · **[English](#english)**

---

## Screenshots

<p align="center">
  <a href="https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-01.png"><img src="https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-01.png" alt="ProjectCheck" width="32%" /></a>
  <a href="https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-02.png"><img src="https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-02.png" alt="ProjectCheck" width="32%" /></a>
  <a href="https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-03.png"><img src="https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-03.png" alt="ProjectCheck" width="32%" /></a>
</p>

Weitere Ansichten / More views: [4](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-04.png) · [5](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-05.png) · [6](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-06.png) · [7](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-07.png) · [8](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-08.png) · [9](https://raw.githubusercontent.com/aSoftwareByDesignRepository/nextcloud-projectcheck/refs/heads/main/screenshots/projectcheck-screenshot-09.png) — Galerie / gallery: [App Store](https://apps.nextcloud.com/apps/projectcheck)

---

## Deutsch

**Projekte, Zeiterfassung und Budgets dort, wo Ihr Team ohnehin arbeitet — in Nextcloud.**

Schluss mit Wechseln zwischen Tabellenkalkulation und dem nächsten SaaS-Tool: **ProjectCheck** bündelt **Kunden**, **Projekte**, **Zeiterfassung** und **Budgettransparenz** in Ihrer selbst gehosteten Nextcloud. Führungskräfte und Mitarbeitende teilen eine gemeinsame Sicht auf Status, Stunden und Kosten. Arbeitszeit wird am Projekt erfasst, der Budgetstand bleibt nachvollziehbar — ohne dass Projektdaten Ihre Infrastruktur verlassen.

### Funktionen

- **Kunden** — Mandanten strukturiert pflegen und von dort zu Projekten und Aktivität springen.
- **Projekte** — Arbeit planen und verfolgen mit Status, Terminen, Suche und Sortierung; optionale Budgets zeigen, was noch übrig ist.
- **Team** — Nextcloud-Benutzer zu Projekten einladen und mit klaren Rollen und Rechten arbeiten.
- **Zeiterfassung** — Zeiten auf Projekten buchen, Einträge filtern und bei Bedarf exportieren (z. B. für Abrechnung oder Reviews).
- **Budgets** — Verbrauch gegen Budgets verfolgen, damit Überschreitungen früh sichtbar werden.
- **Dashboard** — Schneller Überblick über das Wesentliche im Projektportfolio.
- **Dateien** — Dateien am Projekt ablegen, damit Kontext bei der Arbeit bleibt.
- **Einstellungen** — Anpassungen über Admin- und persönliche Einstellungen in Nextcloud.

### Installation

**Aus dem Nextcloud App Store (empfohlen)**

1. Als **Nextcloud-Administrator** anmelden  
2. **Apps** öffnen  
3. Nach **ProjectCheck** suchen  
4. App **herunterladen und aktivieren** — [Eintrag im App Store](https://apps.nextcloud.com/apps/projectcheck)

**Voraussetzungen:** Nextcloud **32–37**, PHP **8.2–8.5**, Datenbank **MySQL/MariaDB** oder **PostgreSQL**.

**Manuelle Installation aus Git** (z. B. für eigene Builds): Repository [nextcloud-projectcheck](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck) nach `apps/projectcheck` klonen, `composer install --no-dev` ausführen, Frontend mit `npm ci && npm run build` bauen, im Nextcloud-Stammverzeichnis `php occ app:enable projectcheck` ausführen.

### Dokumentation

- **Versionshistorie:** [`CHANGELOG.md`](CHANGELOG.md) (Keep a Changelog).

### Sicherheit

Sicherheitsprobleme bitte **nicht** öffentlich in GitHub-Issues melden. Hinweise zur verantwortungsvollen Meldung: **[SECURITY.md](SECURITY.md)**.

### Projekt & Support

**ProjectCheck** wird von **Software by Design** entwickelt und gepflegt.  
Website: [software-by-design.de](https://software-by-design.de/) · E-Mail: [info@software-by-design.de](mailto:info@software-by-design.de)  
Quellcode und Issues: [github.com/aSoftwareByDesignRepository/nextcloud-projectcheck](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)

### Lizenz

[GNU Affero General Public License v3.0 oder später](LICENSE) (AGPL-3.0-or-later).

---

## English

**Run projects, time tracking, and budgets where your team already works — inside Nextcloud.**

Stop switching between spreadsheets and yet another cloud tool. **ProjectCheck** brings **customers**, **projects**, **time tracking**, and **budget awareness** into your self-hosted Nextcloud — so managers and contributors share one place for status, hours, and spend. Log work as it happens, see how budgets are doing, and catch trouble early, without your project data leaving your infrastructure.

### Features

- **Customers** — Keep clients organised and jump from a customer to their projects and recent activity.
- **Projects** — Plan and track work with statuses, dates, search, and sorting; optional budgets help you see what is left.
- **Team** — Invite Nextcloud users to projects and work with clear roles and permissions.
- **Time** — Record time on projects, browse and filter entries, and export when you need numbers for billing or reviews.
- **Budgets** — Follow consumption against budgets so overruns are visible before they surprise you.
- **Dashboard** — Get a quick overview of what matters across your portfolio.
- **Files** — Attach files to projects so context stays next to the work.
- **Settings** — Tune the app with admin and personal options in Nextcloud settings.

### Installation

**From the Nextcloud App Store (recommended)**

1. Sign in as a **Nextcloud administrator**  
2. Open **Apps**  
3. Search for **ProjectCheck**  
4. **Download and enable** — [App Store listing](https://apps.nextcloud.com/apps/projectcheck)

**Requirements:** Nextcloud **32–37**, PHP **8.2–8.5**, and a supported database (**MySQL/MariaDB** or **PostgreSQL**).

**Install from git** (for custom builds): clone [nextcloud-projectcheck](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck) into `apps/projectcheck`, run `composer install --no-dev`, build the frontend with `npm ci && npm run build`, then from your Nextcloud root run `php occ app:enable projectcheck`.

### Documentation

- **Changelog:** [`CHANGELOG.md`](CHANGELOG.md) (Keep a Changelog).

### Security

Please do not report security issues in public GitHub issues. See **[SECURITY.md](SECURITY.md)** for responsible disclosure.

### Project & support

**ProjectCheck** is developed and maintained by **Software by Design**.  
Website: [software-by-design.de](https://software-by-design.de/) · E-mail: [info@software-by-design.de](mailto:info@software-by-design.de)  
Source code and issues: [github.com/aSoftwareByDesignRepository/nextcloud-projectcheck](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)

### License

[GNU Affero General Public License v3.0 or later](LICENSE) (AGPL-3.0-or-later).
