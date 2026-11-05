<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\ProjectMemberHourlyRateMapper;
use OCA\ProjectCheck\Service\ProjectMemberHourlyRateService;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectMemberHourlyRateServiceTest extends TestCase
{
	private function makeService(
		ProjectMemberHourlyRateMapper $mapper,
		bool $activeMember = true,
	): ProjectMemberHourlyRateService {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('eq');

		$result = $this->createMock(IResult::class);
		$result->method('fetchOne')->willReturn($activeMember ? 42 : false);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('setMaxResults')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn('param');
		$qb->method('executeQuery')->willReturn($result);

		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new ProjectMemberHourlyRateService(
			$mapper,
			$db,
			$l,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testAppendRateRowMapsUniqueViolationToConflictMessage(): void
	{
		// The pre-check passes (empty slot) but the unique index fires on the
		// write — the serialized outcome must be the same translated conflict
		// the sequential pre-check reports, never the raw DB exception.
		$mapper = $this->createMock(ProjectMemberHourlyRateMapper::class);
		$mapper->method('existsForProjectUserAndDate')->willReturn(false);
		$mapper->method('insertRate')->willThrowException(
			self::dbException(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION)
		);

		$svc = $this->makeService($mapper);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('A rate with this effective-from date already exists.');
		$svc->appendRateRow(7, 'alice', 55.0, '2026-01-01', 'admin');
	}

	public function testAppendRateRowRethrowsNonUniqueDbException(): void
	{
		$dbError = self::dbException(\OCP\DB\Exception::REASON_CONNECTION_LOST);
		$mapper = $this->createMock(ProjectMemberHourlyRateMapper::class);
		$mapper->method('existsForProjectUserAndDate')->willReturn(false);
		$mapper->method('insertRate')->willThrowException($dbError);

		$svc = $this->makeService($mapper);

		try {
			$svc->appendRateRow(7, 'alice', 55.0, '2026-01-01', 'admin');
			$this->fail('expected the DB exception to propagate');
		} catch (\Exception $e) {
			$this->assertSame($dbError, $e);
		}
	}

	private static function dbException(int $reason): \OCP\DB\Exception
	{
		return new class('db error', 0, null, $reason) extends \OCP\DB\Exception {
			public function __construct(
				string $message,
				int $code,
				?\Throwable $previous,
				private int $reasonValue,
			) {
				parent::__construct($message, $code, $previous);
			}

			public function getReason(): ?int
			{
				return $this->reasonValue;
			}
		};
	}
}
