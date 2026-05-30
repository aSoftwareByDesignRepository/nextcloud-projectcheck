<?php
use OCA\ProjectCheck\Service\IconCatalog;

/**
 * 403 access denied for users without ProjectCheck access
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var \OCP\IL10N $l
 * @var string $message
 * @var string $homeUrl
 */
?>
<?php
use OCP\Util;
Util::addStyle('projectcheck', 'common/status-pages');
Util::addStyle('projectcheck', 'common/accessibility');
?>
<div class="section projectcheck-status-page" role="region" aria-labelledby="projectcheck-access-denied-title">
	<div class="projectcheck-status-page__icon" aria-hidden="true"><?php print_unescaped(IconCatalog::render('shield-alert', 'pc-icon--lg')); ?></div>
	<h2 id="projectcheck-access-denied-title" class="projectcheck-status-page__title"><?php p($l->t('Access denied')); ?></h2>
	<p class="projectcheck-status-page__message"><?php p($message); ?></p>
	<div class="projectcheck-status-page__actions">
		<a class="button primary" href="<?php p($homeUrl); ?>"><?php p($l->t('Go to your Nextcloud')); ?></a>
	</div>
</div>
