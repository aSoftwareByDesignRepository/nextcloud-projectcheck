# ProjectCheck — audit evidence pack (living)

**App version:** 2.0.86  
**Last regenerated:** 2026-07-27  
**Tracks covered:** cost-pricing (M0–M7 @ 2.0.44), settlement (M0–M7 @ 2.0.75), mobile companion v1/v1.1 (@ 2.0.83–2.0.86)

This file replaces the older cost-pricing-only `AUDIT-EVIDENCE.md` referenced by planning docs.
It is the operator/auditor index into automated gates — not a substitute for running them.

---

## Version lockstep

| Surface | Value |
|---------|--------|
| `appinfo/info.xml` | 2.0.86 |
| `appinfo/version` | 2.0.86 |
| `package.json` | 2.0.86 |
| Mobile companion | 1.1.0 (`mobile/projectcheck`) |

---

## Automated gates (run these)

From `nextcloud/`:

```bash
./docker/run-app-phpunit.sh projectcheck
docker compose exec -u www-data nextcloud php custom_apps/projectcheck/tests/Mutation/run-mobile-booking-mutations.php
docker compose exec -u www-data nextcloud php custom_apps/projectcheck/tests/Mutation/run-pc2-license-mutations.php
```

From `nextcloud/apps/projectcheck/` (host):

```bash
bash scripts/run-all-release-gates.sh   # PHPUnit + l10n + DB naming + Lucide + full E2E
npm run db:naming-check
bash e2e/run-smoke.sh                   # full Playwright suite incl. settlement smoke
```

From `mobile/projectcheck/` (host):

```bash
npm run typecheck && npm run i18n:parity
npm run test:coverage -- --runInBand
npm run mutate:core
```

---

## Schema (Docker-verified expectations)

- Cost-pricing: `pc_projects.cost_rate_mode`, `pc_employee_hourly_rates`, `pc_project_member_hourly_rates`
- Settlement: `pc_time_entries.billing_status` (+ billed/paid timestamps), project `stl_*` counters
- Mobile v1.1: `pc_mob_idem` (offline create idempotency) — also repaired via `PcCoreSchemaBootstrap::ensureMobileIdempotencyTable`

---

## Intentionally deferred (not defects)

- Invoice PDF / tax / InvoiceCheck handoff
- Mobile offline edit/delete/settle; timer pause; server-side timer
- iOS App Store native prebuild (Mac); Play internal track until EAS project ID is set
- `page-start` on settings/error/access-denied templates
- Independent project `billing_status` column; `open → paid` API shortcut

---

## Operator migration note

Upgrades through 2.0.86 run Nextcloud migrations + `ProjectCheckSchemaEnsurer` repair.
If SchemaGuard reports incomplete schema, run `occ upgrade` as the web-server user, then
`occ migrations:status projectcheck`. License/seat and `pc_mob_idem` tables are created by
repair when a migration was marked complete without effect.
