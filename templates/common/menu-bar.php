<?php
declare(strict_types=1);
if (!defined('OC_APP')) {
	die('This file can only be accessed within a Nextcloud app');
}

$gen = \OCP\Server::get(\OCP\IURLGenerator::class);
$session = \OCP\Server::get(\OCP\IUserSession::class);
$groupManager = \OCP\Server::get(\OCP\IGroupManager::class);
$appManager = \OCP\Server::get(\OCP\IAppManager::class);

$user = $session->getUser();
$userDisplayName = $user ? $user->getDisplayName() : '';
$userUID = $user ? $user->getUID() : '';

$currentPage = $_GET['page'] ?? 'dashboard';
$isAdmin = $userUID !== '' && $groupManager->isInGroup($userUID, 'admin');
$isLoggedIn = $user !== null;
$canManageSettings = (bool)($_['canManageSettings'] ?? $_['canManageOrg'] ?? false);
$canManageOrganization = (bool)($_['canManageOrganization'] ?? $_['canManageOrg'] ?? false);
$canManageProjects = (bool)($_['canCreateProject'] ?? $canManageOrganization);
$canManageCustomers = (bool)($_['canCreateCustomer'] ?? $canManageOrganization);
$canViewTime = $isLoggedIn;
$canAccessSettings = $canManageSettings;
$logoFile = $appManager->getAppPath('projectcheck') . '/img/logo.svg';
?>
<button class="menu-bar-toggle" id="menu-bar-toggle" type="button" aria-label="<?php p($l->t('Toggle menu')); ?>"
	aria-controls="menu-bar" aria-expanded="false">
	<span class="hamburger-line"></span>
	<span class="hamburger-line"></span>
	<span class="hamburger-line"></span>
</button>

<div class="menu-bar-overlay" id="menu-bar-overlay" aria-hidden="true"></div>

<nav class="menu-bar" id="menu-bar" role="navigation" aria-label="<?php p($l->t('Main navigation')); ?>">

	<div class="menu-bar-top">
		<div class="logo">
			<?php if (is_file($logoFile)) { ?>
				<img src="<?php p($gen->linkTo('projectcheck', 'img/logo.svg')); ?>"
					alt="<?php p($l->t('ProjectCheck')); ?>" width="32" height="32" />
			<?php } else { ?>
				<div class="logo-placeholder" aria-hidden="true">
					<span class="icon icon-folder"></span>
				</div>
			<?php } ?>
		</div>
		<h1 class="brand-name"><?php p($l->t('ProjectCheck')); ?></h1>
	</div>

	<div class="menu-bar-middle">

		<div class="menu-group">
			<div class="menu-section-header" role="presentation"><?php p($l->t('Overview')); ?></div>
			<a href="<?php p($gen->linkToRoute('projectcheck.page.index')); ?>"
				class="menu-item <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'dashboard' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Dashboard')); ?></span>
			</a>
		</div>

		<div class="menu-group">
			<div class="menu-section-header" role="presentation"><?php p($l->t('Projects')); ?></div>
			<a href="<?php p($gen->linkToRoute('projectcheck.project.index')); ?>"
				class="menu-item <?php echo ($currentPage === 'projects') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'projects' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('All projects')); ?></span>
			</a>
			<?php if ($canManageProjects) { ?>
			<a href="<?php p($gen->linkToRoute('projectcheck.project.create')); ?>"
				class="menu-item <?php echo ($currentPage === 'project-form') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'project-form' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('New project')); ?></span>
			</a>
			<?php } ?>
		</div>

		<div class="menu-group">
			<div class="menu-section-header" role="presentation"><?php p($l->t('Customers')); ?></div>
			<a href="<?php p($gen->linkToRoute('projectcheck.customer.index')); ?>"
				class="menu-item <?php echo ($currentPage === 'customers') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'customers' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('All customers')); ?></span>
			</a>
			<?php if ($canManageCustomers) { ?>
			<a href="<?php p($gen->linkToRoute('projectcheck.customer.create')); ?>"
				class="menu-item <?php echo ($currentPage === 'customer-form') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'customer-form' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('New customer')); ?></span>
			</a>
			<?php } ?>
		</div>

		<div class="menu-group">
			<div class="menu-section-header" role="presentation"><?php p($l->t('Time tracking')); ?></div>
			<a href="<?php p($gen->linkToRoute('projectcheck.timeentry.index')); ?>"
				class="menu-item <?php echo ($currentPage === 'time-entries') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'time-entries' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Time entries')); ?></span>
			</a>
			<?php if ($canViewTime) { ?>
			<a href="<?php p($gen->linkToRoute('projectcheck.timeentry.create')); ?>"
				class="menu-item <?php echo ($currentPage === 'time-entry-form') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'time-entry-form' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('New time entry')); ?></span>
			</a>
			<?php } ?>
		</div>

		<div class="menu-group">
			<div class="menu-section-header" role="presentation"><?php p($l->t('People')); ?></div>
			<a href="<?php p($gen->linkToRoute('projectcheck.employee.index')); ?>"
				class="menu-item <?php echo ($currentPage === 'employees') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'employees' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Employees')); ?></span>
			</a>
		</div>
	</div>

	<div class="menu-bar-bottom">
		<div class="user-info" role="group" aria-label="<?php p($l->t('Signed in as')); ?>">
			<div class="user-avatar" aria-hidden="true">
				<?php echo $user ? strtoupper(substr($userDisplayName, 0, 1)) : '?'; ?>
			</div>
			<div class="user-details">
				<div class="user-name"><?php p($userDisplayName ?: '—'); ?></div>
				<div class="user-role"><?php $isAdmin ? p($l->t('Administrator')) : p($l->t('User')); ?></div>
			</div>
		</div>

		<?php if ($canAccessSettings) { ?>
		<div class="menu-group">
			<a href="<?php p($gen->linkToRoute('projectcheck.settings.index')); ?>"
				class="menu-item <?php echo ($currentPage === 'settings') ? 'active' : ''; ?>"
				<?php echo $currentPage === 'settings' ? 'aria-current="page"' : ''; ?>>
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Settings')); ?></span>
			</a>
		</div>
		<?php } ?>

		<?php if ($isAdmin) { ?>
		<div class="menu-group">
			<a href="<?php p($gen->linkToRoute('settings.AdminSettings.index', [ 'section' => 'server' ])); ?>"
				class="menu-item" id="pc-menu-legacy-server-admin">
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Server administration')); ?></span>
			</a>
		</div>
		<?php } ?>

		<div class="menu-group">
			<a href="<?php p($gen->linkToDocs('user')); ?>"
				class="menu-item" rel="noreferrer noopener" target="_blank"
				aria-label="<?php p($l->t('Help (opens in a new window)')); ?>">
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Help')); ?></span>
			</a>
		</div>

		<div class="menu-group">
			<a href="<?php p($gen->linkToRoute('core.login.logout')); ?>"
				class="menu-item" id="pc-menu-legacy-logout"
				aria-label="<?php p($l->t('Log out')); ?>">
				<span class="icon" aria-hidden="true"></span>
				<span class="text"><?php p($l->t('Log out')); ?></span>
			</a>
		</div>
	</div>
</nav>
