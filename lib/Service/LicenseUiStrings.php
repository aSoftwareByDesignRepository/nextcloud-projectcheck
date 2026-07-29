<?php

declare(strict_types=1);

/**
 * All user-facing strings for the license settings panel (PC2 mobile seats).
 * Single place for l10n so the SSR panel and license-settings.js use the same wording.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCP\IL10N;

class LicenseUiStrings
{
	/**
	 * @return array<string, string>
	 */
	public static function forPanel(IL10N $l): array
	{
		return [
			'loading' => $l->t('Loading license status…'),
			'askForLicenseButton' => $l->t('Ask for a license'),
			'seeProductsButton' => $l->t('See products & pricing'),
			'personColumn' => $l->t('Person'),
			'assignedColumn' => $l->t('Assigned'),
			'actionsColumn' => $l->t('Actions'),
			'badgeNotConfigured' => $l->t('Not configured'),
			'badgeActive' => $l->t('Active'),
			'badgeActiveSoon' => $l->t('Active — renew soon'),
			'badgeExpired' => $l->t('Expired'),
			'validUntilLabel' => $l->t('Valid until'),
			'validUntilNone' => $l->t('No license applied'),
			'seatsMeterLabel' => $l->t('Mobile seats used'),
			// Pass self-placeholders so LazyL10N never vsprintf()'s with an empty arg list
			// when forPanel() materialises the catalog for JSON (settings page SSR).
			'seatsUsedText' => $l->t('%1$s of %2$s seats used', ['%1$s', '%2$s']),
			'mobileAppAvailable' => $l->t('The official ProjectCheck mobile app is available. Assign seats below so people can sign in with their Nextcloud account.'),
			'expirySoonTitle' => $l->t('Your license is about to expire'),
			// "{days}" is a literal placeholder (not a gettext %-param) so client-side refreshes
			// can substitute the current day count without re-fetching translated strings.
			'expirySoonBody' => $l->t('This license expires in {days} day(s). Renew soon to avoid an interruption for your mobile seats.'),
			'keyPlaceholder' => $l->t('PC2.…'),
			'keyLabel' => $l->t('License key'),
			'keyHint' => $l->t('Paste the full key from your license email. Line breaks are OK.'),
			'saveButton' => $l->t('Save license'),
			'saving' => $l->t('Saving…'),
			'removeButton' => $l->t('Remove license'),
			'removing' => $l->t('Removing…'),
			'purchaseButton' => $l->t('Purchase or renew'),
			'keyRequired' => $l->t('Paste a PC2 license key.'),
			'saveSuccess' => $l->t('License saved.'),
			'saveFailedGeneric' => $l->t('Could not apply this license key.'),
			'removeSuccess' => $l->t('License removed.'),
			'removeFailedGeneric' => $l->t('Could not remove the license.'),
			'confirmRemoveTitle' => $l->t('Remove this license?'),
			'confirmRemoveBody' => $l->t('Mobile seats will stop working immediately. This does not affect the web app, which stays free.'),
			'confirmRemoveConfirm' => $l->t('Remove license'),
			'confirmRemoveCancel' => $l->t('Cancel'),
			'seatsHeading' => $l->t('Mobile seats'),
			'seatsIntro' => $l->t('Assign named seats to Nextcloud accounts. Each assigned person can sign in to the official ProjectCheck mobile app.'),
			'seatSearchLabel' => $l->t('Search a Nextcloud user to assign a seat'),
			'seatSearchPlaceholder' => $l->t('Type at least 2 characters…'),
			'seatSearchNoResults' => $l->t('No matching users, or everyone found already has a seat.'),
			'seatSearchErrorAuth' => $l->t('Search could not run. Sign in again, then return to this page.'),
			'seatSearchErrorPermission' => $l->t('You are not allowed to search the directory.'),
			'seatSearchErrorNetwork' => $l->t('Could not reach the server to search. Check your network and try again.'),
			'seatSearchErrorServer' => $l->t('The server could not run the search. Try again in a moment.'),
			'seatAssignSuccess' => $l->t('Seat assigned.'),
			'seatAssignExisting' => $l->t('This person already has a seat.'),
			'seatAssignLimitReached' => $l->t('All licensed seats are assigned. Remove a seat or upgrade the license.'),
			'seatAssignBusy' => $l->t('Another seat update is in progress. Try again in a moment.'),
			'seatAssignUnknownUser' => $l->t('This Nextcloud user does not exist.'),
			'seatAssignFailedGeneric' => $l->t('Could not assign the seat.'),
			'seatRemoveButton' => $l->t('Remove'),
			'seatRemoveAria' => $l->t('Remove seat for %s', ['%s']),
			'seatRemoveSuccess' => $l->t('Seat removed.'),
			'seatRemoveFailedGeneric' => $l->t('Could not remove the seat.'),
			'seatOverLimitBadge' => $l->t('Over limit'),
			'seatOverLimitHint' => $l->t('This seat exceeds the current license limit. It stays assigned but has no mobile access until you remove a seat or upgrade.'),
			'seatsEmpty' => $l->t('No seats assigned yet.'),
			'seatAssignedOnLabel' => $l->t('Assigned'),
			'seatAssignedByLabel' => $l->t('By'),
			'networkError' => $l->t('Network error. Please try again.'),
			'genericError' => $l->t('Something went wrong. Please try again.'),
			'accessDenied' => $l->t('You do not have permission to manage the ProjectCheck license.'),
		];
	}
}
