<?php
/**
 * Settings sub-page: App defaults.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */
$settingsSectionsToShow = ['defaults'];
$settingsSectionScope = 'defaults';
$saveButtonLabel = $l->t('Save defaults');
$hidePanelTitles = true;
$formId = $formId ?? 'projectcheck-org-form';
include __DIR__ . '/../org-settings-form.php';
