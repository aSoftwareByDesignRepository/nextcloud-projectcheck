#!/usr/bin/env python3
"""Native Brazilian Portuguese polish pass for ProjectCheck l10n/pt_BR.json.

Applies terminology + phrasing fixes after MT, preserves placeholders, regenerates JS.
"""

from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

APP = Path(__file__).resolve().parents[1]
EN = APP / "l10n" / "en.json"
PT = APP / "l10n" / "pt_BR.json"

# Exact msgid overrides (native BR UX).
EXACT: dict[str, str | list[str]] = {
	"ProjectCheck": "ProjectCheck",
	"CustomerCheck": "CustomerCheck",
	"Access denied": "Acesso negado",
	"Access denied - Admin privileges required": "Acesso negado — é necessário ser administrador",
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
	"Open": "Em aberto",
	"Unavailable": "Indisponível",
	"Linked": "Vinculado",
	"Not linked": "Não vinculado",
	"Not linked in CustomerCheck": "Não vinculado no CustomerCheck",
	"Open CRM company": "Abrir empresa do CRM",
	"No hours logged yet": "Nenhuma hora registrada ainda",
	"Link this customer from CustomerCheck when the CRM hub is installed.": "Vincule este cliente pelo CustomerCheck quando o hub de CRM estiver instalado.",
	"%1$s of %2$s seats used": "%1$s de %2$s assentos em uso",
	'Project "%s" was created successfully!': 'Projeto "%s" criado com sucesso!',
	"A network error occurred": "Ocorreu um erro de rede",
	"Loading…": "Carregando…",
	"Close": "Fechar",
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
	"Please try again.": "Tente novamente.",
	"Please try again": "Tente novamente",
	"Try again": "Tentar novamente",
	"Save project": "Salvar projeto",
	"Settlement": "Acerto",
	"Billing status": "Status de faturamento",
	"_%n team member_::_%n team members_": ["%n membro da equipe", "%n membros da equipe"],
	"_Uploaded %n file._::_Uploaded %n files._": ["%n arquivo enviado.", "%n arquivos enviados."],
	"{actor} logged {hours} hours on project {project}": "{actor} registrou {hours} horas no projeto {project}",
	"Too many entries in one operation (limit %s). Narrow the date range and run it in slices.": "Há entradas demais em uma só operação (limite %s). Reduza o intervalo de datas e execute em lotes.",
	"The save URL was not found. Reload the page. If the problem remains, the app may need to be updated.": "A URL de salvamento não foi encontrada. Recarregue a página. Se o problema continuar, o app pode precisar de uma atualização.",
	"Employee analytics are restricted to your own time entries unless you are an administrator.": "As análises de colaboradores ficam limitadas aos seus próprios lançamentos de horas, a menos que você seja administrador.",
	"{actor} changed the settlement status of multiple time entries to {status}": "{actor} alterou o status de faturamento de vários lançamentos de horas para {status}",
	"Error removing team member. Please try again.": "Erro ao remover o membro da equipe. Tente novamente.",
	"Could not change the role. Please try again.": "Não foi possível alterar a função. Tente novamente.",
	"This entry was changed by someone else in the meantime. Reload the page and try again.": "Este lançamento foi alterado por outra pessoa enquanto isso. Recarregue a página e tente novamente.",
	"Billable work earns money. Overhead keeps the business running. Compare both by year.": "O trabalho faturável gera receita. As horas internas mantêm a operação. Compare os dois por ano.",
	"Add each person individually with their rate when using per-person project pricing.": "Com precificação por pessoa, adicione cada um com a respectiva taxa.",
	"The project has exceeded its allocated budget. Immediate action is required.": "O projeto ultrapassou o orçamento definido. É necessária uma ação imediata.",
	"Calculated from budget ÷ project hourly rate.": "Calculado como orçamento ÷ taxa horária do projeto.",
	"Hourly rate must be a non-negative number": "A taxa horária deve ser um número maior ou igual a zero",
	"The form data could not be read. Try again, or contact your administrator if this keeps happening.": "Não foi possível ler os dados do formulário. Tente novamente ou fale com o administrador se o problema continuar.",
}

