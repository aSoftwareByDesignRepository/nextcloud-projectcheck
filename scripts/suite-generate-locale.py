#!/usr/bin/env python3
"""Generate pt_BR (and optionally other locales) for any Nextcloud app under apps/.

Atomic write: build fully in memory, write once. Protects placeholders + product names.
Native BR phrase polish applied after MT.

Usage:
  python3 scripts/suite-generate-locale.py --app budgetcheck --target pt_BR
  python3 scripts/suite-generate-locale.py --app ticketcheck --targets fr,es,da,nl,it,pl,sv,nb,pt_BR
  python3 scripts/suite-generate-locale.py --all-check --target pt_BR --workers 8
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

from deep_translator import GoogleTranslator, MyMemoryTranslator

ROOT = Path(__file__).resolve().parents[2]  # nextcloud/apps
if not ROOT.is_dir() or not (ROOT / "projectcheck").is_dir():
	ROOT = Path("/home/alex/Development/nextcloud-dev/nextcloud/apps")

CHECK_APPS = [
	"projectcheck",
	"budgetcheck",
	"customercheck",
	"dutycheck",
	"mobilitycheck",
	"arbeitszeitcheck",
	"deskcheck",
	"ticketcheck",
	"invoicecheck",
	"inventorycheck",
	"maintenancecheck",
	"audiocheck",
	"snackcheck",
]

# Google MT codes (fallback)
MT_TARGET = {
	"pt_BR": "pt",
	"fr": "fr",
	"es": "es",
	"da": "da",
	"nl": "nl",
	"it": "it",
	"pl": "pl",
	"sv": "sv",
	"nb": "no",
}

# MyMemory locale pairs (preferred; better rate limits)
MYMEMORY = {
	"pt_BR": ("en-US", "pt-BR"),
	"fr": ("en-GB", "fr-FR"),
	"es": ("en-GB", "es-ES"),
	"da": ("en-GB", "da-DK"),
	"nl": ("en-GB", "nl-NL"),
	"it": ("en-GB", "it-IT"),
	"pl": ("en-GB", "pl-PL"),
	"sv": ("en-GB", "sv-SE"),
	"nb": ("en-GB", "no-NO"),
}

PLURAL = {
	"pt_BR": "nplurals=2; plural=(n > 1);",
	"fr": "nplurals=2; plural=(n > 1);",
	"es": "nplurals=2; plural=(n != 1);",
	"da": "nplurals=2; plural=(n != 1);",
	"nl": "nplurals=2; plural=(n != 1);",
	"it": "nplurals=2; plural=(n != 1);",
	"pl": "nplurals=4; plural=(n==1 ? 0 : (n%10>=2 && n%10<=4) && (n%100<12 || n%100>14) ? 1 : n!=1 && (n%10>=0 && n%10<=1) || (n%10>=5 && n%10<=9) || (n%100>=12 && n%100<=14) ? 2 : 3);",
	"sv": "nplurals=2; plural=(n != 1);",
	"nb": "nplurals=2; plural=(n != 1);",
}

PROTECT_LITERALS = [
	"ProjectCheck",
	"CustomerCheck",
	"BudgetCheck",
	"DutyCheck",
	"InventoryCheck",
	"MobilityCheck",
	"TicketCheck",
	"InvoiceCheck",
	"MaintenanceCheck",
	"AudioCheck",
	"SnackCheck",
	"DeskCheck",
	"ArbeitszeitCheck",
	"Nextcloud",
	"GitHub Sponsors",
	"GitHub",
	"AGPL",
	"CRM",
	"CSV",
	"PDF",
	"API",
	"OAuth",
	"URL",
	"UID",
	"UTC",
	"ISO",
	"WCAG",
]

PLACEHOLDER_RE = re.compile(r"(%%|%(?:\d+\$)?[sd]|\{[a-zA-Z_][a-zA-Z0-9_]*\}|%n)")

BR_PHRASE_FIXES = [
	(r"\bacção\b", "ação"),
	(r"\bAcção\b", "Ação"),
	(r"\bficheiro\b", "arquivo"),
	(r"\bFicheiro\b", "Arquivo"),
	(r"\bficheiros\b", "arquivos"),
	(r"\butilizador\b", "usuário"),
	(r"\bUtilizador\b", "Usuário"),
	(r"\butilizadores\b", "usuários"),
	(r"\bcontacto\b", "contato"),
	(r"\bContacto\b", "Contato"),
	(r"\becrã\b", "tela"),
	(r"\btelemóvel\b", "celular"),
	(r"\bactual\b", "atual"),
	(r"\bActual\b", "Atual"),
	(r"\bactualizar\b", "atualizar"),
	(r"\bprojecto\b", "projeto"),
	(r"\bProjecto\b", "Projeto"),
	(r"\bprojectos\b", "projetos"),
	(r"\bPor favor, tente novamente\b", "Tente novamente"),
	(r"\bo aplicativo\b", "o app"),
	(r"\bO aplicativo\b", "O app"),
]

_local = threading.local()
_cache_lock = threading.Lock()


def printf_ph(s: str) -> list[str]:
	return re.findall(r"%%|%(?:\d+\$)?[sd]|%n", s)


def named_ph(s: str) -> list[str]:
	return re.findall(r"\{[a-zA-Z_][a-zA-Z0-9_]*\}", s)


def ok(src: str, dst: str) -> bool:
	return printf_ph(src) == printf_ph(dst) and named_ph(src) == named_ph(dst)


def protect(text: str) -> tuple[str, list[str]]:
	tokens: list[str] = []

	def ph(m: re.Match[str]) -> str:
		tokens.append(m.group(0))
		return f"⟦PH{len(tokens) - 1}⟧"

	out = PLACEHOLDER_RE.sub(ph, text)
	for lit in sorted(PROTECT_LITERALS, key=len, reverse=True):
		if lit in out:
			tokens.append(lit)
			out = out.replace(lit, f"⟦PH{len(tokens) - 1}⟧")
	return out, tokens


def unprotect(text: str, tokens: list[str]) -> str:
	out = text.replace("[[", "⟦").replace("]]", "⟧")
	for i, tok in enumerate(tokens):
		for c in (f"⟦PH{i}⟧", f"[PH{i}]", f"⟦ PH{i} ⟧", f"⟦ph{i}⟧"):
			out = out.replace(c, tok)
		out = re.sub(rf"⟦?\s*PH\s*{i}\s*⟧?", tok, out, flags=re.I)
	return out


def polish_br(text: str) -> str:
	out = text
	for pat, repl in BR_PHRASE_FIXES:
		out = re.sub(pat, repl, out)
	return out


def get_tr(locale: str, mt_code: str):
	key = f"tr_{locale}"
	tr = getattr(_local, key, None)
	if tr is None:
		if locale in MYMEMORY:
			src, dst = MYMEMORY[locale]
			tr = MyMemoryTranslator(source=src, target=dst)
		else:
			tr = GoogleTranslator(source="en", target=mt_code)
		setattr(_local, key, tr)
	return tr


def translate_one(text: str, mt_code: str, locale: str, cache: dict[str, str]) -> str:
	ck = f"{locale}::{text}"
	with _cache_lock:
		if ck in cache and ok(text, cache[ck]):
			return cache[ck]
	if text.strip() == "" or text in PROTECT_LITERALS:
		with _cache_lock:
			cache[ck] = text
		return text
	protected, tokens = protect(text)
	tr = get_tr(locale, mt_code)
	last: Exception | None = None
	for attempt in range(8):
		try:
			translated = tr.translate(protected) or text
			restored = unprotect(translated, tokens)
			if not ok(text, restored):
				protected2 = protected
				for i in range(len(tokens)):
					protected2 = protected2.replace(f"⟦PH{i}⟧", f" ⟦PH{i}⟧ ")
				restored2 = unprotect(tr.translate(protected2) or text, tokens)
				restored = restored2 if ok(text, restored2) else text
			restored = re.sub(r"[ \t]{2,}", " ", restored).strip()
			if locale == "pt_BR":
				polished = polish_br(restored)
				if ok(text, polished):
					restored = polished
			with _cache_lock:
				cache[ck] = restored
			time.sleep(0.15)
			return restored
		except Exception as exc:  # noqa: BLE001
			last = exc
			# On rate limit, back off hard and optionally fall back to Google once
			time.sleep(1.5 * (attempt + 1))
			if attempt == 3 and locale in MYMEMORY:
				try:
					setattr(_local, f"tr_{locale}", GoogleTranslator(source="en", target=mt_code))
					tr = get_tr(locale, mt_code)
				except Exception:
					pass
	print(f"WARN {locale}: {text!r} -> {last}", file=sys.stderr)
	with _cache_lock:
		cache[ck] = text
	return text


def regenerate_js(app_dir: Path, app_id: str, locales: list[str]) -> None:
	base = app_dir / "l10n"
	for lang in locales:
		json_path = base / f"{lang}.json"
		js_path = base / f"{lang}.js"
		if not json_path.is_file():
			continue
		cat = json.loads(json_path.read_text(encoding="utf-8"))
		translations = cat.get("translations", {})
		plural = cat.get("pluralForm")
		lines = ['OC.L10N.register(\n', f'\t"{app_id}",\n', "\t{\n"]
		items = list(translations.items())
		for i, (key, val) in enumerate(items):
			k = json.dumps(key, ensure_ascii=False)
			v = json.dumps(val, ensure_ascii=False)
			comma = "," if i < len(items) - 1 else ""
			lines.append(f"\t{k} : {v}{comma}\n")
		if plural:
			lines.append("\t},\n")
			lines.append(f"\t{json.dumps(plural)}\n")
			lines.append(");\n")
		else:
			lines.append("\t}\n")
			lines.append(");\n")
		js_path.write_text("".join(lines), encoding="utf-8")
		print(f"  wrote {js_path.name} ({len(translations)} keys)")


def patch_parity_script(app_dir: Path, locale: str) -> None:
	for name in ("check-l10n-parity.php", "regenerate-l10n-js.php", "sync-l10n-from-runtime.php"):
		path = app_dir / "scripts" / name
		if not path.is_file():
			continue
		src = path.read_text(encoding="utf-8")
		if f"'{locale}'" in src or f'"{locale}"' in src:
			continue
		# naive append into common array patterns
		new = src
		if "pt_BR" == locale:
			new = re.sub(
				r"(\[(?:'en'[^]]*?)'nb'\])",
				lambda m: m.group(1)[:-1] + ", 'pt_BR']" if "'pt_BR'" not in m.group(1) else m.group(1),
				new,
				count=1,
			)
			new = re.sub(
				r"(array\s*\(\s*(?:\n\s*\d+\s*=>\s*'[^']+',\s*)*\n\s*\d+\s*=>\s*'nb',?\s*\n\s*\))",
				lambda m: m.group(0).rstrip()[:-1].rstrip() + ",\n  10 => 'pt_BR',\n)",
				new,
				count=1,
			)
		if new != src:
			path.write_text(new, encoding="utf-8")
			print(f"  patched {name}")


def generate_locale(app: str, locale: str, workers: int, cache: dict[str, str]) -> None:
	app_dir = ROOT / app
	en_path = app_dir / "l10n" / "en.json"
	out_path = app_dir / "l10n" / f"{locale}.json"
	if not en_path.is_file():
		raise SystemExit(f"Missing {en_path}")
	en = json.loads(en_path.read_text(encoding="utf-8"))["translations"]
	mt = MT_TARGET[locale]
	existing: dict = {}
	if out_path.is_file():
		existing = json.loads(out_path.read_text(encoding="utf-8")).get("translations", {})

	todo: list[tuple[str, object]] = []
	out: dict[str, object] = {}
	for k, v in en.items():
		prev = existing.get(k)
		if prev is not None:
			if isinstance(v, list) and isinstance(prev, list) and len(v) == len(prev):
				if all(ok(str(a), str(b)) for a, b in zip(v, prev)):
					out[k] = prev
					continue
			elif isinstance(v, str) and isinstance(prev, str) and ok(v, prev):
				out[k] = prev
				continue
		todo.append((k, v))

	print(f"[{app}/{locale}] resume {len(out)} done, {len(todo)} todo", flush=True)

	def work(pair: tuple[str, object]) -> tuple[str, object]:
		k, v = pair
		if isinstance(v, list):
			return k, [translate_one(str(x), mt, locale, cache) for x in v]
		return k, translate_one(str(v), mt, locale, cache)

	with ThreadPoolExecutor(max_workers=max(1, workers)) as pool:
		futs = [pool.submit(work, p) for p in todo]
		for i, fut in enumerate(as_completed(futs), 1):
			k, val = fut.result()
			out[k] = val
			if i % 50 == 0 or i == len(futs):
				print(f"[{app}/{locale}] {len(out)}/{len(en)}", flush=True)

	ordered = {k: out[k] for k in en.keys()}
	assert len(ordered) == len(en)
	payload = {"translations": ordered}
	if locale in PLURAL:
		payload["pluralForm"] = PLURAL[locale]
	tmp = out_path.with_suffix(".json.tmp")
	tmp.write_text(json.dumps(payload, ensure_ascii=False, indent="\t") + "\n", encoding="utf-8")
	tmp.replace(out_path)
	print(f"[{app}/{locale}] FINAL {len(ordered)} -> {out_path}")
	regenerate_js(app_dir, app, [locale])
	if locale == "pt_BR":
		patch_parity_script(app_dir, locale)


def main() -> int:
	ap = argparse.ArgumentParser()
	ap.add_argument("--app")
	ap.add_argument("--all-check", action="store_true")
	ap.add_argument("--target", default="pt_BR")
	ap.add_argument("--targets", default="")
	ap.add_argument("--workers", type=int, default=8)
	ap.add_argument("--skip-existing", action="store_true", help="Skip apps that already have the target json")
	args = ap.parse_args()

	targets = [t.strip() for t in args.targets.split(",") if t.strip()] or [args.target]
	apps = CHECK_APPS if args.all_check else [args.app]
	if not apps or apps == [None]:
		raise SystemExit("Need --app or --all-check")

	cache_path = Path("/tmp/suite-l10n-cache.json")
	cache: dict[str, str] = {}
	if cache_path.is_file():
		cache = json.loads(cache_path.read_text(encoding="utf-8"))

	for app in apps:
		for loc in targets:
			out = ROOT / app / "l10n" / f"{loc}.json"
			if args.skip_existing and out.is_file():
				en_n = len(json.loads((ROOT / app / "l10n" / "en.json").read_text())["translations"])
				pt_n = len(json.loads(out.read_text())["translations"])
				if en_n == pt_n:
					print(f"skip {app}/{loc} ({pt_n} keys)")
					continue
			generate_locale(app, loc, args.workers, cache)
			cache_path.write_text(json.dumps(cache, ensure_ascii=False), encoding="utf-8")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
