<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\SavePolicyUiStrings;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Regression: IFactory::get() returns LazyL10N implementing OCP\IL10N only.
 */
class SavePolicyUiStringsTest extends TestCase
{
	public function testForFormAcceptsOcpIl10N(): void
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$out = SavePolicyUiStrings::forForm($l);

		self::assertIsArray($out);
		self::assertArrayHasKey('errors', $out);
		self::assertIsArray($out['errors']);
		self::assertArrayHasKey('forbidden', $out['errors']);
	}

	public function testApiMessagesAcceptsOcpIl10N(): void
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$out = SavePolicyUiStrings::apiMessages($l);

		self::assertArrayHasKey('unauthorized', $out);
		self::assertArrayHasKey('forbidden', $out);
	}
}
