#!/usr/bin/env bash
# Full release gate runner: host PHPUnit + l10n + DB naming + Lucide + optional Docker E2E.
# From repo root: apps/projectcheck/scripts/run-all-release-gates.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO_ROOT="$(cd "$ROOT/../.." && pwd)"
cd "$ROOT"

echo "=== ProjectCheck — all release gates ==="
echo "App: $ROOT"
echo "Date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo

bash "$ROOT/scripts/run-audit-gates.sh"

echo
echo "--- Docker PHPUnit (recommended for CI parity) ---"
if command -v docker >/dev/null 2>&1 && [ -f "$REPO_ROOT/docker-compose.yml" ]; then
	if docker compose -f "$REPO_ROOT/docker-compose.yml" ps nextcloud 2>/dev/null | grep -q 'Up'; then
		"$REPO_ROOT/docker/run-app-phpunit.sh" projectcheck
	else
		echo "Skip: nextcloud container not running."
	fi
else
	echo "Skip: docker not available."
fi

echo
echo "--- Playwright E2E (requires e2e/.env) ---"
if [ -f "$ROOT/e2e/.env" ]; then
	bash "$ROOT/e2e/run-smoke.sh"
else
	echo "Skip: copy e2e/.env.example to e2e/.env and set E2E_USER / E2E_PASS."
fi

echo
echo "=== All release gates finished ==="
echo "Evidence: nextcloud-development ready2publish/ (private monorepo)"
echo "Manual sign-off: planning/app-ideas/projectcheck/UAT-CHECKLIST.md"
