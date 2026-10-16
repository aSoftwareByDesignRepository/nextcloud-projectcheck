<?php
/**
 * Settings sub-page: Support & us.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 * @var \OCP\IURLGenerator|null $urlGenerator
 */
$supportUsLanguageCode = method_exists($l, 'getLanguageCode') ? (string)$l->getLanguageCode() : 'en';
$supportUsCssPrefix = 'projectcheck';
$supportUsBtnPrimaryClass = 'button primary';
$supportUsBtnSecondaryClass = 'button';
$supportUsLicenseUrl = $_['supportUsLicenseUrl']
	?? ($urlGenerator ?? \OCP\Server::get(\OCP\IURLGenerator::class))->linkToRoute('projectcheck.app_config.settingsSection', ['section' => 'license']) . '#projectcheck-license';
$supportUsLinks = new \OCA\ProjectCheck\Support\SupportUsLinks('ProjectCheck', true, $supportUsLicenseUrl);
include __DIR__ . '/../support-us-section.php';
