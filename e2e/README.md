# ProjectCheck E2E (Playwright)

These tests need a **real** Nextcloud instance and credentials. They are not run in the default `composer test` / CI job unless you provide secrets and a reachable `BASE_URL`.

## Setup

```bash
cd apps/projectcheck
npm ci
npx playwright install chromium
```

## Environment

| Variable     | Description                                    |
|-------------|-------------------------------------------------|
| `BASE_URL`  | Origin only, e.g. `https://cloud.example.com`  |
| `E2E_USER`  | Account that is app admin for ProjectCheck     |
| `E2E_PASS`  | Password                                        |

## Run

```bash
export BASE_URL=http://localhost:8081
export E2E_USER=admin
export E2E_PASS=…
npm run e2e
```

Tests are skipped automatically if `BASE_URL` is unset.

## Security

Do not commit real passwords. Use a disposable test instance or CI secrets.
