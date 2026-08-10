<?php

declare(strict_types=1);

/**
 * Pricing method radio cards (§7.2).
 *
 * When locked (time already logged): show a single readable summary — never a
 * faded three-card radio grid (disabled absolute radios paint broken chrome).
 *
 * @var \OCP\IL10N $l
 * @var bool $costRateModeLocked
 * @var string $selectedMode
 * @var string $employeesIndexUrl
 */

use OCA\ProjectCheck\Util\CostRateMode;

$costRateModeLocked = !empty($costRateModeLocked);
$selectedMode = CostRateMode::normalize($selectedMode ?? CostRateMode::DEFAULT);
$employeesIndexUrl = (string) ($employeesIndexUrl ?? '#');

$modeMeta = [
	CostRateMode::PROJECT => [
		'icon' => 'folder',
		'title' => $l->t('One rate for the whole project'),
		'desc' => $l->t('Everyone uses the project hourly rate you set below.'),
	],
	CostRateMode::EMPLOYEE => [
		'icon' => 'user',
		'title' => $l->t('Rate per employee (master data)'),
		'desc' => $l->t('Rates come from each person’s history under Employees, based on the work date.'),
	],
	CostRateMode::PROJECT_MEMBER => [
		'icon' => 'users',
		'title' => $l->t('Rate per person on this project'),
		'desc' => $l->t('Set a rate when adding each team member; use effective dates when it changes.'),
	],
];
$current = $modeMeta[$selectedMode];
?>
<?php if ($costRateModeLocked) { ?>
	<div
		class="pc-pricing-locked"
		id="pc-pricing-method"
		data-testid="pc-pricing-locked"
		aria-labelledby="pc-pricing-heading pc-pricing-method-help">
		<div class="pc-pricing-lock-notice" role="status">
			<span class="pc-pricing-lock-notice__icon" data-lucide="info" aria-hidden="true"></span>
			<p id="pc-pricing-method-help" class="pc-pricing-lock-notice__text">
				<?php p($l->t('The pricing method is locked because time has already been logged on this project.')); ?>
			</p>
		</div>
		<div class="pc-pricing-summary" role="group" aria-label="<?php p($l->t('Current pricing method')); ?>">
			<span class="pc-pricing-summary__icon" data-lucide="<?php p($current['icon']); ?>" aria-hidden="true"></span>
			<span class="pc-pricing-summary__text">
				<span class="pc-pricing-summary__label"><?php p($l->t('Current method')); ?></span>
				<span class="pc-pricing-summary__title"><?php p($current['title']); ?></span>
				<span class="pc-pricing-summary__desc"><?php p($current['desc']); ?></span>
			</span>
		</div>
		<?php if ($selectedMode === CostRateMode::EMPLOYEE) { ?>
			<p class="pc-form-hint pc-pricing-context-hint" id="pc-pricing-employee-hint">
				<?php p($l->t('Maintain employee rates under')); ?>
				<a href="<?php p($employeesIndexUrl); ?>"><?php p($l->t('Employees')); ?></a>.
			</p>
		<?php } elseif ($selectedMode === CostRateMode::PROJECT_MEMBER) { ?>
			<p class="pc-form-hint pc-pricing-context-hint" id="pc-pricing-member-hint">
				<?php p($l->t('Add each person individually with their rate when using per-person project pricing.')); ?>
			</p>
		<?php } ?>
	</div>
	<input type="hidden" name="cost_rate_mode" value="<?php p($selectedMode); ?>" data-testid="pc-pricing-mode-value">
<?php } else { ?>
<fieldset
	class="pc-pricing-fieldset"
	id="pc-pricing-method"
	aria-labelledby="pc-pricing-heading pc-pricing-method-help">
	<legend class="pc-sr-only"><?php p($l->t('How are hours priced?')); ?></legend>

	<p class="pc-section-intro" id="pc-pricing-method-help">
		<?php p($l->t('Choose how billable hours are calculated. You can change this until someone logs time on the project.')); ?>
	</p>

	<div class="pc-pricing-cards" role="radiogroup" aria-labelledby="pc-pricing-heading pc-pricing-method-help">
		<label class="pc-pricing-card">
			<input
				type="radio"
				class="pc-pricing-card__input"
				name="cost_rate_mode"
				value="<?php p(CostRateMode::PROJECT); ?>"
				<?php echo $selectedMode === CostRateMode::PROJECT ? 'checked' : ''; ?>>
			<span class="pc-pricing-card__surface">
				<span class="pc-pricing-card__icon" data-lucide="folder" aria-hidden="true"></span>
				<span class="pc-pricing-card__text">
					<span class="pc-pricing-card__title"><?php p($modeMeta[CostRateMode::PROJECT]['title']); ?></span>
					<span class="pc-pricing-card__desc"><?php p($modeMeta[CostRateMode::PROJECT]['desc']); ?></span>
				</span>
				<span class="pc-pricing-card__indicator" aria-hidden="true"></span>
			</span>
		</label>
		<label class="pc-pricing-card">
			<input
				type="radio"
				class="pc-pricing-card__input"
				name="cost_rate_mode"
				value="<?php p(CostRateMode::EMPLOYEE); ?>"
				<?php echo $selectedMode === CostRateMode::EMPLOYEE ? 'checked' : ''; ?>>
			<span class="pc-pricing-card__surface">
				<span class="pc-pricing-card__icon" data-lucide="user" aria-hidden="true"></span>
				<span class="pc-pricing-card__text">
					<span class="pc-pricing-card__title"><?php p($modeMeta[CostRateMode::EMPLOYEE]['title']); ?></span>
					<span class="pc-pricing-card__desc"><?php p($modeMeta[CostRateMode::EMPLOYEE]['desc']); ?></span>
				</span>
				<span class="pc-pricing-card__indicator" aria-hidden="true"></span>
			</span>
		</label>
		<label class="pc-pricing-card">
			<input
				type="radio"
				class="pc-pricing-card__input"
				name="cost_rate_mode"
				value="<?php p(CostRateMode::PROJECT_MEMBER); ?>"
				<?php echo $selectedMode === CostRateMode::PROJECT_MEMBER ? 'checked' : ''; ?>>
			<span class="pc-pricing-card__surface">
				<span class="pc-pricing-card__icon" data-lucide="users" aria-hidden="true"></span>
				<span class="pc-pricing-card__text">
					<span class="pc-pricing-card__title"><?php p($modeMeta[CostRateMode::PROJECT_MEMBER]['title']); ?></span>
					<span class="pc-pricing-card__desc"><?php p($modeMeta[CostRateMode::PROJECT_MEMBER]['desc']); ?></span>
				</span>
				<span class="pc-pricing-card__indicator" aria-hidden="true"></span>
			</span>
		</label>
	</div>

	<div class="pc-pricing-hints">
		<p class="pc-form-hint pc-pricing-context-hint" id="pc-pricing-employee-hint" hidden>
			<?php p($l->t('Maintain employee rates under')); ?>
			<a href="<?php p($employeesIndexUrl); ?>"><?php p($l->t('Employees')); ?></a>.
		</p>
		<p class="pc-form-hint pc-pricing-context-hint" id="pc-pricing-member-hint" hidden>
			<?php p($l->t('Add each person individually with their rate when using per-person project pricing.')); ?>
		</p>
	</div>
</fieldset>
<?php } ?>
