<?php

declare(strict_types=1);

/**
 * Builds consistent parameters for templates/error.php.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Util\ErrorPageParams;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Requires {@see $l} and {@see $urlGenerator} on the using controller.
 */
trait ErrorPageTrait
{
	/**
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	protected function errorPage(
		string $message,
		?string $secondaryRoute = 'projectcheck.dashboard.index',
		?string $secondaryLabel = null,
	): array {
		/** @var IL10N $l */
		$l = $this->l;
		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = $this->urlGenerator;

		return ErrorPageParams::build($l, $urlGenerator, $message, $secondaryRoute, $secondaryLabel);
	}

	/**
	 * Guest/unauthenticated flows: home only (no in-app secondary link).
	 *
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	protected function errorPageGuest(string $message): array
	{
		/** @var IL10N $l */
		$l = $this->l;
		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = $this->urlGenerator;

		return ErrorPageParams::forGuest($l, $urlGenerator, $message);
	}

	/**
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	protected function errorPageProjects(string $message): array
	{
		/** @var IL10N $l */
		$l = $this->l;
		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = $this->urlGenerator;

		return ErrorPageParams::forProjectsIndex($l, $urlGenerator, $message);
	}

	/**
	 * @return array{l: IL10N, message: string, homeUrl: string, secondaryUrl: ?string, secondaryLabel: ?string}
	 */
	protected function errorPageCustomers(string $message): array
	{
		/** @var IL10N $l */
		$l = $this->l;
		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = $this->urlGenerator;

		return ErrorPageParams::forCustomersIndex($l, $urlGenerator, $message);
	}
}
