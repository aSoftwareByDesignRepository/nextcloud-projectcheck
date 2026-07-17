<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Db;

use OCA\ProjectCheck\Db\ProjectSettlementFilter;
use OCA\ProjectCheck\Util\SettlementPosture;
use PHPUnit\Framework\TestCase;

/**
 * Filter code normalization must stay aligned with SettlementPosture vocabulary.
 * SQL predicate shape is covered by Docker UAT against live counters.
 */
class ProjectSettlementFilterTest extends TestCase
{
	public function testNormalizeKnownCodes(): void
	{
		$this->assertSame('', ProjectSettlementFilter::normalize(''));
		$this->assertSame('', ProjectSettlementFilter::normalize('all'));
		// Unknown codes fail closed (must not silently mean "no filter").
		$this->assertSame(ProjectSettlementFilter::INVALID, ProjectSettlementFilter::normalize('bogus'));
		$this->assertSame(ProjectSettlementFilter::INVALID, ProjectSettlementFilter::normalize('PaidOut'));
		$this->assertSame('outstanding', ProjectSettlementFilter::normalize(' Outstanding '));
		$this->assertSame(SettlementPosture::OPEN, ProjectSettlementFilter::normalize('open'));
		$this->assertSame(SettlementPosture::PARTIAL, ProjectSettlementFilter::normalize('partial'));
		$this->assertSame(SettlementPosture::AWAITING_PAYMENT, ProjectSettlementFilter::normalize('awaiting_payment'));
		$this->assertSame(SettlementPosture::PAID, ProjectSettlementFilter::normalize('paid'));
		$this->assertSame(SettlementPosture::NA, ProjectSettlementFilter::normalize('n_a'));
	}

	public function testApplyInvalidAddsAlwaysFalsePredicate(): void
	{
		$andWhereArgs = [];
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->expects($this->once())->method('eq')->willReturn('1=0');

		$qb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturnCallback(static fn ($v) => (string) $v);
		$qb->expects($this->once())->method('andWhere')->willReturnCallback(static function ($arg) use (&$andWhereArgs, $qb) {
			$andWhereArgs[] = $arg;
			return $qb;
		});

		ProjectSettlementFilter::apply($qb, 'not-a-real-filter');
		$this->assertSame(['1=0'], $andWhereArgs);
	}

	public function testCodesMatchPostureVocabularyPlusOutstanding(): void
	{
		foreach (SettlementPosture::ALL as $posture) {
			$this->assertContains($posture, ProjectSettlementFilter::CODES);
		}
		$this->assertContains('outstanding', ProjectSettlementFilter::CODES);
	}
}
