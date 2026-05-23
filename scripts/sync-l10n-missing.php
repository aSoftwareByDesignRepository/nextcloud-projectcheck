<?php

declare(strict_types=1);

/**
 * Adds missing ->t() msgids to l10n/en.json and l10n/de.json (audit TX.1).
 */

$base = dirname(__DIR__);
$enPath = $base . '/l10n/en.json';
$dePath = $base . '/l10n/de.json';

$en = json_decode((string) file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
$de = json_decode((string) file_get_contents($dePath), true, 512, JSON_THROW_ON_ERROR);

$missingFile = '/tmp/pc-missing-l10n.txt';
if (!is_file($missingFile)) {
	fwrite(STDERR, "Run find-missing-l10n.php first.\n");
	exit(1);
}
$keys = array_filter(array_map('trim', file($missingFile, FILE_IGNORE_NEW_LINES)));

/** @var array<string, string> */
$deMap = [
	'(today)' => '(heute)',
	'A rate with this effective-from date already exists. Choose a different date.' => 'Für dieses Gültig-ab-Datum existiert bereits ein Satz. Wählen Sie ein anderes Datum.',
	'Add each person individually with their rate when using per-person project pricing.' => 'Bei personenbezogener Projektbewertung jede Person einzeln mit Satz hinzufügen.',
	'Add rate' => 'Satz hinzufügen',
	'Append-only rates used when projects price hours by employee. Time entries keep the rate that was effective on the work date.' => 'Nur ergänzbare Sätze für Mitarbeitenden-Bewertung. Zeiteinträge behalten den am Arbeitstag gültigen Satz.',
	'Available hours are calculated from budget ÷ project hourly rate.' => 'Verfügbare Stunden = Budget ÷ Projekt-Stundensatz.',
	'Budget & capacity' => 'Budget & Kapazität',
	'Budget critical' => 'Budget kritisch',
	'Budget on track' => 'Budget im Plan',
	'Budget warning' => 'Budget-Warnung',
	'Choose how billable hours are calculated. You can change this until someone logs time on the project.' => 'Legen Sie fest, wie abrechenbare Stunden berechnet werden. Änderbar, bis jemand Zeit auf dem Projekt erfasst.',
	'Could not add team member.' => 'Teammitglied konnte nicht hinzugefügt werden.',
	'Could not assign employee to project.' => 'Mitarbeitende/r konnte dem Projekt nicht zugewiesen werden.',
	'Could not calculate budget impact.' => 'Budgetauswirkung konnte nicht berechnet werden.',
	'Could not create customer. Please check your input.' => 'Kunde konnte nicht erstellt werden. Bitte Eingaben prüfen.',
	'Could not create project. Please check your input.' => 'Projekt konnte nicht erstellt werden. Bitte Eingaben prüfen.',
	'Could not create time entry. Please check your input.' => 'Zeiteintrag konnte nicht erstellt werden. Bitte Eingaben prüfen.',
	'Could not delete customer.' => 'Kunde konnte nicht gelöscht werden.',
	'Could not delete project.' => 'Projekt konnte nicht gelöscht werden.',
	'Could not delete the file.' => 'Datei konnte nicht gelöscht werden.',
	'Could not delete time entry.' => 'Zeiteintrag konnte nicht gelöscht werden.',
	'Could not filter projects.' => 'Projekte konnten nicht gefiltert werden.',
	'Could not load analytics data.' => 'Analysedaten konnten nicht geladen werden.',
	'Could not load budget information.' => 'Budgetinformationen konnten nicht geladen werden.',
	'Could not load customer projects.' => 'Kundenprojekte konnten nicht geladen werden.',
	'Could not load deletion impact.' => 'Löschauswirkung konnte nicht geladen werden.',
	'Could not load project details.' => 'Projektdetails konnten nicht geladen werden.',
	'Could not load project files.' => 'Projektdateien konnten nicht geladen werden.',
	'Could not load projects.' => 'Projekte konnten nicht geladen werden.',
	'Could not load team members.' => 'Teammitglieder konnten nicht geladen werden.',
	'Could not remove employee from project.' => 'Mitarbeitende/r konnte nicht vom Projekt entfernt werden.',
	'Could not remove project member.' => 'Projektmitglied konnte nicht entfernt werden.',
	'Could not remove team member.' => 'Teammitglied konnte nicht entfernt werden.',
	'Could not resolve hourly rate.' => 'Stundensatz konnte nicht ermittelt werden.',
	'Could not save rate' => 'Satz konnte nicht gespeichert werden',
	'Could not search projects.' => 'Projektsuche fehlgeschlagen.',
	'Could not update customer. Please check your input.' => 'Kunde konnte nicht aktualisiert werden. Bitte Eingaben prüfen.',
	'Could not update project status.' => 'Projektstatus konnte nicht aktualisiert werden.',
	'Could not update project. Please check your input.' => 'Projekt konnte nicht aktualisiert werden. Bitte Eingaben prüfen.',
	'Could not update team member.' => 'Teammitglied konnte nicht aktualisiert werden.',
	'Could not update time entry. Please check your input.' => 'Zeiteintrag konnte nicht aktualisiert werden. Bitte Eingaben prüfen.',
	'Default hourly rate for new projects in “One rate for the whole project” pricing mode. Employee-wide and per-person project rates use their own settings.' => 'Standard-Stundensatz für neue Projekte im Modus „Ein Satz für das ganze Projekt“. Mitarbeitenden- und personenbezogene Projektsätze haben eigene Einstellungen.',
	'Effective from' => 'Gültig ab',
	'Effective-from date cannot be in the future.' => 'Gültig-ab-Datum darf nicht in der Zukunft liegen.',
	'Effective-from date is required' => 'Gültig-ab-Datum ist erforderlich',
	'Everyone uses the project hourly rate you set below.' => 'Alle nutzen den unten gesetzten Projekt-Stundensatz.',
	'Export failed. Please try again.' => 'Export fehlgeschlagen. Bitte erneut versuchen.',
	'File not found or access denied.' => 'Datei nicht gefunden oder Zugriff verweigert.',
	'File upload failed. Please check your input and try again.' => 'Datei-Upload fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.',
	'Go to team' => 'Zum Team',
	'Hourly rate' => 'Stundensatz',
	'Hourly rate (%s)' => 'Stundensatz (%s)',
	'Hourly rate for this person' => 'Stundensatz für diese Person',
	'Hourly rate history' => 'Stundensatz-Verlauf',
	'Hourly rate must be a positive number' => 'Stundensatz muss eine positive Zahl sein',
	'How are hours priced?' => 'Wie werden Stunden bewertet?',
	'How hours are priced:' => 'Bewertung der Stunden:',
	'Invalid hourly rate' => 'Ungültiger Stundensatz',
	'Invalid hourly rate. Refresh the page and try again.' => 'Ungültiger Stundensatz. Seite aktualisieren und erneut versuchen.',
	'Maintain employee rates under' => 'Mitarbeitenden-Sätze pflegen unter',
	'New hourly rate' => 'Neuer Stundensatz',
	'New hourly rate (%s)' => 'Neuer Stundensatz (%s)',
	'New rate' => 'Neuer Satz',
	'No employee hourly rate is effective on this date. Add a rate under Employees with an effective-from date on or before the work date.' => 'Für dieses Datum gibt es keinen gültigen Mitarbeitenden-Satz. Unter Mitarbeitende einen Satz mit Gültig ab am oder vor dem Arbeitstag anlegen.',
	'No project rate is effective for this person on this date. Add a rate on the project team with an effective-from date on or before the work date.' => 'Für diese Person gibt es an diesem Datum keinen Projektsatz. Im Projektteam einen Satz mit Gültig ab am oder vor dem Arbeitstag anlegen.',
	'No rate yet' => 'Noch kein Satz',
	'No rates yet. Add the first rate with an effective-from date on or before the earliest work date you plan to log.' => 'Noch keine Sätze. Ersten Satz mit Gültig ab am oder vor dem frühesten geplanten Arbeitstag anlegen.',
	'One rate for the whole project' => 'Ein Satz für das ganze Projekt',
	'Over budget' => 'Budget überschritten',
	'Past time entries keep their previous rate.' => 'Frühere Zeiteinträge behalten ihren bisherigen Satz.',
	'Planning hourly rate (%s) — optional' => 'Planungs-Stundensatz (%s) — optional',
	'Planning rate is for capacity estimates only — billed cost uses the pricing method above.' => 'Planungssatz nur für Kapazitätsschätzung — abgerechnete Kosten folgen der gewählten Bewertungsmethode.',
	'Pricing' => 'Bewertung',
	'Project context' => 'Projektkontext',
	'Project created. Add your team next so people can log time.' => 'Projekt erstellt. Als Nächstes Team hinzufügen, damit Zeit erfasst werden kann.',
	'Project hourly rate (%s)' => 'Projekt-Stundensatz (%s)',
	'Rate is set by the server from the project and work date. It cannot be edited.' => 'Der Satz wird serverseitig aus Projekt und Arbeitstag ermittelt und kann nicht bearbeitet werden.',
	'Rate per employee (master data)' => 'Satz je Mitarbeitende/r (Stammdaten)',
	'Rate per person on this project' => 'Satz je Person in diesem Projekt',
	'Rate saved. Past time entries keep their previous rate.' => 'Satz gespeichert. Frühere Zeiteinträge behalten ihren bisherigen Satz.',
	'Rates come from each person’s history under Employees, based on the work date.' => 'Sätze kommen aus dem Verlauf unter Mitarbeitende, basierend auf dem Arbeitstag.',
	'Required when adding someone in per-person project rate mode.' => 'Pflichtfeld, wenn jede Person einen eigenen Projektsatz hat.',
	'Set rate' => 'Satz festlegen',
	'Save rate' => 'Satz speichern',
	'Set a project hourly rate on the project before logging time.' => 'Vor der Zeiterfassung einen Projekt-Stundensatz setzen.',
	'Set a rate when adding each team member; use effective dates when it changes.' => 'Beim Hinzufügen je Teammitglied einen Satz setzen; bei Änderung Gültig-ab-Datum nutzen.',
	'Team member not found' => 'Teammitglied nicht gefunden',
	'The hourly rate does not match the server. Refresh the page and try again.' => 'Stundensatz stimmt nicht mit dem Server überein. Seite aktualisieren und erneut versuchen.',
	'The pricing method is locked because time has already been logged on this project.' => 'Die Bewertungsmethode ist gesperrt, weil bereits Zeit auf diesem Projekt erfasst wurde.',
	'This project uses per-person rates. Open the project team page to add this person with their rate.' => 'Dieses Projekt nutzt personenbezogene Sätze. Projektteam-Seite öffnen und Person mit Satz hinzufügen.',
	'This project uses a separate hourly rate for each team member. Search for a person, then enter their rate before adding them.' => 'In diesem Projekt hat jedes Teammitglied einen eigenen Stundensatz. Person suchen, Satz eintragen, dann hinzufügen.',
	'This rate applies to all time they log on this project from today. You can set a new rate with an effective date later.' => 'Dieser Satz gilt für alle Zeiten auf diesem Projekt ab heute. Später können Sie einen neuen Satz mit Gültig-ab-Datum setzen.',
	'Time cannot be logged on this project. Only Active and On Hold projects accept new entries.' => 'Auf diesem Projekt kann keine Zeit erfasst werden. Nur Aktiv und Pausiert erlauben neue Einträge.',
	'Unknown Customer' => 'Unbekannter Kunde',
	'Unknown Date' => 'Unbekanntes Datum',
	'User is required' => 'Benutzer ist erforderlich',
	'User not found' => 'Benutzer nicht gefunden',
	'You must be on the project team to log time.' => 'Sie müssen im Projektteam sein, um Zeit zu erfassen.',
	'You must be on the project team to log time. Ask a project manager to add you under Team on the project page.' => 'Sie müssen im Projektteam sein. Bitten Sie eine Projektleitung, Sie unter Team auf der Projektseite hinzuzufügen.',
	'incl. this entry' => 'inkl. dieser Eintrag',
];

$added = 0;
foreach ($keys as $key) {
	if ($key === '') {
		continue;
	}
	if (!isset($en['translations'][$key])) {
		$en['translations'][$key] = $key;
		$added++;
	}
	if (!isset($de['translations'][$key])) {
		$de['translations'][$key] = $deMap[$key] ?? $key;
	}
}

ksort($en['translations']);
ksort($de['translations']);

file_put_contents($enPath, json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
file_put_contents($dePath, json_encode($de, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo "Added $added keys to en.json; de.json updated.\n";
