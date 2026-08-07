<?php

declare(strict_types=1);

/**
 * Lucide icon names for ProjectCheck project types (theme-safe; no emoji).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

/**
 * Maps stored project_type keys to Lucide icon ids used with data-lucide.
 * Colour must come from CSS tokens (currentColor) — never from the glyph itself.
 */
final class ProjectTypeIcon
{
	/** @var array<string, string> */
	private const MAP = [
		'client' => 'briefcase',
		'admin' => 'settings',
		'sales' => 'trending-up',
		'customer' => 'headphones',
		'product' => 'box',
		'meeting' => 'users',
		'internal' => 'building-2',
		'research' => 'flask-conical',
		'training' => 'graduation-cap',
		'other' => 'clipboard-list',
	];

	public static function lucideName(?string $projectType): string
	{
		$key = strtolower(trim((string) $projectType));
		return self::MAP[$key] ?? self::MAP['other'];
	}

	/**
	 * @return list<string>
	 */
	public static function knownTypes(): array
	{
		return array_keys(self::MAP);
	}
}
