<?php

declare(strict_types=1);

/**
 * All user-facing strings for save-policy: API JSON and the admin/org form data attribute.
 * Single place for l10N so the API and the client use the same wording.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCP\IL10N;

class SavePolicyUiStrings
{
	/**
	 * Use for every JSON error/success "message" field in AppConfigController::savePolicy.
	 *
	 * @return array{unauthorized: string, forbidden: string, invalidJson: string, invalidSettingsSection: string, server: string}
	 */
	public static function apiMessages(IL10N $l): array
	{
		return [
			'unauthorized' => $l->t('Your session may have expired. Sign in again, then return to this page.'),
			'forbidden' => $l->t('You do not have permission to save. If you are not a system administrator, you may have been removed from the app’s administrator list. Reload the page.'),
			'invalidJson' => $l->t('The form data could not be read. Try again, or contact your administrator if this keeps happening.'),
			'invalidSettingsSection' => $l->t('Invalid settings section. Reload the page and try again.'),
			'server' => $l->t('A server error occurred. Try again later, or ask your Nextcloud administrator to check the log.'),
		];
	}

	/**
	 * Merged: API messages, extra client-only fallbacks, and a nested "errors" map (same as api + extras).
	 *
	 * @return array<string, mixed>
	 */
	public static function forForm(IL10N $l): array
	{
		$api = self::apiMessages($l);
		$extras = [
			'noRequestToken' => $l->t('Security token not available. Reload the page.'),
			'saving' => $l->t('Saving…'),
			'settingsSaved' => $l->t('Settings saved'),
			'invalidResponse' => $l->t('The response was not valid. Reload the page and try again, or check with your server administrator if this keeps happening.'),
			'unexpectedResponse' => $l->t('Unexpected response. Reload the page and check that the app is up to date, or try again in a few minutes.'),
			'networkError' => $l->t('Network error while saving.'),
			'genericSaveFailed' => $l->t('Could not save settings'),
		];
		$err = array_merge(
			$api,
			[
				'validation' => $l->t('The data could not be saved. Check your entries and try again.'),
				'badRequest' => $l->t('The server rejected the data. Check the form values and try again, or use your organization’s help channel.'),
				'notFound' => $l->t('The save URL was not found. Reload the page. If the problem remains, the app may need to be updated.'),
			]
		);

		$picker = self::orgPolicyPickerStrings($l);

		return array_merge(
			$extras,
			[ 'errors' => $err, 'picker' => $picker ]
		);
	}

	/**
	 * User/group pickers on the org access form (data-pc-form-strings.picker in admin-settings.js).
	 *
	 * @return array<string, string>
	 */
	public static function orgPolicyPickerStrings(IL10N $l): array
	{
		return [
			'searchUsersLabel' => $l->t('Search users to add'),
			'searchGroupsLabel' => $l->t('Search groups to add'),
			'searchPlaceholder' => $l->t('Type at least 2 characters…'),
			'selectedUsersLegend' => $l->t('Users allowed to use the app (when restriction is on)'),
			'selectedGroupsLegend' => $l->t('Groups allowed to use the app (when restriction is on)'),
			'selectedAdminsLegend' => $l->t('Delegated app administrators'),
			'noResults' => $l->t('No matches. Try another search — never type a raw user or group id.'),
			'searchErrorAuth' => $l->t('Search could not run. Sign in again, then return to this page and try once more; your session may have ended.'),
			'searchErrorPermission' => $l->t('You are not allowed to search the directory. If this is unexpected, reload the page or ask a Nextcloud administrator.'),
			'searchErrorNetwork' => $l->t('Could not reach the server to search. Check your network and try again.'),
			'searchErrorServer' => $l->t('The server could not run the search. Try again in a moment.'),
			'removeItemAria' => $l->t('Remove from selection'),
			'addButton' => $l->t('Add'),
		];
	}
}
