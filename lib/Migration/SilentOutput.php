<?php

declare(strict_types=1);

/**
 * Discards migration output (runtime schema repair, tests).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCP\Migration\IOutput;

final class SilentOutput implements IOutput
{
	public function debug(string $message): void
	{
	}

	public function info($message): void
	{
	}

	public function warning($message): void
	{
	}

	public function startProgress($max = 0): void
	{
	}

	public function advance($step = 1, $description = ''): void
	{
	}

	public function finishProgress(): void
	{
	}
}
