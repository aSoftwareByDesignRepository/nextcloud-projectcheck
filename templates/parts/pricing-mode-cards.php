<?php

declare(strict_types=1);

/**
 * Pricing method radio cards (§7.2).
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
?>
<fieldset
	class="pc-pricing-fieldset"
	id="pc-pricing-method"
	aria-labelledby="pc-pricing-heading pc-pricing-method-help"
	<?php if ($costRateModeLocked) {
		echo ' aria-disabled="true"';
	} ?>>
	<legend class="pc-sr-only"><?php p($l->t('How are hours priced?')); ?></legend>

	<?php if ($costRateModeLocked) { ?>
		<div class="pc-pricing-lock-notice" role="status">
			<span class="pc-pricing-lock-notice__icon" data-lucide="info" aria-hidden="true"></span>
			<p id="pc-pricing-method-help" class="pc-pricing-lock-notice__text">
				<?php p($l->t('The pricing method is locked because time has already been logged on this project.')); ?>
			</p>
		</div>
	<?php } else { ?>
		<p class="pc-section-intro" id="pc-pricing-method-help">
			<?php p($l->t('Choose how billable hours are calculated. You can change this until someone logs time on the project.')); ?>
		</p>
	<?php } ?>

	<div class="pc-pricing-cards" role="radiogroup" aria-labelledby="pc-pricing-heading pc-pricing-method-help">
		<label class="pc-pricing-card">
			<input
				type="radio"
				class="pc-pricing-card__input"
				name="cost_rate_mode"
				value="<?php p(CostRateMode::PROJECT); ?>"
				<?php echo $selectedMode === CostRateMode::PROJECT ? 'checked' : ''; ?>
				<?php echo $costRateModeLocked ? 'disabled' : ''; ?>>
			<span class="pc-pricing-card__surface">
				<span class="pc-pricing-card__icon" data-lucide="folder" aria-hidden="true"></span>
				<span class="pc-pricing-card__text">
					<span class="pc-pricing-card__title"><?php p($l->t('One rate for the whole project')); ?></span>
					<span class="pc-pricing-card__desc"><?php p($l->t('Everyone uses the project hourly rate you set below.')); ?></span>
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
				<?php echo $selectedMode === CostRateMode::EMPLOYEE ? 'checked' : ''; ?>
				<?php echo $costRateModeLocked ? 'disabled' : ''; ?>>
			<span class="pc-pricing-card__surface">
				<span class="pc-pricing-card__icon" data-lucide="user" aria-hidden="true"></span>
				<span class="pc-pricing-card__text">
					<span class="pc-pricing-card__title"><?php p($l->t('Rate per employee (master data)')); ?></span>
					<span class="pc-pricing-card__desc"><?php p($l->t('Rates come from each person’s history under Employees, based on the work date.')); ?></span>
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
				<?php echo $selectedMode === CostRateMode::PROJECT_MEMBER ? 'checked' : ''; ?>
				<?php echo $costRateModeLocked ? 'disabled' : ''; ?>>
			<span class="pc-pricing-card__surface">
				<span class="pc-pricing-card__icon" data-lucide="users" aria-hidden="true"></span>
				<span class="pc-pricing-card__text">
					<span class="pc-pricing-card__title"><?php p($l->t('Rate per person on this project')); ?></span>
					<span class="pc-pricing-card__desc"><?php p($l->t('Set a rate when adding each team member; use effective dates when it changes.')); ?></span>
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
<?php if ($costRateModeLocked) { ?>
	<!-- Outside disabled controls: hidden fields inside a disabled fieldset are not submitted (HTML). -->
	<input type="hidden" name="cost_rate_mode" value="<?php p($selectedMode); ?>">
<?php } ?>
