<?php

/**
 * Optional secondary header bar (meta + actions) below the page title.
 *
 * Prefer setting $pageHeaderMetaHtml and $pageHeaderActionsHtml on
 * common/page-start.php so actions sit in the unified page header.
 * This partial remains for legacy includes only.
 *
 * Include after common/page-start.php.
 *
 * @var string $headerLead         plain-text lead line (escaped here)
 * @var string $headerMetaHtml     pre-rendered, already escaped meta HTML (badges, meta items)
 * @var string $headerActionsHtml  pre-rendered, already escaped actions HTML (buttons/links)
 * @var string $headerActionsLabel aria-label for the actions group
 * @var string $headerSectionClass extra CSS classes for the section wrapper
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

\OCP\Util::addStyle('projectcheck', 'common/page-header-section');

$headerLead = (string)($headerLead ?? '');
$headerMetaHtml = (string)($headerMetaHtml ?? '');
$headerActionsHtml = trim((string)($headerActionsHtml ?? ''));
$headerActionsLabel = (string)($headerActionsLabel ?? $l->t('Page actions'));
$headerSectionClass = trim('section pc-section pc-page-header-section ' . (string)($headerSectionClass ?? ''));
?>
<?php if ($headerLead !== '' || $headerMetaHtml !== '' || $headerActionsHtml !== ''): ?>
<div class="<?php p($headerSectionClass); ?>">
	<div class="header-content">
		<?php if ($headerLead !== '' || $headerMetaHtml !== ''): ?>
			<div class="header-text">
				<?php if ($headerLead !== ''): ?>
					<p class="pc-page-header__lead"><?php p($headerLead); ?></p>
				<?php endif; ?>
				<?php if ($headerMetaHtml !== ''): ?>
					<div class="header-details"><?php print_unescaped($headerMetaHtml); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ($headerActionsHtml !== ''): ?>
			<div class="header-actions" role="group" aria-label="<?php p($headerActionsLabel); ?>">
				<?php print_unescaped($headerActionsHtml); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>
<?php unset($headerLead, $headerMetaHtml, $headerActionsHtml, $headerActionsLabel, $headerSectionClass); ?>
