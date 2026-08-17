#!/usr/bin/env python3
"""Generate formal B2B quality fixes from de reference + native override tables."""
from __future__ import annotations

import json
import re
from pathlib import Path

L10N = Path(__file__).parent
APPS = L10N.parent.parent
LOCALES = ["da", "sv", "nb", "it", "pt_BR"]

de = json.loads((L10N / "de.json").read_text(encoding="utf-8"))["translations"]
en = json.loads((L10N / "en.json").read_text(encoding="utf-8"))["translations"]

INFORMAL = {
    "da": re.compile(r"\b(du|din|dine|dit|dig)\b", re.I),
    "nb": re.compile(r"\b(du|din|dine|dit|deg)\b", re.I),
    "sv": re.compile(r"\b(du|din|dina|ditt|dig)\b", re.I),
    "it": re.compile(r"\b(tu|tuo|tua|tuoi|tue|ti)\b", re.I),
    "pt_BR": re.compile(r"\b(você|teu|tua|teus|tuas)\b", re.I),
}


def load_seeds(lang: str) -> dict[str, str]:
    out: dict[str, str] = {}
    ds = APPS / "dutycheck/l10n/formal_scandinavian_data.json"
    if ds.exists() and lang in ("da", "sv", "nb"):
        for key, langs in json.loads(ds.read_text(encoding="utf-8")).items():
            if lang in langs:
                out[key] = langs[lang]
    ac = APPS / "audiocheck/l10n/_formal_catalog.json"
    if ac.exists():
        out.update(json.loads(ac.read_text(encoding="utf-8")).get(lang, {}))
    for app in ("maintenancecheck", "mobilitycheck", "inventorycheck", "snackcheck"):
        qf = APPS / app / "l10n" / f"_quality_fixes_{lang}.json"
        if qf.exists():
            out.update(json.loads(qf.read_text(encoding="utf-8")))
    return out


