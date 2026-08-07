<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\CustomerSettlementService;
use OCA\ProjectCheck\Util\SettlementPosture;
use PHPUnit\Framework\TestCase;

/**
 * Pure filter-matching tests (no DB). Rollup SQL is covered by Docker UAT.
 */
class CustomerSettlementFilterTest extends TestCase
{
	private CustomerSettlementService $service;

	protected function setUp(): void
	{
		parent::setUp();
		// Filter matching does not touch $db / $projectService.
		$ref = new \ReflectionClass(CustomerSettlementService::class);
		$this->service = $ref->newInstanceWithoutConstructor();
	}

	/**
	 * @dataProvider filterProvider
	 * @param array<string, mixed> $rollup
	 */
	public function testMatchesSettlementFilter(array $rollup, string $filter, bool $expected): void
	{
		$this->assertSame($expected, $this->service->matchesSettlementFilter($rollup, $filter));
	}

	/**
	 * @return list<array{0: array<string, mixed>, 1: string, 2: bool}>
	 */
	public static function filterProvider(): array
	{
		$open = [
			'posture' => SettlementPosture::OPEN,
			'outstanding_hours' => 4.0,
		];
		$paid = [
			'posture' => SettlementPosture::PAID,
			'outstanding_hours' => 0.0,
		];
		$partial = [
			'posture' => SettlementPosture::PARTIAL,
			'outstanding_hours' => 2.5,
		];

		return [
			[$open, '', true],
			[$open, 'all', true],
			[$open, 'outstanding', true],
			[$paid, 'outstanding', false],
			[$open, 'open', true],
			[$open, 'paid', false],
			[$partial, 'partial', true],
			[$paid, 'paid', true],
			[$paid, 'bogus', false], // invalid filter codes match nothing
		];
	}
}
