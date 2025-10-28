<?php

/**
 * Error template for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addStyle('projectcheck', 'projects');
?>

<div class="projectcheck-error">
    <div class="error-container">
        <h2><?php p($l->t('Error')); ?></h2>
        <p><?php p($l->t($message ?? $error ?? 'An error occurred')); ?></p>
        <a href="<?php p(isset($urlGenerator) ? $urlGenerator->linkToRoute('projectcheck.project.index') : '/apps/projectcheck/projects'); ?>" class="button">
            <?php p($l->t('Back to Projects')); ?>
        </a>
    </div>
</div>