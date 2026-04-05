#!/usr/bin/env bash
# Build a Nextcloud App Store–ready projectcheck-X.Y.Z.tar.gz from the monorepo layout.
# Run from the repository root that contains apps/projectcheck (e.g. nextcloud-dev).
#
# Usage:
#   ./apps/projectcheck/release/build-appstore-archive.sh 2.0.22
#
# Produces: apps/projectcheck/release/projectcheck-VERSION.tar.gz
# Requires: npm, composer, git (for locating repo root).
#
set -euo pipefail

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
	echo "Usage: $0 <version>   (must match appinfo/info.xml)" >&2
	exit 1
fi

ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")/../../.." rev-parse --show-toplevel 2>/dev/null)" || {
	echo "Run from inside the git repository." >&2
	exit 1
}

APP="projectcheck"
APP_DIR="${ROOT}/apps/${APP}"

if [[ ! -d "$APP_DIR" ]]; then
	echo "Missing ${APP_DIR}" >&2
	exit 1
fi

echo "==> npm ci + build (${APP_DIR})"
(cd "${APP_DIR}" && npm ci && npm run build)

echo "==> composer install --no-dev (${APP_DIR})"
(cd "${APP_DIR}" && composer install --no-dev --no-interaction --no-ansi)

OUT="${APP_DIR}/release/${APP}-${VERSION}.tar.gz"
echo "==> packing ${OUT}"

(cd "${ROOT}/apps" && tar \
	--exclude="${APP}/node_modules" \
	--exclude="${APP}/.git" \
	--exclude="${APP}/release/${APP}-*.tar.gz" \
	--exclude="${APP}/tests" \
	--exclude="${APP}/phpunit.xml" \
	-czf "${OUT}" "${APP}")

echo "Done: ${OUT}"
echo "Next: sha256sum / sha512sum, openssl signature (see release/APPSTORE-RELEASE.md)"
