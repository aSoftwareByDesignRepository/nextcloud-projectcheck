<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$en = json_decode((string) file_get_contents($base . '/l10n/en.json'), true, 512, JSON_THROW_ON_ERROR);
$de = json_decode((string) file_get_contents($base . '/l10n/de.json'), true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, string> */
$deMap = [
	'Add project' => 'Projekt hinzufügen',
	'Budget: %1$s%% consumed' => 'Budget: %1$s%% verbraucht',
	'Calculated from budget ÷ planning rate (optional). Actual cost uses each person’s billing rate.' => 'Berechnet aus Budget ÷ Planungssatz (optional). Tatsächliche Kosten nutzen den Abrechnungssatz je Person.',
	'Changes: {changes}' => 'Änderungen: {changes}',
	'Confirm delete of %s' => 'Löschen von %s bestätigen',
	'Could not open the confirmation dialog. Reload the page and try again.' => 'Bestätigungsdialog konnte nicht geöffnet werden. Seite neu laden und erneut versuchen.',
	'Could not save rate.' => 'Satz konnte nicht gespeichert werden.',
	'Could not save rate. Please check your input.' => 'Satz konnte nicht gespeichert werden. Bitte Eingaben prüfen.',
	'Customer deleted: {customer}' => 'Kunde gelöscht: {customer}',
	'Customer updated: {customer}' => 'Kunde aktualisiert: {customer}',
	'Customer: %s' => 'Kunde: %s',
	'Deadline approaching: Project {project} is due in {days} days' => 'Frist naht: Projekt {project} ist in {days} Tagen fällig',
	'Deadline overdue: Project {project} is overdue by {days} days' => 'Frist überschritten: Projekt {project} ist um {days} Tage überfällig',
	'Description: {description}' => 'Beschreibung: {description}',
	'End date must be on or after the start date.' => 'Das Enddatum muss am oder nach dem Startdatum liegen.',
	'File' => 'Datei',
	'File deleted successfully' => 'Datei erfolgreich gelöscht',
	'Full Description' => 'Vollständige Beschreibung',
	'Hour estimate unavailable — costs use each person’s rate. Add an optional planning rate on the project to estimate capacity.' => 'Keine Stundenschätzung — Kosten nutzen den Satz je Person. Optionalen Planungssatz am Projekt hinterlegen, um Kapazität zu schätzen.',
	'Hours must be greater than zero' => 'Stunden müssen größer als null sein',
	'Missing CSRF request token.' => 'CSRF-Anforderungstoken fehlt.',
	'New customer created: {customer}' => 'Neuer Kunde erstellt: {customer}',
	'New project created: {project}' => 'Neues Projekt erstellt: {project}',
	'Page %1$s of %2$s' => 'Seite %1$s von %2$s',
	'Please ensure all tasks are completed before the deadline.' => 'Stellen Sie sicher, dass alle Aufgaben vor der Frist abgeschlossen sind.',
	'Please review the project budget and consider taking action.' => 'Bitte prüfen Sie das Projektbudget und erwägen Sie Maßnahmen.',
	'Project deleted: {project}' => 'Projekt gelöscht: {project}',
	'Project status changed: {project} is now {status}' => 'Projektstatus geändert: {project} ist jetzt {status}',
	'Project updated: {project}' => 'Projekt aktualisiert: {project}',
	'Project {project} has reached {percentage}% of its budget' => 'Projekt {project} hat {percentage}% des Budgets erreicht',
	'Project: %s' => 'Projekt: %s',
	'Rate resolved from server for this project and work date.' => 'Satz serverseitig aus Projekt und Arbeitstag ermittelt.',
	'Request failed.' => 'Anfrage fehlgeschlagen.',
	'Submitting...' => 'Wird gesendet …',
	'Team member' => 'Teammitglied',
	'The project has exceeded its allocated budget. Immediate action is required.' => 'Das Projekt hat sein zugewiesenes Budget überschritten. Sofortiges Handeln ist erforderlich.',
	'The project is overdue. Please update the project status or extend the deadline.' => 'Das Projekt ist überfällig. Bitte Projektstatus aktualisieren oder die Frist verlängern.',
	'This project uses per-person rates.' => 'Dieses Projekt nutzt personenbezogene Sätze.',
	'Time Entry: %s hours on %s' => 'Zeiteintrag: %s Stunden auf %s',
	'Time entry deleted from project {project}' => 'Zeiteintrag aus Projekt {project} gelöscht',
	'Time entry logged: {hours} hours on {project}' => 'Zeiteintrag erfasst: {hours} Stunden auf {project}',
	'Time entry updated on project {project}' => 'Zeiteintrag in Projekt {project} aktualisiert',
	'View all projects' => 'Alle Projekte anzeigen',
	'You are offline' => 'Sie sind offline',
	'You’re offline' => 'Sie sind offline',
	'There is no internet connection right now. ProjectCheck needs a connection for most features. When you are back online, use Try again to reload.' => 'Derzeit besteht keine Internetverbindung. ProjectCheck benötigt für die meisten Funktionen eine Verbindung. Wenn Sie wieder online sind, nutzen Sie „Erneut versuchen“, um neu zu laden.',
	'Try again' => 'Erneut versuchen',
	'Check connection' => 'Verbindung prüfen',
	'Checking connection…' => 'Verbindung wird geprüft …',
	'Connection restored. Reloading…' => 'Verbindung wiederhergestellt. Wird neu geladen …',
	'Still offline. Check your network, then try again.' => 'Weiterhin offline. Netzwerk prüfen und erneut versuchen.',
	'You are still offline.' => 'Sie sind weiterhin offline.',
	'Browser reports online. If the app does not load, use Try again.' => 'Der Browser meldet „online“. Wenn die App nicht lädt, „Erneut versuchen“ nutzen.',
	'Offline — ProjectCheck' => 'Offline — ProjectCheck',
	'{actor} changed status of project {project} to {status}' => '{actor} hat den Status von Projekt {project} auf {status} geändert',
	'{actor} created customer {customer}' => '{actor} hat Kunde {customer} erstellt',
	'{actor} created project {project}' => '{actor} hat Projekt {project} erstellt',
	'{actor} deleted customer {customer}' => '{actor} hat Kunde {customer} gelöscht',
	'{actor} deleted project {project}' => '{actor} hat Projekt {project} gelöscht',
	'{actor} deleted time entry from project {project}' => '{actor} hat Zeiteintrag aus Projekt {project} gelöscht',
	'{actor} logged {hours} hours on project {project}' => '{actor} hat {hours} Stunden auf Projekt {project} erfasst',
	'{actor} removed {member} from project {project}' => '{actor} hat {member} aus Projekt {project} entfernt',
	'{actor} updated customer {customer}' => '{actor} hat Kunde {customer} aktualisiert',
	'{actor} updated project {project}' => '{actor} hat Projekt {project} aktualisiert',
	'{actor} updated time entry on project {project}' => '{actor} hat Zeiteintrag in Projekt {project} aktualisiert',
];

$added = 0;
foreach ($en['translations'] as $key => $val) {
	if (!isset($de['translations'][$key])) {
		$de['translations'][$key] = $deMap[$key] ?? $key;
		$added++;
	}
}

ksort($de['translations']);
file_put_contents($base . '/l10n/de.json', json_encode($de, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo "de.json: +$added keys, total " . count($de['translations']) . "\n";
