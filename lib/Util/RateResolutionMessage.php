<?php

declare(strict_types=1);

/**
 * Maps rate-resolution error codes to localized API messages (never raw exception text).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

use OCA\ProjectCheck\Exception\RateResolutionException;
use OCP\IL10N;

final class RateResolutionMessage
{
	public static function forException(RateResolutionException $e, IL10N $l): string
	{
		return match ($e->getCodeKey()) {
			'user_required' => $l->t('User is required'),
			'project_not_found' => $l->t('Project not found'),
			'project_closed' => $l->t('Time cannot be logged on this project. Only Active and On Hold projects accept new entries.'),
			'not_on_team' => $l->t('You must be on the project team to log time. Ask a project manager to add you under Team on the project page.'),
			'employee_rate_missing' => $l->t('No employee hourly rate is effective on this date. Add a rate under Employees with an effective-from date on or before the work date.'),
			'member_rate_missing' => $l->t('No project rate is effective for this person on this date. Add a rate on the project team with an effective-from date on or before the work date.'),
			'rate_unresolved' => $l->t('Could not resolve hourly rate.'),
			'rate_tamper' => $l->t('Invalid hourly rate. Refresh the page and try again.'),
			'rate_mismatch' => $l->t('The hourly rate does not match the server. Refresh the page and try again.'),
			'invalid_date' => $l->t('Invalid date format'),
			default => $l->t('Could not resolve hourly rate.'),
		};
	}
}
