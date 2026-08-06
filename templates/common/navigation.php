<?php

/**
 * Common navigation template for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCA\ProjectCheck\Service\IconCatalog;

\OCP\Util::addStyle('projectcheck', 'common/feedback-system');
// Centralized locale-aware formatting (number/currency/date). Must load before any
// page module that calls window.ProjectCheckFormat (audit ref. AUDIT-FINDINGS B10/H28).
// Registered first so the script bucket exists; pc-l10n is then prepended so it still loads first.
\OCP\Util::addScript('projectcheck', 'common/format');
\OCP\Util::addScript('projectcheck', 'common/escape');
\OCP\Util::addScript('projectcheck', 'common/dom-ui');
// Patch window.t before other app scripts; keep first in the projectcheck bundle (prepend list).
\OCP\Util::addScript('projectcheck', 'pc-l10n', 'core', true);
// Shared modal accessibility helper (focus trap, Escape, backdrop, restore focus).
// Must load before messaging/components so every openModal call gets the trap.
\OCP\Util::addScript('projectcheck', 'common/modal-a11y');
// Soft keyboard / visualViewport: keep focused notes & inputs above the IME on phones.
\OCP\Util::addScript('projectcheck', 'common/keep-focused-visible');
// Mobile drawer: in-page Menu button (replaces core #app-navigation-toggle).
\OCP\Util::addScript('projectcheck', 'pc-mobile-nav');
// Centralised icon catalog and hydration (audit ref. AUDIT-FINDINGS H22/icon-dedup).
// Replaces six duplicated inline svgIcons blocks across page templates.
\OCP\Util::addScript('projectcheck', 'common/icons');

// Get current page to highlight active navigation item
$currentPage = $_SERVER['REQUEST_URI'] ?? '';
$isProjects = strpos($currentPage, '/projects') !== false;
$isCustomers = strpos($currentPage, '/customers') !== false;
$isEmployees = strpos($currentPage, '/employees') !== false;
$isTimeEntries = strpos($currentPage, '/time-entries') !== false;
$isSettings = strpos($currentPage, '/settings') !== false;
$isOrganization = strpos($currentPage, '/organization') !== false;
// Injected by EnrichTemplateNavigationContext (BeforeTemplateRendered); safe fallbacks for edge cases.
$canManageSettings = $canManageSettings ?? ($_['canManageSettings'] ?? $_['canManageOrg'] ?? false);
$canManageOrganization = $canManageOrganization ?? ($_['canManageOrganization'] ?? $_['canManageOrg'] ?? false);
$canAccessSettings = $canManageSettings || $canManageOrganization;
$orgAppSettingsUrl = $orgAppSettingsUrl ?? ($_['orgAppSettingsUrl'] ?? '/index.php/apps/projectcheck/organization');
$settingsSection = (string)($settingsSection ?? $_['settingsSection'] ?? '');
$settingsSectionLabels = (array)($settingsSectionLabels ?? $_['settingsSectionLabels'] ?? []);
$settingsSectionUrls = (array)(($_['urls']['settingsSections'] ?? []) ?: ($_['settingsSections'] ?? []));
$isOnSettings = $isSettings || $isOrganization || $settingsSection !== '';
// Dashboard is active if URL contains /dashboard OR if it's the base app URL without any specific section
$isDashboard = strpos($currentPage, '/dashboard') !== false ||
	(!$isProjects && !$isCustomers && !$isEmployees && !$isTimeEntries && !$isSettings && !$isOrganization &&
		strpos($currentPage, '/apps/projectcheck') !== false);

$navPageId = 'dashboard';
if ($isTimeEntries) {
	$navPageId = 'time-entries';
} elseif ($isProjects) {
	$navPageId = 'projects';
} elseif ($isCustomers) {
	$navPageId = 'customers';
} elseif ($isEmployees) {
	$navPageId = 'employees';
} elseif ($isOnSettings) {
	$navPageId = 'settings';
} elseif ($isDashboard) {
	$navPageId = 'dashboard';
}

$roleLabel = (string)($_['roleLabel'] ?? '');
if ($roleLabel === '') {
	$roleLabel = $canAccessSettings ? $l->t('Administrator') : $l->t('Member');
}

$settingsChildren = [];
if ($canAccessSettings && $isOnSettings && $settingsSectionLabels !== []) {
	foreach ($settingsSectionLabels as $sectionId => $sectionLabel) {
		$childHref = (string)($settingsSectionUrls[$sectionId] ?? '');
		if ($childHref === '' || $childHref === '#') {
			continue;
		}
		$settingsChildren[] = [
			'id' => (string)$sectionId,
			'label' => (string)$sectionLabel,
			'url' => $childHref,
			'active' => $settingsSection === (string)$sectionId,
		];
	}
}

$groups = [
	[
		'title' => $l->t('Overview'),
		'items' => [
			[
				'id' => 'dashboard',
				'label' => $l->t('Dashboard'),
				'hint' => $l->t('Current status and priorities'),
				'icon' => 'layout-grid',
				'url' => $_['dashboardUrl'] ?? '/index.php/apps/projectcheck/dashboard',
			],
			[
				'id' => 'time-entries',
				'label' => $l->t('Time Entries'),
				'hint' => $l->t('Log and review tracked time'),
				'icon' => 'clock',
				'url' => $_['timeEntriesUrl'] ?? '/index.php/apps/projectcheck/time-entries',
			],
		],
	],
	[
		'title' => $l->t('Management'),
		'items' => [
			[
				'id' => 'projects',
				'label' => $l->t('Projects'),
				'hint' => $l->t('Create and manage projects'),
				'icon' => 'folder',
				'url' => $_['projectsUrl'] ?? '/index.php/apps/projectcheck/projects',
			],
			[
				'id' => 'customers',
				'label' => $l->t('Customers'),
				'hint' => $l->t('Clients and organisations'),
				'icon' => 'users',
				'url' => $_['customersUrl'] ?? '/index.php/apps/projectcheck/customers',
			],
			[
				'id' => 'employees',
				'label' => $l->t('Employees'),
				'hint' => $l->t('Team members and rates'),
				'icon' => 'user-check',
				'url' => $_['employeesUrl'] ?? '/index.php/apps/projectcheck/employees',
			],
		],
	],
];

if ($canAccessSettings) {
	$groups[] = [
		'title' => $l->t('Governance'),
		'items' => [
			[
				'id' => 'settings',
				'label' => $l->t('Settings'),
				'hint' => $l->t('Access, policy and defaults'),
				'icon' => 'settings',
				'url' => $_['settingsUrl'] ?? $orgAppSettingsUrl,
				'children' => $settingsChildren,
			],
		],
	];
}

include __DIR__ . '/pc-l10n-bootstrap.php';
?>

<div id="app-navigation" class="pc-app-nav pc-nav" role="navigation" aria-label="<?php p($l->t('ProjectCheck navigation')); ?>">
	<div class="pc-nav__brand">
		<span class="pc-nav__brand-icon" aria-hidden="true">
			<?php print_unescaped(IconCatalog::render('folder', 'pc-icon--lg')); ?>
		</span>
		<div>
			<h2 class="pc-nav__title"><?php p($l->t('ProjectCheck')); ?></h2>
			<p class="pc-nav__subtitle"><?php p($l->t('Manage your projects')); ?></p>
			<span class="pc-badge pc-badge--info"><?php p($roleLabel); ?></span>
		</div>
	</div>
	<?php foreach ($groups as $group): ?>
		<div class="pc-nav__group">
			<p class="pc-nav__group-title"><?php p((string)$group['title']); ?></p>
			<ul class="pc-nav__list">
				<?php foreach ((array)$group['items'] as $item):
					$children = (array)($item['children'] ?? []);
					$active = $navPageId === $item['id'];
					$parentAriaCurrent = $active && $children === [];
					?>
					<li class="pc-nav__item<?php p($active ? ' is-active active' : ''); ?>">
						<a class="pc-nav__link" href="<?php p((string)$item['url']); ?>"
							<?php if ($parentAriaCurrent): ?>aria-current="page"<?php endif; ?>>
							<span class="pc-nav__icon" aria-hidden="true">
								<?php print_unescaped(IconCatalog::render((string)$item['icon'])); ?>
							</span>
							<span class="pc-nav__label">
								<span class="pc-nav__name"><?php p((string)$item['label']); ?></span>
								<span class="pc-nav__hint"><?php p((string)$item['hint']); ?></span>
							</span>
						</a>
						<?php if ($children !== []): ?>
							<ul class="pc-nav__sublist projectcheck-nav__sublist">
								<?php foreach ($children as $child):
									$childActive = !empty($child['active']);
									?>
									<li class="pc-nav__subitem projectcheck-nav__subitem<?php p($childActive ? ' is-active active' : ''); ?>">
										<a class="pc-nav__sublink projectcheck-nav__sublink" href="<?php p((string)$child['url']); ?>"
											<?php if ($childActive): ?>aria-current="page"<?php endif; ?>>
											<?php p((string)$child['label']); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</div>
