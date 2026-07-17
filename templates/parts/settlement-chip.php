<?php

/**
 * Shared settlement chip (feature spec §12.1/§12.8).
 *
 * Text + icon, never colour alone (WCAG 1.4.1). Expects:
 *   $chipKind  'status' (entry billing status) | 'posture' (project/customer)
 *   $chipValue one of the BillingStatus / SettlementPosture codes
 *   $l         IL10N (always present in templates)
 * Optional:
 *   $chipTitle tooltip / accessible description override
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$chipKind = isset($chipKind) && $chipKind === 'posture' ? 'posture' : 'status';
$chipValue = isset($chipValue) ? (string)$chipValue : '';

if ($chipKind === 'status') {
    $chipMap = [
        'open' => ['label' => $l->t('Open'), 'icon' => 'clock', 'mod' => 'open'],
        'invoiced' => ['label' => $l->t('Invoiced'), 'icon' => 'file-text', 'mod' => 'invoiced'],
        'paid' => ['label' => $l->t('Paid'), 'icon' => 'circle-check', 'mod' => 'paid'],
        'excluded' => ['label' => $l->t('Not billable'), 'icon' => 'circle-slash', 'mod' => 'excluded'],
    ];
    $chipFallback = $chipMap['open'];
} else {
    $chipMap = [
        'n_a' => ['label' => $l->t('Nothing to invoice'), 'icon' => 'minus', 'mod' => 'na'],
        'open' => ['label' => $l->t('Open'), 'icon' => 'clock', 'mod' => 'open'],
        'partial' => ['label' => $l->t('Partially settled'), 'icon' => 'chart-pie', 'mod' => 'partial'],
        'awaiting_payment' => ['label' => $l->t('Awaiting payment'), 'icon' => 'hourglass', 'mod' => 'awaiting'],
        'paid' => ['label' => $l->t('Paid'), 'icon' => 'circle-check', 'mod' => 'paid'],
    ];
    $chipFallback = $chipMap['n_a'];
}

$chip = $chipMap[$chipValue] ?? $chipFallback;
?>
<span class="pc-settlement-chip pc-settlement-chip--<?php p($chip['mod']); ?>"
    data-settlement-value="<?php p($chipValue); ?>"
    <?php if (!empty($chipTitle)): ?>title="<?php p($chipTitle); ?>"<?php endif; ?>>
    <span data-lucide="<?php p($chip['icon']); ?>" class="lucide-icon pc-settlement-chip__icon" aria-hidden="true"></span>
    <span class="pc-settlement-chip__label"><?php p($chip['label']); ?></span>
</span>
<?php
// Reset optionals so repeated includes never leak previous values.
unset($chipTitle);
?>
