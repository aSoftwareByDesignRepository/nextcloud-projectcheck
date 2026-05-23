#!/usr/bin/env bash
# ProjectCheck release / audit gates (README §15.3–15.4).
# Run from repo: apps/projectcheck/scripts/run-audit-gates.sh
# Docker PHPUnit: docker compose exec nextcloud bash -c 'cd /var/www/html/custom_apps/projectcheck && ./vendor/bin/phpunit'
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== ProjectCheck audit gates ==="
echo "Root: $ROOT"
echo "Date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo

echo "--- PHPUnit (host) ---"
./vendor/bin/phpunit
echo

echo "--- l10n parity ---"
php scripts/check-l10n-parity.php
echo

echo "--- l10n placeholders ---"
php scripts/check-l10n-placeholders.php
echo

echo "--- DB naming ---"
bash scripts/check-db-naming.sh
echo

echo "--- Lucide icons ---"
bash scripts/validate-lucide-icons.sh
echo

echo "=== Security test classes (§15.1) ==="
echo "  tests/Unit/Service/HourlyRateServiceTest.php"
echo "  tests/Unit/Service/TimeEntryServiceRateResolutionTest.php"
echo "  tests/Unit/Service/EmployeeHourlyRateServiceTest.php"
echo "  tests/Unit/Service/ProjectServiceTest.php (projectHasLoggedTime, E6 mode lock)"
echo "  tests/Unit/Controller/ProjectControllerTest.php (resolve API, add-all)"
echo

echo "=== P0 controls (§11.3) — verify in code review ==="
echo "  A1/A4: TimeEntryService server resolve + tamper ±0.009"
echo "  A3:    Re-resolve on date/project change (E15/E16 tests)"
echo "  A5:    Money::mul for cost aggregates"
echo "  A6:    resolve-hourly-rate: project access + self user_id"
echo

echo "All automated gates passed."
echo
echo "Optional UI smoke (Docker): cd apps/projectcheck && bash e2e/run-smoke.sh"
