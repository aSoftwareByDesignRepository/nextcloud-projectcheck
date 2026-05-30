<?php

declare(strict_types=1);

/**
 * User-facing error page (schema repair, auth, validation).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var \OCP\IL10N $l
 * @var string $message Pre-translated user message (never pass raw exception text)
 * @var string $homeUrl
 * @var string|null $secondaryUrl
 * @var string|null $secondaryLabel
 */

use OCA\ProjectCheck\Service\IconCatalog;
use OCP\Util;

Util::addStyle('projectcheck', 'common/status-pages');
Util::addStyle('projectcheck', 'common/accessibility');
?>
<div class="section projectcheck-status-page" role="alert" aria-labelledby="projectcheck-error-title">
	<div class="projectcheck-status-page__icon projectcheck-status-page__icon--error" aria-hidden="true">
		<?php print_unescaped(IconCatalog::render('alert-circle', 'pc-icon--lg')); ?>
	</div>
	<h2 id="projectcheck-error-title" class="projectcheck-status-page__title"><?php p($l->t('Something went wrong')); ?></h2>
	<p class="projectcheck-status-page__message"><?php p($message); ?></p>
	<div class="projectcheck-status-page__actions">
		<?php if (!empty($secondaryUrl) && !empty($secondaryLabel)): ?>
			<a class="button primary" href="<?php p($secondaryUrl); ?>"><?php p($secondaryLabel); ?></a>
		<?php endif; ?>
		<a class="button<?php echo !empty($secondaryUrl) ? ' secondary' : ' primary'; ?>" href="<?php p($homeUrl); ?>">
			<?php p($l->t('Go to your Nextcloud')); ?>
		</a>
	</div>
</div>
