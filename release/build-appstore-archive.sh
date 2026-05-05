#!/usr/bin/env bash
# Build a Nextcloud App Store–ready projectcheck-X.Y.Z.tar.gz.
#
# Works in both layouts:
#   • Standalone clone of nextcloud-projectcheck (app files at repo root; this script in release/)
#   • Private monorepo where the app lives at apps/projectcheck/ (script path unchanged)
#
# Usage (from anywhere inside the git tree):
#   ./release/build-appstore-archive.sh X.Y.Z
# Monorepo example:
#   ./apps/projectcheck/release/build-appstore-archive.sh X.Y.Z
#
# Produces: <app>/release/projectcheck-VERSION.tar.gz
# Requires: npm, composer, git.
#
set -euo pipefail

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
	echo "Usage: $0 <version>   (must match appinfo/info.xml)" >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [[ ! -f "${APP_DIR}/appinfo/info.xml" ]] || ! grep -q '<id>projectcheck</id>' "${APP_DIR}/appinfo/info.xml" 2>/dev/null; then
	echo "Expected ProjectCheck app at ${APP_DIR} (parent of release/)." >&2
	exit 1
fi

git -C "${APP_DIR}" rev-parse --show-toplevel >/dev/null 2>&1 || {
	echo "Run from inside the git repository." >&2
	exit 1
}

APP="projectcheck"
OUT="${APP_DIR}/release/${APP}-${VERSION}.tar.gz"
INFO_VERSION="$(sed -n 's:[[:space:]]*<version>\([^<]*\)</version>:\1:p' "${APP_DIR}/appinfo/info.xml" | head -1)"

if [[ -z "${INFO_VERSION}" ]]; then
	echo "Could not read <version> from ${APP_DIR}/appinfo/info.xml" >&2
	exit 1
fi
if [[ "${INFO_VERSION}" != "${VERSION}" ]]; then
	echo "Version mismatch: script argument is '${VERSION}' but info.xml is '${INFO_VERSION}'." >&2
	exit 1
fi

echo "==> DB naming convention check (${APP_DIR})"
(cd "${APP_DIR}" && bash scripts/check-db-naming.sh)

echo "==> npm ci + build (${APP_DIR})"
(cd "${APP_DIR}" && npm ci && npm run build)

echo "==> composer install --no-dev (${APP_DIR})"
(cd "${APP_DIR}" && composer install --no-dev --no-interaction --no-ansi)

echo "==> packing ${OUT}"

PACK_PARENT="$(dirname "${APP_DIR}")"
PACK_BASE="$(basename "${APP_DIR}")"

if [[ "${PACK_BASE}" == "${APP}" ]]; then
	# Monorepo (…/apps/projectcheck) or standalone clone checked out as …/projectcheck
	(cd "${PACK_PARENT}" && tar \
		--exclude="${APP}/node_modules" \
		--exclude="${APP}/.git" \
		--exclude="${APP}/release/${APP}-*.tar.gz" \
		--exclude="${APP}/tests" \
		--exclude="${APP}/phpunit.xml" \
		-czf "${OUT}" "${APP}")
else
	# Standalone clone with another directory name (e.g. nextcloud-projectcheck): archive top folder must be projectcheck/
	(cd "${APP_DIR}" && tar \
		--transform "s,^,${APP}/," \
		--exclude='node_modules' \
		--exclude='.git' \
		--exclude='release/projectcheck-*.tar.gz' \
		--exclude='tests' \
		--exclude='phpunit.xml' \
		-czf "${OUT}" .)
fi

echo "Done: ${OUT}"
echo "Next: sha256sum / sha512sum, openssl signature (see release/APPSTORE-RELEASE.md)"
