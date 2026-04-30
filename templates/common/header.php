<?php
/**
 * Top banner for the legacy `layout.php` path. All destinations use
 * IURLGenerator::linkToRoute (not legacy `link_to(…, '*.php')`).
 */

if (!\interface_exists(\OCP\IURLGenerator::class) && !\interface_exists('OCP\IURLGenerator', false)) {
	\die('Direct access not allowed');
}

$url = \OCP\Server::get(\OCP\IURLGenerator::class);
$groupManager = \OCP\Server::get(\OCP\IGroupManager::class);
$session = \OCP\Server::get(\OCP\IUserSession::class);
$user = $session->getUser();
$appName = 'projectcheck';
$userId = $user ? $user->getUID() : '';
$isAdmin = $userId !== '' && $groupManager->isInGroup($userId, 'admin');
$isLogged = $user !== null;
$rawPath = \urldecode((string) \parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH));
$defaultOrg = $url->linkToRoute('projectcheck.app_config.settingsIndex');
$canManageSettings = false;
$canManageOrganization = false;
$orgAppSettingsUrl = $defaultOrg;
if (isset($_) && \is_array($_)) {
	$canManageSettings = (bool) ($_['canManageSettings'] ?? $_['canManageOrg'] ?? false);
	$canManageOrganization = (bool) ($_['canManageOrganization'] ?? $_['canManageOrg'] ?? false);
	$orgAppSettingsUrl = (string) ($_['orgAppSettingsUrl'] ?? $defaultOrg);
}
$canAccessSettings = $canManageSettings || $canManageOrganization;
$personalInfoUrl = $url->linkToRoute('settings.PersonalSettings.index', [ 'section' => 'personal-info' ]);
$appSettingsUrl = $url->linkToRoute('projectcheck.app_config.settingsIndex');
$serverAdminUrl = $url->linkToRoute('settings.AdminSettings.index', [ 'section' => 'server' ]);
$helpUrl = $url->linkToDocs('user');
$appearanceUrl = $url->linkToRoute('settings.PersonalSettings.index', [ 'section' => 'theming' ]);
$logoSrc = '';
foreach (['logo.svg', 'app-dark.svg', 'app.svg'] as $iconFile) {
	try {
		$logoSrc = $url->imagePath($appName, $iconFile);
		break;
	} catch (\RuntimeException $e) {
		// Continue with fallback candidates.
	}
}
if ($logoSrc === '') {
	try {
		$logoSrc = $url->imagePath('core', 'actions/folder.svg');
	} catch (\RuntimeException $e) {
		$logoSrc = '';
	}
}
$avatarUrl = $isLogged
	? $url->linkToRoute('core.avatar.getAvatar', [ 'userId' => $userId, 'size' => 64 ])
	: '';
