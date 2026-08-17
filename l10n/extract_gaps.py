#!/usr/bin/env python3
"""Extract formal-quality gaps (identical to English) for ProjectCheck locales."""
from __future__ import annotations

import json
import re
from pathlib import Path

L10N = Path(__file__).parent
LOCALES = ["de", "fr", "es", "da", "nl", "it", "pl", "sv", "nb", "pt_BR"]

ALLOW_EXACT = {
    "Nextcloud", "Check Partner", "CSV", "PDF", "API", "URL", "JSON", "OK", "ID",
    "Status", "Name", "Info", "Export", "Import", "Filter", "Sync", "Admin",
    "Standard", "Optional:", "Required:", "BudgetCheck", "BudgetCheck Mobile",
    "AudioCheck", "AudioCheck Mobile", "ArbeitszeitCheck", "DutyCheck", "ProjectCheck",
    "TicketCheck", "MaintenanceCheck", "MobilityCheck", "SnackCheck", "DeskCheck",
    "InventoryCheck", "InvoiceCheck", "CustomerCheck",
    "Pause", "Genres", "Playlist", "Playlists", "Scan", "Admins", "Vegan", "Tags",
    "Snack", "Snacks", "Code", "EUR", "Administrator", "Details", "Normal", "Plan",
    "Symptom", "Register", "Result", "Documents", "Document", "Interval", "Stop",
    "Pass", "Backend", "Dashboard", "Budget", "Budgets", "Governance", "Overhead",
    "Support", "Team", "Compliance", "Diesel", "Hybrid", "Person", "Scanner",
    "Leitweg-ID", "Online", "offline", "staging", "Workflow", "Simulator", "Terminals",
    "Mandantennummer", "Beraternummer", "General", "general", "general_category",
    "category_general", "error", "Error", "online", "optional", "agent", "tickets",
    "J / L",
}


def allowed_identical(value: str, lang: str) -> bool:
    if value in ALLOW_EXACT:
        return True
    if not re.search(r"[A-Za-zÀ-ÿ]", value):
        return True
    if len(value) <= 3:
        return True
    if re.match(r"^\d+\s*%$", value.strip()):
        return True
    if value.startswith("[") and value.endswith("]"):
        return True
    if re.match(r"^%[\d$ns]+", value) and not re.search(
        r"[A-Za-z]{5,}", re.sub(r"%[\d$ns]+", "", value)
    ):
        return True
    stripped = re.sub(r"\{[^}]+\}", "", value)
    if not re.search(r"[A-Za-zÀ-ÿ]{4,}", stripped):
        return True
    return False


def load_en() -> dict[str, str]:
    data = json.loads((L10N / "en.json").read_text(encoding="utf-8"))
    return data["translations"]


def gaps_for_lang(lang: str, en: dict[str, str]) -> list[str]:
    path = L10N / f"{lang}.json"
    if not path.exists():
        return list(en.keys())
    tr = json.loads(path.read_text(encoding="utf-8"))["translations"]
    out: list[str] = []
    for key, en_val in en.items():
        val = tr.get(key, en_val)
        if val == en_val and not allowed_identical(en_val, lang):
            out.append(key)
    return out


def main() -> None:
    en = load_en()
    report: dict[str, int] = {}
    for lang in LOCALES:
        gaps = gaps_for_lang(lang, en)
        report[lang] = len(gaps)
        out_path = L10N / f"_gaps_{lang}.json"
        out_path.write_text(json.dumps(gaps, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"{lang}: {len(gaps)} gaps -> {out_path.name}")
    print("Total gaps:", sum(report.values()))


if __name__ == "__main__":
    main()
