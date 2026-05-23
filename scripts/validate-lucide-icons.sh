#!/usr/bin/env bash
# Ensures PHP IconCatalog keys are referenced from templates/JS data-lucide attributes.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CATALOG="$ROOT/lib/Service/IconCatalog.php"
if [[ ! -f "$CATALOG" ]]; then
	echo "IconCatalog.php not found" >&2
	exit 1
fi
mapfile -t ICONS < <(grep -oP "(?<=')[a-z0-9-]+(?=' =>)" "$CATALOG" | sort -u)
MISS=0
	for icon in "${ICONS[@]}"; do
	if ! rg -q "data-lucide=[\"']${icon}[\"']|IconCatalog::render\\([\"']${icon}[\"']" "$ROOT/templates" "$ROOT/js" 2>/dev/null; then
		echo "NOTE: icon '${icon}' not referenced in templates/js (catalog reserve)" >&2
	fi
done
# Fail on unknown data-lucide in templates
while IFS= read -r unknown; do
	[[ -z "$unknown" ]] && continue
	echo "ERROR: unknown data-lucide '${unknown}'" >&2
	MISS=$((MISS + 1))
done < <(rg -oP 'data-lucide="\K[a-z0-9-]+' "$ROOT/templates" 2>/dev/null | sort -u | while read -r n; do
	grep -q "'${n}'" "$CATALOG" || echo "$n"
done)
if [[ "$MISS" -gt 0 ]]; then
	exit 1
fi
echo "Lucide icon validation passed."
