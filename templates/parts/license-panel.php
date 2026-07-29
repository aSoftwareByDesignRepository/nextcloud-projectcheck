<?php

declare(strict_types=1);

/**
 * ProjectCheck — License panel (PC2 mobile seats).
 *
 * Seats-only: ProjectCheck has no shared-device/signage product. The web app always
 * stays free (AGPL); a PC2 key unlocks named seats for the official mobile companion
 * app (available now). Included from org-settings-form.php (after the form closes, before
 * Support & Us) so the server admin form and the in-app settings page render it identically.
 *
 * @var \OCP\IL10N $l
 * @var array|null $licenseStatus    LicenseService::status() shape, or null if unavailable.
 * @var array|null $licenseSeatsList LicenseService::listSeats() shape: {data, total, limit, offset}.
 * @var array|null $licenseI18n      LicenseUiStrings::forPanel() strings, shared with license-settings.js.
 * @var string $licenseApiUrl
 * @var string $licenseClearUrl
 * @var string $licenseSeatsUrl
 * @var string $licenseAssignSeatUrl
 * @var string $licenseRemoveSeatBase
 * @var string $licenseSearchUsersUrl
 * @var string $requesttoken
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

$l = $l ?? (isset($_['l']) ? $_['l'] : \OCP\Util::getL10N('projectcheck'));

$licenseI18n = $licenseI18n ?? ($_['licenseI18n'] ?? null);
if (!is_array($licenseI18n)) {
	$licenseI18n = \OCA\ProjectCheck\Service\LicenseUiStrings::forPanel($l);
}
$t = static function (string $key, string $fallback = '') use ($licenseI18n): string {
	$v = $licenseI18n[$key] ?? $fallback;
	return is_string($v) ? $v : $fallback;
};

$licenseStatusData = $licenseStatus ?? ($_['licenseStatus'] ?? null);
if (!is_array($licenseStatusData)) {
	$licenseStatusData = null;
}
$licenseState = is_array($licenseStatusData) ? ($licenseStatusData['state'] ?? null) : null;
if (!is_array($licenseState)) {
	$licenseState = null;
}
$licenseSeatsCounts = is_array($licenseStatusData) ? ($licenseStatusData['seats'] ?? null) : null;
if (!is_array($licenseSeatsCounts)) {
	$licenseSeatsCounts = ['assigned' => 0, 'limit' => 0];
}

$licenseSeatsPayload = $licenseSeatsList ?? $licenseSeats ?? ($_['licenseSeatsList'] ?? ($_['licenseSeats'] ?? null));
if (!is_array($licenseSeatsPayload)) {
	$licenseSeatsPayload = ['data' => [], 'total' => 0, 'limit' => 0, 'offset' => 0];
}
$seatRows = is_array($licenseSeatsPayload['data'] ?? null) ? $licenseSeatsPayload['data'] : [];

$licenseApiUrl = (string)($licenseApiUrl ?? ($_['licenseApiUrl'] ?? ''));
$licenseClearUrl = (string)($licenseClearUrl ?? ($_['licenseClearUrl'] ?? $licenseApiUrl));
$licenseSeatsUrl = (string)($licenseSeatsUrl ?? ($_['licenseSeatsUrl'] ?? ''));
$licenseAssignSeatUrl = (string)($licenseAssignSeatUrl ?? ($_['licenseAssignSeatUrl'] ?? $licenseSeatsUrl));
$licenseRemoveSeatBase = (string)($licenseRemoveSeatBase ?? ($_['licenseRemoveSeatBase'] ?? ($licenseSeatsUrl !== '' ? rtrim($licenseSeatsUrl, '/') . '/' : '')));
$licenseSearchUsersUrl = (string)($licenseSearchUsersUrl ?? ($_['licenseSearchUsersUrl'] ?? ($_['orgSearchUsersUrl'] ?? '')));
$requesttoken = (string)($requesttoken ?? ($_['requesttoken'] ?? \OCP\Util::callRegister()));

$productsUrl = 'https://nextcloud.software-by-design.de/';
$purchaseMailto = 'mailto:info@software-by-design.de?subject=' . rawurlencode('ProjectCheck mobile license');

// Badge + status derivation for the first (server-rendered) paint; the client script
// re-derives the same state from the API response after load and after every action.
$hasState = $licenseState !== null;
$isValid = $hasState && !empty($licenseState['valid']);
$expiresSoon = $hasState && !empty($licenseState['expiresSoon']);
if (!$hasState) {
	$badgeText = $t('badgeNotConfigured', $l->t('Not configured'));
	$badgeClass = 'pc-license-badge--none';
} elseif ($isValid && $expiresSoon) {
	$badgeText = $t('badgeActiveSoon', $l->t('Active — renew soon'));
	$badgeClass = 'pc-license-badge--warning';
} elseif ($isValid) {
	$badgeText = $t('badgeActive', $l->t('Active'));
	$badgeClass = 'pc-license-badge--active';
} else {
	$badgeText = $t('badgeExpired', $l->t('Expired'));
	$badgeClass = 'pc-license-badge--expired';
}
$validUntil = $hasState ? (string)($licenseState['validUntil'] ?? '') : '';
$daysRemaining = $hasState && isset($licenseState['daysRemaining']) && is_int($licenseState['daysRemaining'])
	? $licenseState['daysRemaining']
	: null;
$seatsAssigned = (int)($licenseSeatsCounts['assigned'] ?? 0);
$seatsLimit = (int)($licenseSeatsCounts['limit'] ?? 0);
$seatsUsedText = $l->t('%1$s of %2$s seats used', [(string)$seatsAssigned, (string)$seatsLimit]);
$meterPercent = $seatsLimit > 0 ? (int)min(100, round($seatsAssigned / $seatsLimit * 100)) : 0;
$expiryBody = $daysRemaining !== null
	? str_replace('{days}', (string)max(0, $daysRemaining), $t('expirySoonBody'))
	: $t('expirySoonBody');

try {
	$licenseI18nJson = json_encode($licenseI18n, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
} catch (\JsonException) {
	$licenseI18nJson = '{}';
}
?>
<section class="projectcheck-panel pc-license-section" id="projectcheck-license" aria-labelledby="pc-license-heading">
	<div
		id="pc-license-panel"
		class="pc-license-panel"
		data-api-license="<?php p($licenseApiUrl); ?>"
		data-api-clear-license="<?php p($licenseClearUrl); ?>"
		data-api-seats="<?php p($licenseSeatsUrl); ?>"
		data-api-assign-seat="<?php p($licenseAssignSeatUrl); ?>"
		data-api-remove-seat-base="<?php p($licenseRemoveSeatBase); ?>"
		data-api-search-users="<?php p($licenseSearchUsersUrl); ?>"
		data-requesttoken="<?php p($requesttoken); ?>"
		data-i18n="<?php p($licenseI18nJson); ?>"
	>
		<div id="pc-license-live" class="visually-hidden" role="status" aria-live="polite"></div>
		<div id="pc-license-alert" class="visually-hidden" role="alert" aria-live="assertive"></div>

		<header class="pc-license-header">
			<h2 id="pc-license-heading" class="projectcheck-panel__title"><?php p($l->t('Mobile license')); ?></h2>
			<p class="pc-license-intro">
				<?php p($l->t('The ProjectCheck web app always stays free. A PC2 license unlocks named seats for the official ProjectCheck mobile companion app for your organisation.')); ?>
			</p>
			<p class="pc-license-intro pc-license-intro--status">
				<?php p($t('mobileAppAvailable')); ?>
			</p>
		</header>

		<div id="pc-license-feedback" class="pc-license-feedback" role="alert" hidden></div>

		<div class="pc-license-cta">
			<a class="button primary pc-license-cta__link" href="<?php p($purchaseMailto); ?>">
				<?php p($t('askForLicenseButton')); ?>
			</a>
			<a class="button pc-license-cta__link" href="<?php p($productsUrl); ?>" target="_blank" rel="noopener noreferrer">
				<?php p($t('seeProductsButton')); ?>
			</a>
		</div>

		<div class="pc-license-status" id="pc-license-status">
			<div class="pc-license-status__row">
				<span class="pc-license-badge <?php p($badgeClass); ?>" id="pc-license-badge"><?php p($badgeText); ?></span>
				<span class="pc-license-status__valid-until" id="pc-license-valid-until">
					<?php if ($validUntil !== '') { ?>
						<?php p($t('validUntilLabel')); ?> <strong><?php p($validUntil); ?></strong>
					<?php } else { ?>
						<?php p($t('validUntilNone')); ?>
					<?php } ?>
				</span>
			</div>

			<div class="pc-license-meter-wrap">
				<div class="pc-license-meter-label" id="pc-license-meter-label"><?php p($t('seatsMeterLabel')); ?></div>
				<div
					class="pc-license-meter"
					id="pc-license-meter"
					role="meter"
					aria-labelledby="pc-license-meter-label"
					aria-valuemin="0"
					aria-valuenow="<?php p((string)$seatsAssigned); ?>"
					aria-valuemax="<?php p((string)max($seatsLimit, $seatsAssigned, 1)); ?>"
					aria-valuetext="<?php p($seatsUsedText); ?>"
				><div class="pc-license-meter__fill" id="pc-license-meter-fill" style="width: <?php p((string)$meterPercent); ?>%"></div></div>
				<p class="pc-license-meter-text" id="pc-license-meter-text"><?php p($seatsUsedText); ?></p>
			</div>

			<div class="projectcheck-callout projectcheck-callout--caution pc-license-expiry-callout" id="pc-license-expiry-callout" role="status"<?php if (!($isValid && $expiresSoon)) {
				p(' hidden');
			} ?>>
				<p class="projectcheck-callout__p">
					<strong id="pc-license-expiry-title"><?php p($t('expirySoonTitle')); ?></strong>
					<span id="pc-license-expiry-body"><?php p($expiryBody); ?></span>
				</p>
			</div>
		</div>

		<form id="pc-license-form" class="pc-license-form" novalidate>
			<label for="pc-license-key" class="pc-license-form__label"><?php p($t('keyLabel')); ?></label>
			<textarea
				id="pc-license-key"
				name="key"
				class="projectcheck-textarea pc-license-key"
				rows="4"
				autocomplete="off"
				autocapitalize="off"
				spellcheck="false"
				aria-describedby="pc-license-key-hint"
				placeholder="<?php p($t('keyPlaceholder', 'PC2.…')); ?>"
			></textarea>
			<p id="pc-license-key-hint" class="projectcheck-hint"><?php p($t('keyHint')); ?></p>
			<div class="pc-license-actions">
				<button type="submit" class="button primary" id="pc-license-save"><?php p($t('saveButton')); ?></button>
				<button type="button" class="button" id="pc-license-remove"><?php p($t('removeButton')); ?></button>
			</div>
		</form>

		<section class="pc-license-seats" aria-labelledby="pc-license-seats-heading">
			<h3 id="pc-license-seats-heading" class="projectcheck-panel__title pc-license-seats__title"><?php p($t('seatsHeading')); ?></h3>
			<p class="pc-license-intro"><?php p($t('seatsIntro')); ?></p>

			<div class="pc-license-seat-search">
				<label for="pc-license-seat-search-input" class="pc-license-form__label"><?php p($t('seatSearchLabel')); ?></label>
				<div class="pc-license-seat-search__wrap">
					<input
						type="text"
						id="pc-license-seat-search-input"
						class="projectcheck-input pc-license-seat-search__input"
						role="combobox"
						aria-expanded="false"
						aria-autocomplete="list"
						aria-controls="pc-license-seat-search-suggest"
						aria-haspopup="listbox"
						autocomplete="off"
						autocapitalize="off"
						spellcheck="false"
						placeholder="<?php p($t('seatSearchPlaceholder')); ?>"
					>
					<div class="pc-license-seat-search__suggest" id="pc-license-seat-search-suggest" hidden></div>
				</div>
			</div>

			<div class="pc-license-seat-table-wrap" tabindex="0" role="region" aria-label="<?php p($l->t('License seats table')); ?>">
				<table class="pc-license-seat-table" id="pc-license-seat-table">
					<caption class="visually-hidden"><?php p($l->t('Assigned seats')); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php p($t('personColumn')); ?></th>
							<th scope="col"><?php p($t('assignedColumn')); ?></th>
							<th scope="col"><span class="visually-hidden"><?php p($t('actionsColumn')); ?></span></th>
						</tr>
					</thead>
					<tbody id="pc-license-seat-tbody">
						<?php if ($seatRows === []) { ?>
						<tr class="pc-license-seat-row pc-license-seat-row--empty" id="pc-license-seat-empty-row">
							<td colspan="3"><?php p($t('seatsEmpty')); ?></td>
						</tr>
						<?php }
						foreach ($seatRows as $seatRow) {
							$seatUid = (string)($seatRow['uid'] ?? '');
							if ($seatUid === '') {
								continue;
							}
							$seatName = (string)($seatRow['displayName'] ?? $seatUid);
							$seatAssignedAt = (int)($seatRow['assignedAt'] ?? 0);
							$seatWithinLimit = (bool)($seatRow['withinLimit'] ?? true);
							$seatAssignedDate = $seatAssignedAt > 0 ? date('Y-m-d', $seatAssignedAt) : '';
							$seatRemoveAria = $t('seatRemoveAria') !== '' ? sprintf($t('seatRemoveAria'), $seatName) : $seatName;
						?>
						<tr class="pc-license-seat-row" data-uid="<?php p($seatUid); ?>">
							<td class="pc-license-seat-row__person">
								<span class="pc-license-seat-row__name"><?php p($seatName); ?></span>
								<?php if ($seatName !== $seatUid) { ?>
								<span class="pc-license-seat-row__uid"><?php p($seatUid); ?></span>
								<?php } ?>
								<?php if (!$seatWithinLimit) { ?>
								<span class="pc-license-badge pc-license-badge--warning pc-license-seat-row__over"><?php p($t('seatOverLimitBadge')); ?></span>
								<?php } ?>
							</td>
							<td class="pc-license-seat-row__assigned"><?php p($seatAssignedDate); ?></td>
							<td class="pc-license-seat-row__actions">
								<button type="button" class="button pc-license-seat-remove" data-uid="<?php p($seatUid); ?>" aria-label="<?php p($seatRemoveAria); ?>">
									<?php p($t('seatRemoveButton')); ?>
								</button>
							</td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
		</section>

		<div class="pc-license-modal" id="pc-license-confirm-modal" hidden>
			<div class="pc-license-modal__backdrop" data-pc-license-modal-dismiss="1"></div>
			<div
				class="pc-license-modal__dialog"
				role="dialog"
				aria-modal="true"
				aria-labelledby="pc-license-confirm-title"
				aria-describedby="pc-license-confirm-body"
			>
				<h2 id="pc-license-confirm-title" class="pc-license-modal__title"><?php p($t('confirmRemoveTitle')); ?></h2>
				<p id="pc-license-confirm-body" class="pc-license-modal__body"><?php p($t('confirmRemoveBody')); ?></p>
				<div class="pc-license-modal__actions">
					<button type="button" class="button" id="pc-license-confirm-cancel"><?php p($t('confirmRemoveCancel')); ?></button>
					<button type="button" class="button pc-license-modal__confirm-danger" id="pc-license-confirm-ok"><?php p($t('confirmRemoveConfirm')); ?></button>
				</div>
			</div>
		</div>
	</div>
</section>
