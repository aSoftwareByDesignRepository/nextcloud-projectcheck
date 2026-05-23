# ProjectCheck E2E (Playwright)

Automated smoke for **cost-pricing**, WCAG shell, and security (readonly rate, no `data-hourly-rate`, resolve API auth).

Requires Docker Nextcloud at `http://localhost:8081` (or set `BASE_URL`).

## Quick start (Docker dev)

```bash
cd apps/projectcheck
cp e2e/.env.example e2e/.env
# Set E2E_USER / E2E_PASS (admin or any user allowed to use ProjectCheck)
npm ci
npm run e2e:install
bash e2e/run-smoke.sh
```

## Environment

| Variable | Description |
|----------|-------------|
| `BASE_URL` | Origin, e.g. `http://localhost:8081` |
| `E2E_USER` | Nextcloud account with ProjectCheck access |
| `E2E_PASS` | Password (also accepts `E2E_PASSWORD`) |
| `E2E_ASSIGN_USER` | Optional employee uid for UAT 11 assign test (defaults to `E2E_USER`) |
| `E2E_STORAGE_STATE` | Optional cookie jar from `npm run e2e:auth` |

## Scripts

| Command | Purpose |
|---------|---------|
| `npm run e2e:smoke` | Cost-pricing smoke only |
| `npm run e2e` | All e2e specs |
| `npm run e2e:auth` | Save login cookies to `.auth/storage-state.json` |
| `bash e2e/run-smoke.sh` | `occ upgrade` if needed, refresh auth, run all cost-pricing + route specs (**29 cases**) |

`run-smoke.sh` deletes stale `.auth/storage-state.json` on every run so a Nextcloud upgrade screen cannot poison cookies.

## Security

Do not commit `e2e/.env` or `.auth/`. Use a disposable dev instance.

## PHPUnit

Server logic is covered by `bash scripts/run-audit-gates.sh` (171 tests). E2E validates UI shell and critical browser-facing controls.
