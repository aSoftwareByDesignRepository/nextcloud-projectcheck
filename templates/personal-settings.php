<?php

/**
 * Personal settings panel for ProjectCheck (rendered inside Nextcloud's Personal Settings UI).
 *
 * IMPORTANT: This template runs inside Nextcloud's settings layout.
 * It must NOT include any app-level navigation, page chrome or layout
 * containers (#app-navigation / #app-content) -- those belong to the host page.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var \OCP\IL10N $l
 * @var bool $hasAccess
 * @var string $budget_warning_threshold
 * @var string $budget_critical_threshold
 * @var string $appBudgetWarningDefault
 * @var string $appBudgetCriticalDefault
 * @var string $saveUrl
 */

use OCP\Util;

Util::addStyle('projectcheck', 'personal-settings');

$hasAccess = !empty($hasAccess);
$formId = 'projectcheck-personal-settings-form';
$saveUrl = (string) ($saveUrl ?? '');
$warning = (string) ($budget_warning_threshold ?? '');
$critical = (string) ($budget_critical_threshold ?? '');
$appWarn = (string) ($appBudgetWarningDefault ?? '80');
$appCrit = (string) ($appBudgetCriticalDefault ?? '90');
?>
<section class="section projectcheck-personal" aria-labelledby="projectcheck-personal-heading">
	<h2 id="projectcheck-personal-heading" class="projectcheck-personal__title">
		<?php p($l->t('ProjectCheck')); ?>
	</h2>

	<?php if (!$hasAccess) : ?>
		<p class="projectcheck-personal__intro">
			<?php p($l->t('You do not currently have access to ProjectCheck. Ask an administrator if you should be added.')); ?>
		</p>
	<?php else : ?>
		<p class="projectcheck-personal__intro">
			<?php p($l->t('Override the budget alert thresholds your dashboard and notifications use. Leave the defaults to follow the organization settings.')); ?>
		</p>

		<form id="<?php p($formId); ?>"
			class="projectcheck-personal__form"
			method="post"
			action="<?php p($saveUrl); ?>"
			data-save-url="<?php p($saveUrl); ?>"
			novalidate>

			<input type="hidden" name="requesttoken" value="<?php p(\OCP\Util::callRegister()); ?>">

			<fieldset class="projectcheck-personal__fieldset">
				<legend class="projectcheck-personal__legend"><?php p($l->t('Budget alert thresholds')); ?></legend>

				<div class="projectcheck-personal__field">
					<label class="projectcheck-personal__label" for="pc-personal-warning">
						<?php p($l->t('Warning threshold')); ?>
					</label>
					<div class="projectcheck-personal__input-row">
						<input
							type="number"
							id="pc-personal-warning"
							name="budget_warning_threshold"
							class="projectcheck-personal__input"
							inputmode="numeric"
							min="0"
							max="100"
							step="1"
							required
							value="<?php p($warning); ?>"
							aria-describedby="pc-personal-warning-hint pc-personal-warning-error">
						<span class="projectcheck-personal__suffix" aria-hidden="true">%</span>
					</div>
					<p id="pc-personal-warning-hint" class="projectcheck-personal__hint">
						<?php p($l->t('Projects are highlighted as “warning” when budget consumption reaches this percentage.')); ?>
						<span class="projectcheck-personal__default">
							<?php p($l->t('Organization default: %s%%', [ $appWarn ])); ?>
						</span>
					</p>
					<p id="pc-personal-warning-error" class="projectcheck-personal__error" role="alert" hidden></p>
				</div>

				<div class="projectcheck-personal__field">
					<label class="projectcheck-personal__label" for="pc-personal-critical">
						<?php p($l->t('Critical threshold')); ?>
					</label>
					<div class="projectcheck-personal__input-row">
						<input
							type="number"
							id="pc-personal-critical"
							name="budget_critical_threshold"
							class="projectcheck-personal__input"
							inputmode="numeric"
							min="0"
							max="100"
							step="1"
							required
							value="<?php p($critical); ?>"
							aria-describedby="pc-personal-critical-hint pc-personal-critical-error">
						<span class="projectcheck-personal__suffix" aria-hidden="true">%</span>
					</div>
					<p id="pc-personal-critical-hint" class="projectcheck-personal__hint">
						<?php p($l->t('Projects are flagged as “critical” when budget consumption reaches this percentage. Must be higher than the warning threshold.')); ?>
						<span class="projectcheck-personal__default">
							<?php p($l->t('Organization default: %s%%', [ $appCrit ])); ?>
						</span>
					</p>
					<p id="pc-personal-critical-error" class="projectcheck-personal__error" role="alert" hidden></p>
				</div>
			</fieldset>

			<div class="projectcheck-personal__actions">
				<button type="submit"
					class="projectcheck-personal__save button primary"
					data-pc-save>
					<span class="projectcheck-personal__save-label">
						<?php p($l->t('Save preferences')); ?>
					</span>
					<span class="projectcheck-personal__save-spinner" aria-hidden="true" hidden></span>
				</button>
				<p class="projectcheck-personal__status"
					role="status"
					aria-live="polite"
					data-pc-status></p>
			</div>
		</form>

		<?php
		Util::addScript('projectcheck', 'personal-settings');
		?>
	<?php endif; ?>
</section>
