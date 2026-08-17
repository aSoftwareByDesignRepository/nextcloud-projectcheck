#!/usr/bin/env python3
"""Build _quality_fixes_{lang}.json from formal catalog + failure list."""
from __future__ import annotations

import json
from pathlib import Path

L10N = Path(__file__).parent
LOCALES = ["de", "fr", "es", "da", "nl", "it", "pl", "sv", "nb", "pt_BR"]


def main() -> None:
    catalog: dict[str, dict[str, str]] = {}
    cat_path = L10N / "_formal_catalog.json"
    if cat_path.exists():
        catalog = json.loads(cat_path.read_text(encoding="utf-8"))

    for lang in LOCALES:
        fail_path = L10N / f"_fail_{lang}.json"
        if not fail_path.exists():
            continue
        fail = json.loads(fail_path.read_text(encoding="utf-8"))["all"]
        cat = catalog.get(lang, {})
        fixes: dict[str, str] = {}
        missing: list[str] = []
        for key in fail:
            if key in cat and cat[key]:
                fixes[key] = cat[key]
            else:
                missing.append(key)
        out = L10N / f"_quality_fixes_{lang}.json"
        out.write_text(json.dumps(fixes, ensure_ascii=False, indent="\t") + "\n", encoding="utf-8")
        print(f"{lang}: {len(fixes)} fixes, {len(missing)} missing")
        if missing:
            miss_path = L10N / f"_missing_after_build_{lang}.json"
            miss_path.write_text(json.dumps(missing, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
