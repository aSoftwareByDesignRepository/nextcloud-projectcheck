#!/usr/bin/env bash
#
# Static naming audit for ProjectCheck migration sources. The companion live
# audit (after migrations have run on a real DB) is `check-db-live.sh`.
#
# What this enforces:
#  - Every table created by a migration is either prefixed with `pc_` or is on
#    the explicit legacy allow-list.
#  - Every primary-key, index, unique-index and foreign-key name is `pc_`-
#    prefixed (or on the allow-list).
#  - All identifier names stay within 30 characters, which keeps us safe even
#    on Oracle <12c (the strictest engine Nextcloud still tolerates) and well
#    below the 63-character soft limit that core enforces in
#    `MigrationService::ensureNamingConstraints`.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MIGRATION_DIR="${APP_ROOT}/lib/Migration"

if [[ ! -d "${MIGRATION_DIR}" ]]; then
	echo "Migration directory not found: ${MIGRATION_DIR}" >&2
	exit 1
fi

# Keep a strict portability ceiling for identifier names.
MAX_IDENTIFIER_LEN=30
STRICT_PREFIX="pc_"

# Legacy tables intentionally kept for compatibility with historical installs.
# These are renamed to their `pc_` equivalents by Version2006.
LEGACY_TABLES=("projects" "customers" "time_entries" "project_members" "project_files")

# Legacy index/constraint names from historical migrations. They are renamed
# to `pc_*` equivalents by Version2004 / Version2005.
LEGACY_IDENTIFIERS=(
	"projects_pk" "projects_customer_idx" "projects_status_idx" "projects_creator_idx"
	"projects_name_idx" "projects_category_idx" "projects_priority_idx"
	"projects_start_date_idx" "projects_end_date_idx" "projects_project_type_idx"
	"members_pk" "members_project_idx" "members_user_idx" "members_role_idx" "members_unique_idx"
	"customers_pk" "customers_name_idx" "customers_email_idx" "customers_creator_idx"
	"time_entries_pk" "time_entries_project_idx" "time_entries_user_idx" "time_entries_date_idx"
	"time_entries_project_user_idx" "time_entries_project_date_idx"
	"fk_projects_customer" "fk_members_project" "fk_time_entries_project"
)

is_legacy_table() {
	local value="$1"
	for item in "${LEGACY_TABLES[@]}"; do
		[[ "${item}" == "${value}" ]] && return 0
	done
	return 1
}

is_legacy_identifier() {
	local value="$1"
	for item in "${LEGACY_IDENTIFIERS[@]}"; do
		[[ "${item}" == "${value}" ]] && return 0
	done
	return 1
}

errors=0

while IFS= read -r file; do
	while IFS= read -r table; do
		[[ -z "${table}" ]] && continue
		if [[ "${table}" != ${STRICT_PREFIX}* ]] && ! is_legacy_table "${table}"; then
			echo "ERROR ${file}: table '${table}' must use '${STRICT_PREFIX}' prefix (or be an approved legacy table)." >&2
			errors=$((errors + 1))
		fi
		if (( ${#table} > MAX_IDENTIFIER_LEN )); then
			echo "ERROR ${file}: table '${table}' length ${#table} exceeds ${MAX_IDENTIFIER_LEN}." >&2
			errors=$((errors + 1))
		fi
	done < <(rg -o --replace '$1' "createTable\\('([a-zA-Z0-9_]+)'\\)" "${file}")

	while IFS= read -r ident; do
		[[ -z "${ident}" ]] && continue
		if [[ "${ident}" != ${STRICT_PREFIX}* ]] \
			&& [[ "${ident}" != fk_pc_* ]] \
			&& ! is_legacy_identifier "${ident}"; then
			echo "ERROR ${file}: identifier '${ident}' must use '${STRICT_PREFIX}' prefix (or be an approved legacy identifier)." >&2
			errors=$((errors + 1))
		fi
		if (( ${#ident} > MAX_IDENTIFIER_LEN )); then
			echo "ERROR ${file}: identifier '${ident}' length ${#ident} exceeds ${MAX_IDENTIFIER_LEN}." >&2
			errors=$((errors + 1))
		fi
	done < <(rg -o --replace '$1' "(?:setPrimaryKey|addIndex|addUniqueIndex|addForeignKeyConstraint)\\([^\\n]*'([a-zA-Z0-9_]+)'\\)" "${file}")
done < <(rg --files "${MIGRATION_DIR}" -g "Version*.php" | sort)

if (( errors > 0 )); then
	echo "DB naming check failed with ${errors} issue(s)." >&2
	exit 1
fi

echo "DB naming check passed for projectcheck."
