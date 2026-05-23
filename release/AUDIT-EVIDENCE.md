# ProjectCheck cost-pricing — audit evidence pack (M7)

**App version:** 2.0.44 · **Spec:** `pm/app-ideas/projectcheck/README.md` · **Date:** 2026-05-23

## One command

```bash
cd apps/projectcheck && bash scripts/run-all-release-gates.sh
```

| Gate | Command | Expected |
|------|---------|----------|
| PHPUnit | `./docker/run-app-phpunit.sh projectcheck` | 172 tests OK |
| Audit script | `cd apps/projectcheck && bash scripts/run-audit-gates.sh` | All gates passed |
| Playwright E2E | `cd apps/projectcheck && bash e2e/run-smoke.sh` | 31 cases OK |
| DB schema | `docker compose exec -u www-data nextcloud php occ upgrade` | `projectcheck` at current version |

## Release gates (README §15.3)

- [x] PHPUnit green (Docker)
- [x] `check-db-naming.sh`
- [x] l10n parity
- [x] P0 A1–A6 closed (§11.3)
- [x] WCAG §10 (skip links, live regions, no `window.alert`, native dates)
- [x] CHANGELOG + `COST-PRICING-MIGRATION.md`
- [ ] Sibling parity §11.2 screenshots (optional human)
- [ ] Manual UAT sign-off (`pm/app-ideas/projectcheck/UAT-CHECKLIST.md`)

## P0 security controls (§11.3)

| ID | Control | Evidence |
|----|---------|----------|
| A1 | Server resolves `hourly_rate` | `TimeEntryService`, E2E readonly field |
| A2 | No `data-hourly-rate` in DOM | Templates + E2E |
| A3 | Re-resolve on date/project change | `TimeEntryServiceRateResolutionTest` |
| A4 | Tamper ±0.009 | `HourlyRateServiceTest` |
| A5 | Money-safe aggregates | `Money::mul` |
| A6 | Resolve API access | Controller tests + E2E 401/403 |

## Known v1 limitations (disclose)

1. **Resolve API:** session user only (E22).
2. **Icons:** legacy `icon-*-custom` hydrated via `js/common/icons.js`.
3. **Feedback:** `ProjectCheckNotify` — no blocking `window.alert`.

## Operator

`release/COST-PRICING-MIGRATION.md` · Manual checklist: `pm/app-ideas/projectcheck/UAT-CHECKLIST.md`
