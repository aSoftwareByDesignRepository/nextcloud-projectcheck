<?php

declare(strict_types=1);

/**
 * Forensic description of an organization policy save. No PII/allowlist contents;
 * only actor id, high-level flags, and list cardinality where useful.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

final class OrgPolicySaveAudit
{
	/**
	 * @param array{restrictionEnabled: bool, allowedUserIds: list<string>, allowedGroupIds: list<string>, appAdminUserIds: list<string>} $before
	 * @param array{restrictionEnabled: bool, allowedUserIds: list<string>, allowedGroupIds: list<string>, appAdminUserIds: list<string>} $after
	 * @param array<string, string> $defaultsBefore
	 * @param array<string, string> $defaultsAfter
	 * @return array{app: string, event: string, actor: string, flags: array<string, bool|int>}
	 */
	public static function build(
		array $before,
		array $after,
		array $defaultsBefore,
		array $defaultsAfter,
		string $actor,
	): array {
		$uBefore = $before['allowedUserIds'] ?? [];
		$gBefore = $before['allowedGroupIds'] ?? [];
		$aBefore = $before['appAdminUserIds'] ?? [];
		$uAfter = $after['allowedUserIds'] ?? [];
		$gAfter = $after['allowedGroupIds'] ?? [];
		$aAfter = $after['appAdminUserIds'] ?? [];

		$flags = [
			'restriction_toggled' => (bool) ($before['restrictionEnabled'] ?? false) !== (bool) ($after['restrictionEnabled'] ?? false),
			'allowed_users_set_changed' => self::fingerprintList($uBefore) !== self::fingerprintList($uAfter),
			'allowed_groups_set_changed' => self::fingerprintList($gBefore) !== self::fingerprintList($gAfter),
			'app_admins_set_changed' => self::fingerprintList($aBefore) !== self::fingerprintList($aAfter),
		];
		$flags['app_defaults_changed'] = $defaultsBefore != $defaultsAfter;
		$flags['count_allowed_users_before'] = \count($uBefore);
		$flags['count_allowed_users_after'] = \count($uAfter);
		$flags['count_allowed_groups_before'] = \count($gBefore);
		$flags['count_allowed_groups_after'] = \count($gAfter);
		$flags['count_app_admins_before'] = \count($aBefore);
		$flags['count_app_admins_after'] = \count($aAfter);

		return [
			'app' => 'projectcheck',
			'event' => 'org_policy_changed',
			'actor' => $actor,
			'flags' => $flags,
		];
	}

	/**
	 * @param list<string> $ids
	 */
	public static function fingerprintList(array $ids): string
	{
		$unique = array_values(array_unique($ids, \SORT_STRING));
		sort($unique, \SORT_STRING);
		return hash('sha256', (string) json_encode($unique, JSON_THROW_ON_ERROR));
	}
}
