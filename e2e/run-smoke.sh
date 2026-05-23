#!/usr/bin/env bash
# Run cost-pricing Playwright smoke against Docker Nextcloud (localhost:8081).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f e2e/.env ]]; then
	set -a
	# shellcheck disable=SC1091
	source e2e/.env
	set +a
fi

export BASE_URL="${BASE_URL:-http://localhost:8081}"
PC_BASE="${BASE_URL%/}"
# Do not inherit TicketCheck (or other app) E2E_* URLs from the parent shell.
export E2E_DASHBOARD_URL="$PC_BASE/index.php/apps/projectcheck/dashboard"
export E2E_PROJECTS_URL="$PC_BASE/index.php/apps/projectcheck/projects"
export E2E_PROJECT_CREATE_URL="$PC_BASE/index.php/apps/projectcheck/projects/create"
export E2E_TIME_ENTRY_CREATE_URL="$PC_BASE/index.php/apps/projectcheck/time-entries/create"
export E2E_PROJECTCHECK_SETTINGS_URL="$PC_BASE/index.php/apps/projectcheck/settings"
unset E2E_SETTINGS_URL

USER_PW_CACHE="${HOME}/.cache/ms-playwright"
if [[ -d "$USER_PW_CACHE" ]]; then
	if [[ -z "${PLAYWRIGHT_BROWSERS_PATH:-}" ]] || ! compgen -G "${PLAYWRIGHT_BROWSERS_PATH}/chromium-"* >/dev/null 2>&1; then
		export PLAYWRIGHT_BROWSERS_PATH="$USER_PW_CACHE"
	fi
fi

if [[ ! -d node_modules/@playwright/test ]]; then
	npm ci
fi

if [[ ! -d "$HOME/.cache/ms-playwright" ]] && [[ ! -d node_modules/playwright/.local-browsers ]]; then
	npx playwright install chromium
fi

# Ensure Nextcloud is not waiting for upgrade (common after pulling app code).
if command -v docker >/dev/null 2>&1 && docker compose ps nextcloud 2>/dev/null | grep -q 'Up'; then
	if docker compose exec -T -u www-data nextcloud php occ status 2>/dev/null | grep -q 'needsDbUpgrade: true'; then
		echo "Running occ upgrade (needsDbUpgrade)..."
		docker compose exec -T -u www-data nextcloud php occ upgrade
	fi
fi

# Always refresh auth cookies (stale session breaks every UI test).
rm -f .auth/storage-state.json
if [[ -n "${E2E_USER:-}" && -n "${E2E_PASS:-}" ]]; then
	npm run e2e:auth
fi

npx playwright test \
	e2e/cost-pricing-smoke.spec.js \
	e2e/cost-pricing-workflows.spec.js \
	e2e/cost-pricing-uat.spec.js \
	e2e/mutations.spec.ts \
	e2e/organization.spec.ts \
	"$@"
