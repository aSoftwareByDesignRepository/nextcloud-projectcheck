<?php
/**
 * Settings sub-page: App administrators.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */
$settingsSectionsToShow = ['admins'];
$settingsSectionScope = 'admins';
$saveButtonLabel = $l->t('Save administrators');
$hidePanelTitles = true;
$formId = $formId ?? 'projectcheck-org-form';
include __DIR__ . '/../org-settings-form.php';
