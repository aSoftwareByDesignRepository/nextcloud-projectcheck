<?php

declare(strict_types=1);

/**
 * Project member roles (feature spec §7.4, decisions D5a–D5c).
 *
 * `Member` logs time; `Manager` may additionally settle entries and view the
 * whole team's entries on the projects they manage. Role assignment is gated
 * by {@see \OCA\ProjectCheck\Service\ProjectService::canUserManageMembers}.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class ProjectMemberRole
{
	public const MEMBER = 'Member';
	public const MANAGER = 'Manager';

	/** @var list<string> */
	public const ALL = [self::MEMBER, self::MANAGER];

	public static function isValid(string $role): bool
	{
		return in_array($role, self::ALL, true);
	}

	/**
	 * Normalize legacy / free-form values; anything unknown is a Member so
	 * historical rows can never silently gain settle rights.
	 */
	public static function normalize(?string $role): string
	{
		$role = trim((string) $role);
		foreach (self::ALL as $known) {
			if (strcasecmp($role, $known) === 0) {
				return $known;
			}
		}
		return self::MEMBER;
	}

	public static function isManager(?string $role): bool
	{
		return self::normalize($role) === self::MANAGER;
	}
}
