#!/usr/bin/env python3
"""List formal-quality failures (identical + informal) per locale."""
from __future__ import annotations

import json
import re
from pathlib import Path

L10N = Path(__file__).parent

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

INFORMAL = {
    "de": re.compile(r"\b(du|dein|deine|deinen|deinem|deiner|dir|dich)\b", re.I),
    "fr": re.compile(r"\b(tu|ton|ta|tes|toi)\b", re.I),
    "es": re.compile(r"\b(tú|tu|te|contigo)\b", re.I),
    "da": re.compile(r"\b(du|din|dine|dit|dig)\b", re.I),
    "nb": re.compile(r"\b(du|din|dine|dit|deg)\b", re.I),
    "sv": re.compile(r"\b(du|din|dina|ditt|dig)\b", re.I),
    "nl": re.compile(r"\b(je|jij|jou|jouw)\b", re.I),
    "it": re.compile(r"\b(tu|tuo|tua|tuoi|tue|ti)\b", re.I),
    "pl": re.compile(r"\b(ty|twój|twoja|twoje|tobie|cię|ci)\b", re.I),
    "pt_BR": re.compile(r"\b(você|teu|tua|teus|tuas)\b", re.I),
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


def main() -> None:
    en = json.loads((L10N / "en.json").read_text(encoding="utf-8"))["translations"]
    for lang in ["de", "fr", "es", "da", "nl", "it", "pl", "sv", "nb", "pt_BR"]:
        tr = json.loads((L10N / f"{lang}.json").read_text(encoding="utf-8"))["translations"]
        identical: list[str] = []
        informal: list[str] = []
        for key, val in tr.items():
            if not isinstance(val, str):
                continue
            en_val = en.get(key, key)
            if val == en_val and not allowed_identical(en_val, lang):
                identical.append(key)
            if lang in INFORMAL and INFORMAL[lang].search(val):
                informal.append(key)
        all_fail = sorted(set(identical + informal))
        out = {"identical": identical, "informal": informal, "all": all_fail}
        path = L10N / f"_fail_{lang}.json"
        path.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(
            f"{lang}: identical={len(identical)} informal={len(informal)} "
            f"total={len(all_fail)}"
        )


if __name__ == "__main__":
    main()