# Native formal overrides (hand-written, keyed by English msgid).
OVERRIDES: dict[str, dict[str, str]] = {
    "sv": {
        '"{name}" is selected. Press Save at the bottom when you are done.': '"{name}" är vald. Tryck på Spara längst ned när allt är klart.',
        "Access restriction is currently turned on. Users who are not listed in the allowlists (and are not system administrators) cannot use ProjectCheck, including this page if your account is affected.": "Åtkomstbegränsning är aktiv. Användare som inte finns på tillåtelselistorna (och inte är systemadministratörer) kan inte använda ProjectCheck, inklusive denna sida om kontot berörs.",
        "Add your first customer to get started!": "Lägg till den första kunden för att komma igång.",
        "Add your first time entry to get started!": "Lägg till den första tidposten för att komma igång.",
        "After saving, add your team so people can log time": "Efter sparande: lägg till teamet så att tid kan registreras.",
        "Are you sure you want to delete": "Bekräfta borttagning av",
        "Are you sure you want to delete %s? This action cannot be undone.": "Bekräfta borttagning av %s? Åtgärden kan inte ångras.",
        "Are you sure you want to delete the project \"%s\"? This action cannot be undone.": "Bekräfta borttagning av projektet \"%s\"? Åtgärden kan inte ångras.",
        "Are you sure you want to delete this project? This action cannot be undone.": "Bekräfta borttagning av detta projekt? Åtgärden kan inte ångras.",
        "Are you sure you want to delete this time entry? This action cannot be undone.": "Bekräfta borttagning av denna tidpost? Åtgärden kan inte ångras.",
        "Are you sure you want to remove this team member?": "Bekräfta borttagning av teammedlemmen?",
        "Are you sure you want to reset all settings to their default values?": "Bekräfta återställning av alla inställningar till standardvärden?",
        "Ask an administrator if you need a new customer.": "Be en administratör om en ny kund vid behov.",
        "Ask an administrator if you need a new project.": "Be en administratör om ett nytt projekt vid behov.",
        "Before you change anything": "Innan något ändras",
        "Browser reports online. If the app does not load, use Try again.": "Webbläsaren rapporterar online. Om appen inte laddas, använd Försök igen.",
        "Budget:": "Budget:",
        "Choose how billable hours are calculated. You can change this until someone logs time on the project.": "Välj hur fakturerbara timmar beräknas. Kan ändras tills tid registreras på projektet.",
        "Configure your project management preferences": "Konfigurera projektstyrningsinställningarna",
        "Could not create customer. Please check your input.": "Kunden kunde inte skapas. Kontrollera inmatningen.",
        "Could not create project. Please check your input.": "Projektet kunde inte skapas. Kontrollera inmatningen.",
        "Could not create time entry. Please check your input.": "Tidposten kunde inte skapas. Kontrollera inmatningen.",
        "Could not reach the server to search. Check your network and try again, or use manual entry below.": "Servern kunde inte nås för sökning. Kontrollera nätverket och försök igen, eller ange manuellt nedan.",
        "Could not reach the server to search. Check your network and try again.": "Servern kunde inte nås för sökning. Kontrollera nätverket och försök igen.",
        "Could not read your settings.": "Inställningarna kunde inte läsas.",
        "Could not save rate. Please check your input.": "Taxan kunde inte sparas. Kontrollera inmatningen.",
        "Could not update customer. Please check your input.": "Kunden kunde inte uppdateras. Kontrollera inmatningen.",
        "Could not update project. Please check your input.": "Projektet kunde inte uppdateras. Kontrollera inmatningen.",
        "Could not update time entry. Please check your input.": "Tidposten kunde inte uppdateras. Kontrollera inmatningen.",
        "Create your first project to get started!": "Skapa det första projektet för att komma igång.",
        "Customer is selected. Press Save at the bottom when you are done.": "Kund vald. Tryck på Spara längst ned när allt är klart.",
        "DD/MM/YYYY": "DD/MM/ÅÅÅÅ",
        "Dashboard analytics only include your own time entries. Project cards still reflect the projects you are allowed to access.": "Dashboard-analys omfattar endast egna tidposter. Projektkort visar projekt som kontot har åtkomst till.",
        "Default duration when creating new time entries": "Standardlängd vid skapande av nya tidposter",
        "Do you want to remove this person from the selected project?": "Ta bort personen från det valda projektet?",
        "Download": "Ladda ned",
        "Employee analytics are restricted to your own time entries unless you are an administrator.": "Medarbetaranalys är begränsad till egna tidposter, om inte administratörsroll gäller.",
        "Enter a project hourly rate greater than 0 to save budget changes. You can still save name, description, dates, status, and other fields.": "Ange en projekttimtaxa större än 0 för att spara budgetändringar. Namn, beskrivning, datum, status och andra fält kan fortfarande sparas.",
        "Enter an hourly rate for this person before adding them to the project.": "Ange timtaxa för personen innan den läggs till i projektet.",
        "Everyone uses the project hourly rate you set below.": "Alla använder projekttimtaxan som anges nedan.",
        "File upload failed. Please check your input and try again.": "Filuppladdning misslyckades. Kontrollera inmatningen och försök igen.",
        "Fill in the basics, then press Save once at the bottom. You can add the team after saving.": "Fyll i grunduppgifterna och tryck på Spara en gång längst ned. Teamet kan läggas till efter sparande.",
        "Go to your Nextcloud": "Gå till Nextcloud",
        "Hours and costs for people you can see.": "Timmar och kostnader för personer som kontot har insyn i.",
        "How to set up a new project": "Så här skapas ett nytt projekt",
        "Info:": "Information:",
        "Information": "Information",
        "Log time for Active or On Hold projects you are on. Administrators may also log time on projects that use one fixed rate or organisation-wide employee rates without being on the team.": "Registrera tid på aktiva eller pausade projekt där kontot ingår i teamet. Administratörer kan även registrera tid på projekt med fast taxa eller organisationens medarbetartaxa utan teammedlemskap.",
        "Log your time": "Registrera tid",
        "Looking for the team list?": "Söks teamlistan?",
        "Looking to add or remove team members?": "Ska teammedlemmar läggas till eller tas bort?",
        "MM/DD/YYYY": "MM/DD/ÅÅÅÅ",
        "Manage your customer relationships": "Hantera kundrelationer",
        "Manage your projects": "Hantera projekt",
        "Manage your projects and track their progress": "Hantera projekt och följ framdriften",
        "Menu": "Meny",
        "Need email or address? Open full customer form": "Behövs e-post eller adress? Öppna fullständigt kundformulär",
        "No employees match your search": "Inga medarbetare matchar sökningen",
        "No projects available to assign. You need permission to manage a project team.": "Inga projekt att tilldela. Behörighet krävs för att hantera projektteam.",
        "No rates yet. Add the first rate with an effective-from date on or before the earliest work date you plan to log.": "Inga taxor ännu. Lägg till första taxan med giltighetsdatum på eller före tidigaste planerade arbetsdatum.",
        "No time entries match your filters": "Inga tidposter matchar filtren",
        "Note": "Anteckning",
        "Only active projects you can manage are listed here.": "Endast aktiva projekt som kontot får hantera listas här.",
        "Only projects you can log time on are listed. Per-person priced projects require an active team membership with a personal hourly rate.": "Endast projekt där tid kan registreras listas. Personprissatta projekt kräver aktivt teammedlemskap med egen timtaxa.",
        "Only projects you can manage are shown.": "Endast projekt som kontot får hantera visas.",
        "Only projects you can manage are shown. Already assigned projects are hidden.": "Endast projekt som kontot får hantera visas. Redan tilldelade projekt döljs.",
        "Only your data is shown": "Endast data för det aktuella kontot visas",
        "Open and invoiced hours on projects you manage.": "Öppna och fakturerade timmar på projekt som kontot hanterar.",
        "Override the budget alert thresholds your dashboard and notifications use. Leave the defaults to follow the organization settings.": "Åsidosätt budgetvarningsgränser för dashboard och aviseringar. Lämna standard för att följa organisationsinställningarna.",
        "Overview of the projects you can access and your own logged work": "Översikt över tillgängliga projekt och registrerat arbete",
        "Overview of your customer relationships and project data": "Översikt över kundrelationer och projektdata",
        "Overview of your project portfolio and performance": "Översikt över projektportfölj och resultat",
        "Overview of your projects and activities": "Översikt över projekt och aktiviteter",
        "Paste the full key from your license email. Line breaks are OK.": "Klistra in hela nyckeln från licensmejlet. Radbrytningar är tillåtna.",
        "Per-person pricing is used for this project. You must be on the team with an hourly rate to log time here. Ask a project manager to add you under Team.": "Personprissättning används för detta projekt. För att registrera tid krävs teammedlemskap med timtaxa. Be en projektledare lägga till under Team.",
        "Pick a project, enter the date and hours — that is all you need.": "Välj projekt, ange datum och timmar — det räcker.",
        "Project created. Add your team next so people can log time.": "Projekt skapat. Lägg till teamet härnäst så att tid kan registreras.",
        "Remote onboarding or a workshop so your team can roll out cleanly — billed as a service.": "Fjärrintroduktion eller workshop så att teamet kan rulla ut smidigt — faktureras som tjänst.",
        "Required when adding someone in per-person project rate mode.": "Krävs vid tillägg i läge med personbaserad projekttaxa.",
        "Review your own time tracking and yearly performance.": "Granska egen tidregistrering och årsresultat.",
        "STATUS": "Status",
        "Save this form first if you changed anything — then open the team.": "Spara formuläret först vid ändringar — öppna sedan teamet.",
        "Search could not run. Sign in again, then return to this page and try once more; your session may have ended.": "Sökning kunde inte köras. Logga in igen och återvänd till sidan; sessionen kan ha avslutats.",
        "Select entries below, then choose what happens to them. Payments always go through \"Invoiced\" first. To settle every matching entry at once, filter Settlement to Open, Invoiced, Paid, or Not billable.": "Välj poster nedan och ange vad som ska hända. Betalningar går alltid via Fakturerad först. Filtrera Avräkning på Öppen, Fakturerad, Betald eller Ej fakturerbar för att avräkna alla matchande poster.",
        "Set a project hourly rate on the project before logging time.": "Ange projekttimtaxa innan tid registreras.",
        "Set a rate when adding each team member; use effective dates when it changes.": "Ange taxa vid tillägg av varje teammedlem; använd giltighetsdatum vid ändring.",
        "Start": "Start",
        "Start tracking time for this project by adding your first time entry.": "Påbörja tidregistrering genom att lägga till första tidposten.",
        "Start tracking time. You can log your own time here because of your administrator role, even though you are not on the team.": "Påbörja tidregistrering. Administratörsrollen tillåter registrering av egen tid här trots att kontot inte ingår i teamet.",
        "Start tracking your time to see recent entries!": "Påbörja tidregistrering för att se senaste poster.",
        "Status changes are saved with the rest of the form. You can also change status from the project page.": "Statusändringar sparas med resten av formuläret. Status kan även ändras från projektsidan.",
        "Step 1 of 2": "Steg 1 av 2",
        "The ProjectCheck web app always stays free. A PC2 license unlocks named seats for the official ProjectCheck mobile companion app for your organisation.": "ProjectCheck-webbappen är alltid gratis. En PC2-licens låser upp namngivna platser för den officiella ProjectCheck-mobilappen för organisationen.",
        "The data could not be saved. Check your entries and try again.": "Data kunde inte sparas. Kontrollera inmatningen och försök igen.",
        "The form could not be read. Try again, or contact your administrator if this keeps happening.": "Formuläret kunde inte läsas. Försök igen eller kontakta administratören om felet kvarstår.",
        "The form data could not be read. Try again, or contact your administrator if this keeps happening.": "Formulärdata kunde inte läsas. Försök igen eller kontakta administratören om felet kvarstår.",
        "The numbers changed while you were looking at them. The preview has been refreshed — please check again.": "Siffrorna ändrades under granskning. Förhandsgranskningen har uppdaterats — kontrollera igen.",
        "The response was not valid. Reload the page and try again, or check with your server administrator if this keeps happening.": "Svaret var ogiltigt. Ladda om sidan och försök igen, eller kontakta serveradministratören om felet kvarstår.",
        "The server could not complete the request. You can try again. If the problem remains, your administrator can check the server log.": "Servern kunde inte slutföra begäran. Försök igen. Om problemet kvarstår kan administratören granska serverloggen.",
        "The server rejected the data. Check the form values and try again, or use your organization\u2019s help channel.": "Servern avvisade data. Kontrollera formulärvärdena och försök igen, eller använd organisationens supportkanal.",
        "There is no internet connection right now. ProjectCheck needs a connection for most features. When you are back online, use Try again to reload.": "Ingen internetanslutning just nu. ProjectCheck kräver anslutning för de flesta funktioner. När anslutningen är tillbaka, använd Försök igen.",
        "These settings are security-relevant. Wrong allowlists or administrators can lock people out of the app or grant access too widely. If you are unsure, make a small change and test with a second account before you rely on the result.": "Inställningarna är säkerhetskritiska. Felaktiga tillåtelselistor eller administratörer kan låsa ute användare eller ge för bred åtkomst. Vid osäkerhet: gör en liten ändring och testa med ett andra konto.",
        "These users can open ProjectCheck settings and the in-app organization page. System administrators are always included.": "Dessa användare kan öppna ProjectCheck-inställningar och organisationssidan i appen. Systemadministratörer ingår alltid.",
        "This license expires in {days} day(s). Renew soon to avoid an interruption for your mobile seats.": "Licensen löper ut om {days} dag(ar). Förnya i tid för att undvika avbrott för mobila platser.",
        "This page only includes projects and statistics you are allowed to access for this customer.": "Sidan visar endast projekt och statistik som kontot får se för denna kund.",
        "This person is already on all projects you can manage.": "Personen finns redan i alla projekt som kontot får hantera.",
        "This project uses a separate hourly rate for each team member. Search for a person, then enter their rate before adding them.": "Projektet har separat timtaxa per teammedlem. Sök person och ange taxa innan tillägg.",
        "This project uses one fixed hourly rate for everyone. Your entry will be billed at that project rate.": "Projektet har en fast timtaxa för alla. Posten faktureras enligt projekttaxan.",
        "This project uses your organisation-wide employee hourly rate. Make sure a rate is effective for you on the work date.": "Projektet använder organisationens medarbetartimtaxa. Säkerställ att taxa gäller på arbetsdatumet.",
        "This rate applies to all time they log on this project from today. You can set a new rate with an effective date later.": "Taxan gäller all tid på projektet från och med idag. Ny taxa med giltighetsdatum kan sättas senare.",
        "This seat exceeds the current license limit. It stays assigned but has no mobile access until you remove a seat or upgrade.": "Platsen överskrider licensgränsen. Den förblir tilldelad men utan mobil åtkomst tills en plats tas bort eller licensen uppgraderas.",
        "Tip": "Tips",
        "To avoid mistakes, use “Change status” on the project page": "För att undvika misstag, använd Ändra status på projektsidan",
        "Too many rows to export (limit %s). Narrow your filters and try again.": "För många rader att exportera (gräns %s). Begränsa filtren och försök igen.",
        "Total": "Totalt",
        "Track and manage your time entries": "Spåra och hantera tidposter",
        "Try adjusting your search criteria or clear the filters to see all entries.": "Justera sökkriterierna eller rensa filtren för att se alla poster.",
        "Try adjusting your search criteria.": "Justera sökkriterierna.",
        "Type": "Typ",
        "Understanding your billable vs overhead ratio helps you:": "Förståelse av förhållandet fakturerbart kontra overhead hjälper till att:",
        "User search failed. Check your connection and try again.": "Användarsökning misslyckades. Kontrollera anslutningen och försök igen.",
        "Version:": "Version:",
        "We could not save your settings. Please try again.": "Inställningarna kunde inte sparas. Försök igen.",
        "YYYY-MM-DD": "ÅÅÅÅ-MM-DD",
        "You are logging time as an administrator": "Tid registreras som administratör",
        "You are not allowed to search the directory.": "Katalogsökning är inte tillåten.",
        "You are not allowed to search the directory. If this is unexpected, reload the page, or use manual entry below.": "Katalogsökning är inte tillåten. Om detta är oväntat, ladda om sidan eller ange manuellt nedan.",
        "You are not on this project team, but your administrator role lets you record your own time here.": "Kontot ingår inte i projektteamet, men administratörsrollen tillåter registrering av egen tid här.",
        "You are not on this project team. Your administrator role lets you record your own time here only.": "Kontot ingår inte i projektteamet. Administratörsrollen tillåter endast registrering av egen tid här.",
        "You are not signed in.": "Ingen inloggning.",
        "You are offline": "Offline",
        "You are still offline.": "Fortfarande offline.",
        "You can log time on this project because of your administrator role, not because you are on the team.": "Tid kan registreras på projektet på grund av administratörsroll, inte teammedlemskap.",
        "You can log time only for projects with status Active or On Hold that you can access (creator, admin, or active team member).": "Tid kan registreras endast på projekt med status Aktiv eller Pausad som kontot har åtkomst till (skapare, admin eller aktivt teammedlem).",
        "You can only open your own employee profile unless you are an administrator.": "Endast eget medarbetarprofil kan öppnas, om inte administratörsroll gäller.",
        "You can upload up to %d files at once.": "Upp till %d filer kan laddas upp åt gången.",
        "You can upload up to 20 files at once.": "Upp till 20 filer kan laddas upp åt gången.",
        "You do not currently have access to ProjectCheck. Ask an administrator if you should be added.": "Ingen åtkomst till ProjectCheck för närvarande. Be en administratör om tillägg vid behov.",
        "You do not have a mobile seat assigned.": "Ingen mobil plats tilldelad.",
        "You do not have access to ProjectCheck.": "Ingen åtkomst till ProjectCheck.",
        "You do not have permission for this action.": "Ingen behörighet för denna åtgärd.",
        "You do not have permission to change organization settings for ProjectCheck.": "Ingen behörighet att ändra organisationsinställningar för ProjectCheck.",
        "You do not have permission to manage the ProjectCheck license.": "Ingen behörighet att hantera ProjectCheck-licensen.",
        "You do not have permission to perform this action": "Ingen behörighet att utföra denna åtgärd",
        "You do not have permission to save. If you are not a system administrator, you may have been removed from the app's administrator list. Reload the page.": "Ingen behörighet att spara. Om kontot inte är systemadministratör kan det ha tagits bort från appadministratörslistan. Ladda om sidan.",
        "You must be on the project team to log time.": "Teammedlemskap krävs för att registrera tid.",
        "You must be on the project team to log time. Ask a project manager to add you under Team on the project page.": "Teammedlemskap krävs. Be en projektledare lägga till under Team på projektsidan.",
        "Your default hourly rate for time entries": "Standardtimtaxa för tidposter",
        "Your latest project activities": "Senaste projektaktiviteter",
        "Your latest time tracking activities": "Senaste tidregistreringar",
        "Your license is about to expire": "Licensen löper snart ut",
        "Your mobile seat is above the licensed limit.": "Mobil plats ligger över licensgränsen.",
        "Your preferences were saved.": "Inställningarna sparades.",
        "Your session may have expired. Sign in again, then return to this page.": "Sessionen kan ha gått ut. Logga in igen och återvänd till sidan.",
        "Your time entry": "Tidpost",
        "Your time entry on %1$s (%2$s) changed from %3$s to %4$s.": "Tidposten %1$s (%2$s) ändrades från %3$s till %4$s.",
        "Your work overview": "Arbetsöversikt",
        "You\u2019re offline": "Offline",
        "total": "totalt",
        "Note: {note}": "Anteckning: {note}",
        "Step 1 of 2: invoice all open hours. After you confirm, you will review invoiced hours before marking them paid. Nothing skips the invoiced step.": "Steg 1 av 2: fakturera alla öppna timmar. Efter bekräftelse granskas fakturerade timmar innan de markeras som betalda. Inget hoppar över steget Fakturerad.",
        "The server rejected the data. Check the form values and try again, or use your organization\u2019s help channel.": "Servern avvisade data. Kontrollera formulärvärdena och försök igen, eller använd organisationens supportkanal.",
        "Tip: save this form first if you have unsaved changes — leaving the page will discard them.": "Tips: spara formuläret först vid osparade ändringar — sidan kastas bort vid navigering bort.",
        "To avoid mistakes, use \u201cChange status\u201d on the project page: transitions are validated there. Archiving removes the project from the default list and stops new time entries until you reactivate.": "För att undvika misstag, använd Ändra status på projektsidan: övergångar valideras där. Arkivering tar bort projektet från standardlistan och stoppar nya tidposter tills det återaktiveras.",
        "You do not have permission to save. If you are not a system administrator, you may have been removed from the app\u2019s administrator list. Reload the page.": "Ingen behörighet att spara. Om kontot inte är systemadministratör kan det ha tagits bort från appadministratörslistan. Ladda om sidan.",
    },
    "it": {
        "Access restriction is currently turned on. Users who are not listed in the allowlists (and are not system administrators) cannot use ProjectCheck, including this page if your account is affected.": "La restrizione di accesso è attiva. Gli utenti non presenti nelle liste di autorizzazione (e non amministratori di sistema) non possono usare ProjectCheck, inclusa questa pagina se l'account è interessato.",
        "Add your first customer to get started!": "Aggiunga il primo cliente per iniziare.",
        "Add your first time entry to get started!": "Aggiunga la prima registrazione oraria per iniziare.",
        "Budget:": "Budget:",
        "Create your first project to get started!": "Crei il primo progetto per iniziare.",
        "Dashboard analytics only include your own time entries. Project cards still reflect the projects you are allowed to access.": "Le analisi della dashboard includono solo le registrazioni orarie proprie. Le schede progetto riflettono i progetti accessibili.",
        "Email": "E-mail",
        "Employee analytics are restricted to your own time entries unless you are an administrator.": "Le analisi dei dipendenti sono limitate alle proprie registrazioni orarie, salvo ruolo amministratore.",
        "File": "File",
        "Go to your Nextcloud": "Vada al Suo Nextcloud",
        "Home": "Home",
        "Info:": "Informazioni:",
        "Log your time": "Registri il tempo",
        "Manage your projects": "Gestisca i progetti",
        "Manage your projects and track their progress": "Gestisca i progetti e ne monitori l'avanzamento",
        "Menu": "Menù",
        "Only your data is shown": "Sono mostrati solo i dati dell'account corrente",
        "Overview of the projects you can access and your own logged work": "Panoramica dei progetti accessibili e del lavoro registrato",
        "Overview of your projects and activities": "Panoramica dei progetti e delle attività",
        "Review your own time tracking and yearly performance.": "Esamini la propria registrazione oraria e le prestazioni annuali.",
        "Start tracking time for this project by adding your first time entry.": "Avvii la registrazione oraria aggiungendo la prima voce per questo progetto.",
        "Start tracking time. You can log your own time here because of your administrator role, even though you are not on the team.": "Avvii la registrazione oraria. Il ruolo amministratore consente di registrare qui il proprio tempo pur non essendo nel team.",
        "Start tracking your time to see recent entries!": "Avvii la registrazione oraria per vedere le voci recenti.",
        "The server rejected the data. Check the form values and try again, or use your organization\u2019s help channel.": "Il server ha rifiutato i dati. Controlli i valori del modulo e riprovi, oppure usi il canale di assistenza dell'organizzazione.",
        "This project uses one fixed hourly rate for everyone. Your entry will be billed at that project rate.": "Questo progetto usa una tariffa oraria fissa per tutti. La voce sarà fatturata a quella tariffa di progetto.",
        "Track and manage your time entries": "Monitori e gestisca le registrazioni orarie",
        "Understanding your billable vs overhead ratio helps you:": "Comprendere il rapporto tra ore fatturabili e overhead aiuta a:",
        "You are not on this project team, but your administrator role lets you record your own time here.": "L'account non è nel team del progetto, ma il ruolo amministratore consente di registrare qui il proprio tempo.",
        "You are not on this project team. Your administrator role lets you record your own time here only.": "L'account non è nel team del progetto. Il ruolo amministratore consente di registrare qui solo il proprio tempo.",
        "You can only open your own employee profile unless you are an administrator.": "È possibile aprire solo il proprio profilo dipendente, salvo ruolo amministratore.",
        "Your default hourly rate for time entries": "Tariffa oraria predefinita per le registrazioni",
        "Your latest project activities": "Ultime attività di progetto",
        "Your latest time tracking activities": "Ultime registrazioni orarie",
        "Your preferences were saved.": "Le preferenze sono state salvate.",
        "Your time entry": "Registrazione oraria",
        "Your work overview": "Panoramica del lavoro",
    },
    "pt_BR": {
        "Choose how billable hours are calculated. You can change this until someone logs time on the project.": "Escolha como as horas faturáveis são calculadas. Isso pode ser alterado até que alguém registre tempo no projeto.",
        "Dashboard analytics only include your own time entries. Project cards still reflect the projects you are allowed to access.": "A análise do painel inclui apenas os próprios lançamentos de horas. Os cartões de projeto refletem os projetos acessíveis.",
        "Employee analytics are restricted to your own time entries unless you are an administrator.": "As análises de colaboradores ficam limitadas aos próprios lançamentos de horas, salvo perfil de administrador.",
        "Enter a project hourly rate greater than 0 to save budget changes. You can still save name, description, dates, status, and other fields.": "Informe uma taxa horária de projeto maior que 0 para salvar alterações de orçamento. Nome, descrição, datas, status e outros campos ainda podem ser salvos.",
        "Everyone uses the project hourly rate you set below.": "Todos usam a taxa horária de projeto definida abaixo.",
        "Fill in the basics, then press Save once at the bottom. You can add the team after saving.": "Preencha os dados básicos e pressione Salvar uma vez na parte inferior. A equipe pode ser adicionada após salvar.",
        "Hours and costs for people you can see.": "Horas e custos das pessoas visíveis para esta conta.",
        "Log time for Active or On Hold projects you are on. Administrators may also log time on projects that use one fixed rate or organisation-wide employee rates without being on the team.": "Registre horas em projetos Ativos ou Em espera em que a conta participa. Administradores também podem registrar horas em projetos com taxa fixa ou taxa organizacional sem estar na equipe.",
        "Menu": "Cardápio",
        "No projects available to assign. You need permission to manage a project team.": "Nenhum projeto disponível para atribuição. É necessária permissão para gerenciar a equipe do projeto.",
        "No rates yet. Add the first rate with an effective-from date on or before the earliest work date you plan to log.": "Ainda não há taxas. Adicione a primeira taxa com data de vigência igual ou anterior à data de trabalho mais antiga planejada.",
        "Only active projects you can manage are listed here.": "Somente projetos ativos que esta conta pode gerenciar são listados aqui.",
        "Only projects you can log time on are listed. Per-person priced projects require an active team membership with a personal hourly rate.": "Somente projetos em que é possível registrar horas são listados. Projetos com preço por pessoa exigem participação ativa na equipe com taxa horária pessoal.",
        "Only projects you can manage are shown.": "Somente projetos que esta conta pode gerenciar são exibidos.",
        "Only projects you can manage are shown. Already assigned projects are hidden.": "Somente projetos gerenciáveis são exibidos. Projetos já atribuídos ficam ocultos.",
        "Open and invoiced hours on projects you manage.": "Horas em aberto e faturadas nos projetos gerenciados por esta conta.",
        "Overview of the projects you can access and your own logged work": "Visão geral dos projetos acessíveis e do trabalho registrado",
        "Per-person pricing is used for this project. You must be on the team with an hourly rate to log time here. Ask a project manager to add you under Team.": "Este projeto usa preço por pessoa. Para registrar horas, é necessário estar na equipe com taxa horária. Solicite a um gerente de projeto inclusão em Equipe.",
        "Pick a project, enter the date and hours — that is all you need.": "Escolha um projeto, informe data e horas — isso é suficiente.",
        "STATUS": "Status",
        "Save this form first if you changed anything — then open the team.": "Salve o formulário primeiro se houve alterações — depois abra a equipe.",
        "Start tracking time. You can log your own time here because of your administrator role, even though you are not on the team.": "Inicie o registro de horas. O perfil de administrador permite registrar horas próprias aqui mesmo sem participar da equipe.",
        "Status changes are saved with the rest of the form. You can also change status from the project page.": "Alterações de status são salvas com o restante do formulário. O status também pode ser alterado na página do projeto.",
        "Step 1 of 2: invoice all open hours. After you confirm, you will review invoiced hours before marking them paid. Nothing skips the invoiced step.": "Passo 1 de 2: faturar todas as horas em aberto. Após confirmar, revise as horas faturadas antes de marcá-las como pagas. Nada ignora a etapa Faturado.",
        "The numbers changed while you were looking at them. The preview has been refreshed — please check again.": "Os números mudaram durante a visualização. A pré-visualização foi atualizada — verifique novamente.",
        "The server could not complete the request. You can try again. If the problem remains, your administrator can check the server log.": "O servidor não concluiu a solicitação. Tente novamente. Se o problema persistir, o administrador pode verificar o log do servidor.",
        "There is no internet connection right now. ProjectCheck needs a connection for most features. When you are back online, use Try again to reload.": "Não há conexão com a Internet no momento. O ProjectCheck precisa de conexão para a maioria dos recursos. Quando a conexão voltar, use Tentar novamente.",
        "This page only includes projects and statistics you are allowed to access for this customer.": "Esta página inclui apenas projetos e estatísticas permitidos para este cliente.",
        "This person is already on all projects you can manage.": "Esta pessoa já está em todos os projetos gerenciáveis por esta conta.",
        "This project uses your organisation-wide employee hourly rate. Make sure a rate is effective for you on the work date.": "Este projeto usa a taxa horária organizacional de colaboradores. Confirme que há taxa vigente na data de trabalho.",
        "This rate applies to all time they log on this project from today. You can set a new rate with an effective date later.": "Esta taxa se aplica a todo o tempo registrado neste projeto a partir de hoje. Uma nova taxa com data de vigência pode ser definida depois.",
        "This seat exceeds the current license limit. It stays assigned but has no mobile access until you remove a seat or upgrade.": "Este assento excede o limite de licença atual. Permanece atribuído, mas sem acesso móvel até remover um assento ou fazer upgrade.",
        "Tip: save this form first if you have unsaved changes — leaving the page will discard them.": "Dica: salve o formulário primeiro se houver alterações não salvas — sair da página descarta as alterações.",
        "To avoid mistakes, use \u201cChange status\u201d on the project page: transitions are validated there. Archiving removes the project from the default list and stops new time entries until you reactivate.": "Para evitar erros, use Alterar status na página do projeto: as transições são validadas lá. Arquivar remove o projeto da lista padrão e impede novos lançamentos até reativar.",
        "Total": "Total geral",
        "Total: %s": "Total: %s",
        "Total: {hours}": "Total: {hours}",
        "Understanding your billable vs overhead ratio helps you:": "Entender a relação entre horas faturáveis e overhead ajuda a:",
        "You are logging time as an administrator": "Registro de horas como administrador",
        "You are not allowed to search the directory.": "A busca no diretório não é permitida.",
        "You are not allowed to search the directory. If this is unexpected, reload the page, or use manual entry below.": "A busca no diretório não é permitida. Se isso for inesperado, recarregue a página ou use entrada manual abaixo.",
        "You are not on this project team, but your administrator role lets you record your own time here.": "Esta conta não está na equipe do projeto, mas o perfil de administrador permite registrar horas próprias aqui.",
        "You are not on this project team. Your administrator role lets you record your own time here only.": "Esta conta não está na equipe do projeto. O perfil de administrador permite registrar aqui apenas horas próprias.",
        "You are not signed in.": "Sessão não iniciada.",
        "You are offline": "Sem conexão",
        "You are still offline.": "Ainda sem conexão.",
        "You can log time on this project because of your administrator role, not because you are on the team.": "É possível registrar horas neste projeto por causa do perfil de administrador, não por participação na equipe.",
        "You can log time only for projects with status Active or On Hold that you can access (creator, admin, or active team member).": "Horas só podem ser registradas em projetos Ativos ou Em espera acessíveis (criador, admin ou membro ativo da equipe).",
        "You can only open your own employee profile unless you are an administrator.": "Só é possível abrir o próprio perfil de colaborador, salvo perfil de administrador.",
        "You can upload up to %d files at once.": "Até %d arquivos podem ser enviados de uma vez.",
        "You can upload up to 20 files at once.": "Até 20 arquivos podem ser enviados de uma vez.",
        "You do not currently have access to ProjectCheck. Ask an administrator if you should be added.": "Sem acesso ao ProjectCheck no momento. Solicite inclusão a um administrador, se necessário.",
        "You do not have a mobile seat assigned.": "Nenhum assento móvel atribuído.",
        "You do not have access to ProjectCheck.": "Sem acesso ao ProjectCheck.",
        "You do not have permission for this action.": "Sem permissão para esta ação.",
        "You do not have permission to change organization settings for ProjectCheck.": "Sem permissão para alterar configurações organizacionais do ProjectCheck.",
        "You do not have permission to manage the ProjectCheck license.": "Sem permissão para gerenciar a licença do ProjectCheck.",
        "You do not have permission to perform this action": "Sem permissão para executar esta ação",
        "You do not have permission to save. If you are not a system administrator, you may have been removed from the app\u2019s administrator list. Reload the page.": "Sem permissão para salvar. Se a conta não for administradora do sistema, pode ter sido removida da lista de administradores do app. Recarregue a página.",
        "You must be on the project team to log time.": "Participação na equipe do projeto é necessária para registrar horas.",
        "You must be on the project team to log time. Ask a project manager to add you under Team on the project page.": "Participação na equipe é necessária. Solicite inclusão em Equipe na página do projeto a um gerente de projeto.",
        "You\u2019re offline": "Sem conexão",
        "total": "total geral",
    },
}

