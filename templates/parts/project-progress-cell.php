<?php

declare(strict_types=1);

/**
 * Compact progress cell: hours text always; progress track only when consumption > 0.
 *
 * Locals: $budgetInfo (array|null), $l (IL10N)
 *
 * @var array|null $budgetInfo
 * @var \OCP\IL10N $l
 */

use OCA\ProjectCheck\Util\ProgressCellPresentation;

$pct = is_array($budgetInfo) ? ($budgetInfo['consumption_percentage'] ?? 0) : 0;
$hours = is_array($budgetInfo) ? ($budgetInfo['used_hours'] ?? 0) : 0;
$warningLevel = is_array($budgetInfo) ? (string)($budgetInfo['warning_level'] ?? 'none') : 'none';
$normalizedHours = ProgressCellPresentation::normalizedHours($hours);
$showBar = ProgressCellPresentation::shouldShowProgressBar($pct, $normalizedHours);
$barWidth = ProgressCellPresentation::barWidthPercent($pct);
?>
<div class="progress-info<?php if (!$showBar): ?> progress-info--empty<?php endif; ?>">
	<?php if ($showBar): ?>
		<div class="budget-progress-bar compact"
			role="progressbar"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-valuenow="<?php p((int)round($barWidth)); ?>"
			aria-label="<?php p($l->t('Budget consumption: %s%%', [(string)(int)round($barWidth)])); ?>">
			<div class="budget-progress-fill <?php p($warningLevel); ?>"
				style="width: <?php p($barWidth); ?>%"></div>
		</div>
	<?php endif; ?>
	<span class="hours-logged">
		<?php if ($normalizedHours > 0.0): ?>
			<?php p(number_format($normalizedHours, 1)); ?>h <?php p($l->t('logged')); ?>
			<?php if (is_array($budgetInfo) && !empty($budgetInfo['hours_estimated']) && ($budgetInfo['available_hours'] ?? 0) > 0): ?>
				<span class="hours-capacity-estimate" title="<?php p($l->t('Estimated capacity based on planning or project rate')); ?>">
					· <?php p($l->t('%sh remaining (estimate)', [number_format((float)$budgetInfo['remaining_hours'], 1, '.', '')])); ?>
				</span>
			<?php endif; ?>
		<?php else: ?>
			<?php p($l->t('No hours logged yet')); ?>
		<?php endif; ?>
	</span>
</div>
