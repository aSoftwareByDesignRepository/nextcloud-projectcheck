<?php

declare(strict_types=1);

/**
 * Consistent parameters for templates/error.php.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

use OCP\IL10N;
use OCP\IURLGenerator;

final class ErrorPageParams
{
	/**
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	public static function build(
		IL10N $l,
		IURLGenerator $urlGenerator,
		string $message,
		?string $secondaryRoute = 'projectcheck.dashboard.index',
		?string $secondaryLabel = null,
	): array {
		$secondaryUrl = null;
		$resolvedSecondaryLabel = null;
		if ($secondaryRoute !== null) {
			$secondaryUrl = $urlGenerator->linkToRoute($secondaryRoute);
			$resolvedSecondaryLabel = $secondaryLabel ?? $l->t('Back to Dashboard');
		}

		return [
			'l' => $l,
			'message' => $message,
			'homeUrl' => $urlGenerator->linkToDefaultPageUrl(),
			'secondaryUrl' => $secondaryUrl,
			'secondaryLabel' => $resolvedSecondaryLabel,
		];
	}

	/**
	 * Unauthenticated / guest layout: no in-app navigation (user must log in).
	 *
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	public static function forGuest(IL10N $l, IURLGenerator $urlGenerator, string $message): array
	{
		return self::build($l, $urlGenerator, $message, null);
	}

	/**
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	public static function forProjectsIndex(IL10N $l, IURLGenerator $urlGenerator, string $message): array
	{
		return self::build(
			$l,
			$urlGenerator,
			$message,
			'projectcheck.project.index',
			$l->t('Back to Projects'),
		);
	}

	/**
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	public static function forCustomersIndex(IL10N $l, IURLGenerator $urlGenerator, string $message): array
	{
		return self::build(
			$l,
			$urlGenerator,
			$message,
			'projectcheck.customer.index',
			$l->t('Back to Customers'),
		);
	}
}
