<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Css;

use PHPUnit\Framework\TestCase;

/**
 * Pins entity-facts DL resets against Nextcloud core dt chrome
 * (width: 130px; text-align: end).
 */
final class DefinitionListCssContractTest extends TestCase {
	public function testEntityFactsNeutraliseNextcloudCoreDtChrome(): void {
		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/css/common/detail-layout.css');
		self::assertNotSame('', $css);
		self::assertMatchesRegularExpression(
			'/\.pc-entity-facts__row\s+dt[^{]*\{[^}]*text-align:\s*start/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.pc-entity-facts__row\s+dt[^{]*\{[^}]*width:\s*auto/s',
			$css,
		);
	}
}
