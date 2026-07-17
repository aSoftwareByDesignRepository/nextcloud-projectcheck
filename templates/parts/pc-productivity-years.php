<?php

declare(strict_types=1);

/**
 * Billable vs overhead productivity by calendar year.
 *
 * Expects:
 *   $productivityAnalysis array<string|int, array{billable?: array, overhead?: array}>
 *   $l IL10N
 * Optional:
 *   $fmt format helper with hours()/currency()/percent()
 *   $currencyCode string (fallback EUR)
 *   $productivityIdPrefix string for unique heading ids (default pc-prod)
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$productivityAnalysis = is_array($productivityAnalysis ?? null) ? $productivityAnalysis : [];
$fmt = $fmt ?? null;
$currencyCode = isset($currencyCode) && is_string($currencyCode) ? strtoupper(trim($currencyCode)) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
$productivityIdPrefix = isset($productivityIdPrefix) && is_string($productivityIdPrefix) && $productivityIdPrefix !== ''
	? preg_replace('/[^a-zA-Z0-9_-]/', '', $productivityIdPrefix) ?? 'pc-prod'
	: 'pc-prod';

$fmtHours = static function (float $hours) use ($fmt): string {
	if ($fmt && method_exists($fmt, 'hours')) {
		return (string)$fmt->hours($hours);
	}
	return number_format($hours, 1) . 'h';
};
$fmtMoney = static function (float $amount) use ($fmt, $currencyCode): string {
	if ($fmt && method_exists($fmt, 'currency')) {
		return (string)$fmt->currency($amount);
	}
	return $currencyCode . ' ' . number_format($amount, 2);
};
$fmtPercent = static function (float $pct) use ($fmt): string {
	if ($fmt && method_exists($fmt, 'percent')) {
		return (string)$fmt->percent($pct, 1);
	}
	return round($pct, 1) . '%';
};

if ($productivityAnalysis === []) {
	return;
}
?>
<div class="pc-productivity">
	<?php foreach ($productivityAnalysis as $year => $yearData): ?>
		<?php
		if (!is_array($yearData)) {
			continue;
		}
		$billable = is_array($yearData['billable'] ?? null) ? $yearData['billable'] : [];
		$overhead = is_array($yearData['overhead'] ?? null) ? $yearData['overhead'] : [];
		$billHours = max(0.0, (float)($billable['total_hours'] ?? 0));
		$overHours = max(0.0, (float)($overhead['total_hours'] ?? 0));
		$billCost = max(0.0, (float)($billable['total_cost'] ?? 0));
		$overCost = max(0.0, (float)($overhead['total_cost'] ?? 0));
		$totalHours = $billHours + $overHours;
		$billPct = $totalHours > 0.0 ? ($billHours / $totalHours) * 100.0 : 0.0;
		$overPct = $totalHours > 0.0 ? ($overHours / $totalHours) * 100.0 : 0.0;
		$yearLabel = (string)$year;
		$yearHeadingId = $productivityIdPrefix . '-year-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $yearLabel);
		$meterLabel = $l->t('Billable %1$s, overhead %2$s', [
			$fmtPercent($billPct),
			$fmtPercent($overPct),
		]);
		?>
		<article class="pc-productivity__year" aria-labelledby="<?php p($yearHeadingId); ?>">
			<header class="pc-productivity__year-head">
				<h4 id="<?php p($yearHeadingId); ?>" class="pc-productivity__year-title"><?php p($yearLabel); ?></h4>
				<?php if ($totalHours <= 0.0): ?>
					<p class="pc-productivity__year-empty"><?php p($l->t('No hours logged in this year.')); ?></p>
				<?php else: ?>
					<p class="pc-productivity__year-total">
						<?php p($l->t('Total: %s', [$fmtHours($totalHours)])); ?>
					</p>
				<?php endif; ?>
			</header>

			<div class="pc-productivity__split">
				<div class="pc-productivity__meter"
					role="img"
					aria-label="<?php p($meterLabel); ?>">
					<div class="pc-productivity__meter-track" aria-hidden="true">
						<span class="pc-productivity__meter-fill pc-productivity__meter-fill--billable"
							style="width: <?php p(round($billPct, 2)); ?>%"></span>
						<span class="pc-productivity__meter-fill pc-productivity__meter-fill--overhead"
							style="width: <?php p(round($overPct, 2)); ?>%"></span>
					</div>
				</div>
				<ul class="pc-productivity__legend">
					<li class="pc-productivity__legend-item pc-productivity__legend-item--billable">
						<span class="pc-productivity__swatch" aria-hidden="true"></span>
						<span class="pc-productivity__legend-text">
							<?php p($l->t('Billable')); ?>:
							<strong><?php p($fmtPercent($billPct)); ?></strong>
						</span>
					</li>
					<li class="pc-productivity__legend-item pc-productivity__legend-item--overhead">
						<span class="pc-productivity__swatch" aria-hidden="true"></span>
						<span class="pc-productivity__legend-text">
							<?php p($l->t('Overhead')); ?>:
							<strong><?php p($fmtPercent($overPct)); ?></strong>
						</span>
					</li>
				</ul>
			</div>

			<dl class="pc-productivity__grid">
				<div class="pc-productivity__col pc-productivity__col--billable">
					<dt class="pc-productivity__col-title">
						<span data-lucide="dollar-sign" class="lucide-icon" aria-hidden="true"></span>
						<?php p($l->t('Billable work')); ?>
					</dt>
					<dd class="pc-productivity__metrics">
						<div class="pc-productivity__metric">
							<span class="pc-productivity__metric-value"><?php p($fmtHours($billHours)); ?></span>
							<span class="pc-productivity__metric-label"><?php p($l->t('Hours')); ?></span>
						</div>
						<div class="pc-productivity__metric">
							<span class="pc-productivity__metric-value"><?php p($fmtMoney($billCost)); ?></span>
							<span class="pc-productivity__metric-label"><?php p($l->t('Revenue')); ?></span>
						</div>
					</dd>
				</div>
				<div class="pc-productivity__col pc-productivity__col--overhead">
					<dt class="pc-productivity__col-title">
						<span data-lucide="settings" class="lucide-icon" aria-hidden="true"></span>
						<?php p($l->t('Overhead work')); ?>
					</dt>
					<dd class="pc-productivity__metrics">
						<div class="pc-productivity__metric">
							<span class="pc-productivity__metric-value"><?php p($fmtHours($overHours)); ?></span>
							<span class="pc-productivity__metric-label"><?php p($l->t('Hours')); ?></span>
						</div>
						<div class="pc-productivity__metric">
							<span class="pc-productivity__metric-value"><?php p($fmtMoney($overCost)); ?></span>
							<span class="pc-productivity__metric-label"><?php p($l->t('Cost')); ?></span>
						</div>
					</dd>
				</div>
			</dl>
		</article>
	<?php endforeach; ?>
</div>
