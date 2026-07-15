<?php
/**
 * ProjectCheck page shell (after navigation.php).
 *
 * @var string $pageId
 * @var string $pageTitle translated page title (optional)
 * @var string $pageHelp optional lead text
 * @var string $pageKicker optional label above the title
 * @var string $pageTitleId id attribute for h1 (default pc-page-title)
 * @var string $mainContentId id for main landmark (default pc-main-content)
 * @var string $mainContentClass extra classes on main landmark
 * @var string $wrapperClass classes on #app-content-wrapper (default pc-shell)
 * @var bool $includeScopeStrip include scope-strip-project.php when true
 * @var string $pageHeaderActionsHtml optional HTML for header action buttons (escaped by caller)
 * @var string $pageHeaderMetaHtml    optional pre-rendered meta HTML below the lead line (escaped by caller)
 * @var string $pageHeaderActionsLabel aria-label for the header actions group
 */
$pageId = isset($pageId) ? (string)$pageId : (string)($_['pageId'] ?? 'page');
$pageTitle = (string)($pageTitle ?? $_['pageTitle'] ?? '');
$pageHelp = (string)($pageHelp ?? $_['pageHelp'] ?? '');
$pageKicker = (string)($pageKicker ?? $_['pageKicker'] ?? '');
$pageTitleId = (string)($pageTitleId ?? $_['pageTitleId'] ?? 'pc-page-title');
$mainContentId = (string)($mainContentId ?? $_['mainContentId'] ?? 'pc-main-content');
$mainContentClass = trim('pc-main ' . (string)($mainContentClass ?? $_['mainContentClass'] ?? ''));
$wrapperClass = (string)($wrapperClass ?? $_['wrapperClass'] ?? 'pc-shell');
$includeScopeStrip = !empty($includeScopeStrip) || !empty($_['includeScopeStrip']);
$pageHeaderActionsHtml = trim((string)($pageHeaderActionsHtml ?? $_['pageHeaderActionsHtml'] ?? ''));
$pageHeaderMetaHtml = trim((string)($pageHeaderMetaHtml ?? $_['pageHeaderMetaHtml'] ?? ''));
$pageHeaderActionsLabel = (string)($pageHeaderActionsLabel ?? $_['pageHeaderActionsLabel'] ?? $l->t('Page actions'));
?>
<div id="app-content" class="pc-app pc-app--<?php p($pageId); ?>">
	<a class="pc-skip-link" href="#<?php p($mainContentId); ?>"><?php p($l->t('Skip to main content')); ?></a>
	<div id="pc-live-region" class="pc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="pc-alert-region" class="pc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>
	<div id="app-content-wrapper" class="<?php p($wrapperClass); ?> pc-app-shell-stack">
		<?php if ($pageTitle !== ''): ?>
			<header class="pc-page-header" aria-labelledby="<?php p($pageTitleId); ?>">
				<div class="pc-page-header__row">
					<button type="button"
						class="pc-nav-toggle"
						id="pc-nav-toggle"
						data-pc-nav-toggle
						aria-controls="app-navigation"
						aria-expanded="false"
						aria-label="<?php p($l->t('Toggle mobile menu')); ?>"
						data-aria-label-open="<?php p($l->t('Toggle mobile menu')); ?>"
						data-aria-label-close="<?php p($l->t('Close navigation menu')); ?>">
						<span class="pc-nav-toggle__icon" data-lucide="menu" aria-hidden="true"></span>
						<span class="pc-nav-toggle__label"><?php p($l->t('Menu')); ?></span>
					</button>
					<div class="pc-page-header__content">
						<?php if ($pageKicker !== ''): ?>
							<p class="pc-page-header__kicker"><?php p($pageKicker); ?></p>
						<?php endif; ?>
						<h1 id="<?php p($pageTitleId); ?>"><?php p($pageTitle); ?></h1>
						<?php if ($pageHelp !== ''): ?>
							<p class="pc-page-header__lead"><?php p($pageHelp); ?></p>
						<?php endif; ?>
						<?php if ($pageHeaderMetaHtml !== ''): ?>
							<div class="pc-page-header__meta">
								<?php print_unescaped($pageHeaderMetaHtml); ?>
							</div>
						<?php endif; ?>
					</div>
					<?php if ($pageHeaderActionsHtml !== ''): ?>
						<div class="pc-page-header__actions" role="group" aria-label="<?php p($pageHeaderActionsLabel); ?>">
							<?php print_unescaped($pageHeaderActionsHtml); ?>
						</div>
					<?php endif; ?>
				</div>
			</header>
		<?php endif; ?>
		<?php if ($includeScopeStrip): ?>
			<?php include __DIR__ . '/../parts/scope-strip-project.php'; ?>
		<?php endif; ?>
		<main id="<?php p($mainContentId); ?>" class="<?php p($mainContentClass); ?>" tabindex="-1">