# Danish and Norwegian: derive from Swedish with locale-specific adjustments where needed.
DA_FROM_SV = {
    "Menu": "Menukort",
    "Download": "Hent",
    "Start": "Start",
    "Type": "Type",
    "Note": "Note",
    "Tip": "Tip",
    "Information": "Information",
    "Offline": "Offline",
    "You\u2019re offline": "Offline",
    "You are offline": "Offline",
    "You are still offline.": "Stadig offline.",
    "You are not signed in.": "Ikke logget ind.",
    "Version:": "Version:",
    "Budget:": "Budget:",
    "Info:": "Information:",
    "STATUS": "Status",
    "Total": "I alt",
    "total": "i alt",
    "DD/MM/YYYY": "DD/MM/ÅÅÅ",
    "MM/DD/YYYY": "MM/DD/ÅÅÅ",
    "YYYY-MM-DD": "ÅÅÅÅ-MM-DD",
}

NB_FROM_SV = {
    "Menu": "Meny",
    "Download": "Last ned",
    "Start": "Start",
    "Type": "Type",
    "Note": "Notat",
    "Tip": "Tips",
    "Information": "Informasjon",
    "Offline": "Frakoblet",
    "You\u2019re offline": "Frakoblet",
    "You are offline": "Frakoblet",
    "You are still offline.": "Fortsatt frakoblet.",
    "You are not signed in.": "Ikke pålogget.",
    "Version:": "Versjon:",
    "Budget:": "Budsjett:",
    "Info:": "Informasjon:",
    "STATUS": "Status",
    "Total": "Totalt",
    "total": "totalt",
    "DD/MM/YYYY": "DD/MM/ÅÅÅ",
    "MM/DD/YYYY": "MM/DD/ÅÅÅ",
    "YYYY-MM-DD": "ÅÅÅÅ-MM-DD",
}


