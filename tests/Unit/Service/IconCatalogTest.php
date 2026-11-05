<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\IconCatalog;
use PHPUnit\Framework\TestCase;

final class IconCatalogTest extends TestCase
{
	public function testHasReflectsCatalogMembership(): void
	{
		$this->assertTrue(IconCatalog::has('edit'));
		$this->assertFalse(IconCatalog::has('square-pen'));
		$this->assertFalse(IconCatalog::has('does-not-exist'));
	}

	public function testNavFooterCandidateChainsResolve(): void
	{
		// Every candidate list in the synced nav-footer partial must contain at
		// least one catalog icon or the first miss renders a wrong/absent icon.
		$tpl = file_get_contents(__DIR__ . '/../../../templates/parts/feedback-nav-footer.php');
		$this->assertNotFalse($tpl);
		preg_match_all('/Icon\(\s*\$prefix\s*,\s*\[([^\]]+)\]/', $tpl, $matches);
		$this->assertNotEmpty($matches[1], 'no icon candidate call sites found in nav-footer partial');
		foreach ($matches[1] as $listSrc) {
			preg_match_all("/'([a-z0-9_-]+)'/", $listSrc, $names);
			$this->assertNotEmpty(
				array_filter($names[1], [IconCatalog::class, 'has']),
				'no resolvable candidate in [' . $listSrc . ']'
			);
		}
	}
}
