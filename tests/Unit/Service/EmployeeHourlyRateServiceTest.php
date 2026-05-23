<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\EmployeeHourlyRate;
use OCA\ProjectCheck\Db\EmployeeHourlyRateMapper;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\EmployeeHourlyRateService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EmployeeHourlyRateServiceTest extends TestCase
{
	private function makeService(
		EmployeeHourlyRateMapper $mapper,
		bool $canManage = true,
		?IUser $targetUser = null,
	): EmployeeHourlyRateService {
		$access = $this->createMock(AccessControlService::class);
		$access->method('canManageAppConfiguration')->willReturn($canManage);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(static function (string $uid) use ($targetUser) {
			if ($targetUser !== null && $targetUser->getUID() === $uid) {
				return $targetUser;
			}
			return null;
		});

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$logger = $this->createMock(LoggerInterface::class);

		return new EmployeeHourlyRateService($mapper, $access, $userManager, $l, $logger);
	}

	public function testResolveRateForDateReturnsNormalizedRate(): void
	{
		$row = new EmployeeHourlyRate();
		$row->setHourlyRate(88.5);

		$mapper = $this->createMock(EmployeeHourlyRateMapper::class);
		$mapper->method('findEffectiveRate')->with('alice', '2026-04-01')->willReturn($row);

		$svc = $this->makeService($mapper);
		$this->assertEqualsWithDelta(88.5, $svc->resolveRateForDate('alice', '2026-04-01'), 0.001);
	}

	public function testResolveRateForDateThrowsWhenNoRow(): void
	{
		$mapper = $this->createMock(EmployeeHourlyRateMapper::class);
		$mapper->method('findEffectiveRate')->willReturn(null);

		$svc = $this->makeService($mapper);
		$this->expectException(RateResolutionException::class);
		$svc->resolveRateForDate('alice', '2026-04-01');
	}

	public function testAddRateRowDeniedForNonAdmin(): void
	{
		$mapper = $this->createMock(EmployeeHourlyRateMapper::class);
		$svc = $this->makeService($mapper, false);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Access denied');
		$svc->addRateRow('alice', ['hourly_rate' => 50, 'effective_from' => '2026-01-01'], 'bob');
	}

	public function testAddRateRowRejectsFutureEffectiveDate(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$mapper = $this->createMock(EmployeeHourlyRateMapper::class);
		$mapper->expects($this->never())->method('insert');

		$svc = $this->makeService($mapper, true, $user);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Effective-from date cannot be in the future.');
		$svc->addRateRow('alice', [
			'hourly_rate' => 50,
			'effective_from' => gmdate('Y-m-d', strtotime('+2 days')),
		], 'admin');
	}

	public function testAddRateRowRejectsDuplicateEffectiveDate(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$mapper = $this->createMock(EmployeeHourlyRateMapper::class);
		$mapper->method('existsForUserAndDate')->with('alice', '2026-01-01')->willReturn(true);

		$svc = $this->makeService($mapper, true, $user);

		$this->expectException(\Exception::class);
		$svc->addRateRow('alice', ['hourly_rate' => 50, 'effective_from' => '01.01.2026'], 'admin');
	}
}
