# ProjectCheck — operations & audit

## Backups

- **Database:** Include the Nextcloud application tables (or full DB export). ProjectCheck stores app data in standard app tables and `oc_appconfig` keys under `projectcheck`.
- **Files:** If you use project file attachments, back up the same **data directory** (or object storage) that Nextcloud uses.

## Forensic log lines

After a successful save of **organization** (access) settings from the in-app page or API:

- Application log: `projectcheck org policy saved` (JSON `event`: `org_policy_changed`, `actor` UID, `flags` with boolean/list-count metadata — no allowlist contents).
- If the [admin_audit](https://github.com/nextcloud/admin_audit) app is enabled, a line is also emitted via `CriticalActionPerformedEvent` (actor and high-level change summary).

**Denied** save attempts: `projectcheck org save denied` (warning level).

**User lifecycle:** On account deletion, `removeUserFromAllProjectMembershipsForDeletedUser` runs; you may see `ProjectCheck: removed project team memberships for deleted user` with a **row count** (no PII in that message body beyond what your log format adds).

## Emergency: restore access to ProjectCheck

If a misconfiguration locked out all non-system admins:

1. Sign in as a **Nextcloud system administrator** (the `admin` group). System admins are still allowed by design.
2. Or use `occ` from the server (paths and user ids are examples):

   ```bash
   php occ config:app:get projectcheck access_restriction_enabled
   php occ config:app:set projectcheck access_restriction_enabled --value=0
   ```

3. Rebuild allowlists under **Administration → ProjectCheck** or the in-app **Organization** page as appropriate.

Do **not** share internal recovery steps or production credentials in public issues.

## Reproducible build (release)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## Supply chain (CI)

- `composer audit` / `npm audit` should run in your pipeline; treat **critical** advisories on production dependencies as release blockers unless explicitly accepted with a ticket.

## Accessibility (WCAG 2.1 AA)

Automated checks (e.g. axe, Lighthouse) help but do not replace **keyboard** navigation and a **screen reader** pass on the organization form. Track issues for any tool-only false negatives.

## Sidebar layout

The app **must not** inject `<script>` between `#app-navigation` and `#app-content` (it breaks the flex row). Nav icon logic lives in `js/navigation-icons.js` and is registered with `OCP\Util::addScript` from `templates/common/navigation.php`.

## E2E (optional)

With a running Nextcloud and a test user (see `e2e/README.md`):

```bash
export BASE_URL=https://nextcloud.local
export E2E_USER=…
export E2E_PASS=…
npm run e2e
```
