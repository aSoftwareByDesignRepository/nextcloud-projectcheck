<?php

declare(strict_types=1);

/**
 * Project-type analysis by calendar year (table layout).
 *
 * Expects:
 *   $projectTypeStats array<string|int, array<string|int, array>>
 *   $l IL10N
 * Optional:
 *   $fmt format helper with hours()/currency()/number()/percent()
 *   $currencyCode string (fallback EUR)
 *   $typeAnalysisIdPrefix string for unique heading ids (default pc-type)
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$projectTypeStats = is_array($projectTypeStats ?? null) ? $projectTypeStats : [];
$fmt = $fmt ?? null;
$currencyCode = isset($currencyCode) && is_string($currencyCode) ? strtoupper(trim($currencyCode)) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
$typeAnalysisIdPrefix = isset($typeAnalysisIdPrefix) && is_string($typeAnalysisIdPrefix) && $typeAnalysisIdPrefix !== ''
	? preg_replace('/[^a-zA-Z0-9_-]/', '', $typeAnalysisIdPrefix) ?? 'pc-type'
	: 'pc-type';

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
$fmtCount = static function (int $count) use ($fmt): string {
	if ($fmt && method_exists($fmt, 'number')) {
		return (string)$fmt->number($count);
	}
	return (string)$count;
};
$fmtPercent = static function (float $pct) use ($fmt): string {
	if ($fmt && method_exists($fmt, 'percent')) {
		return (string)$fmt->percent($pct, 1);
	}
	return round($pct, 1) . '%';
};

$displayNames = [
	'client' => $l->t('Client Project'),
	'admin' => $l->t('Administrative'),
	'sales' => $l->t('Sales & Marketing'),
	'customer' => $l->t('Customer Support'),
	'product' => $l->t('Product Development'),
	'meeting' => $l->t('Meetings & Overhead'),
	'internal' => $l->t('Internal Project'),
	'research' => $l->t('Research & Development'),
	'training' => $l->t('Training & Education'),
	'other' => $l->t('Other'),
];
$typeIconNames = [
	'client' => 'users',
	'admin' => 'settings',
	'sales' => 'bar-chart-3',
	'customer' => 'users',
	'product' => 'layout-grid',
	'meeting' => 'users',
	'internal' => 'folder',
	'research' => 'search',
	'training' => 'file-text',
	'other' => 'tag',
];

if ($projectTypeStats === []) {
	return;
}
?>
<div class="pc-type-analysis">
	<?php foreach ($projectTypeStats as $year => $yearData): ?>
		<?php
		if (!is_array($yearData) || $yearData === []) {
			continue;
		}
		$yearTotalHours = 0.0;
		$yearTotalCost = 0.0;
		foreach ($yearData as $row) {
			if (!is_array($row)) {
				continue;
			}
			$yearTotalHours += max(0.0, (float)($row['total_hours'] ?? 0));
			$yearTotalCost += max(0.0, (float)($row['total_cost'] ?? 0));
		}
		$yearLabel = (string)$year;
		$yearHeadingId = $typeAnalysisIdPrefix . '-year-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $yearLabel);
		$tableId = $yearHeadingId . '-table';
		?>
		<article class="pc-type-analysis__year" aria-labelledby="<?php p($yearHeadingId); ?>">
			<header class="pc-type-analysis__year-head">
				<h4 id="<?php p($yearHeadingId); ?>" class="pc-type-analysis__year-title"><?php p($yearLabel); ?></h4>
				<p class="pc-type-analysis__year-meta">
					<?php
					p($l->t('%1$s · %2$s', [
						$fmtHours($yearTotalHours),
						$fmtMoney($yearTotalCost),
					]));
					?>
				</p>
			</header>

			<div class="pc-type-analysis__table-wrap">
				<table class="pc-type-analysis__table" id="<?php p($tableId); ?>">
					<caption class="pc-sr-only">
						<?php p($l->t('Project types for %s', [$yearLabel])); ?>
					</caption>
					<thead>
						<tr>
							<th scope="col"><?php p($l->t('Type')); ?></th>
							<th scope="col"><?php p($l->t('Hours')); ?></th>
							<th scope="col"><?php p($l->t('Cost')); ?></th>
							<th scope="col"><?php p($l->t('Entries')); ?></th>
							<th scope="col"><?php p($l->t('Hours Share')); ?></th>
							<th scope="col"><?php p($l->t('Cost Share')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($yearData as $projectTypeKey => $typeData): ?>
							<?php
							if (!is_array($typeData)) {
								continue;
							}
							$typeKey = '';
							if (is_string($projectTypeKey) && $projectTypeKey !== '' && !is_numeric($projectTypeKey)) {
								$typeKey = strtolower(trim($projectTypeKey));
							} elseif (isset($typeData['project_type']) && is_string($typeData['project_type'])) {
								$typeKey = strtolower(trim($typeData['project_type']));
							}
							if ($typeKey === '') {
								$typeKey = 'other';
							}
							$displayName = $displayNames[$typeKey] ?? ucfirst($typeKey);
							$typeIcon = $typeIconNames[$typeKey] ?? 'tag';
							$hours = max(0.0, (float)($typeData['total_hours'] ?? 0));
							$cost = max(0.0, (float)($typeData['total_cost'] ?? 0));
							$entries = max(0, (int)($typeData['entry_count'] ?? 0));
							$hoursShare = $yearTotalHours > 0.0 ? ($hours / $yearTotalHours) * 100.0 : 0.0;
							$costShare = $yearTotalCost > 0.0 ? ($cost / $yearTotalCost) * 100.0 : 0.0;
							$hoursShareRounded = round($hoursShare, 2);
							$costShareRounded = round($costShare, 2);
							?>
							<tr>
								<th scope="row" data-label="<?php p($l->t('Type')); ?>">
									<span class="pc-type-analysis__type">
										<span data-lucide="<?php p($typeIcon); ?>" class="lucide-icon" aria-hidden="true"></span>
										<span><?php p($displayName); ?></span>
									</span>
								</th>
								<td class="pc-type-analysis__num" data-label="<?php p($l->t('Hours')); ?>">
									<?php p($fmtHours($hours)); ?>
								</td>
								<td class="pc-type-analysis__num" data-label="<?php p($l->t('Cost')); ?>">
									<?php p($fmtMoney($cost)); ?>
								</td>
								<td class="pc-type-analysis__num" data-label="<?php p($l->t('Entries')); ?>">
									<?php p($fmtCount($entries)); ?>
								</td>
								<td data-label="<?php p($l->t('Hours Share')); ?>">
									<div class="pc-type-analysis__share"
										role="img"
										aria-label="<?php p($l->t('Hours share: %s', [$fmtPercent($hoursShare)])); ?>">
										<span class="pc-type-analysis__share-value"><?php p($fmtPercent($hoursShare)); ?></span>
										<span class="pc-type-analysis__share-track" aria-hidden="true">
											<span class="pc-type-analysis__share-fill"
												style="width: <?php p($hoursShareRounded); ?>%"></span>
										</span>
									</div>
								</td>
								<td data-label="<?php p($l->t('Cost Share')); ?>">
									<div class="pc-type-analysis__share"
										role="img"
										aria-label="<?php p($l->t('Cost share: %s', [$fmtPercent($costShare)])); ?>">
										<span class="pc-type-analysis__share-value"><?php p($fmtPercent($costShare)); ?></span>
										<span class="pc-type-analysis__share-track" aria-hidden="true">
											<span class="pc-type-analysis__share-fill"
												style="width: <?php p($costShareRounded); ?>%"></span>
										</span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</article>
	<?php endforeach; ?>
</div>
