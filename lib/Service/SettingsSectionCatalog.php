<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCP\IL10N;

/**
 * Single source of truth for the split settings sub-pages.
 *
 * Every artifact that knows about settings sections derives from this class:
 *  - appinfo/routes.php pins its `{section}` requirement to {@see routeRequirement()},
 *  - AppConfigController validates and titles pages through it,
 *  - templates/org-app-settings.php dispatches to templates/parts/settings/<section>.php,
 *  - js/settings-legacy-redirect.js mirrors {@see LEGACY_ANCHORS} for old `/settings#anchor` links.
 *
 * Contract tests in tests/Unit assert all four artifacts stay in sync, so a
 * drifting copy fails CI instead of shipping a dead link.
 */
final class SettingsSectionCatalog
{
	public const DEFAULT_SECTION = 'access';

	/**
	 * Ordered section slugs — order drives the sidebar sub-navigation.
	 *
	 * @var list<string>
	 */
	public const SECTIONS = [
		'access',
		'admins',
		'defaults',
		'license',
		'support',
	];

	/**
	 * Legacy single-page anchors → owning section slug.
	 *
	 * The old settings page was one long document with jump anchors. URL
	 * fragments never reach the server, so js/settings-legacy-redirect.js uses
	 * this map to forward stale bookmarks client-side.
	 *
	 * @var array<string, string>
	 */
	public const LEGACY_ANCHORS = [
		'pc-access-heading' => 'access',
		'pc_restriction_active_notice' => 'access',
		'pc_access_restriction' => 'access',
		'pc_allowed_users_legend' => 'access',
		'pc_allowed_groups_legend' => 'access',
		'pc-admins-heading' => 'admins',
		'pc_app_admins_legend' => 'admins',
		'pc-defaults-heading' => 'defaults',
		'pc_currency' => 'defaults',
		'pc_def_rate' => 'defaults',
		'projectcheck-license' => 'license',
		'pc-license-panel' => 'license',
		'pc-license-heading' => 'license',
		'pc-license-seats-heading' => 'license',
		'projectcheck-support-us' => 'support',
		'projectcheck-support-us-title' => 'support',
		'projectcheck-org-trust-h' => 'access',
	];

	public function isSection(string $section): bool
	{
		return in_array($section, self::SECTIONS, true);
	}

	/**
	 * Value for the `{section}` route placeholder requirement.
	 */
	public static function routeRequirement(): string
	{
		return implode('|', self::SECTIONS);
	}

	/**
	 * Human page title (H1 / breadcrumb current). Longer, descriptive copy.
	 */
	public function label(IL10N $l, string $section): string
	{
		return match ($section) {
			'access' => $l->t('Access and visibility'),
			'admins' => $l->t('App administrators'),
			'defaults' => $l->t('App defaults'),
			'license' => $l->t('Mobile license'),
			'support' => $l->t('Support & us'),
			default => $l->t('Settings'),
		};
	}

	/**
	 * Short sidebar / in-page chip label (DeskCheck parity).
	 */
	public function navLabel(IL10N $l, string $section): string
	{
		return match ($section) {
			'access' => $l->t('Access'),
			'admins' => $l->t('App admins'),
			'defaults' => $l->t('Defaults'),
			'license' => $l->t('License'),
			'support' => $l->t('Support us'),
			default => $l->t('Settings'),
		};
	}

	/**
	 * One-line page lead under the H1. Reuses former in-card / hint copy.
	 *
	 * License and support intentionally return '' — their panels ship a
	 * self-contained intro and a page lead would duplicate that copy.
	 */
	public function help(IL10N $l, string $section): string
	{
		return match ($section) {
			'access' => $l->t('Decide who may open ProjectCheck. Wrong allowlists can lock people out or grant access too widely.'),
			'admins' => $l->t('These people can open ProjectCheck settings. System administrators always keep access. Search and pick — never type a raw user id.'),
			'defaults' => $l->t('Default values for projects and budgets. Changes apply to all users in this Nextcloud.'),
			default => '',
		};
	}
}
