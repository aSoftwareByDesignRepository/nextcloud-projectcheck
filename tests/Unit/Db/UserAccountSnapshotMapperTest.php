<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Db;

use OCA\ProjectCheck\Db\UserAccountSnapshot;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class UserAccountSnapshotMapperTest extends TestCase
{
	public function testInsertRaceRereadsAndTakesUpdateBranch(): void
	{
		// Two concurrent snapshot writes for the same user_id: the loser's
		// insert hits pc_uas_user_uidx. The method must re-read and update the
		// row the winner wrote — that is the documented idempotent contract.
		$existing = new UserAccountSnapshot();
		$existing->setId(5);
		$existing->setUserId('alice');
		$existing->setDisplayName('Alice Old');
		$existing->setAccountDeletedAt(new \DateTime('2026-01-01'));

		$mapper = $this->getMockBuilder(UserAccountSnapshotMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class)])
			->onlyMethods(['findByUserId', 'insert', 'update'])
			->getMock();

		$mapper->expects($this->exactly(2))
			->method('findByUserId')
			->with('alice')
			->willReturnOnConsecutiveCalls(null, $existing);
		$mapper->expects($this->once())
			->method('insert')
			->willThrowException(
				self::dbException(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION)
			);
		$mapper->expects($this->once())
			->method('update')
			->with($this->callback(static function (UserAccountSnapshot $e): bool {
				return $e->getId() === 5
					&& $e->getDisplayName() === 'Alice New'
					&& $e->getAccountDeletedAt()->format('Y-m-d') === '2026-02-02';
			}))
			->willReturnArgument(0);

		$saved = $mapper->saveDeletedAccountSnapshot('alice', 'Alice New', new \DateTime('2026-02-02'));
		$this->assertSame(5, $saved->getId());
	}

	public function testInsertRaceWithoutRowRethrows(): void
	{
		// Unique violation fired but the re-read finds no row (winner rolled
		// back) — the raw DB exception must propagate, not be masked.
		$dbError = self::dbException(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION);

		$mapper = $this->getMockBuilder(UserAccountSnapshotMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class)])
			->onlyMethods(['findByUserId', 'insert', 'update'])
			->getMock();

		$mapper->expects($this->exactly(2))
			->method('findByUserId')
			->willReturn(null);
		$mapper->method('insert')->willThrowException($dbError);
		$mapper->expects($this->never())->method('update');

		try {
			$mapper->saveDeletedAccountSnapshot('alice', 'Alice', new \DateTime('2026-02-02'));
			$this->fail('expected the DB exception to propagate');
		} catch (\OCP\DB\Exception $e) {
			$this->assertSame($dbError, $e);
		}
	}

	public function testNonUniqueDbExceptionPropagates(): void
	{
		$dbError = self::dbException(\OCP\DB\Exception::REASON_CONNECTION_LOST);

		$mapper = $this->getMockBuilder(UserAccountSnapshotMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class)])
			->onlyMethods(['findByUserId', 'insert', 'update'])
			->getMock();

		$mapper->method('findByUserId')->willReturn(null);
		$mapper->method('insert')->willThrowException($dbError);
		$mapper->expects($this->never())->method('update');

		try {
			$mapper->saveDeletedAccountSnapshot('alice', 'Alice', new \DateTime('2026-02-02'));
			$this->fail('expected the DB exception to propagate');
		} catch (\OCP\DB\Exception $e) {
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
