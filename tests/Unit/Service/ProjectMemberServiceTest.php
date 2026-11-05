<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\ProjectMemberService;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class ProjectMemberServiceTest extends TestCase
{
	private function makeDb(\Throwable $insertError): IDBConnection
	{
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('eq');

		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(false);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('values')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn('param');
		$qb->method('executeQuery')->willReturn($result);
		$qb->method('executeStatement')->willThrowException($insertError);

		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		return $db;
	}

	private function makeService(IDBConnection $db): ProjectMemberService
	{
		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectMapper->method('find')->with(7)->willReturn(new Project());

		return new ProjectMemberService(
			$db,
			$projectMapper,
			$this->createMock(TimeEntryMapper::class),
		);
	}

	public function testAddProjectMemberMapsUniqueViolationToAlreadyMember(): void
	{
		// The pre-check passes (no member row) but pc_members_unique_idx fires
		// on the write — a concurrent add claimed it first. The serialized
		// outcome must be the same already-member error.
		$svc = $this->makeService($this->makeDb(
			self::dbException(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION)
		));

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User is already a member of this project');
		$svc->addProjectMember(7, [
			'user_id' => 'alice',
			'role' => 'member',
			'hourly_rate' => 50,
			'assigned_by' => 'admin',
		]);
	}

	public function testAddProjectMemberRethrowsNonUniqueDbException(): void
	{
		$dbError = self::dbException(\OCP\DB\Exception::REASON_CONNECTION_LOST);
		$svc = $this->makeService($this->makeDb($dbError));

		try {
			$svc->addProjectMember(7, [
				'user_id' => 'alice',
				'role' => 'member',
				'hourly_rate' => 50,
				'assigned_by' => 'admin',
			]);
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
