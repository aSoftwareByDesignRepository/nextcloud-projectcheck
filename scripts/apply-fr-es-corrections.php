<?php

declare(strict_types=1);

/**
 * Apply production-quality corrections to fr.json and es.json after machine translation.
 */

$base = dirname(__DIR__);

/** @var array<string, string> */
$frCorrections = [
	'%d items have been processed successfully.' => '%d éléments ont été traités avec succès.',
	'%d items processed successfully, %d failed.' => '%d éléments traités avec succès, %d en échec.',
	'Actions' => 'Actions',
	'Acknowledge' => 'Accuser réception',
	'Acknowledged' => 'Accusé de réception',
	'Budget Critical' => 'Budget critique',
	'Confirm delete of %s' => 'Confirmer la suppression de %s',
	'Request failed.' => 'Échec de la requête.',
	'Submitting...' => 'Envoi en cours…',
	'You’re offline' => 'Vous êtes hors ligne',
	'Description' => 'Description',
	'Information' => 'Informations',
	'Menu' => 'Menu',
	'Notifications' => 'Notifications',
	'Page' => 'Page',
	'Date' => 'Date',
	'Budget' => 'Budget',
	'Budget:' => 'Budget :',
	'Cascade' => 'Cascade',
	'Classification' => 'Classification',
	'DATE' => 'DATE',
	'DESCRIPTION' => 'DESCRIPTION',
];

/** @var array<string, string> */
$esCorrections = [
	'%d items have been processed successfully.' => '%d elementos se han procesado correctamente.',
	'%d items processed successfully, %d failed.' => '%d elementos procesados correctamente, %d con error.',
	'Actions' => 'Acciones',
	'Acknowledge' => 'Confirmar',
	'Acknowledged' => 'Confirmado',
	'Submitting...' => 'Enviando…',
	'You’re offline' => 'Estás sin conexión',
	'Error' => 'Error',
	'Error: %s' => 'Error: %s',
	'Legal' => 'Legal',
	'Total' => 'Total',
	'total' => 'total',
	'Project Control' => 'Control de proyectos',
	'_Uploaded %n file._::_Uploaded %n files._' => null, // handled below
];

// Plural array corrections (msgid => [singular, plural])
/** @var array<string, array{0: string, 1: string}> */
$frPluralCorrections = [
	'_Uploaded %n file._::_Uploaded %n files._' => ['%n fichier téléversé.', '%n fichiers téléversés.'],
];
/** @var array<string, array{0: string, 1: string}> */
$esPluralCorrections = [
	'_Uploaded %n file._::_Uploaded %n files._' => ['%n archivo subido.', '%n archivos subidos.'],
];

foreach ([
	'fr' => ['map' => $frCorrections, 'plural' => $frPluralCorrections],
	'es' => ['map' => $esCorrections, 'plural' => $esPluralCorrections],
] as $lang => $cfg) {
	$path = $base . '/l10n/' . $lang . '.json';
	$data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	$fixed = 0;
	foreach ($cfg['map'] as $key => $value) {
		if ($value === null) {
			continue;
		}
		if (isset($data['translations'][$key]) && $data['translations'][$key] !== $value) {
			$data['translations'][$key] = $value;
			$fixed++;
		}
	}
	foreach ($cfg['plural'] as $key => $forms) {
		if (!isset($data['translations'][$key]) || !\is_array($data['translations'][$key])) {
			continue;
		}
		if ($data['translations'][$key] !== $forms) {
			$data['translations'][$key] = $forms;
			$fixed++;
		}
	}
	ksort($data['translations']);
	file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
	echo "{$lang}.json: applied {$fixed} corrections, total " . count($data['translations']) . " keys\n";
}