def sv_to_da(text: str) -> str:
    repl = [
        ("organisationen", "organisationen"),
        ("Tryck på", "Tryk på"),
        ("Spara", "Gem"),
        ("längst ned", "nederst"),
        ("Försök igen", "Prøv igen"),
        ("Webbläsaren", "Browseren"),
        ("kontot", "kontoet"),
        ("Medarbetaranalys", "Medarbejderanalyse"),
        ("tidposter", "tidsregistreringer"),
        ("Tidpost", "Tidsregistrering"),
        ("Projekttimtaxa", "Projekttimepris"),
        ("timtaxa", "timepris"),
        ("taxa", "sats"),
        ("Taxan", "Satsen"),
        ("taxor", "satser"),
        ("Granska", "Gennemgå"),
        ("Ladda om", "Genindlæs"),
        ("Logga in", "Log ind"),
        ("sessionen", "sessionen"),
        ("Förhandsgranskningen", "Forhåndsvisningen"),
        ("Siffrorna", "Tallene"),
        ("Inställningarna", "Indstillingerne"),
        ("Administratörsrollen", "Administratorrollen"),
        ("teammedlem", "teammedlem"),
        ("projektteamet", "projektteamet"),
        ("Åtkomst", "Adgang"),
        ("behörighet", "tilladelse"),
        ("Behörighet", "Tilladelse"),
        ("användare", "bruger"),
        ("Användare", "Bruger"),
        ("Skapa", "Opret"),
        ("Lägg till", "Tilføj"),
        ("Ta bort", "Fjern"),
        ("Välj", "Vælg"),
        ("Kontrollera", "Kontrollér"),
        ("Fakturerade", "Fakturerede"),
        ("Öppna", "Åbne"),
        ("Steg", "Trin"),
        ("Tips", "Tip"),
        ("Anteckning", "Note"),
        ("Meny", "Menukort"),
        ("Ladda ned", "Hent"),
        ("Fortfarande", "Stadig"),
        ("Ingen inloggning", "Ikke logget ind"),
        ("Offline", "Offline"),
        ("ÅÅÅÅ", "ÅÅÅ"),
    ]
    out = text
    for a, b in repl:
        out = out.replace(a, b)
    return out


