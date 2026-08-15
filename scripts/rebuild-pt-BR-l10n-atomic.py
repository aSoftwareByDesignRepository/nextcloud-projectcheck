#!/usr/bin/env python3
"""Atomically rebuild l10n/pt_BR.json (no concurrent partial writes)."""

from __future__ import annotations

import json
import re
import sys
import time
from pathlib import Path

from deep_translator import GoogleTranslator

APP = Path(__file__).resolve().parents[1]
EN_PATH = APP / "l10n" / "en.json"
OUT = APP / "l10n" / "pt_BR.json"
CACHE_PATH = APP / "scripts" / "l10n_maps" / "pt_BR-cache.json"
PLURAL = "nplurals=2; plural=(n > 1);"

PROTECT_LITERALS = [
	"ProjectCheck",
	"CustomerCheck",
	"DutyCheck",
	"BudgetCheck",
	"InventoryCheck",
	"MobilityCheck",
	"TimeCheck",
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
]
PLACEHOLDER_RE = re.compile(r"(%%|%(?:\d+\$)?[sd]|\{[a-zA-Z_][a-zA-Z0-9_]*\}|%n)")
MANUAL: dict[str, str | list[str]] = {
	"ProjectCheck": "ProjectCheck",
	"CustomerCheck": "CustomerCheck",
	"Access denied": "Acesso negado",
	"Access denied - Admin privileges required": "Acesso negado — privilégios de administrador necessários",
	"Dashboard": "Painel",
	"Projects": "Projetos",
	"Project": "Projeto",
	"Customers": "Clientes",
	"Customer": "Cliente",
	"Employees": "Colaboradores",
	"Employee": "Colaborador",
	"Time Entries": "Lançamentos de horas",
	"Time entries": "Lançamentos de horas",
	"Time Entry": "Lançamento de horas",
	"Time entry": "Lançamento de horas",
	"Settings": "Configurações",
	"Save": "Salvar",
	"Cancel": "Cancelar",
	"Delete": "Excluir",
	"Edit": "Editar",
	"Add": "Adicionar",
	"Search": "Pesquisar",
	"Filter": "Filtrar",
	"Actions": "Ações",
	"Active": "Ativo",
	"Inactive": "Inativo",
	"Budget": "Orçamento",
	"Hourly rate": "Taxa horária",
	"Add hourly rate": "Adicionar taxa horária",
	"Add Time Entry": "Adicionar lançamento de horas",
	"Add time entry": "Adicionar lançamento de horas",
	"Add Customer": "Adicionar cliente",
	"Add customer": "Adicionar cliente",
	"Add project": "Adicionar projeto",
	"Add team member": "Adicionar membro da equipe",
	"Team members": "Membros da equipe",
	"Invoiced": "Faturado",
	"Paid": "Pago",
	"Open": "Aberto",
	"Overdue": "Atrasado",
	"Unavailable": "Indisponível",
	"Linked": "Vinculado",
	"Not linked": "Não vinculado",
	"Not linked in CustomerCheck": "Não vinculado no CustomerCheck",
	"Open CRM company": "Abrir empresa do CRM",
	"No hours logged yet": "Nenhuma hora registrada ainda",
	"Link this customer from CustomerCheck when the CRM hub is installed.": "Vincule este cliente a partir do CustomerCheck quando o hub de CRM estiver instalado.",
	"_%n team member_::_%n team members_": ["%n membro da equipe", "%n membros da equipe"],
	"_Uploaded %n file._::_Uploaded %n files._": ["%n arquivo enviado.", "%n arquivos enviados."],
	"%1$s of %2$s seats used": "%1$s de %2$s assentos usados",
	"A network error occurred": "Ocorreu um erro de rede",
	"Loading…": "Carregando…",
	"Loading...": "Carregando...",
	"Close": "Fechar",
	"Back": "Voltar",
	"Next": "Próximo",
	"Previous": "Anterior",
	"Yes": "Sim",
	"No": "Não",
	"Confirm": "Confirmar",
	"Remove": "Remover",
	"Download": "Baixar",
	"Upload": "Enviar",
	"Name": "Nome",
	"Description": "Descrição",
	"Notes": "Observações",
	"Date": "Data",
	"Hours": "Horas",
	"Status": "Status",
	"Total": "Total",
	"Currency": "Moeda",
	"Language": "Idioma",
	'Project "%s" was created successfully!': 'Projeto "%s" criado com sucesso!',
}


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


def main() -> int:
	en = json.loads(EN_PATH.read_text(encoding="utf-8"))["translations"]
	cache: dict[str, str] = {}
	if CACHE_PATH.is_file():
		cache = json.loads(CACHE_PATH.read_text(encoding="utf-8"))
	if OUT.is_file():
		partial = json.loads(OUT.read_text(encoding="utf-8")).get("translations", {})
		for k, v in partial.items():
			if k in en and isinstance(en[k], str) and isinstance(v, str) and ok(en[k], v):
				cache.setdefault(en[k], v)

	tr = GoogleTranslator(source="en", target="pt")

	def translate_one(text: str) -> str:
		if text in MANUAL and isinstance(MANUAL[text], str):
			return MANUAL[text]  # type: ignore[return-value]
		if text in cache and ok(text, cache[text]):
			return cache[text]
		if text.strip() == "" or text in PROTECT_LITERALS:
			cache[text] = text
			return text
		protected, tokens = protect(text)
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
				cache[text] = restored
				return restored
			except Exception as exc:  # noqa: BLE001
				last = exc
				time.sleep(0.7 * (attempt + 1))
		print(f"WARN fail {text!r}: {last}", file=sys.stderr)
		cache[text] = text
		return text

	out: dict[str, str | list] = {}
	total = len(en)
	for i, (k, v) in enumerate(en.items(), 1):
		if k in MANUAL:
			out[k] = MANUAL[k]
		elif isinstance(v, list):
			out[k] = [translate_one(str(x)) for x in v]
		else:
			out[k] = translate_one(str(v))
		if i % 40 == 0 or i == total:
			CACHE_PATH.parent.mkdir(parents=True, exist_ok=True)
			CACHE_PATH.write_text(json.dumps(cache, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
			print(f"[{i}/{total}] cache={len(cache)}", flush=True)

	assert len(out) == total
	tmp = OUT.with_suffix(".json.tmp")
	tmp.write_text(
		json.dumps({"translations": out, "pluralForm": PLURAL}, ensure_ascii=False, indent="\t") + "\n",
		encoding="utf-8",
	)
	tmp.replace(OUT)
	CACHE_PATH.write_text(json.dumps(cache, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
	verify = json.loads(OUT.read_text(encoding="utf-8"))["translations"]
	print(f"FINAL {len(verify)} keys -> {OUT}")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
