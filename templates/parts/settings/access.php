<?php
/**
 * Settings sub-page: Access and visibility.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */
$settingsSectionsToShow = ['access'];
$settingsSectionScope = 'access';
$saveButtonLabel = $l->t('Save access settings');
$hidePanelTitles = true;
$formId = $formId ?? 'projectcheck-org-form';
include __DIR__ . '/../org-settings-form.php';
