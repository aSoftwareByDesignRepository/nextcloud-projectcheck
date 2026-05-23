<?php
/**
 * Hours capacity line (planning estimate or budget-only fallback).
 *
 * @var array $budgetInfo
 * @var float|int $usedHours
 * @var \OCA\ProjectCheck\Service\LocaleFormatService|null $fmt
 */
declare(strict_types=1);

$budgetInfo = $budgetInfo ?? [];
$usedHours = (float) ($usedHours ?? ($budgetInfo['used_hours'] ?? 0));
$hoursEstimated = !empty($budgetInfo['hours_estimated']);
$availableHours = (float) ($budgetInfo['available_hours'] ?? 0);
$remainingHours = (float) ($budgetInfo['remaining_hours'] ?? 0);
$capacityBasis = (string) ($budgetInfo['capacity_basis'] ?? '');
$compact = !empty($compact);
$compactSilent = !empty($compactSilent);
?>
<div class="pc-capacity-hours<?php echo $compact ? ' pc-capacity-hours--compact' : ''; ?>" data-capacity-estimated="<?php echo $hoursEstimated ? '1' : '0'; ?>">
	<?php if ($compact): ?>
		<?php if ($hoursEstimated && $availableHours > 0): ?>
			<p class="pc-capacity-hours__sub">
				<?php p($l->t('%sh remaining (estimate)', [number_format($remainingHours, 1, '.', '')])); ?>
			</p>
		<?php elseif (!$compactSilent): ?>
			<p class="pc-capacity-hours__sub pc-capacity-hours__sub--muted">
				<?php p($l->t('No hour estimate — add a planning rate')); ?>
			</p>
		<?php endif; ?>
	<?php elseif ($hoursEstimated && $availableHours > 0): ?>
		<p class="pc-capacity-hours__primary">
			<span class="pc-capacity-hours__logged">
				<?php
				if ($fmt) {
					p($fmt->hours($usedHours));
				} else {
					p(number_format($usedHours, 1, '.', '') . 'h');
				}
				?>
			</span>
			<span class="pc-capacity-hours__sep" aria-hidden="true">/</span>
			<span class="pc-capacity-hours__total">
				<?php
				if ($fmt) {
					p($fmt->hours($availableHours));
				} else {
					p(number_format($availableHours, 1, '.', '') . 'h');
				}
				?>
			</span>
			<span class="pc-capacity-hours__label"><?php p($l->t('estimated capacity')); ?></span>
		</p>
		<p class="pc-capacity-hours__sub">
			<?php p($l->t('%sh remaining (estimate)', [number_format($remainingHours, 1, '.', '')])); ?>
			<?php if ($capacityBasis === \OCA\ProjectCheck\Util\ProjectCapacity::BASIS_PLANNING_RATE): ?>
				<span class="pc-capacity-hours__note"> — <?php p($l->t('based on planning rate')); ?></span>
			<?php endif; ?>
		</p>
	<?php else: ?>
		<p class="pc-capacity-hours__primary">
			<span class="pc-capacity-hours__logged">
				<?php
				if ($fmt) {
					p($fmt->hours($usedHours));
				} else {
					p(number_format($usedHours, 1, '.', '') . 'h');
				}
				?>
			</span>
			<span class="pc-capacity-hours__label"><?php p($l->t('logged')); ?></span>
		</p>
		<p class="pc-capacity-hours__sub pc-capacity-hours__sub--muted">
			<?php p($l->t('Hour estimate unavailable — costs use each person’s rate. Add an optional planning rate on the project to estimate capacity.')); ?>
		</p>
	<?php endif; ?>
</div>
