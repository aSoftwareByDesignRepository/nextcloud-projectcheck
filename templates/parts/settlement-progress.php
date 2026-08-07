<?php

/**
 * Settlement progress (paid / invoiced / open of chargeable hours).
 *
 * Expects:
 *   $progress  array from SettlementProgress::fromCounters() / settlement info
 *   $l         IL10N
 * Optional:
 *   $progressVariant  'full' (detail) | 'compact' (lists/cards) — default full
 *   $progressId       id for aria-labelledby / region (auto if empty)
 *
 * WCAG: text labels + percentages (not colour alone); progressbar role with
 * valuemin/valuemax/valuenow on the billed headline bar; segments aria-hidden
 * with legend in text.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

declare(strict_types=1);

$progress = (isset($progress) && is_array($progress)) ? $progress : [];
$progressVariant = (isset($progressVariant) && $progressVariant === 'compact') ? 'compact' : 'full';
$hasChargeable = !empty($progress['has_chargeable']);
$bar = is_array($progress['bar'] ?? null) ? $progress['bar'] : ['paid' => 0, 'invoiced' => 0, 'open' => 0];
$paidPct = $hasChargeable ? (int)($progress['paid_percent'] ?? 0) : null;
$billedPct = $hasChargeable ? (int)($progress['billed_percent'] ?? 0) : null;
$invoicedPct = $hasChargeable ? (int)($progress['invoiced_percent'] ?? 0) : null;
$openPct = $hasChargeable ? (int)($progress['open_percent'] ?? 0) : null;
$progressId = isset($progressId) && is_string($progressId) && $progressId !== ''
	? $progressId
	: ('pc-stl-progress-' . bin2hex(random_bytes(4)));

if (!$hasChargeable):
	if ($progressVariant === 'full'): ?>
	<div class="pc-stl-progress pc-stl-progress--empty pc-stl-progress--full"
		role="status">
		<p class="pc-stl-progress__empty"><?php p($l->t('No chargeable hours yet — nothing to measure.')); ?></p>
	</div>
	<?php endif;
else:
	$paidLabel = $l->t('Paid so far: %s%%', [(string)$paidPct]);
	$billedLabel = $l->t('Invoiced or paid: %s%%', [(string)$billedPct]);
	$openLabel = $l->t('Still open: %s%%', [(string)$openPct]);
	$invoicedOnlyLabel = $l->t('Invoiced (awaiting payment): %s%%', [(string)$invoicedPct]);
	$regionLabel = $l->t('How much of the chargeable hours is paid or invoiced');
	?>
	<div class="pc-stl-progress pc-stl-progress--<?php p($progressVariant); ?>"
		role="region"
		aria-labelledby="<?php p($progressId); ?>-title"
		data-paid-percent="<?php p((string)$paidPct); ?>"
		data-billed-percent="<?php p((string)$billedPct); ?>">
		<h4 id="<?php p($progressId); ?>-title" class="pc-stl-progress__title<?php if ($progressVariant === 'compact'): ?> pc-sr-only<?php endif; ?>">
			<?php p($l->t('Settlement progress')); ?>
		</h4>
		<p class="pc-stl-progress__lead pc-sr-only"><?php p($regionLabel); ?></p>

		<div class="pc-stl-progress__stats" role="list">
			<div class="pc-stl-progress__stat" role="listitem">
				<span class="pc-stl-progress__stat-label"><?php p($l->t('Paid so far')); ?></span>
				<span class="pc-stl-progress__stat-value" aria-label="<?php p($paidLabel); ?>">
					<?php p((string)$paidPct); ?>%
				</span>
			</div>
			<div class="pc-stl-progress__stat" role="listitem">
				<span class="pc-stl-progress__stat-label"><?php p($l->t('Invoiced or paid')); ?></span>
				<span class="pc-stl-progress__stat-value" aria-label="<?php p($billedLabel); ?>">
					<?php p((string)$billedPct); ?>%
				</span>
			</div>
			<?php if ($progressVariant === 'full'): ?>
			<div class="pc-stl-progress__stat" role="listitem">
				<span class="pc-stl-progress__stat-label"><?php p($l->t('Still open')); ?></span>
				<span class="pc-stl-progress__stat-value" aria-label="<?php p($openLabel); ?>">
					<?php p((string)$openPct); ?>%
				</span>
			</div>
			<?php endif; ?>
		</div>

		<div class="pc-stl-progress__bar"
			role="progressbar"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-valuenow="<?php p((string)$billedPct); ?>"
			aria-valuetext="<?php p($billedLabel); ?>"
			aria-label="<?php p($l->t('Share of chargeable hours that are invoiced or paid')); ?>">
			<span class="pc-stl-progress__seg pc-stl-progress__seg--paid"
				style="width: <?php p((string)(int)$bar['paid']); ?>%"
				aria-hidden="true"></span>
			<span class="pc-stl-progress__seg pc-stl-progress__seg--invoiced"
				style="width: <?php p((string)(int)$bar['invoiced']); ?>%"
				aria-hidden="true"></span>
			<span class="pc-stl-progress__seg pc-stl-progress__seg--open"
				style="width: <?php p((string)(int)$bar['open']); ?>%"
				aria-hidden="true"></span>
		</div>

		<ul class="pc-stl-progress__legend<?php if ($progressVariant === 'compact'): ?> pc-sr-only<?php endif; ?>" aria-label="<?php p($l->t('Legend')); ?>">
			<li class="pc-stl-progress__legend-item">
				<span class="pc-stl-progress__swatch pc-stl-progress__swatch--paid" aria-hidden="true"></span>
				<span><?php p($paidLabel); ?></span>
			</li>
			<li class="pc-stl-progress__legend-item">
				<span class="pc-stl-progress__swatch pc-stl-progress__swatch--invoiced" aria-hidden="true"></span>
				<span><?php p($invoicedOnlyLabel); ?></span>
			</li>
			<li class="pc-stl-progress__legend-item">
				<span class="pc-stl-progress__swatch pc-stl-progress__swatch--open" aria-hidden="true"></span>
				<span><?php p($openLabel); ?></span>
			</li>
		</ul>
	</div>
<?php
endif;

unset($progressVariant, $progressId, $progress);
?>