def sv_to_nb(text: str) -> str:
    repl = [
        ("Tryck på", "Trykk"),
        ("Spara", "Lagre"),
        ("längst ned", "nederst"),
        ("Försök igen", "Prøv igjen"),
        ("Webbläsaren", "Nettleseren"),
        ("kontot", "kontoet"),
        ("Medarbetaranalys", "Ansattanalyse"),
        ("tidposter", "tidsregistreringer"),
        ("Tidpost", "Tidsregistrering"),
        ("Projekttimtaxa", "Prosjekttimepris"),
        ("timtaxa", "timepris"),
        ("taxa", "sats"),
        ("Taxan", "Satsen"),
        ("taxor", "satser"),
        ("Granska", "Gjennomgå"),
        ("Ladda om", "Last inn på nytt"),
        ("Logga in", "Logg inn"),
        ("Förhandsgranskningen", "Forhåndsvisningen"),
        ("Siffrorna", "Tallene"),
        ("Inställningarna", "Innstillingene"),
        ("Administratörsrollen", "Administratorrollen"),
        ("teammedlem", "teammedlem"),
        ("projektteamet", "prosjektteamet"),
        ("Åtkomst", "Tilgang"),
        ("behörighet", "tillatelse"),
        ("Behörighet", "Tillatelse"),
        ("användare", "bruker"),
        ("Användare", "Brukere"),
        ("Skapa", "Opprett"),
        ("Lägg till", "Legg til"),
        ("Ta bort", "Fjern"),
        ("Välj", "Velg"),
        ("Kontrollera", "Kontroller"),
        ("Fakturerade", "Fakturerte"),
        ("Öppna", "Åpne"),
        ("Steg", "Trinn"),
        ("Anteckning", "Notat"),
        ("Meny", "Meny"),
        ("Ladda ned", "Last ned"),
        ("Fortfarande", "Fortsatt"),
        ("Ingen inloggning", "Ikke pålogget"),
        ("Offline", "Frakoblet"),
        ("ÅÅÅÅ", "ÅÅÅ"),
        ("Budget:", "Budsjett:"),
        ("Version:", "Versjon:"),
        ("Information:", "Informasjon:"),
    ]
    out = text
    for a, b in repl:
        out = out.replace(a, b)
    return out


