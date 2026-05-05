#!/usr/bin/env bash
#
# Live database audit for ProjectCheck.
#
# Verifies that the actual database after migrations matches Nextcloud naming
# best practices: no legacy generic table names remain, every projectcheck
# table is `pc_`-prefixed, and every projectcheck identifier
# (table / index / constraint / column) is within the 30-character portability
# ceiling that protects us across MariaDB / PostgreSQL / Oracle.
#
# Usage:
#   apps/projectcheck/scripts/check-db-live.sh                       # auto-detect docker env
#   DB_CONTAINER=mariadb DB_NAME=nextcloud DB_USER=root \
#   DB_PASSWORD=nextcloud_root_password TABLE_PREFIX=oc_ \
#       apps/projectcheck/scripts/check-db-live.sh
set -euo pipefail

DB_CONTAINER="${DB_CONTAINER:-mariadb}"
DB_NAME="${DB_NAME:-nextcloud}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-nextcloud_root_password}"
TABLE_PREFIX="${TABLE_PREFIX:-oc_}"
APP_PREFIX="pc_"
MAX_IDENTIFIER_LEN=30

LEGACY_TABLES=("projects" "customers" "time_entries" "project_members" "project_files")

run_sql() {
	docker compose exec -T "${DB_CONTAINER}" \
		mysql -u"${DB_USER}" -p"${DB_PASSWORD}" -N -B "${DB_NAME}" -e "$1"
}

errors=0

# 1) Legacy generic table names must be gone.
for legacy in "${LEGACY_TABLES[@]}"; do
	count=$(run_sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${TABLE_PREFIX}${legacy}'" | tr -d '[:space:]')
	if [[ "${count}" != "0" ]]; then
		echo "ERROR legacy table '${TABLE_PREFIX}${legacy}' still exists. Run pending projectcheck migrations." >&2
		errors=$((errors + 1))
	fi
done

# 2) Every projectcheck table must be `pc_`-prefixed and stay under the limit.
table_violations=$(run_sql "SELECT TABLE_NAME, LENGTH(TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME LIKE '${TABLE_PREFIX}${APP_PREFIX}%' AND LENGTH(TABLE_NAME) > ${MAX_IDENTIFIER_LEN}")
if [[ -n "${table_violations}" ]]; then
	echo "ERROR projectcheck tables exceed ${MAX_IDENTIFIER_LEN} chars:" >&2
	echo "${table_violations}" >&2
	errors=$((errors + 1))
fi

# 3) Every index on a projectcheck table must be `pc_`-prefixed (or PRIMARY)
#    and within the length limit.
index_violations=$(run_sql "
SELECT TABLE_NAME, INDEX_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='${DB_NAME}'
  AND TABLE_NAME LIKE '${TABLE_PREFIX}${APP_PREFIX}%'
  AND INDEX_NAME != 'PRIMARY'
  AND (INDEX_NAME NOT LIKE '${APP_PREFIX}%' AND INDEX_NAME NOT LIKE 'fk_${APP_PREFIX}%')
GROUP BY TABLE_NAME, INDEX_NAME")
if [[ -n "${index_violations}" ]]; then
	echo "ERROR projectcheck indexes that are not pc_-prefixed:" >&2
	echo "${index_violations}" >&2
	errors=$((errors + 1))
fi

index_too_long=$(run_sql "
SELECT TABLE_NAME, INDEX_NAME, LENGTH(INDEX_NAME)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='${DB_NAME}'
  AND TABLE_NAME LIKE '${TABLE_PREFIX}${APP_PREFIX}%'
  AND INDEX_NAME != 'PRIMARY'
  AND LENGTH(INDEX_NAME) > ${MAX_IDENTIFIER_LEN}
GROUP BY TABLE_NAME, INDEX_NAME")
if [[ -n "${index_too_long}" ]]; then
	echo "ERROR projectcheck indexes exceed ${MAX_IDENTIFIER_LEN} chars:" >&2
	echo "${index_too_long}" >&2
	errors=$((errors + 1))
fi

# 4) Every column on a projectcheck table must stay within the limit.
col_violations=$(run_sql "
SELECT TABLE_NAME, COLUMN_NAME, LENGTH(COLUMN_NAME)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA='${DB_NAME}'
  AND TABLE_NAME LIKE '${TABLE_PREFIX}${APP_PREFIX}%'
  AND LENGTH(COLUMN_NAME) > ${MAX_IDENTIFIER_LEN}")
if [[ -n "${col_violations}" ]]; then
	echo "ERROR projectcheck columns exceed ${MAX_IDENTIFIER_LEN} chars:" >&2
	echo "${col_violations}" >&2
	errors=$((errors + 1))
fi

# 5) Foreign key checks: every FK on a projectcheck table must reference another
#    projectcheck table (no dangling references into legacy table names).
fk_violations=$(run_sql "
SELECT KCU.TABLE_NAME, KCU.CONSTRAINT_NAME, KCU.REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE KCU
WHERE KCU.CONSTRAINT_SCHEMA='${DB_NAME}'
  AND KCU.TABLE_NAME LIKE '${TABLE_PREFIX}${APP_PREFIX}%'
  AND KCU.REFERENCED_TABLE_NAME IS NOT NULL
  AND KCU.REFERENCED_TABLE_NAME NOT LIKE '${TABLE_PREFIX}${APP_PREFIX}%'")
if [[ -n "${fk_violations}" ]]; then
	echo "ERROR projectcheck FK references a non-pc_ table:" >&2
	echo "${fk_violations}" >&2
	errors=$((errors + 1))
fi

if (( errors > 0 )); then
	echo "Live DB audit FAILED with ${errors} issue(s)." >&2
	exit 1
fi

echo "Live DB audit passed: every projectcheck identifier is pc_-prefixed and within length limits."