# Ordered phrase replacements inside translated values (BR orthography / tone).
PHRASE_FIXES: list[tuple[str, str]] = [
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
	(r"\bcontactos\b", "contatos"),
	(r"\becrã\b", "tela"),
	(r"\btelemóvel\b", "celular"),
	(r"\bactual\b", "atual"),
	(r"\bActual\b", "Atual"),
	(r"\bactualizar\b", "atualizar"),
	(r"\bprojecto\b", "projeto"),
	(r"\bProjecto\b", "Projeto"),
	(r"\bprojectos\b", "projetos"),
	(r"\bentradas de tempo\b", "lançamentos de horas"),
	(r"\bentradas de horário\b", "lançamentos de horas"),
	(r"\bentrada de tempo\b", "lançamento de horas"),
	(r"\bentrada de horário\b", "lançamento de horas"),
	(r"\bstatus de liquidação\b", "status de faturamento"),
	(r"\bStatus de liquidação\b", "Status de faturamento"),
	(r"\bliquidação\b", "acerto"),
	(r"\bPor favor, tente novamente\b", "Tente novamente"),
	(r"\bpor favor, tente novamente\b", "tente novamente"),
	(r"\bo aplicativo\b", "o app"),
	(r"\bO aplicativo\b", "O app"),
	(r"\ba aplicação\b", "o app"),
	(r"\bA aplicação\b", "O app"),
	(r"\bem fatias\b", "em lotes"),
	(r"\bO salvamento URL\b", "A URL de salvamento"),
	(r"\bmúltiplas entradas\b", "vários lançamentos"),
	(r"\banálises de funcionários\b", "análises de colaboradores"),
	(r"\bfuncionários\b", "colaboradores"),
	(r"\bFuncionários\b", "Colaboradores"),
	(r"\bfuncionário\b", "colaborador"),
	(r"\bFuncionário\b", "Colaborador"),
]


def printf_ph(s: str) -> list[str]:
	return re.findall(r"%%|%(?:\d+\$)?[sd]|%n", s)


def named_ph(s: str) -> list[str]:
	return re.findall(r"\{[a-zA-Z_][a-zA-Z0-9_]*\}", s)


def ok(src: str, dst: str) -> bool:
	return printf_ph(src) == printf_ph(dst) and named_ph(src) == named_ph(dst)


def polish_text(text: str) -> str:
	out = text
	for pat, repl in PHRASE_FIXES:
		out = re.sub(pat, repl, out)
	return out


def main() -> int:
	en = json.loads(EN.read_text(encoding="utf-8"))["translations"]
	pt = json.loads(PT.read_text(encoding="utf-8"))
	t = pt["translations"]
	changed = 0
	for k, v in list(t.items()):
		if k in EXACT:
			nv = EXACT[k]
			if nv != v:
				t[k] = nv
				changed += 1
			continue
		if isinstance(v, list):
			nv = [polish_text(str(x)) for x in v]
			if nv != v and all(ok(str(a), str(b)) for a, b in zip(en.get(k, v), nv)):
				t[k] = nv
				changed += 1
			continue
		if not isinstance(v, str):
			continue
		nv = polish_text(v)
		src = en.get(k, k)
		if isinstance(src, str) and nv != v and ok(src, nv):
			t[k] = nv
			changed += 1
		elif isinstance(src, str) and nv != v and not ok(src, nv):
			# keep previous if polish broke placeholders
			pass

	# Ensure key parity with en order
	ordered = {k: t[k] for k in en.keys() if k in t}
	pt["translations"] = ordered
	pt["pluralForm"] = "nplurals=2; plural=(n > 1);"
	PT.write_text(json.dumps(pt, ensure_ascii=False, indent="\t") + "\n", encoding="utf-8")
	print(f"Polished {changed} strings -> {PT}")
	subprocess.check_call(["php", str(APP / "scripts" / "regenerate-l10n-js.php")])
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
