<?php

declare(strict_types=1);

/**
 * Single-line budget remaining / over-budget label for templates.
 *
 * Expects: $budgetInfo (array), $fmt (LocaleFormatService|null), optional $currencyCode.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

$budgetInfo = $budgetInfo ?? [];
$fmt = $fmt ?? null;
$currencyCode = isset($currencyCode) && is_string($currencyCode) ? strtoupper(trim($currencyCode)) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}

$isOver = !empty($budgetInfo['is_over_budget']);
$overAmount = (float)($budgetInfo['over_budget_amount'] ?? 0);
$remaining = (float)($budgetInfo['remaining_budget'] ?? 0);

if ($isOver && $overAmount > 0) {
	$formatted = $fmt ? $fmt->currency($overAmount) : $currencyCode . ' ' . number_format($overAmount, 2);
	p($l->t('Over by %s', [$formatted]));
	return;
}

$formatted = $fmt ? $fmt->currency($remaining) : $currencyCode . ' ' . number_format($remaining, 2);
p($l->t('%s remaining', [$formatted]));
