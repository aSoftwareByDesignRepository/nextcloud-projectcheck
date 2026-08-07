<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud DB-Standards (auto-generated)
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Regenerate via:
 *     php scripts/check-nextcloud-db-standards.php sync-uninstall --app=projectcheck
 */
namespace OCA\ProjectCheck\Repair;

/**
 * Distinguishes disable uninstall repair from explicit app removal.
 *
 * Nextcloud uses the same {@see UninstallDropTables} repair step for both paths.
 */
final class UninstallRepairFlow
{
	/**
	 * @param list<array{class?: string, function?: string, type?: string}>|null $backtrace
	 */
	public static function isRemovalContext(?array $backtrace = null): bool
	{
		$frames = $backtrace ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24);

		foreach ($frames as $frame) {
			$class = $frame['class'] ?? '';
			$function = $frame['function'] ?? '';

			if ($class === 'OC\\Installer' && $function === 'removeApp') {
				return true;
			}

			if ($class === 'OCA\\Settings\\Controller\\AppSettingsController' && $function === 'uninstallApp') {
				return true;
			}
		}

		return false;
	}
}
