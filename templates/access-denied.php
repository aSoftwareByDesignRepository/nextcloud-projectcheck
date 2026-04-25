<?php
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
<div class="section projectcheck-access-denied" role="region" aria-labelledby="projectcheck-access-denied-title">
	<h2 id="projectcheck-access-denied-title"><?php p($l->t('Access denied')); ?></h2>
	<p class="projectcheck-access-denied__message"><?php p($message); ?></p>
	<p>
		<a class="button primary" href="<?php p($homeUrl); ?>"><?php p($l->t('Go to your Nextcloud')); ?></a>
	</p>
</div>
