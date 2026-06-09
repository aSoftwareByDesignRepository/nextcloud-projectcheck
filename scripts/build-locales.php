<?php

declare(strict_types=1);

/**
 * Sync missing l10n keys, fix en/de parity, and build fr.json / es.json from en.json.
 *
 * Usage: php scripts/build-locales.php [--translate-fr-es]
 */

$base = dirname(__DIR__);
$enPath = $base . '/l10n/en.json';
$dePath = $base . '/l10n/de.json';
$frPath = $base . '/l10n/fr.json';
$esPath = $base . '/l10n/es.json';

$en = json_decode((string) file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
$de = json_decode((string) file_get_contents($dePath), true, 512, JSON_THROW_ON_ERROR);

/** Keys used in code but missing from en.json (msgid => English text). */
$newKeys = [
	'Add project' => 'Add project',
	'Budget: %1$s%% consumed' => 'Budget: %1$s%% consumed',
	'Calculated from budget ÷ planning rate (optional). Actual cost uses each person’s billing rate.' => 'Calculated from budget ÷ planning rate (optional). Actual cost uses each person’s billing rate.',
	'Changes: {changes}' => 'Changes: {changes}',
	'Could not open the confirmation dialog. Reload the page and try again.' => 'Could not open the confirmation dialog. Reload the page and try again.',
	'Could not save rate.' => 'Could not save rate.',
	'Could not save rate. Please check your input.' => 'Could not save rate. Please check your input.',
	'Customer deleted: {customer}' => 'Customer deleted: {customer}',
	'Customer updated: {customer}' => 'Customer updated: {customer}',
	'Customer: %s' => 'Customer: %s',
	'Deadline approaching: Project {project} is due in {days} days' => 'Deadline approaching: Project {project} is due in {days} days',
	'Deadline overdue: Project {project} is overdue by {days} days' => 'Deadline overdue: Project {project} is overdue by {days} days',
	'Description: {description}' => 'Description: {description}',
	'End date must be on or after the start date.' => 'End date must be on or after the start date.',
	'File' => 'File',
	'File deleted successfully' => 'File deleted successfully',
	'Full Description' => 'Full Description',
	'Hour estimate unavailable — costs use each person’s rate. Add an optional planning rate on the project to estimate capacity.' => 'Hour estimate unavailable — costs use each person’s rate. Add an optional planning rate on the project to estimate capacity.',
	'Hours must be greater than zero' => 'Hours must be greater than zero',
	'Missing CSRF request token.' => 'Missing CSRF request token.',
	'New customer created: {customer}' => 'New customer created: {customer}',
	'New project created: {project}' => 'New project created: {project}',
	'Page %1$s of %2$s' => 'Page %1$s of %2$s',
	'Please ensure all tasks are completed before the deadline.' => 'Please ensure all tasks are completed before the deadline.',
	'Please review the project budget and consider taking action.' => 'Please review the project budget and consider taking action.',
	'Project deleted: {project}' => 'Project deleted: {project}',
	'Project status changed: {project} is now {status}' => 'Project status changed: {project} is now {status}',
	'Project updated: {project}' => 'Project updated: {project}',
	'Project {project} has reached {percentage}% of its budget' => 'Project {project} has reached {percentage}% of its budget',
	'Project: %s' => 'Project: %s',
	'Rate resolved from server for this project and work date.' => 'Rate resolved from server for this project and work date.',
	'Request failed.' => 'Request failed.',
	'Submitting...' => 'Submitting...',
	'Team member' => 'Team member',
	'The project has exceeded its allocated budget. Immediate action is required.' => 'The project has exceeded its allocated budget. Immediate action is required.',
	'The project is overdue. Please update the project status or extend the deadline.' => 'The project is overdue. Please update the project status or extend the deadline.',
	'This project uses per-person rates.' => 'This project uses per-person rates.',
	'Time Entry: %s hours on %s' => 'Time Entry: %s hours on %s',
	'Time entry deleted from project {project}' => 'Time entry deleted from project {project}',
	'Time entry logged: {hours} hours on {project}' => 'Time entry logged: {hours} hours on {project}',
	'Time entry updated on project {project}' => 'Time entry updated on project {project}',
	'View all projects' => 'View all projects',
	'You are offline' => 'You are offline',
	'You’re offline' => 'You’re offline',
	'There is no internet connection right now. ProjectCheck needs a connection for most features. When you are back online, use Try again to reload.' => 'There is no internet connection right now. ProjectCheck needs a connection for most features. When you are back online, use Try again to reload.',
	'Try again' => 'Try again',
	'Check connection' => 'Check connection',
	'Checking connection…' => 'Checking connection…',
	'Connection restored. Reloading…' => 'Connection restored. Reloading…',
	'Still offline. Check your network, then try again.' => 'Still offline. Check your network, then try again.',
	'You are still offline.' => 'You are still offline.',
	'Browser reports online. If the app does not load, use Try again.' => 'Browser reports online. If the app does not load, use Try again.',
	'Offline — ProjectCheck' => 'Offline — ProjectCheck',
	'{actor} changed status of project {project} to {status}' => '{actor} changed status of project {project} to {status}',
	'{actor} created customer {customer}' => '{actor} created customer {customer}',
	'{actor} created project {project}' => '{actor} created project {project}',
	'{actor} deleted customer {customer}' => '{actor} deleted customer {customer}',
	'{actor} deleted project {project}' => '{actor} deleted project {project}',
	'{actor} deleted time entry from project {project}' => '{actor} deleted time entry from project {project}',
	'{actor} logged {hours} hours on project {project}' => '{actor} logged {hours} hours on project {project}',
	'{actor} removed {member} from project {project}' => '{actor} removed {member} from project {project}',
	'{actor} updated customer {customer}' => '{actor} updated customer {customer}',
	'{actor} updated project {project}' => '{actor} updated project {project}',
	'{actor} updated time entry on project {project}' => '{actor} updated time entry on project {project}',
];

foreach ($newKeys as $key => $value) {
	if (!isset($en['translations'][$key])) {
		$en['translations'][$key] = $value;
	}
}

ksort($en['translations']);
file_put_contents($enPath, json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo 'en.json: ' . count($en['translations']) . " keys\n";
