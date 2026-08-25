<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Activity;

use PHPUnit\Framework\TestCase;

/**
 * ActivityService publishes projectcheck events — without an info.xml provider
 * the stream never parses them and the Provider class is dead code.
 */
final class ActivityProviderRegistrationContractTest extends TestCase
{
	public function testInfoXmlRegistersActivityProvider(): void
	{
		$xml = (string)file_get_contents(dirname(__DIR__, 3) . '/appinfo/info.xml');
		$this->assertStringContainsString('<activity>', $xml);
		$this->assertStringContainsString(
			'<provider>OCA\\ProjectCheck\\Activity\\Provider</provider>',
			$xml
		);
	}

	public function testProviderThrowsUnknownActivityNotInvalidArgument(): void
	{
		$src = (string)file_get_contents(dirname(__DIR__, 3) . '/lib/Activity/Provider.php');
		$this->assertStringContainsString('UnknownActivityException', $src);
		$this->assertStringNotContainsString('throw new \\InvalidArgumentException', $src);
		$this->assertStringNotContainsString('throw new \InvalidArgumentException', $src);
	}
}
