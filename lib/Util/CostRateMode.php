<?php

declare(strict_types=1);

/**
 * Cost pricing mode constants for projects.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class CostRateMode
{
	public const PROJECT = 'project';
	public const EMPLOYEE = 'employee';
	public const PROJECT_MEMBER = 'project_member';

	public const DEFAULT = self::PROJECT;

	/** @var list<string> */
	private const VALID = [
		self::PROJECT,
		self::EMPLOYEE,
		self::PROJECT_MEMBER,
	];

	public static function isValid(?string $mode): bool
	{
		return $mode !== null && $mode !== '' && in_array($mode, self::VALID, true);
	}

	public static function normalize(?string $mode): string
	{
		if (self::isValid($mode)) {
			return (string) $mode;
		}
		return self::DEFAULT;
	}

	/**
	 * @return list<string>
	 */
	public static function all(): array
	{
		return self::VALID;
	}
}