def build_locale(lang: str) -> dict[str, str]:
    fail = json.loads((L10N / f"_fail_{lang}.json").read_text(encoding="utf-8"))["all"]
    seeds = load_seeds(lang)
    fixes: dict[str, str] = {}
    sv_map = OVERRIDES.get("sv", {})
    for key in fail:
        if key in seeds:
            fixes[key] = seeds[key]
        elif lang == "sv" and key in sv_map:
            fixes[key] = sv_map[key]
        elif lang == "da" and key in sv_map:
            fixes[key] = DA_FROM_SV.get(key, sv_to_da(sv_map[key]))
        elif lang == "nb" and key in sv_map:
            fixes[key] = NB_FROM_SV.get(key, sv_to_nb(sv_map[key]))
        elif lang in OVERRIDES and key in OVERRIDES[lang]:
            fixes[key] = OVERRIDES[lang][key]
    return fixes


def main() -> None:
    for lang in LOCALES:
        fixes = build_locale(lang)
        fail = json.loads((L10N / f"_fail_{lang}.json").read_text(encoding="utf-8"))["all"]
        missing = [k for k in fail if k not in fixes]
        out = L10N / f"_quality_fixes_{lang}.json"
        out.write_text(json.dumps(fixes, ensure_ascii=False, indent="\t") + "\n", encoding="utf-8")
        print(f"{lang}: {len(fixes)} fixes, {len(missing)} missing")
        if missing:
            (L10N / f"_missing_after_build_{lang}.json").write_text(
                json.dumps(missing, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
            )


if __name__ == "__main__":
    main()