$routes = [
	'home' => $url->linkToRoute('projectcheck.page.index'),
	'dashboard' => $url->linkToRoute('projectcheck.page.index'),
	'projects' => $url->linkToRoute('projectcheck.project.index'),
	'newProject' => $url->linkToRoute('projectcheck.project.create'),
	'customers' => $url->linkToRoute('projectcheck.customer.index'),
	'newCustomer' => $url->linkToRoute('projectcheck.customer.create'),
	'timeEntries' => $url->linkToRoute('projectcheck.timeentry.index'),
	'newTime' => $url->linkToRoute('projectcheck.timeentry.create'),
	'employees' => $url->linkToRoute('projectcheck.employee.index'),
	'organization' => $orgAppSettingsUrl,
	'settings' => $appSettingsUrl,
];
if (!isset($currentPage) || $currentPage === '') {
	$currentPage = 'dashboard';
	if (strpos($rawPath, '/projectcheck/organization') !== false) {
		$currentPage = 'organization';
	} elseif ((strpos($rawPath, '/projectcheck/settings') !== false || strpos($rawPath, '/projectcheck/organization') !== false)
		&& strpos($rawPath, '/settings/user/') === false
		&& strpos($rawPath, '/settings/admin/') === false) {
		$currentPage = 'settings';
	} elseif (strpos($rawPath, '/projectcheck/employees') !== false) {
		$currentPage = 'employees';
	} elseif (strpos($rawPath, '/projectcheck/time-entries') !== false) {
		$currentPage = (strpos($rawPath, '/projectcheck/time-entries/create') !== false) ? 'time-entry-form' : 'time-entries';
	} elseif (strpos($rawPath, '/projectcheck/customers') !== false) {
		$currentPage = (strpos($rawPath, '/projectcheck/customers/create') !== false) ? 'customer-form' : 'customers';
	} elseif (strpos($rawPath, '/projectcheck/projects') !== false) {
		$currentPage = (preg_match('#/projectcheck/projects(?:/create)(?:$|[/?])#i', $rawPath) > 0) ? 'project-form' : 'projects';
	} elseif (strpos($rawPath, '/projectcheck/dashboard') !== false || preg_match('#/index\.php/\s*apps/\s*projectcheck/\s*?$#i', (string) $rawPath) || (preg_match('#/apps/\s*projectcheck/\s*?$#i', (string) $rawPath))) {
		$currentPage = 'dashboard';
	}
}
$mapPage = static function (string $p): string {
	$map = [
		'project-form' => 'projects',
		'customer-form' => 'customers',
		'time-entry-form' => 'time-entries',
	];
	return $map[$p] ?? $p;
};
$g = $mapPage((string) $currentPage);
$on = static function (string $section) use ($g): array {
	$is = $g === $section;
	return [
		'c' => $is ? ' pcl-header__link--active' : '',
		'cur' => $is,
	];
};
?>
<header class="header pcl-header" id="projectcheck-legacy-top" role="banner" aria-label="<?php p($l->t('ProjectCheck')); ?>">
	<div class="pcl-header__bar">
		<div class="pcl-header__logo">
			<a class="pcl-header__logo-link" href="<?php p($routes['home']); ?>">
				<img src="<?php p($logoSrc); ?>"
					class="pcl-header__logo-image" width="32" height="32" alt=""><?php /* one label: wordmark follows */ ?>
				<span class="pcl-header__logo-text"><?php p($l->t('ProjectCheck')); ?></span>
			</a>
		</div>
		<nav class="header__navigation pcl-header__nav pcl-header__nav--desktop" aria-label="<?php p($l->t('Main navigation')); ?>">
			<ul class="header__nav-list pcl-header__nav-list" role="list">
				<li>
					<?php $a = $on('dashboard'); ?>
					<a class="header__nav-link pcl-header__link<?php p($a['c']); ?>" href="<?php p($routes['dashboard']); ?>"
						<?php if ($a['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Dashboard')); ?></a>
				</li>
				<li>
					<?php $a = $on('projects'); ?>
					<a class="header__nav-link pcl-header__link<?php p($a['c']); ?>" href="<?php p($routes['projects']); ?>"
						<?php if ($a['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Projects')); ?></a>
				</li>
				<li>
					<?php $a = $on('customers'); ?>
					<a class="header__nav-link pcl-header__link<?php p($a['c']); ?>" href="<?php p($routes['customers']); ?>"
						<?php if ($a['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Customers')); ?></a>
				</li>
				<li>
					<?php $a = $on('time-entries'); ?>
					<a class="header__nav-link pcl-header__link<?php p($a['c']); ?>" href="<?php p($routes['timeEntries']); ?>"
						<?php if ($a['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Time Entries')); ?></a>
				</li>
				<li>
					<?php $a = $on('employees'); ?>
					<a class="header__nav-link pcl-header__link<?php p($a['c']); ?>" href="<?php p($routes['employees']); ?>"
						<?php if ($a['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Employees')); ?></a>
				</li>
				<?php if ($canAccessSettings) { ?>
				<?php $a = $on('settings'); ?>
				<li>
					<a class="header__nav-link pcl-header__link<?php p($a['c']); ?>" href="<?php p($appSettingsUrl); ?>"
						<?php if ($a['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Settings')); ?></a>
				</li>
				<?php } ?>
			</ul>
		</nav>
		<div class="header__actions pcl-header__actions">
			<?php
			if ($isLogged) {
				?>
			<div class="header__quick-actions pcl-header__quick pcl-header__quick--desktop" role="group" aria-label="<?php p($l->t('Quick actions')); ?>">
				<a class="header__action-btn header__action-btn--primary pcl-header__action pcl-header__action--primary" href="<?php p($routes['newTime']); ?>"><?php p($l->t('New time entry')); ?></a>
				<a class="header__action-btn pcl-header__action" href="<?php p($routes['newProject']); ?>"><?php p($l->t('New project')); ?></a>
				<a class="header__action-btn pcl-header__action" href="<?php p($routes['newCustomer']); ?>"><?php p($l->t('New customer')); ?></a>
			</div>
			<?php
			}
			?>
			<div class="header__user-menu pcl-header__user-wrap pcl-header__user--desktop">
			<?php
			if ($isLogged) {
				?>
				<button class="header__user-btn pcl-header__user-menu-btn" type="button" id="pcl-header-usr" aria-controls="pcl-header-userdropdown" aria-expanded="false" aria-label="<?php p($l->t('Account menu')); ?>">
					<span class="header__user-avatar pcl-header__user-avatar" aria-hidden="true">
						<img class="header__user-avatar-image" src="<?php p($avatarUrl); ?>"
							width="32" height="32" alt="" loading="lazy" decoding="async" />
					</span>
					<span class="header__user-name pcl-header__user-name"><?php p($user->getDisplayName()); ?></span>
					<span class="header__user-arrow pcl-header__caret" aria-hidden="true">▾</span>
				</button>
				<nav class="header__user-dropdown pcl-header__dropdown pcl-header__dropdown--user" id="pcl-header-userdropdown" hidden aria-label="<?php p($l->t('Account menu')); ?>" aria-hidden="true">
					<ul class="header__user-dropdown-list pcl-header__dropdown-list" role="list">
						<li class="header__user-dropdown-item">
							<a class="header__user-dropdown-link pcl-header__link" href="<?php p($personalInfoUrl); ?>"><?php p($l->t('Nextcloud account settings')); ?></a>
						</li>
						<li class="header__user-dropdown-item">
							<a class="header__user-dropdown-link pcl-header__link" href="<?php p($appearanceUrl); ?>"><?php p($l->t('Appearance and accessibility')); ?></a>
						</li>
						<?php if ($canAccessSettings) { ?>
						<li class="header__user-dropdown-item">
							<a class="header__user-dropdown-link pcl-header__link" href="<?php p($appSettingsUrl); ?>"><?php p($l->t('ProjectCheck app settings')); ?></a>
						</li>
						<?php } ?>
						<?php
						if ($isAdmin) {
							?>
						<li class="header__user-dropdown-item">
							<a class="header__user-dropdown-link pcl-header__link" id="pc-header-legacy-srv" href="<?php p($serverAdminUrl); ?>"><?php p($l->t('Server administration')); ?></a>
						</li>
							<?php
						}
						?>
						<li class="header__user-dropdown-item">
							<a class="header__user-dropdown-link pcl-header__link" href="<?php p($helpUrl); ?>" rel="noreferrer noopener" target="_blank"><?php p($l->t('Help (opens in a new window)')); ?></a>
						</li>
						<li class="header__user-dropdown-item pcl-header__dropdown-logoutli">
							<a class="header__user-dropdown-link pcl-header__link" href="<?php p($url->linkToRoute('core.login.logout')); ?>"><?php p($l->t('Log out')); ?></a>
						</li>
					</ul>
				</nav>
			<?php
			} else {
			?>
				<a class="pcl-header__action pcl-header__action--primary" href="<?php p($url->linkToRoute('core.login.showLoginForm')); ?>"><?php p($l->t('Login')); ?></a>
			<?php
			}
			?>
			</div>
			<button class="header__mobile-toggle pcl-header__hamburger" type="button" id="pcl-header-burger" aria-label="<?php p($l->t('Menu')); ?>" aria-controls="pcl-header-mnav" aria-expanded="false">
				<span class="header__mobile-toggle-line pcl-header__hamburger-line" aria-hidden="true"></span>
				<span class="header__mobile-toggle-line pcl-header__hamburger-line" aria-hidden="true"></span>
				<span class="header__mobile-toggle-line pcl-header__hamburger-line" aria-hidden="true"></span>
			</button>
		</div>
	</div>
	<nav class="header__mobile-nav pcl-header__mobile pcl-header__mobile-panel" id="pcl-header-mnav" role="region" hidden aria-label="<?php p($l->t('Mobile menu')); ?>">
		<?php
		$a0 = $on('dashboard');
		$a1 = $on('projects');
		$a2 = $on('customers');
		$a3 = $on('time-entries');
		$a4 = $on('employees');
		$a5 = $on('organization');
		$a6 = $on('settings');
		?>
		<p class="pcl-header__section-label" id="pch-mn1"><?php p($l->t('Main navigation')); ?></p>
		<ul class="header__mobile-nav-list pcl-header__nav-list pcl-header__nav-list--stack" aria-labelledby="pch-mn1" role="list">
			<li><a class="header__mobile-nav-link pcl-header__link<?php p($a0['c']); ?>" href="<?php p($routes['dashboard']); ?>" <?php if ($a0['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Dashboard')); ?></a></li>
			<li><a class="header__mobile-nav-link pcl-header__link<?php p($a1['c']); ?>" href="<?php p($routes['projects']); ?>" <?php if ($a1['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Projects')); ?></a></li>
			<li><a class="header__mobile-nav-link pcl-header__link<?php p($a2['c']); ?>" href="<?php p($routes['customers']); ?>" <?php if ($a2['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Customers')); ?></a></li>
			<li><a class="header__mobile-nav-link pcl-header__link<?php p($a3['c']); ?>" href="<?php p($routes['timeEntries']); ?>" <?php if ($a3['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Time Entries')); ?></a></li>
			<li><a class="header__mobile-nav-link pcl-header__link<?php p($a4['c']); ?>" href="<?php p($routes['employees']); ?>" <?php if ($a4['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Employees')); ?></a></li>
			<?php
			if ($canAccessSettings) {
			?>
			<li><a class="header__mobile-nav-link pcl-header__link<?php p($a6['c']); ?>" href="<?php p($appSettingsUrl); ?>" <?php if ($a6['cur']) { ?>aria-current="page"<?php } ?>><?php p($l->t('Settings')); ?></a></li>
			<?php } ?>
		</ul>
		<?php
		if ($isLogged) {
		?>
		<div class="header__mobile-actions pcl-header__actions pcl-header__actions--stack" role="group" aria-labelledby="pch-qa">
			<p class="pcl-header__section-label" id="pch-qa"><?php p($l->t('Quick actions')); ?></p>
			<a class="header__mobile-action-btn header__mobile-action-btn--primary pcl-header__action pcl-header__action--primary" href="<?php p($routes['newTime']); ?>"><?php p($l->t('New time entry')); ?></a>
			<a class="header__mobile-action-btn pcl-header__action" href="<?php p($routes['newProject']); ?>"><?php p($l->t('New project')); ?></a>
			<a class="header__mobile-action-btn pcl-header__action" href="<?php p($routes['newCustomer']); ?>"><?php p($l->t('New customer')); ?></a>
		</div>
		<?php
		}
		?>
		<div class="pcl-header__user--mobile">
			<p class="pcl-header__section-label" id="pch-a"><?php p($l->t('Account menu')); ?></p>
			<ul class="pcl-header__nav-list pcl-header__nav-list--stack" aria-labelledby="pch-a" role="list">
		<?php
		if ($isLogged) {
		?>
				<li><a class="pcl-header__link" href="<?php p($personalInfoUrl); ?>"><?php p($l->t('Nextcloud account settings')); ?></a></li>
				<li><a class="pcl-header__link" href="<?php p($appearanceUrl); ?>"><?php p($l->t('Appearance and accessibility')); ?></a></li>
				<?php if ($canAccessSettings) { ?>
				<li><a class="pcl-header__link" href="<?php p($appSettingsUrl); ?>"><?php p($l->t('ProjectCheck app settings')); ?></a></li>
				<?php } ?>
				<?php
				if ($isAdmin) {
			?><li><a class="pcl-header__link" href="<?php p($serverAdminUrl); ?>"><?php p($l->t('Server administration')); ?></a></li><?php
			} ?>
			<li class="pcl-header__dropdown-logoutli"><a class="pcl-header__link" href="<?php p($url->linkToRoute('core.login.logout')); ?>"><?php p($l->t('Log out')); ?></a></li>
		<?php
		} else { ?>
		<li><a class="pcl-header__link pcl-header__action--primary" href="<?php p($url->linkToRoute('core.login.showLoginForm')); ?>"><?php p($l->t('Login')); ?></a></li>
		<?php } ?>
			<li><a class="pcl-header__link" href="<?php p($helpUrl); ?>" rel="noreferrer noopener" target="_blank"><?php p($l->t('Help (opens in a new window)')); ?></a></li>
			</ul>
		</div>
	</nav>
</header>