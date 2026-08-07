<?php
/**
 * Project context strip: customer, status, pricing mode, budget health.
 *
 * @var \OCA\ProjectCheck\Db\Project $project
 * @var string $customerName
 * @var string $pricingModeLabel
 * @var array $budgetInfo
 * @var \OCA\ProjectCheck\Service\LocaleFormatService|null $fmt
 */
if (!isset($project) || !($project instanceof \OCA\ProjectCheck\Db\Project)) {
	return;
}
$budgetInfo = $budgetInfo ?? [];
$warningLevel = (string)($budgetInfo['warning_level'] ?? 'none');
$consumption = isset($budgetInfo['consumption_percentage']) ? (float)$budgetInfo['consumption_percentage'] : 0.0;
$badgeClass = 'pc-badge--neutral';
$budgetLabel = $l->t('Budget on track');
if ($warningLevel === 'critical' || $consumption > 100) {
	$badgeClass = 'pc-badge--critical';
	$budgetLabel = $l->t('Budget critical');
} elseif ($warningLevel === 'warning') {
	$badgeClass = 'pc-badge--warning';
	$budgetLabel = $l->t('Budget warning');
}
$statusClass = 'status-' . strtolower(str_replace(' ', '-', (string)$project->getStatus()));
?>
<div class="pc-scope-strip" role="region" aria-label="<?php p($l->t('Project context')); ?>">
	<span class="pc-scope-strip__item">
		<span class="pc-scope-strip__label"><?php p($l->t('Customer')); ?>:</span>
		<strong><?php p($customerName ?? ''); ?></strong>
	</span>
	<span class="pc-scope-strip__item">
		<span class="pc-scope-strip__label"><?php p($l->t('Status')); ?>:</span>
		<span class="status-badge <?php p($statusClass); ?>"><?php p($project->getStatus()); ?></span>
	</span>
	<span class="pc-scope-strip__item">
		<span class="pc-scope-strip__label"><?php p($l->t('Pricing')); ?>:</span>
		<span class="pc-badge pc-badge--info"><?php p($pricingModeLabel ?? $project->getCostRateMode()); ?></span>
	</span>
	<span class="pc-scope-strip__item">
		<span class="pc-scope-strip__label"><?php p($l->t('Budget')); ?>:</span>
		<span class="pc-badge <?php p($badgeClass); ?>"><?php p($budgetLabel); ?></span>
		<?php if ($consumption > 0): ?>
			<span class="pc-scope-strip__meta"><?php p($l->t('%s%% used', [round($consumption, 0)])); ?></span>
		<?php endif; ?>
	</span>
</div>
